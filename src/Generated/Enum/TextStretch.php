<?php

declare(strict_types=1);

namespace Libui\Generated\Enum;

/**
 * uiTextStretch represents possible stretches (also called "widths") of a font.
 *
 * GENERATED from libui `uiTextStretch`. DO NOT EDIT.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
enum TextStretch: int
{
    case UltraCondensed = 0;
    case ExtraCondensed = 1;
    case Condensed = 2;
    case SemiCondensed = 3;
    case Normal = 4;
    case SemiExpanded = 5;
    case Expanded = 6;
    case ExtraExpanded = 7;
    case UltraExpanded = 8;
}
