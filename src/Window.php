<?php

declare(strict_types=1);

namespace Libui;

/**
 * Top-level window. Adds lifecycle sugar on top of the generated API: sensible
 * constructor defaults, an onClose() cleanup hook, and a one-call run().
 */
class Window extends Generated\Window
{
    /** True once any Window has been constructed this process — menus are then locked. */
    private static bool $menusLocked = false;

    /** @var (callable():void)|null */
    private $onClose = null;

    /** Content size requested at construction; the fallback for {@see getContentSize()}. */
    private int $width = 640;

    private int $height = 480;

    public function __construct(string $title, int $width = 640, int $height = 480, bool $hasMenubar = false)
    {
        parent::__construct($title, $width, $height, $hasMenubar);
        $this->width = $width;
        $this->height = $height;
        self::$menusLocked = true; // libui freezes the menu list at first window creation
    }

    /** Whether any Window has been created (after which new Menus are illegal). */
    public static function menusLocked(): bool
    {
        return self::$menusLocked;
    }

    /**
     * Reset the menu-ordering lock so a fresh libui session (after Ffi::uninit())
     * may build menus again. Called automatically by Ffi::uninit(); also useful
     * directly in tests that need to construct a Menu after a Window already exists.
     *
     * @internal
     */
    public static function resetMenuLock(): void
    {
        self::$menusLocked = false;
    }

    /** A Dialogs facade bound to this window as the parent. */
    public function dialogs(): Dialogs
    {
        return new Dialogs($this);
    }

    /**
     * Centre the window on the primary display.
     *
     * libui exposes no screen-size API, so the dimensions must come from
     * somewhere. On macOS they are detected automatically (a pure-C CoreGraphics
     * probe — no extra toolchain); on every other platform, or to target a
     * specific display, pass them explicitly:
     *
     *     $window->centered();            // macOS: auto-detected
     *     $window->centered(1920, 1080);  // anywhere: explicit
     *
     * Positioning is a hint that some Unix window managers ignore (see
     * {@see setPosition()}). Call before {@see show()} to place the window on open.
     *
     * @param int|null $screenWidth  Target display width in points, or null to auto-detect.
     * @param int|null $screenHeight Target display height in points, or null to auto-detect.
     * @throws \RuntimeException If no dimensions are given and the screen size can't be detected.
     */
    public function centered(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        if ($screenWidth === null || $screenHeight === null) {
            $screen = self::detectScreenSize();
            if ($screen === null) {
                throw new \RuntimeException(
                    'Window::centered() cannot detect the screen size on this platform. Pass explicit dimensions, e.g. $window->centered(1920, 1080).',
                );
            }
            [$screenWidth, $screenHeight] = $screen;
        }

        [$winWidth, $winHeight] = $this->getContentSize();

        return $this->setPosition(
            (int) \max(0, ($screenWidth - $winWidth) / 2),
            (int) \max(0, ($screenHeight - $winHeight) / 2),
        );
    }

    /**
     * The window's current content size, falling back to the constructed size
     * when libui reports a non-positive value (it may on Unix before layout).
     *
     * The content size excludes window decorations like menus or title bars.
     *
     * @return array{int, int} [width, height]
     */
    public function getContentSize(): array
    {
        $out = Ffi::get()->new('int[2]');
        Ffi::get()->uiWindowContentSize($this->handle, \FFI::addr($out[0]), \FFI::addr($out[1]));

        return [
            $out[0] > 0 ? $out[0] : $this->width,
            $out[1] > 0 ? $out[1] : $this->height,
        ];
    }

    /**
     * The window's current position, measured from the top-left of the screen.
     *
     * @note May return inaccurate or dummy values on Unix platforms.
     *
     * @return array{int, int} [x, y]
     */
    public function getPosition(): array
    {
        $out = Ffi::get()->new('int[2]');
        Ffi::get()->uiWindowPosition($this->handle, \FFI::addr($out[0]), \FFI::addr($out[1]));

        return [$out[0], $out[1]];
    }

    /**
     * Best-effort primary-display size in points, cached for the process.
     *
     * macOS only, via the pure-C CoreGraphics functions (the framework ships
     * with the OS, so this stays true to libui's "no extra toolchain" promise).
     * Returns null on any other platform or if the probe fails, leaving the
     * caller to supply dimensions explicitly.
     *
     * @return array{int, int}|null [width, height], or null if unavailable.
     */
    private static function detectScreenSize(): ?array
    {
        /** @var array{int, int}|null $size */
        static $size = null;
        static $probed = false;

        if ($probed) {
            return $size;
        }
        $probed = true;

        if (\PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        try {
            $cg = \FFI::cdef(
                'typedef uint32_t CGDirectDisplayID;'
                . 'typedef struct { double x; double y; } CGPoint;'
                . 'typedef struct { double width; double height; } CGSize;'
                . 'typedef struct { CGPoint origin; CGSize size; } CGRect;'
                . 'CGDirectDisplayID CGMainDisplayID(void);'
                . 'CGRect CGDisplayBounds(CGDirectDisplayID display);',
                '/System/Library/Frameworks/CoreGraphics.framework/CoreGraphics',
            );
            // @phpstan-ignore-next-line dynamic CoreGraphics FFI calls on a local \FFI handle
            $bounds = $cg->CGDisplayBounds($cg->CGMainDisplayID());
            $size = [(int) $bounds->size->width, (int) $bounds->size->height];
        } catch (\Throwable) {
            $size = null;
        }

        return $size;
    }

    /**
     * Run cleanup when the window is closed, before the app quits. Unlike the raw
     * onClosing(), you don't manage the loop or return a value.
     */
    public function onClose(callable $cb): static
    {
        $this->onClose = $cb;
        return $this;
    }

    /**
     * Show the window and run the event loop until it closes — the all-in-one
     * entry point for a single-window app. Initialises libui if needed, wires the
     * close button to quit (after any onClose() cleanup), and uninits on exit.
     *
     * $afterClose runs once the loop has returned and the window (and its child
     * controls) have been destroyed, just before libui shuts down — the safe
     * place to free native resources that outlive a control, e.g. a TableModel
     * (libui aborts if a model is freed while its table is still alive).
     *
     * For multiple windows or an app-level should-quit handler, use {@see App}.
     */
    public function run(?callable $afterClose = null): void
    {
        Ffi::init();

        $this->onClosing(function () {
            if ($this->onClose !== null) {
                ($this->onClose)();
            }
            Ffi::quit();
            return true;
        });

        $this->show();

        try {
            Ffi::main();

            if ($afterClose !== null) {
                $afterClose();
            }
        } finally {
            // Always tear libui down, even if $afterClose throws — otherwise the
            // next init() in the same process would be left in a bad state.
            Ffi::uninit();
        }
    }
}
