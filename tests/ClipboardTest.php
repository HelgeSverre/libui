<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Utils\Clipboard;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the clipboard helper round-trips text through the OS.
 *
 * Skipped off macOS: CI Linux/Windows runners don't ship a clipboard tool
 * (xclip/xsel/wl-copy), so there's nothing to exercise there. pbcopy/pbpaste
 * are always present on macOS.
 */
final class ClipboardTest extends TestCase
{
    public function testCopyPasteRoundTrip(): void
    {
        if (\PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('No guaranteed clipboard tool on CI Linux/Windows; verified on macOS.');
        }

        // Preserve and restore whatever the developer had copied.
        $saved = Clipboard::paste();
        try {
            $text = 'oklch(0.781 0.121 220.4) · ' . bin2hex(random_bytes(4));
            $this->assertTrue(Clipboard::copy($text), 'copy() should succeed via pbcopy');
            $this->assertSame($text, Clipboard::paste(), 'paste() should return exactly what was copied');
        } finally {
            if ($saved !== null) {
                Clipboard::copy($saved);
            }
        }
    }
}
