<?php

declare(strict_types=1);

namespace Libui;

/**
 * Image widget and helper for working with uiImage.
 *
 * This class wraps libui-ng's uiImage type, which represents a bitmap that can be
 * displayed in a Table's image column or drawn in an Area.
 */
final class Image
{
    private ?\FFI\CData $handle;

    /** Whether {@see free()} has already released the image (guards double-free). */
    private bool $freed = false;

    /**
     * Creates a new empty image with the specified dimensions.
     *
     * The image registers itself with the {@see Lifecycle} registry so a
     * forgotten free() does not leak a uiImage into uiUninit() — libui's leak
     * checker aborts the process on a live uiImage at shutdown.
     *
     * @param float $width The width of the image in pixels
     * @param float $height The height of the image in pixels
     */
    public function __construct(float $width, float $height)
    {
        $this->handle = Ffi::get()->uiNewImage($width, $height);
        Lifecycle::registerImage($this);
    }

    /**
     * Returns the native uiImage handle.
     */
    public function handle(): ?\FFI\CData
    {
        return $this->handle;
    }

    /**
     * Frees the image and releases its resources.
     *
     * Idempotent: a second call is a no-op (freeing a uiImage twice would abort
     * libui), and the image de-registers from the {@see Lifecycle} registry so
     * uninit()'s sweep skips it. The registry calls this for any image still
     * live at uninit().
     */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        $this->freed = true;
        if (isset($this->handle) && ! \FFI::isNull($this->handle)) {
            Ffi::get()->uiFreeImage($this->handle);
            $this->handle = null;
        }
        Lifecycle::unregisterImage($this);
    }

    /**
     * Appends RGBA pixel data to the image.
     *
     * The pixels are expected as a flat array of bytes in RGBA order (4 bytes per pixel).
     *
     * @param string $pixels The raw pixel data as a string of bytes
     * @param int $pixelWidth The width of the pixel data in pixels
     * @param int $pixelHeight The height of the pixel data in pixels
     * @param int $byteStride The byte stride (distance between row starts, typically 4 * pixelWidth)
     */
    public function append(string $pixels, int $pixelWidth, int $pixelHeight, int $byteStride): void
    {
        if ($this->handle === null) {
            throw new \RuntimeException('Cannot append to a freed image');
        }
        if ($pixelWidth <= 0 || $pixelHeight <= 0) {
            throw new \InvalidArgumentException("Pixel dimensions must be positive, got {$pixelWidth}x{$pixelHeight}");
        }
        if ($byteStride < (4 * $pixelWidth)) {
            throw new \InvalidArgumentException("byteStride ({$byteStride}) must be at least 4 * pixelWidth (" . (4 * $pixelWidth) . ')');
        }
        $expected = $byteStride * $pixelHeight;
        if (\strlen($pixels) < $expected) {
            throw new \InvalidArgumentException("Pixel buffer too small: need {$expected} bytes, got " . \strlen($pixels));
        }

        $ffi = Ffi::get();
        // Create a temporary C buffer from the PHP string
        $buf = $ffi->new('unsigned char[' . \strlen($pixels) . ']');
        \FFI::memcpy($buf, $pixels, \strlen($pixels));

        $ffi->uiImageAppend($this->handle, $buf, $pixelWidth, $pixelHeight, $byteStride);
    }

    /**
     * Creates an Image from a PNG file.
     *
     * This method uses PHP's GD extension to decode the PNG and convert it to RGBA.
     * If GD is not available, it throws a \RuntimeException.
     *
     * @param string $path Path to the PNG file
     * @return static A new Image instance with the decoded PNG data
     * @throws \RuntimeException If GD extension is not available or PNG cannot be decoded
     */
    public static function fromPng(string $path): static
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException(
                'GD extension is required for PNG decoding. Install with: pecl install gd or enable in php.ini',
            );
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new \RuntimeException("Unable to read PNG file: {$path}");
        }

        [$width, $height, $type] = $imageInfo;

        $gdImage = @imagecreatefrompng($path);
        if ($gdImage === false) {
            throw new \RuntimeException("Unable to decode PNG file: {$path}");
        }

        // Convert to truecolor if needed
        if (! imageistruecolor($gdImage)) {
            $trueColor = imagecreatetruecolor($width, $height);
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            imagecopy($trueColor, $gdImage, 0, 0, 0, 0, $width, $height);
            $gdImage = $trueColor;
        }

        $pixelData = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($gdImage, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                // GD stores alpha as 0-127 (0 = opaque, 127 = transparent); scale
                // to libui's straight 0-255 RGBA (255 = opaque).
                $gdAlpha = ($rgba >> 24) & 0x7F;
                $a = 255 - (int) round(($gdAlpha * 255) / 127);

                $pixelData .= \chr($r) . \chr($g) . \chr($b) . \chr($a);
            }
        }

        $image = new static((float) $width, (float) $height);
        $byteStride = 4 * $width; // 4 bytes per pixel (RGBA)
        $image->append($pixelData, $width, $height, $byteStride);

        return $image;
    }

    /**
     * Creates an Image from raw RGBA bytes.
     *
     * This is a GD-free path for when you already have RGBA pixel data.
     *
     * @param string $rgbaData Raw RGBA pixel data (4 bytes per pixel)
     * @param int $width Image width in pixels
     * @param int $height Image height in pixels
     * @return static A new Image instance with the pixel data
     */
    public static function fromRgba(string $rgbaData, int $width, int $height): static
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException("Image dimensions must be positive, got {$width}x{$height}");
        }
        $expected = $width * $height * 4;
        if (\strlen($rgbaData) !== $expected) {
            throw new \InvalidArgumentException("RGBA data must be exactly {$expected} bytes ({$width}x{$height}x4), got " . \strlen($rgbaData));
        }

        $image = new static((float) $width, (float) $height);
        $byteStride = 4 * $width;
        $image->append($rgbaData, $width, $height, $byteStride);

        return $image;
    }
}
