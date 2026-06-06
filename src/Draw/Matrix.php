<?php

declare(strict_types=1);

namespace Libui\Draw;

use Libui\Ffi;

/**
 * An affine transform, wrapping the uiDrawMatrix struct (M11..M32).
 *
 * libui mutates the matrix in place through its uiDrawMatrix* helpers, so the
 * struct is built once and kept on this object; every transform method calls
 * the matching native function against its address and returns $this for
 * chaining. Hand the finished matrix to DrawContext::transform().
 */
final class Matrix
{
    private \FFI\CData $matrix;

    public function __construct()
    {
        $this->matrix = Ffi::get()->new('uiDrawMatrix');
        $this->setIdentity();
    }

    public function setIdentity(): self
    {
        Ffi::get()->uiDrawMatrixSetIdentity($this->addr());
        return $this;
    }

    public function translate(float $x, float $y): self
    {
        Ffi::get()->uiDrawMatrixTranslate($this->addr(), $x, $y);
        return $this;
    }

    public function scale(float $xCenter, float $yCenter, float $x, float $y): self
    {
        Ffi::get()->uiDrawMatrixScale($this->addr(), $xCenter, $yCenter, $x, $y);
        return $this;
    }

    /** Rotate by $amount radians around the point ($x, $y). */
    public function rotate(float $x, float $y, float $amount): self
    {
        Ffi::get()->uiDrawMatrixRotate($this->addr(), $x, $y, $amount);
        return $this;
    }

    public function skew(float $x, float $y, float $xamount, float $yamount): self
    {
        Ffi::get()->uiDrawMatrixSkew($this->addr(), $x, $y, $xamount, $yamount);
        return $this;
    }

    /** Multiply this matrix by $src (this becomes this * src). */
    public function multiply(Matrix $src): self
    {
        Ffi::get()->uiDrawMatrixMultiply($this->addr(), $src->addr());
        return $this;
    }

    public function toCData(): \FFI\CData
    {
        return $this->matrix;
    }

    public function addr(): \FFI\CData
    {
        return \FFI::addr($this->matrix);
    }
}
