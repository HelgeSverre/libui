<?php

declare(strict_types=1);

namespace Libui;

/**
 * Top-level window. Adds lifecycle sugar on top of the generated API: sensible
 * constructor defaults, an onClose() cleanup hook, and a one-call run().
 */
class Window extends Generated\Window
{
    /** @var (callable():void)|null */
    private $onClose = null;

    public function __construct(string $title, int $width = 640, int $height = 480, bool $hasMenubar = false)
    {
        parent::__construct($title, $width, $height, $hasMenubar);
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
        Ffi::main();

        if ($afterClose !== null) {
            $afterClose();
        }

        Ffi::uninit();
    }
}
