<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

/** A PHP view of uiAreaKeyEvent. */
final class AreaKeyEvent
{
    public function __construct(
        public readonly int $key, // ASCII code of the key, or 0 for an extended key
        public readonly int $extKey, // see Generated\Enum\ExtKey
        public readonly int $modifier, // a modifier pressed by itself (0 otherwise)
        public readonly int $modifiers, // bitmask of modifiers held
        public readonly bool $up, // true = key released, false = key pressed
    ) {}

    public static function fromCData(\FFI\CData $e): self
    {
        return new self(
            // C `char Key` binds to a one-char PHP string in FFI; (int) cast is always 0.
            $e->Key === '' ? 0 : \ord($e->Key),
            $e->ExtKey,
            $e->Modifier,
            $e->Modifiers,
            $e->Up !== 0,
        );
    }

    /** The pressed character, or '' for an extended (non-printable) key. */
    public function char(): string
    {
        return $this->key > 0 ? \chr($this->key) : '';
    }
}
