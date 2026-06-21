<?php

declare(strict_types=1);

namespace Libui;

/**
 * The single FFI handle bound to libui-ng, plus library lifecycle and the
 * low-level marshalling helpers the generated classes and the drawing adapter
 * rely on.
 *
 * Dynamic libui functions are documented on {@see \Libui\Generated\FfiFunctions}
 * and intersected with the raw \FFI return type so static analysis understands
 * calls like `Ffi::get()->uiMsgBox(...)`.
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

    /**
     * Returns the absolute path to the package root directory.
     *
     * This works both when running from the repository itself and when
     * the library is installed as a Composer dependency.
     *
     * @return string The absolute path to the package root (directory containing src/)
     */
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
            'Darwin' => [
                $root . '/lib/darwin/libui.dylib', // universal: arm64 + x86_64
                $root . '/lib/libui.dylib',
            ],
            'Windows' => [
                $root . '/lib/windows-x86_64/libui.dll',
                $root . '/lib/libui.dll',
            ],
            default => $isArm
                ? [$root . '/lib/linux-aarch64/libui.so', $root . '/lib/libui.so']
                : [$root . '/lib/linux-x86_64/libui.so', $root . '/lib/libui.so'],
        };

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Provide helpful guidance on how to obtain the library
        $platform = match (\PHP_OS_FAMILY) {
            'Darwin' => 'macOS (universal)',
            'Windows' => 'Windows x86_64',
            default => $isArm ? 'Linux ARM64' : 'Linux x86_64',
        };

        throw new \RuntimeException(
            "libui shared library not found.\n\n"
            . "Expected: lib/{$platform}/libui."
            . (\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : (\PHP_OS_FAMILY === 'Windows' ? 'dll' : 'so'))
            . "\n\n"
            . "Options:\n"
            . "  1. Build from source: composer build-lib\n"
            . "  2. Override path: LIBUI_LIB=/path/to/libui.so composer test\n"
            . '  3. Install prebuilt: see README.md Platform support section',
        );
    }

    /**
     * Returns the singleton FFI instance bound to libui-ng.
     *
     * Lazily loads the cleaned FFI header and shared library on first call.
     * The returned \FFI instance has all libui functions bound and callable.
     *
     * @return \FFI The singleton FFI handle with libui methods
     * @see \Libui\Generated\FfiFunctions for the generated method contract
     * @throws \RuntimeException If FFI extension is not loaded
     * @throws \RuntimeException If generated header is missing (run: composer regen)
     * @throws \RuntimeException If libui library is missing (run: composer build-lib)
     */
    public static function get(): \FFI
    {
        if (self::$ffi === null) {
            $header = self::root() . '/src/Native/libui.gen.h';
            $lib = self::libPath();

            if (! \extension_loaded('FFI')) {
                throw new \RuntimeException('The FFI extension is required (it ships enabled on the PHP CLI).');
            }
            if (! is_file($header)) {
                throw new \RuntimeException("Generated header missing at {$header} (run: composer regen).");
            }
            if (! is_file($lib)) {
                throw new \RuntimeException("libui library missing at {$lib} (run: composer build-lib).");
            }

            self::$ffi = \FFI::cdef(file_get_contents($header), $lib);
        }

        return self::$ffi;
    }

    /**
     * Initializes the libui library.
     *
     * This must be called exactly once per process before using any libui widgets.
     * It is idempotent - repeat calls are no-ops, which is safe for test suites
     * where multiple test cases may call init().
     *
     * @throws \RuntimeException If libui initialization fails (includes the error message)
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $ffi = self::get();
        $opts = $ffi->new('uiInitOptions');
        $opts->Size = \FFI::sizeof($opts);

        $err = $ffi->uiInit(\FFI::addr($opts));
        if ($err !== null) {
            $message = \FFI::string($err);
            // uiInit errors are freed with uiFreeInitError, NOT uiFreeText.
            $ffi->uiFreeInitError($err);
            throw new \RuntimeException("uiInit failed: {$message}");
        }

        self::$initialized = true;
    }

    /**
     * Checks whether libui has been initialized in the current process.
     *
     * @return bool True if libui is initialized, false otherwise
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Runs the libui event loop.
     *
     * This blocks until Ffi::quit() is called or all windows are closed.
     * For a complete application lifecycle, use App::run() instead.
     *
     * @see App::run() for a higher-level application lifecycle
     */
    public static function main(): void
    {
        self::get()->uiMain();
    }

    /**
     * Requests that the event loop quit.
     *
     * This causes Ffi::main() to return. Safe to call from any thread.
     */
    public static function quit(): void
    {
        self::get()->uiQuit();
    }

    /**
     * Shuts down the libui library.
     *
     * This uninitializes libui and frees all resources. Must be called after
     * Ffi::main() returns, typically via App::run() which handles this automatically.
     *
     * Note: After calling uninit(), you must call init() again before using libui.
     */
    public static function uninit(): void
    {
        Lifecycle::freeAll(); // free any forgotten TableModels before libui's leak check
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
        $cb = static function ($data) use ($fn): void {
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
        $cb = static function ($data) use ($fn): int {
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
        $cb = static function ($data) use ($fn): int {
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

    /**
     * Upcasts any libui widget handle to the generic uiControl pointer type.
     *
     * This is used internally by the generated widget classes for operations
     * that work on any control, such as adding to containers.
     *
     * @param \FFI\CData $handle The widget-specific handle (e.g., uiButton *)
     * @return \FFI\CData The upcast handle as uiControl *
     */
    public static function control(\FFI\CData $handle): \FFI\CData
    {
        return self::get()->cast('uiControl *', $handle);
    }

    /**
     * Allocates a C value or struct of the given type.
     *
     * This is a convenience wrapper around \FFI::new() that uses the singleton FFI handle.
     * Use this to create C structs, arrays, or primitive values.
     *
     * @param string $type The C type to allocate (e.g., 'uiAreaHandler', 'double[4]', 'int')
     * @param bool $owned Whether the C memory is owned by PHP (default: true)
     * @return \FFI\CData The allocated C data
     *
     * @see \FFI::new() for the underlying FFI allocation
     */
    public static function new(string $type, bool $owned = true): \FFI\CData
    {
        return self::get()->new($type, $owned);
    }

    /**
     * Copies an owned C string into PHP and frees it with uiFreeText.
     *
     * Use this for C functions that return a heap-allocated char * that libui owns.
     * The string is copied into PHP memory and the C memory is freed.
     *
     * @param \FFI\CData|null $ptr Pointer to the C string, or null
     * @return string The copied string content, or empty string if $ptr is null
     *
     * @see Ffi::borrowedString() for strings that should NOT be freed
     */
    public static function ownedString(?\FFI\CData $ptr): string
    {
        if ($ptr === null) {
            return '';
        }
        $value = \FFI::string($ptr);
        self::get()->uiFreeText($ptr);
        return $value;
    }

    /**
     * Copies a borrowed C string into PHP without freeing it.
     *
     * Use this for C functions that return a const char * or a pointer to static/stack
     * memory that libui does NOT own. The string is copied but the C memory is not freed.
     *
     * @param \FFI\CData|null $ptr Pointer to the C string, or null
     * @return string The copied string content, or empty string if $ptr is null
     *
     * @see Ffi::ownedString() for strings that should be freed
     */
    public static function borrowedString(?\FFI\CData $ptr): string
    {
        return $ptr === null ? '' : \FFI::string($ptr);
    }
}
