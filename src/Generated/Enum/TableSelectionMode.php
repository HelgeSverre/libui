<?php

declare(strict_types=1);

namespace Libui\Generated\Enum;

/**
 * Table selection modes.
 *
 * GENERATED from libui `uiTableSelectionMode`. DO NOT EDIT.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
enum TableSelectionMode: int
{
    case None = 0;
    case ZeroOrOne = 1;
    case One = 2;
    case ZeroOrMany = 3;
}
