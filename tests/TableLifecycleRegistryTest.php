<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Lifecycle;
use Libui\TableModel;
use Libui\TableModelDelegate;

/**
 * The process-wide {@see Lifecycle} registry that frees forgotten TableModels
 * right before uiUninit().
 *
 * freeAll() itself can only be exercised end-to-end in a subprocess: in the
 * shared PHPUnit process the global registry accumulates models from other
 * FFI-backed tests whose uiTables are still alive, and freeing one of those
 * would abort libui ("cannot free a uiTableModel while uiTables are using it").
 * The subprocess proof lives in {@see TableModelTest} ('auto' / 'leak' modes);
 * here we cover the in-process register / unregister / idempotent-free contract
 * against bare models (no Table attached, so they are always safe to free).
 */
final class TableLifecycleRegistryTest extends LibuiTestCase
{
    private function makeModel(): TableModel
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'x';
            }
        };

        return new TableModel($delegate);
    }

    public function testRegisteredModelFreesCleanly(): void
    {
        $this->expectNotToPerformAssertions();

        $model = $this->makeModel();
        $model->free();

        // A second free() must be a no-op; if it weren't idempotent libui aborts.
        $model->free();
    }

    public function testUnregisterIsSafeAndFreeStillWorks(): void
    {
        $this->expectNotToPerformAssertions();

        $model = $this->makeModel();
        Lifecycle::unregisterModel($model);

        // Unregistering does not free; we still own it and free it ourselves so
        // no leak survives to uiUninit().
        $model->free();
    }

    public function testUnregisterUnknownModelIsNoOp(): void
    {
        $this->expectNotToPerformAssertions();

        $model = $this->makeModel();
        $model->free(); // frees + de-registers

        // De-registering an already-removed model must not error.
        Lifecycle::unregisterModel($model);
    }
}
