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
     * @param \Libui\Control $parent Parent window.
     * @return string File path, `NULL` on cancel.
     * @note File paths are separated by the underlying OS file path separator.
     *
     * libui: uiOpenFile
     */
    public static function openFile(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiOpenFile($parent->handle()));
    }

    /**
     * Folder chooser dialog window to select a single folder.
     *
     * @param \Libui\Control $parent Parent window.
     * @return string Folder path, `NULL` on cancel.
     * @note File paths are separated by the underlying OS file path separator.
     *
     * libui: uiOpenFolder
     */
    public static function openFolder(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiOpenFolder($parent->handle()));
    }

    /**
     * Save file dialog window. The user is asked to confirm overwriting existing files, should the chosen file path already...
     *
     * @param \Libui\Control $parent Parent window.
     * @return string File path, `NULL` on cancel.
     * @note File paths are separated by the underlying OS file path separator.
     *
     * libui: uiSaveFile
     */
    public static function saveFile(\Libui\Control $parent): string
    {
        return \Libui\Ffi::ownedString(\Libui\Ffi::get()->uiSaveFile($parent->handle()));
    }

    /**
     * Message box dialog window. A message box displayed in a new window indicating a common message.
     *
     * @param \Libui\Control $parent Parent window.
     * @param string $title Dialog window title text.
     * @param string $description Dialog message text.
     *
     * libui: uiMsgBox
     */
    public static function msgBox(\Libui\Control $parent, string $title, string $description): void
    {
        \Libui\Ffi::get()->uiMsgBox($parent->handle(), $title, $description);
    }

    /**
     * Error message box dialog window. A message box displayed in a new window indicating an error. On some systems this ma...
     *
     * @param \Libui\Control $parent Parent window.
     * @param string $title Dialog window title text.
     * @param string $description Dialog message text.
     *
     * libui: uiMsgBoxError
     */
    public static function msgBoxError(\Libui\Control $parent, string $title, string $description): void
    {
        \Libui\Ffi::get()->uiMsgBoxError($parent->handle(), $title, $description);
    }
}
