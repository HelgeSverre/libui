<?php

declare(strict_types=1);

namespace Libui\Generated\Enum;

/**
 * uiAttributeType holds the possible uiAttribute types that may be returned by uiAttributeGetType().
 *
 * GENERATED from libui `uiAttributeType`. DO NOT EDIT.
 */
enum AttributeType: int
{
    case Family = 0;
    case Size = 1;
    case Weight = 2;
    case Italic = 3;
    case Stretch = 4;
    case Color = 5;
    case Background = 6;
    case Underline = 7;
    case UnderlineColor = 8;
    case Features = 9;
}
