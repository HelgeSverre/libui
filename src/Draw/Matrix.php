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

    /** Scale by $x and $y around the origin (0,0). */
    public function scale(float $x, float $y): self
    {
        Ffi::get()->uiDrawMatrixScale($this->addr(), 0.0, 0.0, $x, $y);
        return $this;
    }

    /** Scale by $x and $y around point ($xCenter, $yCenter). */
    public function scaleAround(float $xCenter, float $yCenter, float $x, float $y): self
    {
        Ffi::get()->uiDrawMatrixScale($this->addr(), $xCenter, $yCenter, $x, $y);
        return $this;
    }

    /** Rotate by $amount radians around the origin (0,0). */
    public function rotate(float $amount): self
    {
        Ffi::get()->uiDrawMatrixRotate($this->addr(), 0.0, 0.0, $amount);
        return $this;
    }

    /** Rotate by $amount radians around the point ($x, $y). */
    public function rotateAround(float $x, float $y, float $amount): self
    {
        Ffi::get()->uiDrawMatrixRotate($this->addr(), $x, $y, $amount);
        return $this;
    }

    /** Skew by $xamount and $yamount around the origin (0,0). */
    public function skew(float $xamount, float $yamount): self
    {
        Ffi::get()->uiDrawMatrixSkew($this->addr(), 0.0, 0.0, $xamount, $yamount);
        return $this;
    }

    /** Skew by $xamount and $yamount around point ($x, $y). */
    public function skewAround(float $x, float $y, float $xamount, float $yamount): self
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

    /** Invert this matrix in place. Returns $this for chaining, or throws if not invertible. */
    public function invert(): self
    {
        $result = Ffi::get()->uiDrawMatrixInvert($this->addr());
        if ($result === 0) {
            throw new \RuntimeException('Matrix is not invertible');
        }
        return $this;
    }

    /** Reset this matrix to identity. */
    public function reset(): self
    {
        return $this->setIdentity();
    }

    public function toCData(): \FFI\CData
    {
        return $this->addr();
    }

    public function addr(): \FFI\CData
    {
        return \FFI::addr($this->matrix);
    }
}
