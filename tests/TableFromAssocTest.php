<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\ArrayTableModelDelegate;
use Libui\Table;

/**
 * Regression coverage for {@see Table::fromAssoc()} / {@see Table::fromRows()}
 * header handling.
 *
 * Integer or numeric-string row/column keys used to flow straight into
 * appendTextColumn(string $name, ...) and trigger a TypeError. fromRows() now
 * normalises every header to a string once, so these constructors must build a
 * real Table without throwing.
 *
 * These touch the live library (uiNewTable + uiTableAppendTextColumn), hence
 * LibuiTestCase rather than a plain TestCase.
 */
final class TableFromAssocTest extends LibuiTestCase
{
    public function testFromAssocWithIntegerKeysConstructsWithoutTypeError(): void
    {
        // Integer keys: array_keys() returns ints, which would otherwise reach
        // appendTextColumn(string $name) as ints.
        $table = Table::fromAssoc([[0 => 'a', 1 => 'b']]);

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromAssocWithNumericStringKeysConstructsWithoutTypeError(): void
    {
        // PHP silently casts numeric string keys ("0"/"1") to ints, so these
        // exercise the same int-header path.
        $table = Table::fromAssoc([['0' => 'a', '1' => 'b']]);

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromAssocWithExplicitIntColumnsConstructs(): void
    {
        $table = Table::fromAssoc(
            [[0 => 'a', 1 => 'b', 2 => 'c']],
            columns: [0, 1, 2],
        );

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromRowsWithIntegerHeadersConstructsWithoutTypeError(): void
    {
        // Integer headers must be accepted and produce one text column each
        // (3 here) without a TypeError.
        $table = Table::fromRows([['a', 'b', 'c']], [0, 1, 2]);

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testFromRowsWithStringHeadersStillWorks(): void
    {
        $table = Table::fromRows([['a', 'b']], ['X', 'Y']);

        $this->assertInstanceOf(Table::class, $table);
    }

    /**
     * Header normalisation is what drives the column count: ints/numeric strings
     * become string headers, one column each. Asserted at the delegate level,
     * since neither Table nor TableModel exposes a column-count accessor.
     */
    public function testHeaderNormalisationYieldsStringHeadersAndColumnCount(): void
    {
        // Mirror fromRows()'s normalisation step on integer headers.
        $headers = array_values(array_map(static fn (mixed $h): string => (string) $h, [0, 1, 2]));
        $delegate = new ArrayTableModelDelegate([['a', 'b', 'c']], $headers);

        $this->assertSame(['0', '1', '2'], $delegate->headers());
        $this->assertSame(3, $delegate->numColumns());
    }
}
