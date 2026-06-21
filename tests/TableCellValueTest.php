<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Color;
use Libui\Generated\Enum\TableValueType;
use Libui\Image;
use Libui\TableModel;
use Libui\TableModelDelegate;

/**
 * The CellValue marshaller in {@see TableModel} must mint the right uiTableValue
 * per column type: Int, String, Image, and Color. We drive the retained
 * 'CellValue' trampoline directly and assert libui reports the expected type
 * back via uiTableValueGetType.
 */
final class TableCellValueTest extends LibuiTestCase
{
    private function cellValueType(TableModel $model, int $row, int $column): int
    {
        $prop = new \ReflectionProperty(TableModel::class, 'callbacks');
        /** @var array<string,callable> $callbacks */
        $callbacks = $prop->getValue($model);
        $value = $callbacks['CellValue'](null, null, $row, $column);

        return \Libui\Ffi::get()->uiTableValueGetType($value);
    }

    private function model(TableModelDelegate $delegate): TableModel
    {
        return new TableModel($delegate);
    }

    public function testStringColumnYieldsStringValue(): void
    {
        $d = new class extends TableModelDelegate {
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
                return 'hi';
            }
        };
        $this->assertSame(TableValueType::String->value, $this->cellValueType($this->model($d), 0, 0));
    }

    public function testIntColumnYieldsIntValue(): void
    {
        $d = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function columnType(int $column): TableValueType
            {
                return TableValueType::Int;
            }

            public function cellValue(int $row, int $column): int
            {
                return 5;
            }
        };
        $this->assertSame(TableValueType::Int->value, $this->cellValueType($this->model($d), 0, 0));
    }

    public function testColorColumnYieldsColorValue(): void
    {
        $d = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function columnType(int $column): TableValueType
            {
                return TableValueType::Color;
            }

            public function cellValue(int $row, int $column): Color
            {
                return Color::rgb(0xFF_0000);
            }
        };
        $this->assertSame(TableValueType::Color->value, $this->cellValueType($this->model($d), 0, 0));
    }

    public function testImageColumnYieldsImageValue(): void
    {
        $image = new Image(4.0, 4.0);
        $d = new class($image) extends TableModelDelegate {
            public function __construct(
                private Image $image,
            ) {}

            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function columnType(int $column): TableValueType
            {
                return TableValueType::Image;
            }

            public function cellValue(int $row, int $column): Image
            {
                return $this->image;
            }
        };
        $this->assertSame(TableValueType::Image->value, $this->cellValueType($this->model($d), 0, 0));
    }

    public function testColorColumnWithNullYieldsNoValue(): void
    {
        $d = new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function numRows(): int
            {
                return 1;
            }

            public function columnType(int $column): TableValueType
            {
                return TableValueType::Color;
            }

            public function cellValue(int $row, int $column): null
            {
                return null;
            }
        };
        $prop = new \ReflectionProperty(TableModel::class, 'callbacks');
        /** @var array<string,callable> $callbacks */
        $callbacks = $prop->getValue($this->model($d));
        $this->assertNull($callbacks['CellValue'](null, null, 0, 0));
    }
}
