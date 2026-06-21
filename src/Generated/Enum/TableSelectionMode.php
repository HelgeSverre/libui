<?php

declare(strict_types=1);

namespace Libui\Generated\Enum;

/**
 * Table selection modes. Table selection that enforce how a user can interact with a table.
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
