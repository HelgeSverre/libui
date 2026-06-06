<?php

declare(strict_types=1);

/**
 * Tiny object wrapper around libui-ng, loaded through PHP's FFI extension.
 *
 * This is the modern, working analogue of the abandoned PHP 7 `ext-ui`
 * (https://www.php.net/manual/en/book.ui.php). Instead of a compiled PHP
 * extension, we load the libui-ng shared library at runtime and call its C
 * API directly with FFI — so it runs on stock PHP 8.5.
 */
final class Ui
{
    public \FFI $ffi;

    /** Keep PHP closures used as C callbacks alive for the program's lifetime. */
    private array $callbacks = [];

    public function __construct(?string $headerPath = null, ?string $libPath = null)
    {
        if (!extension_loaded('FFI')) {
            throw new RuntimeException('The FFI extension is required (enable it in php.ini).');
        }

        $headerPath ??= __DIR__ . '/libui.h';
        $libPath ??= dirname(__DIR__) . '/lib/darwin/libui.dylib';

        if (!is_file($libPath)) {
            throw new RuntimeException("libui shared library not found at: $libPath");
        }

        $this->ffi = \FFI::cdef(file_get_contents($headerPath), $libPath);
    }

    /** Initialise libui. Throws with the libui error message on failure. */
    public function init(): void
    {
        $opts = $this->ffi->new('uiInitOptions');
        $opts->Size = \FFI::sizeof($opts);
        $err = $this->ffi->uiInit(\FFI::addr($opts));
        if ($err !== null) {
            $message = \FFI::string($err);
            $this->ffi->uiFreeText($err);
            throw new RuntimeException("uiInit failed: $message");
        }
    }

    /** Cast any libui object pointer to the generic `uiControl *`. */
    public function control(\FFI\CData $obj): \FFI\CData
    {
        return $this->ffi->cast('uiControl *', $obj);
    }

    /** Read the text out of a uiEntry (and free the C string libui hands back). */
    public function entryText(\FFI\CData $entry): string
    {
        $ptr = $this->ffi->uiEntryText($entry);
        $value = \FFI::string($ptr);
        $this->ffi->uiFreeText($ptr);
        return $value;
    }

    /**
     * Register a PHP closure as a C callback and keep a reference so the
     * garbage collector doesn't free it while libui still holds the pointer.
     */
    public function keepCallback(callable $cb): callable
    {
        $this->callbacks[] = $cb;
        return $cb;
    }

    /** Run the GUI event loop. Blocks until the app quits. */
    public function main(): void
    {
        $this->ffi->uiMain();
    }

    public function quit(): void
    {
        $this->ffi->uiQuit();
    }

    /** Forward unknown calls (uiNewWindow, uiBoxAppend, ...) straight to libui. */
    public function __call(string $name, array $args): mixed
    {
        return $this->ffi->$name(...$args);
    }
}
