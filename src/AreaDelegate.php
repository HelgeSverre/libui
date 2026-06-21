<?php

declare(strict_types=1);

namespace Libui;

use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;

/**
 * Override the methods you need to drive a custom-drawn Area. All default to
 * no-ops so a draw-only delegate just overrides draw().
 */
abstract class AreaDelegate
{
    private ?Area $area = null;

    public function draw(DrawContext $ctx, AreaDrawParams $params): void {}

    public function mouse(AreaMouseEvent $event): void {}

    public function mouseCrossed(bool $left): void {}

    public function dragBroken(): void {}

    /** Return true if the key event was handled. */
    public function key(AreaKeyEvent $event): bool
    {
        return false;
    }

    /**
     * Bind this delegate to its Area. Called by {@see Area::__construct()};
     * not intended for direct use.
     *
     * @internal
     */
    public function bindArea(Area $area): void
    {
        $this->area = $area;
    }

    /** The Area this delegate drives, or null if not yet bound. */
    public function area(): ?Area
    {
        return $this->area;
    }

    /**
     * Queue a full repaint of the bound Area. No-op if the delegate has not
     * been bound to an Area yet. Subclasses call $this->redraw() from event
     * handlers instead of storing an Area and calling queueRedrawAll().
     */
    public function redraw(): void
    {
        $this->area?->queueRedrawAll();
    }
}
