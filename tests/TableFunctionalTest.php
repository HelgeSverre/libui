<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Generated\Enum\SortIndicator;
use Libui\Generated\Enum\TableSelectionMode;
use Libui\Generated\Enum\TableValueType;
use Libui\Table;
use Libui\TableModel;
use Libui\TableModelDelegate;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for the Table widget and TableModel subsystem.
 * Tests beyond the leak detection that's already covered by TableModelTest.
 */
final class TableFunctionalTest extends TestCase
{
    // ========================================================================
    // TABLE MODEL DELEGATE TESTS
    // ========================================================================

    public function testTableModelDelegateConstructs(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $this->assertInstanceOf(TableModelDelegate::class, $delegate);
    }

    public function testTableModelDelegateNumColumns(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 3;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $this->assertSame(3, $delegate->numColumns());
    }

    public function testTableModelDelegateNumRows(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 5;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $this->assertSame(5, $delegate->numRows());
    }

    public function testTableModelDelegateCellValue(): void
    {
        $data = [['A1', 'B1'], ['A2', 'B2']];

        $delegate = new class($data) extends TableModelDelegate {
            public function __construct(
                private array $data,
            ) {}

            public function numColumns(): int
            {
                return 2;
            }

            public function numRows(): int
            {
                return count($this->data);
            }

            public function cellValue(int $row, int $column): string
            {
                return $this->data[$row][$column];
            }
        };

        $this->assertSame('A1', $delegate->cellValue(0, 0));
        $this->assertSame('B1', $delegate->cellValue(0, 1));
        $this->assertSame('A2', $delegate->cellValue(1, 0));
        $this->assertSame('B2', $delegate->cellValue(1, 1));
    }

    public function testTableModelDelegateSetCellValue(): void
    {
        $data = [['A1']];

        $delegate = new class($data) extends TableModelDelegate {
            public function __construct(
                private array &$data,
            ) {}

            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return count($this->data);
            }

            public function cellValue(int $row, int $column): string
            {
                return $this->data[$row][$column];
            }

            public function setCellValue(int $row, int $column, mixed $value): void
            {
                $this->data[$row][$column] = $value;
            }
        };

        $delegate->setCellValue(0, 0, 'New Value');
        $this->assertSame('New Value', $data[0][0]);
    }

    // ========================================================================
    // TABLE MODEL TESTS
    // ========================================================================

    public function testTableModelConstructs(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $model = TableModel::fromDelegate($delegate);
        $this->assertInstanceOf(TableModel::class, $model);
    }

    public function testTableModelHandle(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $model = TableModel::fromDelegate($delegate);
        $handle = $model->handle();

        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }

    public function testTableModelFree(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $model = TableModel::fromDelegate($delegate);
        $model->free();

        $this->assertTrue(true, 'TableModel::free() should complete without error');
    }

    // ========================================================================
    // TABLE WIDGET TESTS
    // Note: These require Ffi::init() which needs libui loaded
    // ========================================================================

    public function testTableConstructsWithoutLibui(): void
    {
        // We can test that the class exists and has the expected methods
        $this->assertTrue(class_exists(Table::class));
        $this->assertTrue(method_exists(Table::class, 'fromModel'));
        $this->assertTrue(method_exists(Table::class, 'fromDelegate'));
    }

    public function testTableColumnTypes(): void
    {
        // Verify the TableValueType enum has all expected values
        $this->assertSame(0, TableValueType::String->value);
        $this->assertSame(1, TableValueType::Image->value);
        $this->assertSame(2, TableValueType::Int->value);
        $this->assertSame(3, TableValueType::Color->value);
    }

    public function testTableSelectionModes(): void
    {
        $this->assertSame(0, TableSelectionMode::None->value);
        $this->assertSame(1, TableSelectionMode::Single->value);
        $this->assertSame(2, TableSelectionMode::Multiple->value);
    }

    public function testSortIndicators(): void
    {
        $this->assertSame(0, SortIndicator::None->value);
        $this->assertSame(1, SortIndicator::Ascending->value);
        $this->assertSame(2, SortIndicator::Descending->value);
    }

    // ========================================================================
    // TABLE MODEL DELEGATE WITH ALL METHODS
    // ========================================================================

    public function testTableModelDelegateWithAllOptionalMethods(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 2;
            }

            public function numRows(): int
            {
                return 3;
            }

            public function cellValue(int $row, int $column): string
            {
                return "Row $row, Col $column";
            }

            public function setCellValue(int $row, int $column, mixed $value): void
            {
                // Optional: implement if editable
            }

            public function cellEditable(int $row, int $column): bool
            {
                // Optional: return true for editable cells
                return true;
            }

            public function cellValueChanged(int $row, int $column): void
            {
                // Optional: handle cell value changes
            }
        };

        $this->assertInstanceOf(TableModelDelegate::class, $delegate);
        $this->assertSame(2, $delegate->numColumns());
        $this->assertSame(3, $delegate->numRows());
        $this->assertSame('Row 0, Col 0', $delegate->cellValue(0, 0));
        $this->assertTrue($delegate->cellEditable(0, 0));
    }

    public function testTableModelDelegateDefaultImplementations(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }

            // The base class provides default implementations for optional methods
        };

        // These should not throw - they use the default implementations
        $this->assertNull($delegate->cellEditable(0, 0));
        $this->assertNull($delegate->cellValueChanged(0, 0));
        $this->assertNull($delegate->setCellValue(0, 0, 'new'));
    }

    // ========================================================================
    // TABLE WITH DELEGATE TESTS
    // ========================================================================

    public function testTableFromDelegate(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 2;
            }

            public function numRows(): int
            {
                return 10;
            }

            public function cellValue(int $row, int $column): string
            {
                return "R$row,C$column";
            }
        };

        // This tests the factory method exists and has the right signature
        // We can't actually create a Table without Ffi::init()
        $this->assertTrue(method_exists(Table::class, 'fromDelegate'));
    }

    public function testTableFromModel(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $model = TableModel::fromDelegate($delegate);

        // This tests the factory method exists
        $this->assertTrue(method_exists(Table::class, 'fromModel'));
    }

    // ========================================================================
    // MEMORY MANAGEMENT TESTS
    // ========================================================================

    public function testTableModelFreeIsIdempotent(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'test';
            }
        };

        $model = TableModel::fromDelegate($delegate);

        // Free multiple times - should be safe
        $model->free();
        $model->free();

        $this->assertTrue(true, 'TableModel::free() should be idempotent');
    }

    public function testTableModelDelegateCanHoldState(): void
    {
        $data = [
            ['Name' => 'Alice', 'Age' => '30'],
            ['Name' => 'Bob', 'Age' => '25'],
        ];

        $delegate = new class($data) extends TableModelDelegate {
            public function __construct(
                private array $data,
            ) {}

            public function numColumns(): int
            {
                return 2;
            }

            public function numRows(): int
            {
                return count($this->data);
            }

            public function cellValue(int $row, int $column): string
            {
                $keys = array_keys($this->data[$row]);
                return $this->data[$row][$keys[$column]];
            }

            public function columnName(int $column): string
            {
                $keys = array_keys($this->data[0]);
                return $keys[$column];
            }
        };

        $this->assertSame(2, $delegate->numColumns());
        $this->assertSame(2, $delegate->numRows());
        $this->assertSame('Alice', $delegate->cellValue(0, 0));
        $this->assertSame('30', $delegate->cellValue(0, 1));
        $this->assertSame('Name', $delegate->columnName(0));
        $this->assertSame('Age', $delegate->columnName(1));
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function testTableModelDelegateWithEmptyTable(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 0;
            }

            public function numRows(): int
            {
                return 0;
            }

            public function cellValue(int $row, int $column): string
            {
                return '';
            }
        };

        $this->assertSame(0, $delegate->numColumns());
        $this->assertSame(0, $delegate->numRows());
    }

    public function testTableModelDelegateWithSingleCell(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function cellValue(int $row, int $column): string
            {
                return 'Single Cell';
            }
        };

        $this->assertSame(1, $delegate->numColumns());
        $this->assertSame(1, $delegate->numRows());
        $this->assertSame('Single Cell', $delegate->cellValue(0, 0));
    }

    public function testTableModelDelegateWithLargeTable(): void
    {
        $delegate = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 100;
            }

            public function numRows(): int
            {
                return 1000;
            }

            public function cellValue(int $row, int $column): string
            {
                return "($row,$column)";
            }
        };

        $this->assertSame(100, $delegate->numColumns());
        $this->assertSame(1000, $delegate->numRows());
        $this->assertSame('(50,50)', $delegate->cellValue(50, 50));
        $this->assertSame('(999,99)', $delegate->cellValue(999, 99));
    }
}
