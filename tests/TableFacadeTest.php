<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\ArrayTableModelDelegate;
use Libui\Generated\Enum\TableValueType;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage for the Tables facade helpers that need no live libui:
 * the {@see ArrayTableModelDelegate} delegate behaviour. Factory wiring that
 * constructs a real Table (which calls uiNewTable) lives in TableFunctionalTest.
 */
final class TableFacadeTest extends TestCase
{
    public function testArrayDelegateNumColumnsFromHeaders(): void
    {
        $d = new ArrayTableModelDelegate([['a', 'b']], ['X', 'Y']);
        $this->assertSame(2, $d->numColumns());
        $this->assertSame(['X', 'Y'], $d->headers());
    }

    public function testArrayDelegateNumRowsReflectsArray(): void
    {
        $this->assertSame(0, new ArrayTableModelDelegate([], ['X'])->numRows());
        $this->assertSame(2, new ArrayTableModelDelegate([['a'], ['b']], ['X'])->numRows());
    }

    public function testArrayDelegateCellValuesMatch(): void
    {
        $d = new ArrayTableModelDelegate([['hello', 42]], ['X', 'Y']);
        $this->assertSame('hello', $d->cellValue(0, 0));
        $this->assertSame(42, $d->cellValue(0, 1));
    }

    public function testArrayDelegateMissingCellYieldsEmptyString(): void
    {
        $d = new ArrayTableModelDelegate([['only']], ['X', 'Y']);
        $this->assertSame('', $d->cellValue(0, 1));
        $this->assertSame('', $d->cellValue(5, 0));
    }

    public function testArrayDelegateColumnTypeDefaultsString(): void
    {
        $d = new ArrayTableModelDelegate([['a']], ['X']);
        $this->assertSame(TableValueType::String, $d->columnType(0));
    }

    public function testArrayDelegateColumnTypeHonoursTypesMap(): void
    {
        $d = new ArrayTableModelDelegate([[1]], ['N'], [0 => TableValueType::Int]);
        $this->assertSame(TableValueType::Int, $d->columnType(0));
        $this->assertSame(TableValueType::String, $d->columnType(1));
    }

    public function testArrayDelegatePreservesScalarTypes(): void
    {
        // Strings stay strings, ints stay ints — no silent coercion.
        $d = new ArrayTableModelDelegate([['x', 7]], ['A', 'B']);
        $this->assertIsString($d->cellValue(0, 0));
        $this->assertIsInt($d->cellValue(0, 1));
    }
}
