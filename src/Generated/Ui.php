<?php

declare(strict_types=1);

namespace Libui\Generated;

/**
 * GENERATED facade for libui free functions (dialogs, etc.). DO NOT EDIT.
 *
 * @generated from libui-ng ui.h by tools/generate.php
 */
final class Ui
{
    /**
     * File chooser dialog window to select a single file.
     *
     * @see uiOpenFile
     */
    public static function openFile(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiOpenFile($parent->handle()));
    }

    /**
     * Folder chooser dialog window to select a single folder.
     *
     * @see uiOpenFolder
     */
    public static function openFolder(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiOpenFolder($parent->handle()));
    }

    /**
     * Save file dialog window.
     *
     * @see uiSaveFile
     */
    public static function saveFile(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiSaveFile($parent->handle()));
    }

    /**
     * Message box dialog window.
     *
     * @see uiMsgBox
     */
    public static function msgBox(\Libui\Control $parent, string $title, string $description): void
    {
        \Libui\Ffi::get()->uiMsgBox($parent->handle(), $title, $description);
    }

    /**
     * Error message box dialog window.
     *
     * @see uiMsgBoxError
     */
    public static function msgBoxError(\Libui\Control $parent, string $title, string $description): void
    {
        \Libui\Ffi::get()->uiMsgBoxError($parent->handle(), $title, $description);
    }
}
