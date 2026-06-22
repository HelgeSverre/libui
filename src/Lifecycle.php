<?php

declare(strict_types=1);

namespace Libui;

/**
 * Process-wide registry of native resources that must be released before
 * uiUninit() — libui's leak checker aborts in uiUninit() if a uiTableModel or
 * uiImage is left unfreed, so {@see Ffi::uninit()} drains this registry first.
 *
 * This neutralises the "forgotten free()" footgun: a {@see TableModel} or
 * {@see Image} registers itself on construction and de-registers on free(), and
 * uninit() frees whatever is still live (after the control tree — hence any
 * uiTable — is destroyed).
 *
 * Ordering matters: models are freed BEFORE images. A {@see TableModel}'s
 * lazy fallback {@see Image} (see {@see TableModel::imageValue()}) is pinned by
 * the model's CellValue closure, so freeing images first could pull that Image
 * out from under a model that {@see TableModel::free()} still touches.
 */
final class Lifecycle
{
    /** @var \SplObjectStorage<TableModel,null>|null */
    private static ?\SplObjectStorage $models = null;

    /** @var \SplObjectStorage<Image,null>|null */
    private static ?\SplObjectStorage $images = null;

    public static function registerModel(TableModel $model): void
    {
        self::$models ??= new \SplObjectStorage();
        self::$models->offsetSet($model, null);
    }

    public static function unregisterModel(TableModel $model): void
    {
        if (self::$models?->offsetExists($model)) {
            self::$models->offsetUnset($model);
        }
    }

    public static function registerImage(Image $image): void
    {
        self::$images ??= new \SplObjectStorage();
        self::$images->offsetSet($image, null);
    }

    public static function unregisterImage(Image $image): void
    {
        if (self::$images?->offsetExists($image)) {
            self::$images->offsetUnset($image);
        }
    }

    /**
     * Free every still-live registered resource exactly once.
     *
     * Called by {@see Ffi::uninit()} immediately before uiUninit().
     * {@see TableModel::free()} and {@see Image::free()} are idempotent and
     * de-register, so manual free()s earlier are safe — they just make this a
     * no-op for those resources.
     *
     * Models are freed before images so a model's fallback {@see Image} is never
     * freed out from under it during its own teardown.
     */
    public static function freeAll(): void
    {
        if (self::$models !== null) {
            // Snapshot: free() mutates the storage via unregisterModel().
            foreach (iterator_to_array(self::$models) as $model) {
                $model->free();
            }
            self::$models = new \SplObjectStorage();
        }

        if (self::$images !== null) {
            // Snapshot: free() mutates the storage via unregisterImage().
            foreach (iterator_to_array(self::$images) as $image) {
                $image->free();
            }
            self::$images = new \SplObjectStorage();
        }
    }
}
