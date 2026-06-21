<?php

declare(strict_types=1);

namespace Libui;

use Libui\Generated\Enum\TableValueType;

/**
 * Bridges a {@see TableModelDelegate} to libui's uiTableModel.
 *
 * libui pulls table data through a uiTableModelHandler — a struct of five C
 * function pointers (NumColumns/ColumnType/NumRows/CellValue/SetCellValue). We
 * build that struct, bind each field to a PHP closure that defers to the
 * delegate, and keep both the struct and the closures alive for the model's
 * lifetime (libui holds raw pointers to them).
 *
 * The closures run inside libui's event loop, so a PHP exception escaping one
 * is a hard fatal ("throwing from FFI callbacks is not allowed"); each is
 * wrapped in guard() and reports to STDERR with a safe fallback instead.
 */
final class TableModel
{
    /** The uiTableModelHandler vtable; retained so libui's pointer stays valid. */
    private \FFI\CData $handler;

    /** The uiTableModel* created from the handler. */
    private \FFI\CData $model;

    /** Whether {@see free()} has already released the model (guards double-free). */
    private bool $freed = false;

    /** Trampolines for the five vtable fields, retained against GC. */
    private array $callbacks = [];

    public static function fromDelegate(TableModelDelegate $delegate): self
    {
        return new self($delegate);
    }

    public function __construct(
        private readonly TableModelDelegate $delegate,
    ) {
        $ffi = Ffi::get();
        $this->handler = $this->makeHandler();
        $this->model = $ffi->uiNewTableModel(\FFI::addr($this->handler));
        Lifecycle::registerModel($this);
    }

    /** The raw uiTableModel* — pass this into a {@see Table}. */
    public function handle(): \FFI\CData
    {
        return $this->model;
    }

    /**
     * Release the underlying uiTableModel.
     *
     * libui's allocation tracker aborts the process inside {@see Ffi::uninit()}
     * if any model is left unfreed (SIGTRAP, exit 133), so every model must be
     * freed exactly once. The ordering is strict — libui also aborts if you
     * free a model while a uiTable is still using it — so:
     *
     *   1. the owning {@see Table} must already be destroyed, and
     *   2. {@see Ffi::uninit()} must come afterwards.
     *
     * In the usual flow the table is destroyed together with its window when
     * the window closes, so free the model once the loop has returned:
     *
     *   Ffi::main();
     *   $table->model()->free();
     *   Ffi::uninit();
     *
     * Idempotent: a second call is a no-op (freeing twice also aborts libui).
     *
     * You no longer have to remember this: every model registers itself with
     * {@see Lifecycle}, and {@see Ffi::uninit()} frees whatever is still live
     * before tearing libui down. Calling free() explicitly remains valid (it
     * frees + de-registers, leaving uninit()'s sweep a no-op).
     */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        Ffi::get()->uiFreeTableModel($this->model);
        $this->freed = true;
        Lifecycle::unregisterModel($this);
    }

    /** Notify libui that a new row appeared at $index so it can refresh. */
    public function rowInserted(int $index): void
    {
        Ffi::get()->uiTableModelRowInserted($this->model, $index);
    }

    /** Notify libui that the row at $index changed so it can repaint it. */
    public function rowChanged(int $index): void
    {
        Ffi::get()->uiTableModelRowChanged($this->model, $index);
    }

    /** Notify libui that the row at $index was removed. */
    public function rowDeleted(int $index): void
    {
        Ffi::get()->uiTableModelRowDeleted($this->model, $index);
    }

    private function makeHandler(): \FFI\CData
    {
        $ffi = Ffi::get();
        $handler = $ffi->new('uiTableModelHandler');
        $delegate = $this->delegate;

        $this->callbacks['NumColumns'] = static fn ($mh, $m) => self::guard($delegate->numColumns(...), 0);
        $this->callbacks['ColumnType'] = static fn ($mh, $m, $column) => self::guard(
            static fn () => $delegate->columnType($column)->value,
            TableValueType::String->value,
        );
        $this->callbacks['NumRows'] = static fn ($mh, $m) => self::guard($delegate->numRows(...), 0);
        // libui takes ownership of the returned uiTableValue* and frees it, so we
        // mint a fresh one per call and hand off the pointer. The image fallback
        // (below) is created on demand and pinned by this closure — itself retained
        // in $this->callbacks — so it outlives the table.
        $fallbackImage = null;
        $this->callbacks['CellValue'] = static function ($mh, $m, $row, $column) use ($delegate, $ffi, &$fallbackImage) {
            return self::guard(
                static function () use ($delegate, $ffi, $row, $column, &$fallbackImage) {
                    $type = $delegate->columnType($column);
                    $value = $delegate->cellValue($row, $column);

                    // A null return means "no value" for this cell (e.g. an empty
                    // row-background colour) — fine for Color/String/Int, which libui
                    // NULL-guards. Image is the exception (see imageValue()).
                    return match ($type) {
                        TableValueType::Int => $ffi->uiNewTableValueInt((int) $value),
                        TableValueType::Color => $value instanceof Color
                            ? $ffi->uiNewTableValueColor($value->r, $value->g, $value->b, $value->a)
                            : null,
                        TableValueType::Image => self::imageValue($ffi, $value, $fallbackImage),
                        // String column: bool is cast to "1"/"" by PHP — checkbox columns
                        // are Int, so bool-as-text here is the caller's explicit choice.
                        default => $ffi->uiNewTableValueString((string) $value),
                    };
                },
                null,
            );
        };
        $this->callbacks['SetCellValue'] = static function ($mh, $m, $row, $column, $value) use ($delegate, $ffi): void {
            self::guard(
                static function () use ($delegate, $ffi, $row, $column, $value): void {
                    // $value is null when libui clears a cell (e.g. button columns).
                    $marshalled = null;
                    if ($value !== null) {
                        $type = $ffi->uiTableValueGetType($value);
                        $marshalled = $type === TableValueType::Int->value
                            ? $ffi->uiTableValueInt($value)
                            : Ffi::borrowedString($ffi->uiTableValueString($value));
                    }
                    $delegate->setCellValue($row, $column, $marshalled);
                },
                null,
            );
        };

        $handler->NumColumns = $this->callbacks['NumColumns'];
        $handler->ColumnType = $this->callbacks['ColumnType'];
        $handler->NumRows = $this->callbacks['NumRows'];
        $handler->CellValue = $this->callbacks['CellValue'];
        $handler->SetCellValue = $this->callbacks['SetCellValue'];

        return $handler;
    }

    /** Run a delegate callback, returning $fallback rather than throwing into C. */
    private static function guard(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[TableModel] handler error: {$e->getMessage()}\n  at {$e->getFile()}:{$e->getLine()}\n");
            return $fallback;
        }
    }

    /**
     * Marshal an Image-column cell value into a uiTableValue*.
     *
     * libui's image column dereferences the value with NO null guard (unlike the
     * colour/string/int paths), so returning a C NULL for a missing image would
     * segfault the process. When a cell yields a non-Image we substitute a shared
     * 1x1 transparent fallback (lazily created, pinned via $fallback by reference)
     * and warn, turning a hard crash into a blank cell.
     */
    private static function imageValue(\FFI $ffi, mixed $value, ?Image &$fallback): \FFI\CData
    {
        if ($value instanceof Image && $value->handle() !== null) {
            return $ffi->uiNewTableValueImage($value->handle());
        }

        fwrite(STDERR, "[TableModel] an Image column cell returned a non-Image value; using a blank fallback\n");
        $fallback ??= Image::fromRgba("\x00\x00\x00\x00", 1, 1);

        return $ffi->uiNewTableValueImage($fallback->handle());
    }
}
