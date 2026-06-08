<?php

declare(strict_types=1);

namespace Libui;

use Libui\Generated\Enum\TableSelectionMode;

/**
 * A data-grid widget backed by a {@see TableModel}.
 *
 * Build a model from a {@see TableModelDelegate}, hand it here, then declare the
 * columns (which map onto model columns by index). libui pulls cell data from
 * the model on demand, so the table updates whenever you notify the model.
 */
final class Table extends Control
{
    /** Columns are never user-editable (libui's uiTableModelColumnNeverEditable). */
    private const NEVER_EDITABLE = -1;

    /** No per-row background colour column. */
    private const NO_ROW_BACKGROUND = -1;

    /** The uiTableParams struct; retained so libui's Model pointer stays valid. */
    private \FFI\CData $params;

    /** Kept so the model (and its handler/closures) outlive the table. */
    private TableModel $model;

    public function __construct(TableModel $model)
    {
        $ffi = Ffi::get();
        $this->model = $model;

        $this->params = $ffi->new('uiTableParams');
        $this->params->Model = $model->handle();
        $this->params->RowBackgroundColorModelColumn = self::NO_ROW_BACKGROUND;

        $this->handle = $ffi->uiNewTable(\FFI::addr($this->params));
    }

    /** Convenience: build the model from a delegate and wrap it in a table. */
    public static function fromDelegate(TableModelDelegate $delegate): self
    {
        return new self(new TableModel($delegate));
    }

    /** Convenience: wrap an existing TableModel in a table. */
    public static function fromModel(TableModel $model): self
    {
        return new self($model);
    }

    /** The TableModel backing this table. */
    public function model(): TableModel
    {
        return $this->model;
    }

    /**
     * Append a read-only text column titled $name that reads from model column
     * $modelColumn (String or Int values are both rendered as text).
     */
    public function appendTextColumn(string $name, int $modelColumn, ?int $editableModelColumn = null): static
    {
        Ffi::get()->uiTableAppendTextColumn(
            $this->handle,
            $name,
            $modelColumn,
            $editableModelColumn ?? self::NEVER_EDITABLE,
            null, // optional params (colour) — not used
        );
        return $this;
    }

    /**
     * Append a read-only image column titled $name that reads from model column
     * $imageModelColumn. The model should return Image instances or null for this column.
     */
    public function appendImageColumn(string $name, int $imageModelColumn): static
    {
        Ffi::get()->uiTableAppendImageColumn(
            $this->handle,
            $name,
            $imageModelColumn,
        );
        return $this;
    }

    /**
     * Append a read-only image+text column titled $name that reads from model column
     * $imageModelColumn. The model should return Image instances for the image part.
     */
    public function appendImageTextColumn(string $name, int $imageModelColumn): static
    {
        Ffi::get()->uiTableAppendImageTextColumn(
            $this->handle,
            $name,
            $imageModelColumn,
        );
        return $this;
    }

    /**
     * Append a checkbox column titled $name that reads from model column
     * $modelColumn. The model should return bool values for this column.
     */
    public function appendCheckboxColumn(string $name, int $modelColumn): static
    {
        Ffi::get()->uiTableAppendCheckboxColumn(
            $this->handle,
            $name,
            $modelColumn,
        );
        return $this;
    }

    /**
     * Append a checkbox+text column titled $name that reads from model column
     * $modelColumn. The model should return bool values for the checkbox part.
     */
    public function appendCheckboxTextColumn(string $name, int $modelColumn): static
    {
        Ffi::get()->uiTableAppendCheckboxTextColumn(
            $this->handle,
            $name,
            $modelColumn,
        );
        return $this;
    }

    /**
     * Append a progress bar column titled $name that reads from model column
     * $modelColumn. The model should return int values (0-100) for this column.
     */
    public function appendProgressBarColumn(string $name, int $modelColumn): static
    {
        Ffi::get()->uiTableAppendProgressBarColumn(
            $this->handle,
            $name,
            $modelColumn,
        );
        return $this;
    }

    /**
     * Append a button column titled $name that reads from model column
     * $modelColumn.
     */
    public function appendButtonColumn(string $name, int $modelColumn): static
    {
        Ffi::get()->uiTableAppendButtonColumn(
            $this->handle,
            $name,
            $modelColumn,
        );
        return $this;
    }

    /** Whether the column header row is shown. */
    public function headerVisible(): bool
    {
        return Ffi::get()->uiTableHeaderVisible($this->handle) !== 0;
    }

    public function setHeaderVisible(bool $visible): static
    {
        Ffi::get()->uiTableHeaderSetVisible($this->handle, (int) $visible);
        return $this;
    }

    /** Set a column's width in pixels. */
    public function setColumnWidth(int $column, int $width): static
    {
        Ffi::get()->uiTableColumnSetWidth($this->handle, $column, $width);
        return $this;
    }

    // ========================================================================
    // SELECTION MANAGEMENT
    // ========================================================================

    /**
     * Get the current selection mode for the table.
     *
     * @return \Libui\Generated\Enum\TableSelectionMode
     */
    public function selectionMode(): TableSelectionMode
    {
        return TableSelectionMode::from(Ffi::get()->uiTableGetSelectionMode($this->handle));
    }

    /**
     * Set the selection mode for the table.
     *
     * @param \Libui\Generated\Enum\TableSelectionMode $mode
     */
    public function setSelectionMode(TableSelectionMode $mode): static
    {
        Ffi::get()->uiTableSetSelectionMode($this->handle, $mode->value);
        return $this;
    }

    /**
     * Get the currently selected rows.
     *
     * @return int[] Array of selected row indices
     */
    public function selectedRows(): array
    {
        $ffi = Ffi::get();
        $sel = $ffi->uiTableGetSelection($this->handle);

        $numSelected = $sel->NumRows;
        $rows = [];

        if ($numSelected > 0 && $sel->Rows !== null) {
            for ($i = 0; $i < $numSelected; $i++) {
                $rows[] = $sel->Rows[$i];
            }
        }

        $ffi->uiFreeTableSelection($sel);

        return $rows;
    }

    /**
     * Set the selected rows programmatically.
     *
     * Honours the current selection mode (e.g. a One/ZeroOrOne table keeps only
     * one row). Pass an empty array to clear the selection.
     *
     * @param int[] $rows Array of row indices to select
     */
    public function setSelectedRows(array $rows): static
    {
        $ffi = Ffi::get();
        $sel = $ffi->new('uiTableSelection');

        if ($rows === []) {
            $sel->NumRows = 0;
            $sel->Rows = null;
            $ffi->uiTableSetSelection($this->handle, \FFI::addr($sel));
            return $this;
        }

        $rows = array_values($rows);
        $count = \count($rows);

        // Allocate a C int[] for the row indices and keep it alive for the call.
        $buffer = $ffi->new("int[{$count}]");
        foreach ($rows as $i => $row) {
            $buffer[$i] = $row;
        }

        $sel->NumRows = $count;
        $sel->Rows = $ffi->cast('int *', \FFI::addr($buffer[0]));
        $ffi->uiTableSetSelection($this->handle, \FFI::addr($sel));

        return $this;
    }

    /**
     * Register a callback for when the table selection changes.
     */
    public function onSelectionChanged(callable $cb): static
    {
        $fn = Control::keep(function ($t) use ($cb) {
            $cb($this);
        });
        Ffi::get()->uiTableOnSelectionChanged($this->handle, $fn, null);
        return $this;
    }

    /**
     * Register a callback for when a row is clicked.
     *
     * The callback receives the clicked Table instance.
     */
    public function onRowClicked(callable $cb): static
    {
        $fn = Control::keep(function ($t) use ($cb) {
            $cb($this);
        });
        Ffi::get()->uiTableOnRowClicked($this->handle, $fn, null);
        return $this;
    }

    /**
     * Register a callback for when a row is double-clicked.
     *
     * The callback receives the clicked Table instance.
     */
    public function onRowDoubleClicked(callable $cb): static
    {
        $fn = Control::keep(function ($t) use ($cb) {
            $cb($this);
        });
        Ffi::get()->uiTableOnRowDoubleClicked($this->handle, $fn, null);
        return $this;
    }
}
