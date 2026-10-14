<?php

declare(strict_types=1);

namespace Libui;

/**
 * Application lifecycle for richer apps — multiple windows, a should-quit
 * handler, and one place that owns init / main loop / uninit:
 *
 *   App::new()
 *      ->window($main)
 *      ->onShouldQuit(fn () => $document->isSaved())
 *      ->run();
 *
 * For a single window with no app-level concerns, prefer {@see Window::run()}.
 */
final class App
{
    /** @var Window[] */
    private array $windows = [];

    /** @var (callable():bool)|null */
    private $shouldQuit = null;

    public static function new(): self
    {
        return new self();
    }

    /** Register a window to show when the app runs. The first one drives app lifetime. */
    public function window(Window $window): static
    {
        $this->windows[] = $window;
        return $this;
    }

    /**
     * Install a should-quit handler. Return true to allow the app to quit, false
     * to keep it running (e.g. to prompt for unsaved changes).
     */
    public function onShouldQuit(callable $cb): static
    {
        $this->shouldQuit = $cb;
        return $this;
    }

    /** Initialise libui, show the windows, run the loop until quit, then uninit. */
    public function run(): void
    {
        Ffi::init();

        if ($this->shouldQuit !== null) {
            Ffi::onShouldQuit($this->shouldQuit);
        }

        // Track which windows libui has already destroyed (a close handler that
        // returns true triggers uiControlDestroy), so the post-loop sweep below
        // doesn't double-free them.
        $closed = [];
        foreach ($this->windows as $index => $window) {
            $closed[$index] = false;
            $window->onClosing(static function () use (&$closed, $index) {
                $closed[$index] = true;
                // Closing the primary (first) window quits the app; others just close.
                if ($index === 0) {
                    Ffi::quit();
                }
                return true; // let libui destroy this window now
            });
            $window->show();
        }

        try {
            Ffi::main();

            // Destroy any window still alive (e.g. the app was quit via a
            // should-quit handler or Ffi::quit() without every window being
            // closed) before uiUninit() — leaked controls make libui abort.
            foreach ($this->windows as $index => $window) {
                if ($closed[$index]) {
                    continue;
                }
                $window->destroy();
            }
        } finally {
            Ffi::uninit();
        }
    }
}
