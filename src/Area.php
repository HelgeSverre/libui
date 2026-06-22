<?php

declare(strict_types=1);

namespace Libui;

use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Generated\Enum\WindowResizeEdge;

/**
 * A custom-drawn surface, driven by an AreaDelegate.
 *
 * libui delivers draw/mouse/key events through a uiAreaHandler — a struct of C
 * function pointers. We build that struct, bind each field to a PHP closure
 * that marshals the event and calls the delegate, and keep both the struct and
 * the closures alive for the Area's lifetime (libui holds raw pointers to them).
 */
final class Area extends Control
{
    /** The uiAreaHandler struct; retained so libui's pointer stays valid. */
    private \FFI\CData $handler;

    public function __construct(AreaDelegate $delegate, ?int $scrollWidth = null, ?int $scrollHeight = null)
    {
        $ffi = Ffi::get();
        $this->handler = $this->makeHandler($delegate);

        $this->handle = $scrollWidth !== null
            ? $ffi->uiNewScrollingArea(\FFI::addr($this->handler), $scrollWidth, $scrollHeight ?? 0)
            : $ffi->uiNewArea(\FFI::addr($this->handler));

        $delegate->bindArea($this); // let the delegate call $this->redraw()
    }

    public static function scrolling(AreaDelegate $delegate, int $width, int $height): self
    {
        return new self($delegate, $width, $height);
    }

    public function queueRedrawAll(): void
    {
        Ffi::get()->uiAreaQueueRedrawAll($this->handle);
    }

    public function setSize(int $width, int $height): void
    {
        Ffi::get()->uiAreaSetSize($this->handle, $width, $height);
    }

    /**
     * Scroll the Area so the given rectangle is visible.
     *
     * Only meaningful on a scrolling Area (one built via {@see Area::scrolling()});
     * on a non-scrolling Area this is a no-op.
     */
    public function scrollTo(float $x, float $y, float $width, float $height): void
    {
        Ffi::get()->uiAreaScrollTo($this->handle, $x, $y, $width, $height);
    }

    /**
     * Begin a user-driven move of the window containing this Area.
     *
     * MUST be called only from inside an {@see AreaDelegate::mouse()} handler
     * while a mouse button is held down (a "down" event). libui's Unix/GTK
     * backend hard-aborts the process if it is called at any other time, so
     * never invoke it outside a live mouse-down handler.
     */
    public function beginUserWindowMove(): void
    {
        Ffi::get()->uiAreaBeginUserWindowMove($this->handle);
    }

    /**
     * Begin a user-driven resize of the window containing this Area from the
     * given edge.
     *
     * Subject to the same constraint as {@see beginUserWindowMove()}: call it
     * only from within an {@see AreaDelegate::mouse()} handler during a
     * mouse-down event, or libui's Unix/GTK backend aborts the process.
     */
    public function beginUserWindowResize(WindowResizeEdge $edge): void
    {
        Ffi::get()->uiAreaBeginUserWindowResize($this->handle, $edge->value);
    }

    private function makeHandler(AreaDelegate $delegate): \FFI\CData
    {
        $handler = Ffi::get()->new('uiAreaHandler');

        // libui's event loop calls these C function pointers; a PHP exception
        // escaping one is a hard fatal ("throwing from FFI callbacks is not
        // allowed"), so each is guarded and reports to STDERR instead.
        $handler->Draw = static::keep(static function ($ah, $area, $params) use ($delegate): void {
            self::guard(static fn () => $delegate->draw(new DrawContext($params->Context), AreaDrawParams::fromCData($params)));
        });
        $handler->MouseEvent = static::keep(static function ($ah, $area, $event) use ($delegate): void {
            self::guard(static fn () => $delegate->mouse(AreaMouseEvent::fromCData($event)));
        });
        $handler->MouseCrossed = static::keep(static function ($ah, $area, $left) use ($delegate): void {
            self::guard(static fn () => $delegate->mouseCrossed($left !== 0));
        });
        $handler->DragBroken = static::keep(static function ($ah, $area) use ($delegate): void {
            self::guard($delegate->dragBroken(...));
        });
        $handler->KeyEvent = static::keep(static fn ($ah, $area, $event) => self::guard(static fn () => $delegate->key(AreaKeyEvent::fromCData($event)) ? 1 : 0) ?? 0);

        return $handler;
    }

    /** Run a delegate callback, reporting any error without throwing into C. */
    private static function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[Area] handler error: {$e->getMessage()}\n  at {$e->getFile()}:{$e->getLine()}\n");
            return null;
        }
    }
}
