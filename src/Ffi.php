<?php

declare(strict_types=1);

namespace Libui;

/**
 * The single FFI handle bound to libui-ng, plus library lifecycle and the
 * low-level marshalling helpers the generated classes and the drawing adapter
 * rely on.
 *
 * One process holds one libui instance, so the handle is a lazily-created
 * singleton.
 */
final class Ffi
{
    private static ?\FFI $ffi = null;

    /**
     * Native callback trampolines for the event-loop helpers below.
     *
     * libui keeps raw C function pointers to these closures (a queued task,
     * a repeating timer, the should-quit handler). PHP would otherwise GC the
     * closure while libui still holds the pointer, freeing it mid-loop and
     * crashing — so we retain them here for the process lifetime.
     */
    private static array $retained = [];

    /** Whether uiInit() has already run (it must run exactly once per process). */
    private static bool $initialized = false;

    /** The package root (works both in-repo and as an installed dependency). */
    public static function root(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Resolve the libui shared library for the current OS + architecture.
     *
     * Resolution order, mirroring the per-platform layout we ship in lib/:
     *   1. $LIBUI_LIB / $LIBUI_DYLIB env override (explicit path, for dev)
     *   2. the prebuilt library committed under lib/<platform>/
     *   3. a flat lib/libui.<ext> (legacy / local one-off build)
     *
     * Linux artifacts dynamically link GTK 3, which the consumer must have
     * installed at runtime.
     */
    private static function libPath(): string
    {
        foreach (['LIBUI_LIB', 'LIBUI_DYLIB'] as $envVar) {
            $override = getenv($envVar);
            if (is_string($override) && $override !== '' && is_file($override)) {
                return $override;
            }
        }

        $root = self::root();
        $arch = strtolower(php_uname('m'));
        $isArm = str_contains($arch, 'aarch64') || str_contains($arch, 'arm');

        $candidates = match (\PHP_OS_FAMILY) {
            'Darwin'  => [
                $root . '/lib/darwin/libui.dylib', // universal: arm64 + x86_64
                $root . '/lib/libui.dylib',
            ],
            'Windows' => [
                $root . '/lib/windows-x86_64/libui.dll',
                $root . '/lib/libui.dll',
            ],
            default   => $isArm
                ? [$root . '/lib/linux-aarch64/libui.so', $root . '/lib/libui.so']
                : [$root . '/lib/linux-x86_64/libui.so', $root . '/lib/libui.so'],
        };

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Name a real, buildable location so the FFI failure is actionable.
        return $candidates[array_key_last($candidates)];
    }

    /** Lazily load the cleaned header + shared library and return the shared handle. */
    public static function get(): \FFI
    {
        if (self::$ffi === null) {
            $header = self::root() . '/src/Native/libui.gen.h';
            $lib    = self::libPath();

            if (!\extension_loaded('FFI')) {
                throw new \RuntimeException('The FFI extension is required (it ships enabled on PHP 8.5 CLI).');
            }
            if (!is_file($header)) {
                throw new \RuntimeException("Generated header missing at $header (run: composer regen).");
            }
            if (!is_file($lib)) {
                throw new \RuntimeException("libui library missing at $lib (run: composer build-lib).");
            }

            self::$ffi = \FFI::cdef(file_get_contents($header), $lib);
        }

        return self::$ffi;
    }

    /**
     * Initialise libui. Idempotent — libui's uiInit() may only run once per
     * process, so repeat calls (e.g. from each test case) are no-ops.
     * Throws the libui error message on failure.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $ffi  = self::get();
        $opts = $ffi->new('uiInitOptions');
        $opts->Size = \FFI::sizeof($opts);

        $err = $ffi->uiInit(\FFI::addr($opts));
        if ($err !== null) {
            $message = \FFI::string($err);
            // uiInit errors are freed with uiFreeInitError, NOT uiFreeText.
            $ffi->uiFreeInitError($err);
            throw new \RuntimeException("uiInit failed: $message");
        }

        self::$initialized = true;
    }

    /** Whether libui has been initialised in this process. */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    public static function main(): void
    {
        self::get()->uiMain();
    }

    public static function quit(): void
    {
        self::get()->uiQuit();
    }

    public static function uninit(): void
    {
        self::get()->uiUninit();
        self::$initialized = false;
    }

    /**
     * Queue a callback to run once on the main thread, on the next loop tick.
     *
     * The callback takes no arguments and its return value is ignored. Any
     * exception is caught and reported to STDERR — a throw escaping into the C
     * trampoline would be a hard fatal.
     */
    public static function queueMain(callable $fn): void
    {
        $cb = function ($data) use ($fn): void {
            try {
                $fn();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[queueMain] {$e->getMessage()}\n");
            }
        };
        self::$retained[] = $cb;
        self::get()->uiQueueMain($cb, null);
    }

    /**
     * Run a callback repeatedly every $milliseconds on the main thread.
     *
     * Return true (or null) from $fn to keep firing, false to stop the timer.
     * Exceptions are caught, reported to STDERR, and stop the timer.
     */
    public static function timer(int $milliseconds, callable $fn): void
    {
        $cb = function ($data) use ($fn): int {
            try {
                return $fn() === false ? 0 : 1;
            } catch (\Throwable $e) {
                fwrite(STDERR, "[timer] {$e->getMessage()}\n");
                return 0;
            }
        };
        self::$retained[] = $cb;
        self::get()->uiTimer($milliseconds, $cb, null);
    }

    /**
     * Install the should-quit handler, invoked when the platform asks the app
     * to quit. Return true from $fn to allow the quit, false to veto it.
     */
    public static function onShouldQuit(callable $fn): void
    {
        $cb = function ($data) use ($fn): int {
            try {
                return $fn() === false ? 0 : 1;
            } catch (\Throwable $e) {
                fwrite(STDERR, "[onShouldQuit] {$e->getMessage()}\n");
                return 0;
            }
        };
        self::$retained[] = $cb;
        self::get()->uiOnShouldQuit($cb, null);
    }

    /** Upcast any libui object pointer to the generic `uiControl *`. */
    public static function control(\FFI\CData $handle): \FFI\CData
    {
        return self::get()->cast('uiControl *', $handle);
    }

    /** Allocate a C value of the given type (e.g. 'uiAreaHandler', 'double[4]'). */
    public static function new(string $type, bool $owned = true): \FFI\CData
    {
        return self::get()->new($type, $owned);
    }

    /** Copy an owned C string into PHP and free it with uiFreeText. */
    public static function ownedString(?\FFI\CData $ptr): string
    {
        if ($ptr === null) {
            return '';
        }
        $value = \FFI::string($ptr);
        self::get()->uiFreeText($ptr);
        return $value;
    }

    /** Copy a borrowed C string into PHP without freeing it. */
    public static function borrowedString(?\FFI\CData $ptr): string
    {
        return $ptr === null ? '' : \FFI::string($ptr);
    }
}
