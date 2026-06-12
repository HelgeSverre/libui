<?php

declare(strict_types=1);

namespace Libui\Generated\Flags;

/**
 * GENERATED bit-flags from libui `uiModifiers`. DO NOT EDIT.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
final class Modifiers
{
    public const int Ctrl = 1;
    public const int Alt = 2;
    public const int Shift = 4;
    public const int Super = 8;

    public static function has(int $mask, int $flag): bool
    {
        return ($mask & $flag) === $flag;
    }
}
