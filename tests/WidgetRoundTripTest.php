<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Checkbox;
use Libui\Combobox;
use Libui\Entry;
use Libui\Group;
use Libui\Label;
use Libui\MultilineEntry;
use Libui\ProgressBar;
use Libui\Slider;
use Libui\Spinbox;
use Libui\Window;

/**
 * Round-trip setter/getter pairs across widgets, asserting each getter returns
 * what its setter was handed. Proves the generated marshalling for strings,
 * ints, and bools survives the FFI boundary in both directions.
 */
final class WidgetRoundTripTest extends LibuiTestCase
{
    public function testButtonText(): void
    {
        $button = new Button('initial');
        $button->setText('Save');
        $this->assertSame('Save', $button->text());
    }

    public function testLabelText(): void
    {
        $label = new Label('initial');
        $label->setText('Status');
        $this->assertSame('Status', $label->text());
    }

    public function testCheckboxChecked(): void
    {
        $checkbox = new Checkbox('Agree');

        $checkbox->setChecked(true);
        $this->assertTrue($checkbox->checked());

        $checkbox->setChecked(false);
        $this->assertFalse($checkbox->checked());
    }

    public function testSliderValue(): void
    {
        $slider = new Slider(0, 100);
        $slider->setValue(73);
        $this->assertSame(73, $slider->value());
    }

    public function testSpinboxValue(): void
    {
        $spinbox = new Spinbox(0, 100);
        $spinbox->setValue(58);
        $this->assertSame(58, $spinbox->value());
    }

    public function testProgressBarValue(): void
    {
        $progress = new ProgressBar();
        $progress->setValue(40);
        $this->assertSame(40, $progress->value());
    }

    public function testEntryText(): void
    {
        $entry = new Entry();
        $entry->setText('hello world');
        $this->assertSame('hello world', $entry->text());
    }

    public function testMultilineEntryTextAndAppend(): void
    {
        $multiline = new MultilineEntry();
        $multiline->setText('line one');
        $this->assertSame('line one', $multiline->text());

        $multiline->append("\nline two");
        $this->assertSame("line one\nline two", $multiline->text());
    }

    public function testComboboxAppendAndSelection(): void
    {
        $combo = new Combobox();
        $combo->append('red')->append('green')->append('blue');
        $this->assertSame(3, $combo->numItems());

        $combo->setSelected(2);
        $this->assertSame(2, $combo->selected());
    }

    public function testGroupTitle(): void
    {
        $group = new Group('initial');
        $group->setTitle('Options');
        $this->assertSame('Options', $group->title());
    }

    public function testWindowTitle(): void
    {
        $window = new Window('initial', 320, 200, false);
        $window->setTitle('Main');
        $this->assertSame('Main', $window->title());
    }

    public function testWindowMargined(): void
    {
        $window = new Window('initial', 320, 200, false);

        $window->setMargined(true);
        $this->assertTrue($window->margined());

        $window->setMargined(false);
        $this->assertFalse($window->margined());
    }

    public function testWindowCenteredWithExplicitScreenSize(): void
    {
        $window = new Window('center', 100, 100, false);

        $this->assertSame($window, $window->centered(1920, 1080));

        // (1920 - 100) / 2 = 910, (1080 - 100) / 2 = 490
        [$x, $y] = $this->windowPosition($window);
        $this->assertSame(910, $x);
        $this->assertSame(490, $y);
    }

    public function testWindowCenteredClampsToZeroWhenLargerThanScreen(): void
    {
        $window = new Window('huge', 800, 600, false);
        $window->centered(640, 480);

        [$x, $y] = $this->windowPosition($window);
        $this->assertSame(0, $x);
        $this->assertSame(0, $y);
    }

    /**
     * Read a window's screen position straight from libui.
     *
     * @return array{int, int} [x, y]
     */
    private function windowPosition(Window $window): array
    {
        $out = \Libui\Ffi::get()->new('int[2]');
        \Libui\Ffi::get()->uiWindowPosition($window->handle(), \FFI::addr($out[0]), \FFI::addr($out[1]));

        return [$out[0], $out[1]];
    }
}
