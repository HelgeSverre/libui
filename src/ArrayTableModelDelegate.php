<?php

declare(strict_types=1);

namespace Libui;

use Libui\Generated\Enum\TableValueType;

/**
 * A ready-made {@see TableModelDelegate} over an in-memory, row-major array.
 *
 * Hand it a list of rows (each row a positional list of cells) plus the column
 * headers, and optionally a per-column type map. It backs the zero-boilerplate
 * {@see Table::fromRows()} / {@see Table::fromAssoc()} factories, and is itself a
 * usable base for read-only grids without writing a delegate by hand.
 */
class ArrayTableModelDelegate extends TableModelDelegate
{
    /**
     * @param list<list<string|int|bool|Color|Image|null>> $rows    row-major cells
     * @param list<string>                                  $headers column titles (drives numColumns)
     * @param array<int,TableValueType>                     $types   per-column type override (default String)
     */
    public function __construct(
        protected array $rows,
        protected array $headers,
        protected array $types = [],
    ) {}

    /** @return list<string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function numColumns(): int
    {
        return \count($this->headers);
    }

    public function numRows(): int
    {
        return \count($this->rows);
    }

    public function columnType(int $column): TableValueType
    {
        return $this->types[$column] ?? TableValueType::String;
    }

    public function cellValue(int $row, int $column): string|int|bool|Color|Image|null
    {
        return $this->rows[$row][$column] ?? '';
    }
}
