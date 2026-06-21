<?php

declare(strict_types=1);

namespace Libui;

/**
 * Process-wide registry of native resources that must be released before
 * uiUninit(). Today: uiTableModels — libui's leak checker aborts in uiUninit()
 * if a model is left unfreed, so {@see Ffi::uninit()} drains this registry first.
 *
 * This neutralises the "forgotten free()" footgun: a {@see TableModel} registers
 * itself on construction and de-registers on free(), and uninit() frees whatever
 * is still live (after the control tree — hence the uiTable — is destroyed).
 */
final class Lifecycle
{
    /** @var \SplObjectStorage<TableModel,null>|null */
    private static ?\SplObjectStorage $models = null;

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

    /**
     * Free every still-live registered model exactly once.
     *
     * Called by {@see Ffi::uninit()} immediately before uiUninit().
     * {@see TableModel::free()} is idempotent and de-registers, so manual free()s
     * earlier are safe — they just make this a no-op for those models.
     */
    public static function freeAll(): void
    {
        if (self::$models === null) {
            return;
        }
        // Snapshot: free() mutates the storage via unregisterModel().
        foreach (iterator_to_array(self::$models) as $model) {
            $model->free();
        }
        self::$models = new \SplObjectStorage();
    }
}
