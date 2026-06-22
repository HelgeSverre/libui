<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Draw\Params\AreaKeyEvent;
use Libui\Ffi;

/**
 * Regression test for the `char Key` cast: PHP FFI binds a C `char` to a
 * one-character string, so `(int) "a"` is 0. fromCData() must use ord().
 */
final class AreaKeyEventTest extends LibuiTestCase
{
    public function testPrintableKeyReadsItsAsciiCode(): void
    {
        $e = Ffi::get()->new('uiAreaKeyEvent');
        $e->Key = 'a';
        $e->ExtKey = 0;
        $e->Modifier = 0;
        $e->Modifiers = 0;
        $e->Up = 0;

        $event = AreaKeyEvent::fromCData($e);

        $this->assertSame(97, $event->key, "'a' should decode to ASCII 97");
        $this->assertSame('a', $event->char(), 'char() should round-trip back to the character');
    }

    public function testNulKeyDecodesToZero(): void
    {
        $e = Ffi::get()->new('uiAreaKeyEvent');
        $e->Key = "\0"; // extended/non-printable key: C char is NUL
        $e->ExtKey = 0;
        $e->Modifier = 0;
        $e->Modifiers = 0;
        $e->Up = 0;

        $event = AreaKeyEvent::fromCData($e);

        $this->assertSame(0, $event->key, 'a NUL key should decode to 0');
        $this->assertSame('', $event->char(), 'char() of an extended key should be empty');
    }
}
