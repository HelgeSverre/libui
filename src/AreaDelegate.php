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
    public function draw(DrawContext $ctx, AreaDrawParams $params): void {}

    public function mouse(AreaMouseEvent $event): void {}

    public function mouseCrossed(bool $left): void {}

    public function dragBroken(): void {}

    /** Return true if the key event was handled. */
    public function key(AreaKeyEvent $event): bool
    {
        return false;
    }
}
