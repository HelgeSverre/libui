<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Image;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the {@see Image} helper: construction, RGBA validation, and the
 * optional GD-backed PNG decoder.
 */
#[Group('smoke')]
final class ImageTest extends LibuiTestCase
{
    public function testConstructCreatesHandle(): void
    {
        $image = new Image(16.0, 16.0);
        $this->assertNotNull($image->handle());
        $image->free();
    }

    public function testFreeIsIdempotent(): void
    {
        $image = new Image(8.0, 8.0);
        $image->free();
        $image->free(); // must not throw or double-free
        $this->assertNull($image->handle());
    }

    public function testFromRgbaAcceptsExactBuffer(): void
    {
        $width = 4;
        $height = 4;
        $pixels = str_repeat("\xFF\x00\x00\xFF", $width * $height); // solid red

        $image = Image::fromRgba($pixels, $width, $height);
        $this->assertNotNull($image->handle());
        $image->free();
    }

    public function testFromRgbaRejectsTooShortBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Image::fromRgba(str_repeat("\xFF", 10), 4, 4); // need 64 bytes
    }

    public function testFromRgbaRejectsTooLongBuffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Image::fromRgba(str_repeat("\xFF", 128), 4, 4); // need 64 bytes
    }

    public function testFromRgbaRejectsNonPositiveDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Image::fromRgba('', 0, 4);
    }

    public function testAppendRejectsFreedImage(): void
    {
        $image = new Image(4.0, 4.0);
        $image->free();

        $this->expectException(\RuntimeException::class);
        $image->append(str_repeat("\x00", 64), 4, 4, 16);
    }

    public function testAppendRejectsTooSmallStride(): void
    {
        $image = new Image(4.0, 4.0);
        try {
            $this->expectException(\InvalidArgumentException::class);
            $image->append(str_repeat("\x00", 64), 4, 4, 4); // stride must be >= 16
        } finally {
            $image->free();
        }
    }

    public function testAppendRejectsTooSmallBuffer(): void
    {
        $image = new Image(4.0, 4.0);
        try {
            $this->expectException(\InvalidArgumentException::class);
            $image->append(str_repeat("\x00", 16), 4, 4, 16); // need 64 bytes
        } finally {
            $image->free();
        }
    }

    public function testFromPngDecodesOpaquePixels(): void
    {
        if (! \extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Build a tiny 2x2 PNG: a known set of opaque colours.
        $gd = imagecreatetruecolor(2, 2);
        imagesavealpha($gd, true);
        imagesetpixel($gd, 0, 0, (int) imagecolorallocate($gd, 255, 0, 0));
        imagesetpixel($gd, 1, 0, (int) imagecolorallocate($gd, 0, 255, 0));
        imagesetpixel($gd, 0, 1, (int) imagecolorallocate($gd, 0, 0, 255));
        imagesetpixel($gd, 1, 1, (int) imagecolorallocate($gd, 255, 255, 255));

        $path = tempnam(sys_get_temp_dir(), 'libui_img_') . '.png';
        imagepng($gd, $path);

        try {
            $image = Image::fromPng($path);
            $this->assertNotNull($image->handle());
            $image->free();
        } finally {
            @unlink($path);
        }
    }

    public function testFromPngRejectsMissingFile(): void
    {
        if (! \extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $this->expectException(\RuntimeException::class);
        Image::fromPng('/nonexistent/path/to/file.png');
    }
}
