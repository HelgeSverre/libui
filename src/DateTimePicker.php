<?php

declare(strict_types=1);

namespace Libui;

/**
 * DateTimePicker widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\DateTimePicker.
 *
 * The raw getter/setter deal in a C `struct tm` (tm_year offset by 1900, tm_mon
 * 0-based); getValue()/setValue() bridge that to PHP's DateTimeImmutable.
 */
class DateTimePicker extends Generated\DateTimePicker
{
    /** The picked moment as a DateTimeImmutable (local wall-clock fields). */
    public function getValue(): \DateTimeImmutable
    {
        $ffi = Ffi::get();
        $tm = $ffi->new('struct tm');
        $this->time(\FFI::addr($tm));

        return new \DateTimeImmutable()
            ->setDate($tm->tm_year + 1900, $tm->tm_mon + 1, $tm->tm_mday)
            ->setTime($tm->tm_hour, $tm->tm_min, $tm->tm_sec);
    }

    /** Set the picker to $when (its broken-down local-time fields). */
    public function setValue(\DateTimeInterface $when): static
    {
        $ffi = Ffi::get();
        $tm = $ffi->new('struct tm');
        $tm->tm_sec = (int) $when->format('s');
        $tm->tm_min = (int) $when->format('i');
        $tm->tm_hour = (int) $when->format('G');
        $tm->tm_mday = (int) $when->format('j');
        $tm->tm_mon = (int) $when->format('n') - 1;
        $tm->tm_year = (int) $when->format('Y') - 1900;
        $tm->tm_isdst = -1; // let the platform resolve DST (required on Windows)

        $this->setTime(\FFI::addr($tm));
        return $this;
    }
}
