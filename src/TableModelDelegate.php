<?php

declare(strict_types=1);

namespace Libui;

use Libui\Generated\Enum\TableValueType;

/**
 * Drives a {@see TableModel} — implement this to feed a {@see Table} its data.
 *
 * The model is column-oriented: every cell is addressed by (row, column) and
 * libui pulls values lazily as it paints. Override the methods you need; the
 * defaults give you a read-only, all-string grid, which is the common case.
 *
 * libui calls these from its event loop via C function pointers, so keep the
 * implementations cheap and side-effect free (no UI mutation mid-callback).
 */
abstract class TableModelDelegate
{
    /** Total number of columns the model exposes. */
    abstract public function numColumns(): int;

    /** Total number of rows currently in the model. */
    abstract public function numRows(): int;

    /**
     * The value type of a column, deciding how libui renders/marshals it.
     * Defaults to String — override only for Int (or Color) columns.
     */
    public function columnType(int $column): TableValueType
    {
        return TableValueType::String;
    }

    /**
     * The value to display at a cell. Return a string for String columns and an
     * int for Int columns (it is marshalled into the matching uiTableValue).
     */
    abstract public function cellValue(int $row, int $column): string|int;

    /**
     * Persist an edit made in the UI. No-op by default (read-only tables); when
     * a text column is made editable, override this to store $value.
     */
    public function setCellValue(int $row, int $column, mixed $value): void {}
}
