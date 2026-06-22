<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Image;
use Libui\Lifecycle;
use Libui\TableModel;
use Libui\TableModelDelegate;

/**
 * The {@see Lifecycle} registry side of {@see Image}: every image registers on
 * construction, free() is idempotent and de-registers, and freeAll() frees
 * models BEFORE images so a model's lazy fallback Image (see
 * {@see TableModel::imageValue()}) is never pulled out from under it.
 *
 * Like {@see TableLifecycleRegistryTest}, freeAll()'s full sweep can only be
 * driven safely against bare resources here — the shared PHPUnit process
 * accumulates live uiImages/uiTableModels from other FFI tests whose owners are
 * still alive, and freeing one of those would abort libui. So we assert the
 * in-process register / unregister / idempotent-free / ordering contract using
 * resources we own end-to-end.
 */
final class ImageLifecycleTest extends LibuiTestCase
{
    public function testConstructedImageRegistersAndUnregistersOnFree(): void
    {
        $image = new Image(4.0, 4.0);

        // A freshly constructed image is in the registry; unregister returns it
        // to our sole ownership without freeing (handle still valid).
        Lifecycle::unregisterImage($image);
        $this->assertNotNull($image->handle(), 'unregister must not free the image');

        // Re-register so free() exercises the de-register path, then free.
        Lifecycle::registerImage($image);
        $image->free();
        $this->assertNull($image->handle(), 'free() should null the handle');
    }

    public function testFreeIsIdempotentAndDeRegisters(): void
    {
        $this->expectNotToPerformAssertions();

        $image = new Image(8.0, 8.0);
        $image->free(); // frees + de-registers
        $image->free(); // must be a no-op; a double uiFreeImage aborts libui

        // De-registering an already-removed image must not error.
        Lifecycle::unregisterImage($image);
    }

    public function testUnregisterUnknownImageIsNoOp(): void
    {
        $this->expectNotToPerformAssertions();

        $image = new Image(2.0, 2.0);
        $image->free();
        Lifecycle::unregisterImage($image); // already gone — no error
    }

    /**
     * Drop-path regression: a model and its fallback Image must tear down in the
     * model-before-image order freeAll() uses. We reproduce that order against a
     * bare model (no Table attached, always safe to free) plus an Image, proving
     * the sequence libui requires does not abort. Reversing it (image first)
     * would risk a use-after-free if the model's teardown still touched the
     * image — the very bug the ordering prevents.
     */
    public function testModelIsFreedBeforeImageOrdering(): void
    {
        $this->expectNotToPerformAssertions();

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

        $model = new TableModel($delegate);
        $image = new Image(1.0, 1.0);

        // Same order as Lifecycle::freeAll(): models first, then images.
        $model->free();
        $image->free();
    }
}
