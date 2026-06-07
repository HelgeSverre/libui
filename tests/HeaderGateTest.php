<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Ffi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The "gate": prove FFI::cdef() accepts the full generated header and that the
 * generator emitted the symbols every subsystem depends on. If this is red, the
 * whole binding is broken — run `composer regen`.
 */
#[Group('gate')]
final class HeaderGateTest extends LibuiTestCase
{
    public function testGeneratedHeaderExists(): void
    {
        $this->assertFileExists(Ffi::root() . '/src/Native/libui.gen.h');
    }

    public function testFfiAcceptsTheGeneratedHeader(): void
    {
        // setUpBeforeClass() already ran FFI::cdef() via Ffi::init(); a header
        // FFI couldn't parse would have thrown there. Assert we hold a handle.
        $this->assertInstanceOf(\FFI::class, Ffi::get());
    }

    #[DataProvider('representativeSymbols')]
    public function testHeaderDeclaresSymbol(string $symbol): void
    {
        $header = file_get_contents(Ffi::root() . '/src/Native/libui.gen.h');

        $this->assertStringContainsString(
            $symbol . '(',
            $header,
            "{$symbol} should be declared in the generated header",
        );
    }

    /**
     * A spread of declarations across every subsystem (core, widgets, dialogs,
     * drawing, tables, and the struct-tm date picker).
     *
     * @return iterable<string, array{string}>
     */
    public static function representativeSymbols(): iterable
    {
        $symbols = [
            'uiInit',
            'uiMain',
            'uiQuit',
            'uiNewWindow',
            'uiNewButton',
            'uiNewCheckbox',
            'uiNewSlider',
            'uiNewCombobox',
            'uiNewMultilineEntry',
            'uiNewDatePicker',
            'uiMsgBox',
            'uiOpenFile',
            'uiNewArea',
            'uiDrawNewPath',
            'uiNewTableModel',
            'uiDateTimePickerTime',
        ];

        foreach ($symbols as $symbol) {
            yield $symbol => [$symbol];
        }
    }
}
