<?php

declare(strict_types=1);

namespace Libui;

/**
 * Stacks children vertically (default) or horizontally. Adds a padded
 * constructor option and a readable stretchy append on top of the generated API.
 */
class Box extends Generated\Box
{
    public function __construct(bool $padded = false)
    {
        parent::__construct();
        if ($padded) {
            $this->setPadded(true);
        }
    }

    public static function horizontal(bool $padded = false): static
    {
        $box = parent::horizontal();
        if ($padded) {
            $box->setPadded(true);
        }
        return $box;
    }

    /** Append a child; $stretchy (bool, or the raw 0/1 int) defaults to non-stretching. */
    public function append(Control $child, bool|int $stretchy = false): static
    {
        return parent::append($child, (int) $stretchy);
    }

    /** Append a child that grows to fill the box's main axis. */
    public function appendStretchy(Control $child): static
    {
        return parent::append($child, 1);
    }
}
