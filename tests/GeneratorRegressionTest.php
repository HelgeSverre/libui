<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Ffi;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests pinning behaviour the code generator must keep emitting.
 *
 * These guard two bugs that `composer regen` previously (re)introduced:
 *   1. scalar out-pointer params (double/int pointers) passed without
 *      \FFI::addr(), which is a hard FFI type error at call time;
 *   2. user callbacks invoked from C without a try/catch, so a thrown exception
 *      would unwind into libui's trampoline and fatal the process.
 */
#[Group('smoke')]
final class GeneratorRegressionTest extends LibuiTestCase
{
    public function testWindowContentSizeOutPointerRoundTrip(): void
    {
        $window = new Window('Regression', 320, 240, false);
        $window->setContentSize(300, 200);

        $ffi = Ffi::get();
        $width = $ffi->new('int');
        $height = $ffi->new('int');

        // Would throw "expecting 'int*', found 'int'" if the generator regressed.
        $window->contentSize($width, $height);

        $this->assertSame(300, $width->cdata);
        $this->assertSame(200, $height->cdata);
    }

    public function testWindowPositionOutPointerDoesNotThrow(): void
    {
        $window = new Window('Regression', 100, 100, false);

        $ffi = Ffi::get();
        $x = $ffi->new('int');
        $y = $ffi->new('int');

        $window->position($x, $y);

        $this->assertIsInt($x->cdata);
        $this->assertIsInt($y->cdata);
    }

    public function testGeneratedClickCallbackIsGuarded(): void
    {
        // The generated onClicked() must wrap the user callback in try/catch.
        // We can't synthesise a native click headlessly, so assert the emitted
        // source guards the callback — the contract the generator promises.
        $source = file_get_contents(Ffi::root() . '/src/Generated/Button.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('try {', $source, 'generated callbacks must be wrapped in try/catch');
        $this->assertStringContainsString('catch (\\Throwable', $source);
    }

    public function testButtonCallbackRegistersWithoutError(): void
    {
        $button = new Button('Click');
        $result = $button->onClicked(static function (): void {
            throw new \RuntimeException('should never escape into C');
        });

        // Registration returns $this for chaining; the throw above is only reached
        // on a real click, where the generated guard catches it.
        $this->assertSame($button, $result);
    }
}
