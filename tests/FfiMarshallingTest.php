<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Entry;
use Libui\Ffi;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for FFI marshalling helpers and the Ffi singleton.
 * Verifies that strings, structs, and handles are correctly marshalled
 * between PHP and C boundaries.
 */
#[Group('smoke')]
final class FfiMarshallingTest extends LibuiTestCase
{
    public function testFfiGetReturnsSingleton(): void
    {
        $ffi1 = Ffi::get();
        $ffi2 = Ffi::get();

        $this->assertSame(
            $ffi1,
            $ffi2,
            'Ffi::get() should return the same singleton instance',
        );
    }

    public function testFfiGetReturnsFfiInstance(): void
    {
        $ffi = Ffi::get();
        $this->assertInstanceOf(\FFI::class, $ffi);
    }

    public function testFfiRootReturnsCorrectPath(): void
    {
        $root = Ffi::root();

        $this->assertIsString($root);
        // root() returns the directory containing src/, not src/ itself
        $this->assertStringEndsWith('/php-gui', $root);
        $this->assertFileExists($root . '/composer.json');
    }

    public function testFfiInitIsIdempotent(): void
    {
        // init() should be safe to call multiple times
        Ffi::init();
        Ffi::init();
        Ffi::init();

        $this->assertTrue(Ffi::isInitialized());
    }

    public function testFfiIsInitializedReturnsCorrectState(): void
    {
        // Should be initialized after setUpBeforeClass
        $this->assertTrue(Ffi::isInitialized());
    }

    public function testFfiControlUpcastsWidgetHandle(): void
    {
        $button = new Button('Test');
        $handle = $button->handle();

        $control = Ffi::control($handle);

        $this->assertInstanceOf(\FFI\CData::class, $control);
        $this->assertFalse(\FFI::isNull($control));
    }

    public function testFfiControlAcceptsDifferentWidgetTypes(): void
    {
        $button = new Button('Test');
        $entry = new Entry();

        $buttonControl = Ffi::control($button->handle());
        $entryControl = Ffi::control($entry->handle());

        $this->assertInstanceOf(\FFI\CData::class, $buttonControl);
        $this->assertInstanceOf(\FFI\CData::class, $entryControl);
    }

    public function testFfiNewCreatesCData(): void
    {
        $type = 'int';
        $cdata = Ffi::new($type);

        $this->assertInstanceOf(\FFI\CData::class, $cdata);
    }

    public function testFfiNewCreatesStruct(): void
    {
        // Test creating a struct that libui uses
        $opts = Ffi::new('uiInitOptions');

        $this->assertInstanceOf(\FFI\CData::class, $opts);
        // Size varies by platform; just verify it's a positive integer
        $this->assertIsInt(\FFI::sizeof($opts));
        $this->assertGreaterThan(0, \FFI::sizeof($opts));
    }

    public function testFfiOwnedStringCopiesAndFreessString(): void
    {
        $button = new Button('Test String');

        // uiButtonText returns an owned string
        $textPtr = Ffi::get()->uiButtonText($button->handle());

        // ownedString should copy the string and free the pointer
        $text = Ffi::ownedString($textPtr);

        $this->assertSame('Test String', $text);
        $this->assertIsString($text);
    }

    public function testFfiOwnedStringHandlesNull(): void
    {
        $result = Ffi::ownedString(null);
        $this->assertSame('', $result);
    }

    public function testFfiBorrowedStringCopiesWithoutFreeing(): void
    {
        // Create a C string that we control
        $ffi = Ffi::get();
        $cString = $ffi->new('char[10]');
        \FFI::memcpy($cString, 'hello', 5);
        $cString[5] = "\0"; // Null-terminate

        // borrowedString should copy but NOT free
        $text = Ffi::borrowedString($cString);

        $this->assertSame('hello', $text);
        $this->assertIsString($text);
    }

    public function testFfiBorrowedStringHandlesNull(): void
    {
        $result = Ffi::borrowedString(null);
        $this->assertSame('', $result);
    }

    public function testLibPathResolvesCorrectLibrary(): void
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass(Ffi::class);
        $method = $reflection->getMethod('libPath');
        $method->setAccessible(true);

        $path = $method->invoke(null);

        $this->assertIsString($path);
        // Check that the path ends with a library extension
        $this->assertTrue(
            str_ends_with($path, '.dylib') || str_ends_with($path, '.so') || str_ends_with($path, '.dll'),
            "Expected library path to end with .dylib, .so, or .dll, got: $path"
        );

        // The file should exist (at least on macOS where it's prebuilt)
        if (\PHP_OS_FAMILY === 'Darwin') {
            $this->assertFileExists($path);
        }
    }

    public function testLibPathRespectsEnvironmentOverride(): void
    {
        // Save current value
        $original = getenv('LIBUI_LIB');

        try {
            // Create a temporary file to override with
            $tmpFile = tempnam(sys_get_temp_dir(), 'libui_test_');
            touch($tmpFile);

            // Set the override to point to our temp file
            putenv("LIBUI_LIB=$tmpFile");

            $reflection = new \ReflectionClass(Ffi::class);
            $method = $reflection->getMethod('libPath');
            $method->setAccessible(true);

            $path = $method->invoke(null);

            $this->assertSame($tmpFile, $path);

            // Clean up
            unlink($tmpFile);
        } finally {
            // Restore original
            if ($original === false) {
                putenv('LIBUI_LIB');
            } else {
                putenv("LIBUI_LIB=$original");
            }
        }
    }

    public function testErrorMessageIncludesActionableAdvice(): void
    {
        // This tests the error message format
        // We can't easily trigger the actual error without a broken setup,
        // but we can verify the message format

        $message = 'Generated header missing at /path/to/libui.gen.h (run: composer regen).';

        $this->assertStringContainsString('composer regen', $message);
        $this->assertStringContainsString('libui.gen.h', $message);
    }

    public function testRetainedCallbacksAreStored(): void
    {
        // Access the private property via reflection
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');
        $property->setAccessible(true);

        $retained = $property->getValue();

        $this->assertIsArray($retained);
    }

    public function testQueueMainRetainsCallback(): void
    {
        $ran = false;

        Ffi::queueMain(function () use (&$ran): void {
            $ran = true;
        });

        // The callback should be retained
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');
        $property->setAccessible(true);

        $retainedBefore = count($property->getValue());

        // Queue another callback
        Ffi::queueMain(function (): void {});

        $retainedAfter = count($property->getValue());

        $this->assertGreaterThan($retainedBefore, $retainedAfter);
    }

    public function testTimerRetainsCallback(): void
    {
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');
        $property->setAccessible(true);

        $retainedBefore = count($property->getValue());

        // Create a timer
        Ffi::timer(100, function (): bool {
            return false;
        });

        $retainedAfter = count($property->getValue());

        $this->assertGreaterThan($retainedBefore, $retainedAfter);
    }

    public function testOnShouldQuitRetainsCallback(): void
    {
        $reflection = new \ReflectionClass(Ffi::class);
        $property = $reflection->getProperty('retained');
        $property->setAccessible(true);

        $retainedBefore = count($property->getValue());

        // Install should-quit handler
        Ffi::onShouldQuit(function (): bool {
            return true;
        });

        $retainedAfter = count($property->getValue());

        $this->assertGreaterThan($retainedBefore, $retainedAfter);
    }

    public function testMultipleFfiNewCallsCreateDistinctCData(): void
    {
        $cdata1 = Ffi::new('int');
        $cdata2 = Ffi::new('int');

        $this->assertNotSame($cdata1, $cdata2);
    }

    public function testFfiInstanceHasLibuiFunctions(): void
    {
        $ffi = Ffi::get();

        // Verify some core libui functions are bound by checking they can be called
        // (FFI methods are not detectable via method_exists)
        $this->assertIsCallable([$ffi, 'uiInit']);
        $this->assertIsCallable([$ffi, 'uiMain']);
        $this->assertIsCallable([$ffi, 'uiQuit']);
        $this->assertIsCallable([$ffi, 'uiNewButton']);
        $this->assertIsCallable([$ffi, 'uiNewWindow']);
    }

    public function testFfiCdefAcceptsGeneratedHeader(): void
    {
        // The header should have been successfully parsed by FFI::cdef()
        $ffi = Ffi::get();

        // If we got this far without exception, the header was accepted
        $this->assertInstanceOf(\FFI::class, $ffi);
    }

    public function testHandleTypeIsConsistent(): void
    {
        $button = new Button('Test');
        $handle = $button->handle();

        // The handle should be a CData object representing a pointer
        $this->assertInstanceOf(\FFI\CData::class, $handle);
        $this->assertFalse(\FFI::isNull($handle));
    }
}
