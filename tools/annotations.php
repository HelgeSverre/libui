<?php

declare(strict_types=1);

/**
 * Hand-maintained tables encoding the ~2% of libui that the generator cannot
 * infer from the header's naming convention. Everything else is mechanical.
 */
return [
    // Widget types the generator must NOT emit (handled elsewhere):
    //   uiControl — the hand-written base class (Libui\Control)
    //   uiArea    — the hand-written drawing adapter (Libui\Area)
    //   uiTable   — deferred to a later pass (vtable model adapter)
    'skip_types' => ['uiControl', 'uiArea', 'uiTable'],

    // Types whose default constructor isn't a plain `uiNew<Type>`, plus their
    // alternate constructors exposed as static factory methods.
    //   type => ['primary' => uiNew*|null, 'factories' => [phpMethod => uiNew*]]
    'constructors' => [
        'uiBox' => [
            'primary' => 'uiNewVerticalBox',
            'factories' => ['horizontal' => 'uiNewHorizontalBox'],
        ],
        'uiSeparator' => [
            'primary' => 'uiNewHorizontalSeparator',
            'factories' => ['vertical' => 'uiNewVerticalSeparator'],
        ],
        'uiEntry' => [
            'primary' => 'uiNewEntry',
            'factories' => ['password' => 'uiNewPasswordEntry', 'search' => 'uiNewSearchEntry'],
        ],
        'uiMultilineEntry' => [
            'primary' => 'uiNewMultilineEntry',
            'factories' => ['nonWrapping' => 'uiNewNonWrappingMultilineEntry'],
        ],
        'uiDateTimePicker' => [
            // 'timeOnly' avoids clashing with the instance time() getter.
            'primary' => 'uiNewDateTimePicker',
            'factories' => ['dateOnly' => 'uiNewDatePicker', 'timeOnly' => 'uiNewTimePicker'],
        ],
    ],

    // Functions whose `int` is semantically a bool (setter's last arg / getter's
    // return). Cosmetic — anything not listed stays `int` and still works.
    'bool_funcs' => [
        'uiWindowMargined',
        'uiWindowSetMargined',
        'uiWindowFullscreen',
        'uiWindowSetFullscreen',
        'uiWindowBorderless',
        'uiWindowSetBorderless',
        'uiWindowResizeable',
        'uiWindowSetResizeable',
        'uiBoxPadded',
        'uiBoxSetPadded',
        'uiCheckboxChecked',
        'uiCheckboxSetChecked',
        'uiGroupMargined',
        'uiGroupSetMargined',
        'uiFormPadded',
        'uiFormSetPadded',
        'uiGridPadded',
        'uiGridSetPadded',
        'uiEntryReadOnly',
        'uiEntrySetReadOnly',
        'uiMultilineEntryReadOnly',
        'uiMultilineEntrySetReadOnly',
        'uiSliderHasToolTip',
        'uiSliderSetHasToolTip',
        'uiMenuItemChecked',
        'uiMenuItemSetChecked',
    ],

    // Enums that are bit-flags: emitted as a const class (PHP backed enums
    // cannot be OR-combined), not as a PHP enum.
    'flag_enums' => ['uiModifiers'],

    // Free (non-widget) functions to expose on the static Libui\Generated\Ui
    // facade. Everything else unmatched stays raw-callable via Ffi::get().
    'facade_funcs' => [
        'uiMsgBox',
        'uiMsgBoxError',
        'uiOpenFile',
        'uiOpenFolder',
        'uiSaveFile',
    ],

    // Callback functions with a non-standard trampoline shape (not the usual
    // `void (*f)(uiType *sender, void *data)`). Keyed by C function name.
    //   'int'        => callback returns int (PHP bool/int coerced; default 1)
    //   'menuitem'   => callback is (uiMenuItem*, uiWindow*, void*)
    'deviating_callbacks' => [
        'uiWindowOnClosing' => 'int',
        'uiMenuItemOnClicked' => 'menuitem',
    ],
];
