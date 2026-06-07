<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;

/**
 * A string with per-range styling, wrapping uiAttributedString*.
 *
 * Ranges are byte offsets into the UTF-8 string. Build the text up with
 * append()/appendUnattributed(), then hand the whole thing to a TextLayout for
 * drawing. The string owns any attributes applied to it.
 */
final class AttributedString
{
    private \FFI\CData $string;

    public function __construct(string $initial = '')
    {
        $this->string = Ffi::get()->uiNewAttributedString($initial);
    }

    public function handle(): \FFI\CData
    {
        return $this->string;
    }

    /** Current length in bytes (matches strlen of the underlying UTF-8). */
    public function len(): int
    {
        return Ffi::get()->uiAttributedStringLen($this->string);
    }

    /** Alias for len(). */
    public function length(): int
    {
        return $this->len();
    }

    public function insert(string $text, int $at): self
    {
        Ffi::get()->uiAttributedStringInsertAtUnattributed($this->string, $text, $at);
        return $this;
    }

    public function delete_(int $start, int $end): self
    {
        Ffi::get()->uiAttributedStringDelete($this->string, $start, $end);
        return $this;
    }

    public function appendUnattributed(string $text): self
    {
        Ffi::get()->uiAttributedStringAppendUnattributed($this->string, $text);
        return $this;
    }

    public function setAttribute(Attribute $attribute, ?int $start = null, ?int $end = null): self
    {
        // Use the attribute's stored range if not provided
        if ($start === null) {
            $start = $attribute->getStart();
        }
        if ($end === null) {
            $end = $attribute->getEnd();
        }
        Ffi::get()->uiAttributedStringSetAttribute($this->string, $attribute->handle(), $start, $end);
        return $this;
    }

    /**
     * Append $text and apply each $attrs over exactly that new span.
     *
     * The byte range is computed from the length before and after the append
     * (UTF-8 byte offsets), so multi-byte text is handled correctly.
     */
    public function append(string $text, Attribute ...$attrs): self
    {
        $start = $this->len();
        $this->appendUnattributed($text);
        $end = $this->len();

        foreach ($attrs as $attribute) {
            $this->setAttribute($attribute, $start, $end);
        }

        return $this;
    }

    public function free(): void
    {
        Ffi::get()->uiFreeAttributedString($this->string);
    }
}
