<?php

declare(strict_types=1);

namespace Libui\Text;

use Libui\Ffi;

/**
 * A bag of OpenType feature tags, wrapping uiOpenTypeFeatures*.
 *
 * Each entry maps a four-character feature tag (e.g. "liga", "smcp") to a
 * uint32 value. Hand the bag to {@see Attribute} via AttributeType::Features;
 * libui clones the features into the attribute, so this wrapper keeps ownership
 * of its own uiOpenTypeFeatures and frees it on destruction.
 */
final class OpenTypeFeatures
{
    private \FFI\CData $otf;

    private bool $freed = false;

    public function __construct()
    {
        $this->otf = Ffi::get()->uiNewOpenTypeFeatures();
    }

    /**
     * Add (or overwrite) a feature. $tag must be exactly four characters.
     */
    public function add(string $tag, int $value): static
    {
        if (\strlen($tag) !== 4) {
            throw new \InvalidArgumentException("OpenType feature tag must be exactly 4 characters, got: \"{$tag}\"");
        }

        // PHP's FFI binds a C `char` parameter to a one-character PHP string,
        // not an int — pass the raw bytes. The generated stub types these
        // params as int, hence the ignore.
        // @phpstan-ignore-next-line
        Ffi::get()->uiOpenTypeFeaturesAdd($this->otf, $tag[0], $tag[1], $tag[2], $tag[3], $value);

        return $this;
    }

    /**
     * Look up a feature's value, or null if the tag is not present.
     * $tag must be exactly four characters.
     */
    public function get(string $tag): ?int
    {
        if (\strlen($tag) !== 4) {
            throw new \InvalidArgumentException("OpenType feature tag must be exactly 4 characters, got: \"{$tag}\"");
        }

        $ffi = Ffi::get();
        $out = $ffi->new('uint32_t');
        // char params bind to one-character strings (see add()); the stub types them as int.
        // @phpstan-ignore-next-line
        $present = $ffi->uiOpenTypeFeaturesGet($this->otf, $tag[0], $tag[1], $tag[2], $tag[3], \FFI::addr($out));

        // @phpstan-ignore-next-line  scalar FFI\CData value access (uint32_t out-param)
        return $present !== 0 ? $out->cdata : null;
    }

    public function handle(): \FFI\CData
    {
        return $this->otf;
    }

    /**
     * Free the native features. Idempotent, and runs automatically on destruction.
     */
    public function free(): void
    {
        if ($this->freed) {
            return;
        }
        Ffi::get()->uiFreeOpenTypeFeatures($this->otf);
        $this->freed = true;
    }

    public function __destruct()
    {
        $this->free();
    }
}
