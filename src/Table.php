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

    /**
     * uiTableTextColumnOptionalParams structs retained while libui holds their
     * pointers. Mirrors how {@see $params} is kept alive as a property.
     *
     * @var list<\FFI\CData>
     */
    private array $retainedStructs = [];

    /**
     * @param TableModel $model the data model backing the table
     * @param int|null $rowBackgroundModelColumn a Color model column to use for
     *        per-row background, or null for none. Read once by uiNewTable() and
     *        immutable thereafter — there is no live setter (see setRowBackground).
     */
    public function __construct(TableModel $model, ?int $rowBackgroundModelColumn = null)
    {
        $ffi = Ffi::get();
        $this->model = $model;

        $this->params = $ffi->new('uiTableParams');
        $this->params->Model = $model->handle();
        $this->params->RowBackgroundColorModelColumn = $rowBackgroundModelColumn ?? self::NO_ROW_BACKGROUND;

        $this->handle = $ffi->uiNewTable(\FFI::addr($this->params));
    }

    /** Convenience: build the model from a delegate and wrap it in a table. */
    public static function fromDelegate(TableModelDelegate $delegate, ?int $rowBackgroundModelColumn = null): self
    {
        return new self(new TableModel($delegate), $rowBackgroundModelColumn);
    }

    /** Convenience: wrap an existing TableModel in a table. */
    public static function fromModel(TableModel $model, ?int $rowBackgroundModelColumn = null): self
    {
        return new self($model, $rowBackgroundModelColumn);
    }

    /**
     * Build a read-only table from a list of positional rows.
     *
     * @param list<array<string|int>> $rows    row-major scalar cells
     * @param array<string>            $headers column titles; if empty, one column
     *        per first-row cell named "Column 1".."Column N"
     */
    public static function fromRows(array $rows, array $headers = []): static
    {
        if ($headers === []) {
            $width = $rows === [] ? 0 : \count($rows[0]);
            $headers = $width === 0
                ? []
                : array_map(static fn (int $i) => 'Column ' . ($i + 1), range(0, $width - 1));
        }

        $delegate = new ArrayTableModelDelegate(array_map('array_values', $rows), array_values($headers));
        $table = self::fromDelegate($delegate);
        foreach (array_values($headers) as $i => $name) {
            $table->appendTextColumn($name, $i);
        }

        return $table;
    }

    /**
     * Build a read-only table from a list of associative rows.
     *
     * @param list<array<string,string|int>> $rows
     * @param array<string>|null $columns column keys to show, in order; defaults
     *        to array_keys() of the first row. Header = key.
     */
    public static function fromAssoc(array $rows, ?array $columns = null): static
    {
        $columns ??= $rows === [] ? [] : array_keys($rows[0]);
        $columns = array_values($columns);

        $positional = array_map(
            static fn (array $row) => array_map(static fn (string $k) => $row[$k] ?? '', $columns),
            $rows,
        );

        return self::fromRows($positional, $columns);
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
    public function appendTextColumn(
        string $name,
        int $modelColumn,
        ?int $editableModelColumn = null,
        ?int $colorModelColumn = null,
    ): static {
        $ffi = Ffi::get();

        $params = null;
        if ($colorModelColumn !== null) {
            $struct = $ffi->new('uiTableTextColumnOptionalParams');
            $struct->ColorModelColumn = $colorModelColumn;
            $this->keepStruct($struct); // retain so the pointer stays valid for libui
            $params = \FFI::addr($struct);
        }

        $ffi->uiTableAppendTextColumn(
            $this->handle,
            $name,
            $modelColumn,
            $editableModelColumn ?? self::NEVER_EDITABLE,
            $params,
        );
        return $this;
    }

    /** Retain an optional-params struct so libui's pointer to it stays valid. */
    private function keepStruct(\FFI\CData $struct): void
    {
        $this->retainedStructs[] = $struct;
    }

    /**
     * Point the table at a Color model column for per-row background. This is
     * NOT a live setter: uiTableParams.RowBackgroundColorModelColumn is read once
     * by uiNewTable() and cannot change afterward. The method exists only to point
     * you at the constructor argument, and always throws.
     */
    public function setRowBackground(int $colorModelColumn): static
    {
        throw new \LogicException(
            'Row background must be set at construction: new Table($model, rowBackgroundModelColumn: N). '
            . 'uiTableParams.RowBackgroundColorModelColumn is read once by uiNewTable() and cannot change afterward.',
        );
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
    public function appendCheckboxColumn(string $name, int $modelColumn, ?int $editableModelColumn = null): static
    {
        Ffi::get()->uiTableAppendCheckboxColumn(
            $this->handle,
            $name,
            $modelColumn,
            $editableModelColumn ?? self::NEVER_EDITABLE,
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
     *
     * libui delivers a button press through the model's
     * SetCellValue(row, $modelColumn, null) — route click handling through the
     * delegate's {@see TableModelDelegate::setCellValue()}. The optional
     * $clickableModelColumn points at a model column gating which rows show an
     * enabled button (default: always clickable).
     */
    public function appendButtonColumn(string $name, int $modelColumn, ?int $clickableModelColumn = null): static
    {
        Ffi::get()->uiTableAppendButtonColumn(
            $this->handle,
            $name,
            $modelColumn,
            $clickableModelColumn ?? self::NEVER_EDITABLE,
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
     * The callback receives the Table instance and the clicked row index:
     * `fn (Table $t, int $row)`. Old `fn ($t)` callbacks keep working — the
     * extra argument is simply ignored.
     */
    public function onRowClicked(callable $cb): static
    {
        $fn = Control::keep(function ($t, $row) use ($cb) {
            try {
                $cb($this, $row);
            } catch (\Throwable $e) {
                fwrite(STDERR, "[Table::onRowClicked] {$e->getMessage()}\n");
            }
        });
        Ffi::get()->uiTableOnRowClicked($this->handle, $fn, null);
        return $this;
    }

    /**
     * Register a callback for when a row is double-clicked.
     *
     * The callback receives the Table instance and the clicked row index:
     * `fn (Table $t, int $row)`. Old `fn ($t)` callbacks keep working — the
     * extra argument is simply ignored.
     */
    public function onRowDoubleClicked(callable $cb): static
    {
        $fn = Control::keep(function ($t, $row) use ($cb) {
            try {
                $cb($this, $row);
            } catch (\Throwable $e) {
                fwrite(STDERR, "[Table::onRowDoubleClicked] {$e->getMessage()}\n");
            }
        });
        Ffi::get()->uiTableOnRowDoubleClicked($this->handle, $fn, null);
        return $this;
    }
}
