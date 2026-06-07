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

    /**
     * Creates a new empty image with the specified dimensions.
     *
     * @param float $width The width of the image in pixels
     * @param float $height The height of the image in pixels
     */
    public function __construct(float $width, float $height)
    {
        $this->handle = Ffi::get()->uiNewImage($width, $height);
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
     */
    public function free(): void
    {
        if (isset($this->handle) && ! \FFI::isNull($this->handle)) {
            Ffi::get()->uiFreeImage($this->handle);
            $this->handle = null;
        }
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
            imagedestroy($gdImage);
            $gdImage = $trueColor;
        }

        $pixelData = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($gdImage, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $a = 0xFF - (($rgba >> 24) & 0xFF); // GD uses 0-127 for alpha (0=opaque, 127=transparent)

                $pixelData .= \chr($r) . \chr($g) . \chr($b) . \chr($a);
            }
        }

        imagedestroy($gdImage);

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
        $image = new static((float) $width, (float) $height);
        $byteStride = 4 * $width;
        $image->append($rgbaData, $width, $height, $byteStride);

        return $image;
    }
}
