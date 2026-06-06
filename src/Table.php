<?php

declare(strict_types=1);

namespace Libui;

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

    /** The TableModel backing this table. */
    public function model(): TableModel
    {
        return $this->model;
    }

    /**
     * Append a read-only text column titled $name that reads from model column
     * $modelColumn (String or Int values are both rendered as text).
     */
    public function appendTextColumn(string $name, int $modelColumn): static
    {
        Ffi::get()->uiTableAppendTextColumn(
            $this->handle,
            $name,
            $modelColumn,
            self::NEVER_EDITABLE,
            null, // optional params (colour) — not used
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
}
