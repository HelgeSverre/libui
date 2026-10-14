<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiWindow`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Window subclass instead.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
class Window extends Control
{
    /**
     * Creates a new uiWindow.
     *
     * @param string $title Window title text.
     * @param int $width Window width.
     * @param int $height Window height.
     * @param bool $hasMenubar Whether or not the window should display a menu bar.
     *
     * libui: uiNewWindow
     */
    public function __construct(string $title, int $width, int $height, bool $hasMenubar)
    {
        $this->handle = \Libui\Ffi::get()->uiNewWindow($title, $width, $height, (int) $hasMenubar);
    }

    /**
     * Returns the window title.
     *
     * @return string The window title text.
     *
     * libui: uiWindowTitle
     */
    public function title(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiWindowTitle($this->handle));
    }

    /**
     * Sets the window title.
     *
     * @param string $title Window title text.
     * @note This method is merely a hint and may be ignored on unix platforms.
     *
     * libui: uiWindowSetTitle
     */
    public function setTitle(string $title): static
    {
        \Libui\Ffi::get()->uiWindowSetTitle($this->handle, $title);
        return $this;
    }

    /**
     * Gets the window position. Coordinates are measured from the top left corner of the screen.
     *
     * @param \FFI\CData $x Output pointer written by libui.
     * @param \FFI\CData $y Output pointer written by libui.
     * @note This method may return inaccurate or dummy values on Unix platforms.
     *
     * libui: uiWindowPosition
     */
    public function position(\FFI\CData $x, \FFI\CData $y): static
    {
        \Libui\Ffi::get()->uiWindowPosition($this->handle, \FFI::addr($x), \FFI::addr($y));
        return $this;
    }

    /**
     * Moves the window to the specified position. Coordinates are measured from the top left corner of the screen.
     *
     * @param int $x New x position of the window.
     * @param int $y New y position of the window.
     * @note This method is merely a hint and may be ignored on Unix platforms.
     *
     * libui: uiWindowSetPosition
     */
    public function setPosition(int $x, int $y): static
    {
        \Libui\Ffi::get()->uiWindowSetPosition($this->handle, $x, $y);
        return $this;
    }

    /**
     * Registers a callback for when the window moved.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note Only one callback can be registered at a time.
     * @note The callback is not triggered when calling uiWindowSetPosition().
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiWindowOnPositionChanged
     */
    public function onPositionChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onPositionChanged] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnPositionChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Gets the window content size.
     *
     * @param \FFI\CData $width Output pointer written by libui.
     * @param \FFI\CData $height Output pointer written by libui.
     * @note The content size does NOT include window decorations like menus or title bars.
     *
     * libui: uiWindowContentSize
     */
    public function contentSize(\FFI\CData $width, \FFI\CData $height): static
    {
        \Libui\Ffi::get()->uiWindowContentSize($this->handle, \FFI::addr($width), \FFI::addr($height));
        return $this;
    }

    /**
     * Sets the window content size.
     *
     * @param int $width Window content width to set.
     * @param int $height Window content height to set.
     * @note The content size does NOT include window decorations like menus or title bars.
     * @note This method is merely a hint and may be ignored by the system.
     *
     * libui: uiWindowSetContentSize
     */
    public function setContentSize(int $width, int $height): static
    {
        \Libui\Ffi::get()->uiWindowSetContentSize($this->handle, $width, $height);
        return $this;
    }

    /**
     * Returns whether or not the window is full screen.
     *
     * @return bool `TRUE` if full screen, `FALSE` otherwise.
     *
     * libui: uiWindowFullscreen
     */
    public function fullscreen(): bool
    {
        return \Libui\Ffi::get()->uiWindowFullscreen($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is full screen.
     *
     * @param bool $fullscreen `TRUE` to make window full screen, `FALSE` otherwise.
     * @note This method is merely a hint and may be ignored by the system.
     *
     * libui: uiWindowSetFullscreen
     */
    public function setFullscreen(bool $fullscreen): static
    {
        \Libui\Ffi::get()->uiWindowSetFullscreen($this->handle, (int) $fullscreen);
        return $this;
    }

    /**
     * Registers a callback for when the window content size is changed.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note The callback is not triggered when calling uiWindowSetContentSize().
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiWindowOnContentSizeChanged
     */
    public function onContentSizeChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onContentSizeChanged] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnContentSizeChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the window is to be closed.
     *
     * @param callable(static): (bool|int) $cb Return false/0 to cancel, true/non-zero to continue.
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiWindowOnClosing
     */
    public function onClosing(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $result = $cb($this);
                return $result === false ? 0 : (\is_int($result) ? $result : 1);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onClosing] {$exception->getMessage()}\n");
                return 0;
            }
        });
        \Libui\Ffi::get()->uiWindowOnClosing($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the window focus changes.
     *
     * @param callable(static): void $cb Receives this widget.
     * @note Only one callback can be registered at a time.
     * @note Registering a second handler supersedes the first at the C level; the prior trampoline stays retained for the lifetime of this object.
     *
     * libui: uiWindowOnFocusChanged
     */
    public function onFocusChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, "[onFocusChanged] {$exception->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnFocusChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the window is focused.
     *
     * @return bool `TRUE` if window is focused, `FALSE` otherwise.
     *
     * libui: uiWindowFocused
     */
    public function focused(): bool
    {
        return \Libui\Ffi::get()->uiWindowFocused($this->handle) !== 0;
    }

    /**
     * Returns whether or not the window is borderless.
     *
     * @return bool `TRUE` if window is borderless, `FALSE` otherwise.
     *
     * libui: uiWindowBorderless
     */
    public function borderless(): bool
    {
        return \Libui\Ffi::get()->uiWindowBorderless($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is borderless.
     *
     * @param bool $borderless `TRUE` to make window borderless, `FALSE` otherwise.
     * @note This method is merely a hint and may be ignored by the system.
     *
     * libui: uiWindowSetBorderless
     */
    public function setBorderless(bool $borderless): static
    {
        \Libui\Ffi::get()->uiWindowSetBorderless($this->handle, (int) $borderless);
        return $this;
    }

    /**
     * Sets the window's child.
     *
     * @param \Libui\Control $child Control to be made child.
     *
     * libui: uiWindowSetChild
     */
    public function setChild(\Libui\Control $child): static
    {
        \Libui\Ffi::get()->uiWindowSetChild($this->handle, \Libui\Ffi::control($child->handle()));
        return $this;
    }

    /**
     * Returns whether or not the window has a margin.
     *
     * @return bool `TRUE` if window has a margin, `FALSE` otherwise.
     *
     * libui: uiWindowMargined
     */
    public function margined(): bool
    {
        return \Libui\Ffi::get()->uiWindowMargined($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window has a margin. The margin size is determined by the OS defaults.
     *
     * @param bool $margined `TRUE` to set a window margin, `FALSE` otherwise.
     *
     * libui: uiWindowSetMargined
     */
    public function setMargined(bool $margined): static
    {
        \Libui\Ffi::get()->uiWindowSetMargined($this->handle, (int) $margined);
        return $this;
    }

    /**
     * Returns whether or not the window is user resizeable.
     *
     * @return bool `TRUE` if window is resizable, `FALSE` otherwise.
     *
     * libui: uiWindowResizeable
     */
    public function resizeable(): bool
    {
        return \Libui\Ffi::get()->uiWindowResizeable($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is user resizeable.
     *
     * @param bool $resizeable `TRUE` to make window resizable, `FALSE` otherwise.
     * @note This method is merely a hint and may be ignored by the system.
     *
     * libui: uiWindowSetResizeable
     */
    public function setResizeable(bool $resizeable): static
    {
        \Libui\Ffi::get()->uiWindowSetResizeable($this->handle, (int) $resizeable);
        return $this;
    }

    /**
     * Returns whether or not the window is kept in front of other windows.
     *
     * @return int `TRUE` if window is kept in front, `FALSE` otherwise.
     *
     * libui: uiWindowKeepAbove
     */
    public function keepAbove(): int
    {
        return \Libui\Ffi::get()->uiWindowKeepAbove($this->handle);
    }

    /**
     * Sets whether or not the window is kept in front of other windows.
     *
     * @param int $keepAbove `TRUE` to keep window in front of others, `FALSE` otherwise.
     * @note This method is merely a hint and may be ignored by the system.
     *
     * libui: uiWindowSetKeepAbove
     */
    public function setKeepAbove(int $keepAbove): static
    {
        \Libui\Ffi::get()->uiWindowSetKeepAbove($this->handle, $keepAbove);
        return $this;
    }

    /**
     * Designates a child control as the window's draggable titlebar. Intended for borderless (custom-chrome) windows: press...
     *
     * @param \Libui\Control $titlebar A child control to use as the drag handle, or `NULL`.
     *
     * libui: uiWindowSetTitlebar
     */
    public function setTitlebar(\Libui\Control $titlebar): static
    {
        \Libui\Ffi::get()->uiWindowSetTitlebar($this->handle, \Libui\Ffi::control($titlebar->handle()));
        return $this;
    }

    /**
     * Returns the window's corner style.
     *
     * @return \Libui\Generated\Enum\WindowCornerStyle the current uiWindowCornerStyle.
     *
     * libui: uiWindowGetCornerStyle
     */
    public function getCornerStyle(): \Libui\Generated\Enum\WindowCornerStyle
    {
        return \Libui\Generated\Enum\WindowCornerStyle::from(\Libui\Ffi::get()->uiWindowGetCornerStyle($this->handle));
    }

    /**
     * Sets the window's corner style. Most relevant for borderless windows. On Windows this maps to the DWM corner preferen...
     *
     * @param \Libui\Generated\Enum\WindowCornerStyle $style a uiWindowCornerStyle.
     * @note Best-effort; may be ignored where the platform/compositor cannot honor it.
     *
     * libui: uiWindowSetCornerStyle
     */
    public function setCornerStyle(\Libui\Generated\Enum\WindowCornerStyle $style): static
    {
        \Libui\Ffi::get()->uiWindowSetCornerStyle($this->handle, $style->value);
        return $this;
    }

    /**
     * Returns whether the window casts a drop shadow.
     *
     * @return bool `TRUE` if the window has a shadow.
     *
     * libui: uiWindowShadow
     */
    public function shadow(): bool
    {
        return \Libui\Ffi::get()->uiWindowShadow($this->handle) !== 0;
    }

    /**
     * Sets whether the window casts a drop shadow. Useful to restore the shadow a borderless window otherwise loses.
     *
     * @param bool $shadow `TRUE` to cast a shadow, `FALSE` otherwise.
     *
     * libui: uiWindowSetShadow
     */
    public function setShadow(bool $shadow): static
    {
        \Libui\Ffi::get()->uiWindowSetShadow($this->handle, (int) $shadow);
        return $this;
    }
}
