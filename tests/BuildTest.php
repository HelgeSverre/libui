<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Box;
use Libui\Build;
use Libui\Entry;
use Libui\Form;
use Libui\Label;
use Libui\Window;

/**
 * Construction-correctness tests for the declarative Build facade (headless).
 */
final class BuildTest extends LibuiTestCase
{
    public function testVboxReturnsBoxWithChildren(): void
    {
        $box = Build::vbox(new Label('a'), new Label('b'));

        $this->assertInstanceOf(Box::class, $box);
        $this->assertFalse(\FFI::isNull($box->handle()));
        $this->assertSame(2, $box->numChildren());
    }

    public function testHboxReturnsBoxWithChildren(): void
    {
        $box = Build::hbox(new Label('a'), new Label('b'), new Label('c'));

        $this->assertInstanceOf(Box::class, $box);
        $this->assertFalse(\FFI::isNull($box->handle()));
        $this->assertSame(3, $box->numChildren());
    }

    public function testStretchyChildIsAcceptedViaWrapper(): void
    {
        $box = Build::vbox(new Label('a'), Build::stretchy(new Label('b')));

        $this->assertSame(2, $box->numChildren());
    }

    public function testStretchyReturnsInternalMarkerConsumedByFill(): void
    {
        // White-box guard: stretchy()'s opaque marker must stay in sync with
        // what fill() consumes. Not a public contract — see Build::stretchy().
        $label = new Label('a');
        $wrapped = Build::stretchy($label);

        $this->assertSame(['stretchy' => true, 'control' => $label], $wrapped);
    }

    public function testFormReturnsFormWithFields(): void
    {
        $form = Build::form(['Name' => new Entry()]);

        $this->assertInstanceOf(Form::class, $form);
        $this->assertFalse(\FFI::isNull($form->handle()));
        $this->assertSame(1, $form->numChildren());
    }

    public function testFormWithMultipleFields(): void
    {
        $form = Build::form([
            'Name' => new Entry(),
            'Email' => new Entry(),
        ]);

        $this->assertSame(2, $form->numChildren());
    }

    public function testWindowReturnsWindowWithChild(): void
    {
        $box = Build::vbox(new Label('a'));
        $window = Build::window('T', 640, 480, $box);

        $this->assertInstanceOf(Window::class, $window);
        $this->assertFalse(\FFI::isNull($window->handle()));
        $this->assertTrue($window->margined());
    }

    public function testWindowMarginedCanBeDisabled(): void
    {
        $window = Build::window('T', 320, 240, new Label('a'), false);

        $this->assertFalse(\FFI::isNull($window->handle()));
        $this->assertFalse($window->margined());
    }
}
