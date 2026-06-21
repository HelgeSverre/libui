<?php

declare(strict_types=1);

namespace Libui;

/**
 * Dialog helpers bound to a parent Window, so call sites don't repeat $parent.
 *
 *   $dialogs = $window->dialogs();         // or Dialogs::for($window)
 *   $dialogs->msgBox('Done', 'Saved.');
 *   $path = $dialogs->openFile();          // null on cancel
 *
 * Wraps the same libui functions as Libui\Generated\Ui, but returns ?string
 * (null on cancel) instead of '' for the file choosers.
 *
 * Known limitation: libui has no native yes/no confirmation dialog and no
 * synchronous modal result. msgBox() / error() are informational (OK-only). To
 * confirm an action, build a small modal Window with two Buttons (e.g.
 * "Confirm"/"Cancel") and run your continuation in the button's onClicked
 * callback, or drive the flow with Ffi::queueMain(...).
 */
final class Dialogs
{
    public function __construct(
        private readonly Window $parent,
    ) {}

    public static function for(Window $parent): self
    {
        return new self($parent);
    }

    public function msgBox(string $title, string $description): void
    {
        Ffi::get()->uiMsgBox($this->parent->handle(), $title, $description);
    }

    public function error(string $title, string $description): void
    {
        Ffi::get()->uiMsgBoxError($this->parent->handle(), $title, $description);
    }

    /** @return string|null Selected path, or null if cancelled. */
    public function openFile(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiOpenFile($this->parent->handle())));
    }

    /** @return string|null Selected folder, or null if cancelled. */
    public function openFolder(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiOpenFolder($this->parent->handle())));
    }

    /** @return string|null Chosen save path, or null if cancelled. */
    public function saveFile(): ?string
    {
        return $this->nullIfEmpty(Ffi::ownedString(Ffi::get()->uiSaveFile($this->parent->handle())));
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
