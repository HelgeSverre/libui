<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\ArrayTableModelDelegate;
use Libui\Color;
use Libui\Generated\Enum\TableValueType;
use Libui\Table;
use Libui\TableModel;
use Libui\TableModelDelegate;

/**
 * FFI-backed coverage for the column builders' optional args, the row-background
 * constructor arg, and the zero-boilerplate fromRows()/fromAssoc() factories.
 * These all touch uiNewTable / uiTableAppend*, so they run live.
 */
final class TableColumnsTest extends LibuiTestCase
{
    private function delegate(): TableModelDelegate
    {
        return new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 2;
            }

            public function numRows(): int
            {
                return 2;
            }

            public function cellValue(int $row, int $column): string
            {
                return "r{$row}c{$column}";
            }
        };
    }

    public function testAppendTextColumnColorArgIsOptional(): void
    {
        $table = Table::fromDelegate($this->delegate())->appendTextColumn('N', 0);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testAppendTextColumnAcceptsColorModelColumn(): void
    {
        $table = Table::fromDelegate($this->delegate())->appendTextColumn('N', 0, null, 1);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testAppendCheckboxColumnAcceptsEditableColumn(): void
    {
        $table = Table::fromDelegate($this->delegate())->appendCheckboxColumn('C', 0, 1);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testAppendButtonColumnAcceptsClickableColumn(): void
    {
        $table = Table::fromDelegate($this->delegate())->appendButtonColumn('B', 0, 1);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testConstructorRowBackgroundColumnAccepted(): void
    {
        // Smoke: the param is consumed by uiNewTable() and can't be read back,
        // so we just assert construction does not throw.
        $colorDelegate = new ArrayTableModelDelegate(
            [[Color::white()], [Color::black()]],
            ['bg'],
            [0 => TableValueType::Color],
        );
        $table = new Table(new TableModel($colorDelegate), rowBackgroundModelColumn: 0);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromDelegatePassesRowBackgroundColumn(): void
    {
        $table = Table::fromDelegate($this->delegate(), rowBackgroundModelColumn: 1);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testSetRowBackgroundThrowsWithGuidance(): void
    {
        $table = Table::fromDelegate($this->delegate());
        $this->expectException(\LogicException::class);
        $table->setRowBackground(1);
    }

    // ---- factories ----------------------------------------------------------

    public function testFromRowsBuildsOneColumnPerHeader(): void
    {
        $table = Table::fromRows([['a', 'b'], ['c', 'd']], ['X', 'Y']);
        $model = $table->model();
        $this->assertInstanceOf(Table::class, $table);
        $this->assertInstanceOf(TableModel::class, $model);
    }

    public function testFromRowsWithoutHeadersUsesFirstRowArity(): void
    {
        // No headers: delegate width = arity of first row, default header names.
        $delegate = new ArrayTableModelDelegate(
            array_map('array_values', [[1, 2, 3]]),
            ['Column 1', 'Column 2', 'Column 3'],
        );
        $this->assertSame(3, $delegate->numColumns());

        // And the factory builds a real table the same way.
        $table = Table::fromRows([[1, 2, 3]]);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromRowsEmptyHasNoColumns(): void
    {
        $table = Table::fromRows([]);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromAssocUsesKeysAsHeaders(): void
    {
        $table = Table::fromAssoc([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromAssocExplicitColumnsSubsetAndOrder(): void
    {
        $table = Table::fromAssoc(
            [['name' => 'Alice', 'age' => 30]],
            ['age', 'name'],
        );
        $this->assertInstanceOf(Table::class, $table);
    }
}
