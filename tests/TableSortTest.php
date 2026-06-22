<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\SortIndicator;
use Libui\Table;
use Libui\TableModelDelegate;

/**
 * FFI-backed coverage for the sortable-column header API
 * (onHeaderClicked / setSortIndicator / sortIndicator).
 */
final class TableSortTest extends LibuiTestCase
{
    private function table(): Table
    {
        $delegate = new class extends TableModelDelegate {
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

        return Table::fromDelegate($delegate)->appendTextColumn('N', 0);
    }

    public function testOnHeaderClickedReturnsSameInstance(): void
    {
        $table = $this->table();
        $this->assertSame($table, $table->onHeaderClicked(static function (Table $t, int $column): void {}));
    }

    public function testSetSortIndicatorReturnsSameInstance(): void
    {
        $table = $this->table();
        $this->assertSame($table, $table->setSortIndicator(0, SortIndicator::Ascending));
    }

    public function testSortIndicatorRoundTrips(): void
    {
        $table = $this->table();
        $table->setSortIndicator(0, SortIndicator::Descending);
        $indicator = $table->sortIndicator(0);
        $this->assertInstanceOf(SortIndicator::class, $indicator);
        $this->assertSame(SortIndicator::Descending, $indicator);
    }
}
