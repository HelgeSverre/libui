<?php

declare(strict_types=1);

namespace Libui\Generated;

use Libui\Control;

/**
 * GENERATED wrapper for libui `uiWindow`. DO NOT EDIT — run `composer regen`.
 * Add convenience methods in a hand-written Libui\\Window subclass instead.
 */
class Window extends Control
{
    /**
     * Creates a new uiWindow.
     *
     * @see uiNewWindow
     */
    public function __construct(string $title, int $width, int $height, bool $hasMenubar)
    {
        $this->handle = \Libui\Ffi::get()->uiNewWindow($title, $width, $height, (int) $hasMenubar);
    }

    /**
     * Returns the window title.
     *
     * @see uiWindowTitle
     */
    public function title(): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiWindowTitle($this->handle));
    }

    /**
     * Sets the window title.
     *
     * @see uiWindowSetTitle
     */
    public function setTitle(string $title): static
    {
        \Libui\Ffi::get()->uiWindowSetTitle($this->handle, $title);
        return $this;
    }

    /**
     * Gets the window position.
     *
     * @see uiWindowPosition
     */
    public function position(\FFI\CData $x, \FFI\CData $y): static
    {
        \Libui\Ffi::get()->uiWindowPosition($this->handle, \FFI::addr($x), \FFI::addr($y));
        return $this;
    }

    /**
     * Moves the window to the specified position.
     *
     * @see uiWindowSetPosition
     */
    public function setPosition(int $x, int $y): static
    {
        \Libui\Ffi::get()->uiWindowSetPosition($this->handle, $x, $y);
        return $this;
    }

    /**
     * Registers a callback for when the window moved.
     *
     * @see uiWindowOnPositionChanged
     */
    public function onPositionChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onPositionChanged] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnPositionChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Gets the window content size.
     *
     * @see uiWindowContentSize
     */
    public function contentSize(\FFI\CData $width, \FFI\CData $height): static
    {
        \Libui\Ffi::get()->uiWindowContentSize($this->handle, \FFI::addr($width), \FFI::addr($height));
        return $this;
    }

    /**
     * Sets the window content size.
     *
     * @see uiWindowSetContentSize
     */
    public function setContentSize(int $width, int $height): static
    {
        \Libui\Ffi::get()->uiWindowSetContentSize($this->handle, $width, $height);
        return $this;
    }

    /**
     * Returns whether or not the window is full screen.
     *
     * @see uiWindowFullscreen
     */
    public function fullscreen(): bool
    {
        return \Libui\Ffi::get()->uiWindowFullscreen($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is full screen.
     *
     * @see uiWindowSetFullscreen
     */
    public function setFullscreen(bool $fullscreen): static
    {
        \Libui\Ffi::get()->uiWindowSetFullscreen($this->handle, (int) $fullscreen);
        return $this;
    }

    /**
     * Registers a callback for when the window content size is changed.
     *
     * @see uiWindowOnContentSizeChanged
     */
    public function onContentSizeChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onContentSizeChanged] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnContentSizeChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the window is to be closed.
     *
     * @see uiWindowOnClosing
     */
    public function onClosing(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $r = $cb($this);
                return $r === false ? 0 : (\is_int($r) ? $r : 1);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onClosing] {$e->getMessage()}\n");
                return 0;
            }
        });
        \Libui\Ffi::get()->uiWindowOnClosing($this->handle, $fn, null);
        return $this;
    }

    /**
     * Registers a callback for when the window focus changes.
     *
     * @see uiWindowOnFocusChanged
     */
    public function onFocusChanged(callable $cb): static
    {
        $fn = static::keep(function ($sender, $data) use ($cb) {
            try {
                $cb($this);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[onFocusChanged] {$e->getMessage()}\n");
            }
        });
        \Libui\Ffi::get()->uiWindowOnFocusChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Returns whether or not the window is focused.
     *
     * @see uiWindowFocused
     */
    public function focused(): int
    {
        return \Libui\Ffi::get()->uiWindowFocused($this->handle);
    }

    /**
     * Returns whether or not the window is borderless.
     *
     * @see uiWindowBorderless
     */
    public function borderless(): bool
    {
        return \Libui\Ffi::get()->uiWindowBorderless($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is borderless.
     *
     * @see uiWindowSetBorderless
     */
    public function setBorderless(bool $borderless): static
    {
        \Libui\Ffi::get()->uiWindowSetBorderless($this->handle, (int) $borderless);
        return $this;
    }

    /**
     * Sets the window's child.
     *
     * @see uiWindowSetChild
     */
    public function setChild(\Libui\Control $child): static
    {
        \Libui\Ffi::get()->uiWindowSetChild($this->handle, \Libui\Ffi::control($child->handle()));
        return $this;
    }

    /**
     * Returns whether or not the window has a margin.
     *
     * @see uiWindowMargined
     */
    public function margined(): bool
    {
        return \Libui\Ffi::get()->uiWindowMargined($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window has a margin.
     *
     * @see uiWindowSetMargined
     */
    public function setMargined(bool $margined): static
    {
        \Libui\Ffi::get()->uiWindowSetMargined($this->handle, (int) $margined);
        return $this;
    }

    /**
     * Returns whether or not the window is user resizeable.
     *
     * @see uiWindowResizeable
     */
    public function resizeable(): bool
    {
        return \Libui\Ffi::get()->uiWindowResizeable($this->handle) !== 0;
    }

    /**
     * Sets whether or not the window is user resizeable.
     *
     * @see uiWindowSetResizeable
     */
    public function setResizeable(bool $resizeable): static
    {
        \Libui\Ffi::get()->uiWindowSetResizeable($this->handle, (int) $resizeable);
        return $this;
    }
}
