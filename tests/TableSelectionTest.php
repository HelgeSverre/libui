<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\TableSelectionMode;
use Libui\Table;
use Libui\TableModelDelegate;
use PHPUnit\Framework\Attributes\Group;

/**
 * Selection-mode and row-selection round trips on a live Table.
 *
 * Pins three bugs: setSelectionMode passed the enum object (not its int value),
 * selectionMode() returned a raw int where the signature promised the enum, and
 * setSelectedRows() was a no-op stub.
 */
#[Group('smoke')]
final class TableSelectionTest extends LibuiTestCase
{
    private function makeTable(int $rows = 5): Table
    {
        $delegate = new class($rows) extends TableModelDelegate {
            public function __construct(
                private int $rows,
            ) {}

            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return $this->rows;
            }

            public function cellValue(int $row, int $column): string
            {
                return "row{$row}";
            }
        };

        $table = Table::fromDelegate($delegate);
        $table->appendTextColumn('Name', 0);
        return $table;
    }

    public function testSelectionModeRoundTrip(): void
    {
        $table = $this->makeTable();

        $table->setSelectionMode(TableSelectionMode::ZeroOrMany);
        $this->assertSame(TableSelectionMode::ZeroOrMany, $table->selectionMode());

        $table->setSelectionMode(TableSelectionMode::One);
        $this->assertSame(TableSelectionMode::One, $table->selectionMode());
    }

    public function testSetSelectedRowsSelectsThem(): void
    {
        $table = $this->makeTable();
        $table->setSelectionMode(TableSelectionMode::ZeroOrMany);

        $table->setSelectedRows([1, 3]);
        $this->assertSame([1, 3], $table->selectedRows());
    }

    public function testSetSelectedRowsEmptyClearsSelection(): void
    {
        $table = $this->makeTable();
        $table->setSelectionMode(TableSelectionMode::ZeroOrMany);

        $table->setSelectedRows([2]);
        $this->assertSame([2], $table->selectedRows());

        $table->setSelectedRows([]);
        $this->assertSame([], $table->selectedRows());
    }
}
