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
    /** Native ownership cannot be duplicated by PHP object cloning. */
    private function __clone(): void {}

    private \FFI\CData $string;

    private bool $freed = true;

    /** @var \WeakMap<TextLayout,null>|null */
    private ?\WeakMap $layouts = null;

    public function __construct(string $initial = '')
    {
        Ffi::assertInitialized();
        self::assertText($initial);
        $this->string = Ffi::get()->uiNewAttributedString($initial);
        $this->freed = false;
        \Libui\Lifecycle::registerDrawingResource($this);
    }

    public function handle(): \FFI\CData
    {
        if ($this->freed) {
            throw new \LogicException('This native resource has been freed.');
        }
        return $this->string;
    }

    /** Keep explicit string teardown behind every layout that uses it. @internal */
    public function registerLayout(TextLayout $layout): void
    {
        $this->layouts ??= new \WeakMap();
        $this->layouts[$layout] = null;
    }

    private static function assertText(string $text): void
    {
        if (\str_contains($text, "\0") || \preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('Attributed text must be valid UTF-8 without NUL bytes.');
        }
    }

    private function assertOffset(int $offset): void
    {
        $length = $this->len();
        if ($offset < 0 || $offset > $length) {
            throw new \OutOfBoundsException('Text byte offset out of range: ' . $offset);
        }
        $text = Ffi::get()->uiAttributedStringString($this->handle());
        if ($offset < $length && (\ord($text[$offset]) & 0xC0) === 0x80) {
            throw new \InvalidArgumentException('Text offsets must be on UTF-8 code-point boundaries.');
        }
    }

    private function assertRange(int $start, int $end): void
    {
        $this->assertOffset($start);
        $this->assertOffset($end);
        if ($start > $end) {
            throw new \InvalidArgumentException('Text range start must not exceed its end.');
        }
    }

    /** Current length in bytes (matches strlen of the underlying UTF-8). */
    public function len(): int
    {
        return Ffi::get()->uiAttributedStringLen($this->handle());
    }

    /** Alias for len(). */
    public function length(): int
    {
        return $this->len();
    }

    public function insert(string $text, int $at): self
    {
        self::assertText($text);
        $this->assertOffset($at);
        Ffi::get()->uiAttributedStringInsertAtUnattributed($this->handle(), $text, $at);
        return $this;
    }

    public function delete_(int $start, int $end): self
    {
        $this->assertRange($start, $end);
        if ($start !== $end) {
            Ffi::get()->uiAttributedStringDelete($this->handle(), $start, $end);
        }
        return $this;
    }

    public function appendUnattributed(string $text): self
    {
        self::assertText($text);
        Ffi::get()->uiAttributedStringAppendUnattributed($this->handle(), $text);
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
        $this->assertRange($start, $end);
        if ($start !== $end) {
            Ffi::get()->uiAttributedStringSetAttribute($this->handle(), $attribute->transferHandle(), $start, $end);
        }
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
        foreach ($attrs as $attribute) {
            $attribute->handle();
        }
        $start = $this->len();
        $this->appendUnattributed($text);
        $end = $this->len();

        foreach ($attrs as $attribute) {
            $this->setAttribute($attribute, $start, $end);
        }

        return $this;
    }

    /**
     * Free the native string. Idempotent, and runs automatically on destruction.
     *
     * A TextLayout built from this string retains it (see TextLayout), so the
     * layout is always freed before the string it references — do not free a
     * string yourself while a live TextLayout still uses it.
     */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        foreach ($this->layouts ?? [] as $layout => $_) {
            if (! $layout->isFreed()) {
                throw new \LogicException('Free every text layout using this attributed string first.');
            }
        }
        Ffi::get()->uiFreeAttributedString($this->string);
        $this->freed = true;
    }

    public function __destruct()
    {
        $this->free();
    }
}
