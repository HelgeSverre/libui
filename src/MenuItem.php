<?php

declare(strict_types=1);

namespace Libui;

/**
 * MenuItem widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\MenuItem.
 */
class MenuItem extends Generated\MenuItem
{
    /** Re-wrap a generated MenuItem handle as a hand-written Libui\MenuItem. */
    public static function fromGenerated(Generated\MenuItem $g): self
    {
        return self::wrap($g->handle());
    }

    /**
     * Register a click handler that receives only this typed MenuItem.
     *
     * Unlike the raw onClicked(), this hides libui's raw uiWindow* second
     * argument (which must never be passed to the Dialogs/Ui facade). Capture
     * your typed Window via `use ($window)` if you need it for dialogs.
     *
     *   $item->onClick(fn (MenuItem $item) => $item->setChecked(! $item->checked()));
     *
     * @param callable(MenuItem):void $cb
     */
    public function onClick(callable $cb): static
    {
        $fn = static::keep(function ($sender, $window, $data) use ($cb): void {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onClick] {$e->getMessage()}\n");
            }
        });
        Ffi::get()->uiMenuItemOnClicked($this->handle(), $fn, null);
        return $this;
    }
}
