<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Box;
use Libui\Button;
use Libui\Checkbox;
use Libui\Combobox;
use Libui\Entry;
use Libui\Form;
use Libui\Generated\Enum\Align;
use Libui\Generated\Enum\At;
use Libui\Grid;
use Libui\Label;
use Libui\ProgressBar;
use Libui\RadioButtons;
use Libui\Slider;
use Libui\Spinbox;
use Libui\Tab;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for container widgets (Box, Grid, Form, Tab).
 * Tests child management, layout properties, and nesting.
 */
#[Group('smoke')]
final class ContainerTest extends LibuiTestCase
{
    // ========================================================================
    // BOX TESTS
    // ========================================================================

    public function testBoxConstructsSuccessfully(): void
    {
        $box = new Box();
        $this->assertFalse(\FFI::isNull($box->handle()));
    }

    public function testBoxHorizontalFactory(): void
    {
        $box = Box::horizontal();
        $this->assertFalse(\FFI::isNull($box->handle()));
    }

    public function testBoxAppendSingleChild(): void
    {
        $box = new Box();
        $button = new Button('Button');

        $result = $box->append($button);

        $this->assertSame($box, $result);
    }

    public function testBoxAppendMultipleChildren(): void
    {
        $box = new Box();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $button3 = new Button('Button 3');

        $box->append($button1)->append($button2)->append($button3);

        $this->assertTrue(true, 'Multiple children should be appenable');
    }

    public function testBoxAppendWithStretch(): void
    {
        $box = new Box();
        $button = new Button('Button');

        $result = $box->append($button, 1); // Stretchy

        $this->assertSame($box, $result);
    }

    public function testBoxAppendWithNoStretch(): void
    {
        $box = new Box();
        $button = new Button('Button');

        $result = $box->append($button, 0); // Not stretchy

        $this->assertSame($box, $result);
    }

    public function testBoxDeleteChild(): void
    {
        $box = new Box();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $box->append($button1)->append($button2);

        // Note: Box doesn't have a numChildren() method, but delete should work
        $result = $box->delete(0);

        $this->assertSame($box, $result);
    }

    public function testBoxPaddedDefault(): void
    {
        $box = new Box();
        $padded = $box->padded();
        $this->assertIsBool($padded);
    }

    public function testBoxSetPaddedTrue(): void
    {
        $box = new Box();
        $result = $box->setPadded(true);

        $this->assertSame($box, $result);
        $this->assertTrue($box->padded());
    }

    public function testBoxSetPaddedFalse(): void
    {
        $box = new Box();
        $box->setPadded(true);
        $result = $box->setPadded(false);

        $this->assertSame($box, $result);
        $this->assertFalse($box->padded());
    }

    public function testBoxPaddedRoundTrip(): void
    {
        $box = new Box();

        $box->setPadded(true);
        $this->assertTrue($box->padded());

        $box->setPadded(false);
        $this->assertFalse($box->padded());

        $box->setPadded(true);
        $this->assertTrue($box->padded());
    }

    public function testBoxChaining(): void
    {
        $box = new Box(padded: true);
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $result = $box->append($button1, 1)->append($button2, 0);

        $this->assertSame($box, $result);
    }

    // ========================================================================
    // GRID LAYOUT TESTS
    // ========================================================================

    public function testGridAppendWithAllParameters(): void
    {
        $grid = new Grid();
        $button = new Button('Button');

        $result = $grid->append(
            $button,
            left: 0,
            top: 0,
            xspan: 1,
            yspan: 1,
            hexpand: 1,
            halign: Align::Fill,
            vexpand: 1,
            valign: Align::Fill,
        );

        $this->assertSame($grid, $result);
    }

    public function testGridAppendMultipleControls(): void
    {
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $label = new Label('Label');

        $grid->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $grid->append($button2, 1, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $grid->append($label, 0, 1, 2, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertTrue(true, 'Multiple controls should be appenable to grid');
    }

    public function testGridInsertAtWithExistingControl(): void
    {
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $grid->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);

        // Insert button2 to the trailing (right) of button1
        $result = $grid->insertAt(
            $button2,
            $button1,
            At::Trailing,
            xspan: 1,
            yspan: 1,
            hexpand: 0,
            halign: Align::Fill,
            vexpand: 0,
            valign: Align::Fill,
        );

        $this->assertSame($grid, $result);
    }

    public function testGridInsertAtPositions(): void
    {
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $button3 = new Button('Button 3');
        $button4 = new Button('Button 4');

        $grid->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);

        // Use At enum constants
        $grid->insertAt($button2, $button1, At::Leading, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $grid->insertAt($button3, $button1, At::Trailing, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $grid->insertAt($button4, $button1, At::Top, 1, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertTrue(true, 'InsertAt with different positions should work');
    }

    // ========================================================================
    // FORM LAYOUT TESTS
    // ========================================================================

    public function testFormAppendWithStretchy(): void
    {
        $form = new Form();
        $entry = new Entry();

        $result = $form->append('Name:', $entry, stretchy: 0); // Not stretchy

        $this->assertSame($form, $result);
    }

    public function testFormAppendStretchyField(): void
    {
        $form = new Form();
        $entry = new Entry();

        $result = $form->append('Description:', $entry, stretchy: 1); // Stretchy

        $this->assertSame($form, $result);
    }

    public function testFormAppendMixedFields(): void
    {
        $form = new Form();
        $entry = new Entry();
        $checkbox = new Checkbox('Agree');
        $slider = new Slider(0, 100);
        $spinbox = new Spinbox(0, 100);

        $form
            ->append('Name:', $entry, 1)
            ->append('Age:', $spinbox, 0)
            ->append('Score:', $slider, 1)
            ->append('Agree:', $checkbox, 0);

        $this->assertSame(4, $form->numChildren());
    }

    public function testFormDeleteByIndex(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();
        $entry3 = new Entry();

        $form->append('Field 1:', $entry1, 0);
        $form->append('Field 2:', $entry2, 0);
        $form->append('Field 3:', $entry3, 0);

        $this->assertSame(3, $form->numChildren());

        $result = $form->delete(1); // Delete middle field

        $this->assertSame($form, $result);
        $this->assertSame(2, $form->numChildren());
    }

    public function testFormDeleteFirst(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();

        $form->append('Field 1:', $entry1, 0);
        $form->append('Field 2:', $entry2, 0);

        $form->delete(0);

        $this->assertSame(1, $form->numChildren());
    }

    public function testFormDeleteLast(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();

        $form->append('Field 1:', $entry1, 0);
        $form->append('Field 2:', $entry2, 0);

        $form->delete(1);

        $this->assertSame(1, $form->numChildren());
    }

    // ========================================================================
    // TAB CONTAINER TESTS
    // ========================================================================

    public function testTabAppendWithTabName(): void
    {
        $tab = new Tab();
        $button = new Button('Button on Tab');

        $result = $tab->append('First Tab', $button);

        $this->assertSame($tab, $result);
    }

    public function testTabAppendMultipleTabs(): void
    {
        $tab = new Tab();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $button3 = new Button('Button 3');

        $tab->append('Tab 1', $button1);
        $tab->append('Tab 2', $button2);
        $tab->append('Tab 3', $button3);

        $this->assertSame(3, $tab->numPages());
    }

    public function testTabNumPagesEmpty(): void
    {
        $tab = new Tab();
        $this->assertSame(0, $tab->numPages());
    }

    public function testTabInsertAtPosition(): void
    {
        $tab = new Tab();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $tab->append('Tab 1', $button1);
        $tab->insertAt('Tab 0', 0, $button2);

        $this->assertSame(2, $tab->numPages());
    }

    public function testTabDeleteAtIndex(): void
    {
        $tab = new Tab();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $button3 = new Button('Button 3');

        $tab->append('Tab 1', $button1);
        $tab->append('Tab 2', $button2);
        $tab->append('Tab 3', $button3);

        $this->assertSame(3, $tab->numPages());

        $result = $tab->delete(1);

        $this->assertSame($tab, $result);
        $this->assertSame(2, $tab->numPages());
    }

    public function testTabMarginedGetSet(): void
    {
        $tab = new Tab();
        $button = new Button('Button');
        $tab->append('Tab', $button);

        $margined = $tab->margined(0);
        $this->assertIsBool($margined);

        $result = $tab->setMargined(0, true);
        $this->assertSame($tab, $result);

        $marginedAfter = $tab->margined(0);
        $this->assertTrue($marginedAfter);
    }

    // ========================================================================
    // NESTED CONTAINERS TESTS
    // ========================================================================

    public function testBoxInBox(): void
    {
        $outerBox = new Box();
        $innerBox = new Box();
        $button = new Button('Button');

        $innerBox->append($button);
        $outerBox->append($innerBox);

        $this->assertTrue(true, 'Box can contain another Box');
    }

    public function testTabInBox(): void
    {
        $box = new Box();
        $tab = new Tab();
        $button = new Button('Button');

        $tab->append('Tab', $button);
        $box->append($tab);

        $this->assertTrue(true, 'Box can contain Tab');
    }

    public function testFormInBox(): void
    {
        $box = new Box();
        $form = new Form();
        $entry = new Entry();

        $form->append('Field:', $entry, 0);
        $box->append($form);

        $this->assertTrue(true, 'Box can contain Form');
    }

    public function testGridInBox(): void
    {
        $box = new Box();
        $grid = new Grid();
        $button = new Button('Button');

        $grid->append($button, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $box->append($grid);

        $this->assertTrue(true, 'Box can contain Grid');
    }

    public function testComplexNestedLayout(): void
    {
        $mainBox = new Box();

        // Tab container
        $tab = new Tab();

        // Form inside first tab
        $form1 = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();
        $form1->append('Name:', $entry1, 1);
        $form1->append('Email:', $entry2, 1);

        // Grid inside second tab
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');
        $grid->append($button1, 0, 0, 1, 1, 1, Align::Fill, 1, Align::Fill);
        $grid->append($button2, 1, 0, 1, 1, 1, Align::Fill, 1, Align::Fill);

        $tab->append('Form Tab', $form1);
        $tab->append('Grid Tab', $grid);

        $mainBox->append($tab);

        $this->assertTrue(true, 'Complex nested layout should work');
    }

    // ========================================================================
    // CONTAINER PROPERTIES TESTS
    // ========================================================================

    public function testBoxPaddedProperty(): void
    {
        $box = new Box();

        // Default
        $this->assertIsBool($box->padded());

        // Set to true
        $box->setPadded(true);
        $this->assertTrue($box->padded());

        // Set to false
        $box->setPadded(false);
        $this->assertFalse($box->padded());
    }

    public function testGridPaddedProperty(): void
    {
        $grid = new Grid();

        $this->assertIsBool($grid->padded());

        $grid->setPadded(true);
        $this->assertTrue($grid->padded());

        $grid->setPadded(false);
        $this->assertFalse($grid->padded());
    }

    public function testFormPaddedProperty(): void
    {
        $form = new Form();

        $this->assertIsBool($form->padded());

        $form->setPadded(true);
        $this->assertTrue($form->padded());

        $form->setPadded(false);
        $this->assertFalse($form->padded());
    }

    // ========================================================================
    // CHILD MANAGEMENT TESTS
    // ========================================================================

    public function testBoxWithVariousChildTypes(): void
    {
        $box = new Box();

        $box
            ->append(new Button('Button'))
            ->append(new Label('Label'))
            ->append(new Entry())
            ->append(new Checkbox('Checkbox'))
            ->append(new Slider(0, 100))
            ->append(new Spinbox(0, 100))
            ->append(new ProgressBar())
            ->append(new RadioButtons())
            ->append(new Combobox());

        $this->assertTrue(true, 'Box can contain various widget types');
    }

    public function testFormWithVariousChildTypes(): void
    {
        $form = new Form();

        $form
            ->append('Button:', new Button('Click'), 0)
            ->append('Label:', new Label('Text'), 0)
            ->append('Entry:', new Entry(), 1)
            ->append('Checkbox:', new Checkbox('Check'), 0)
            ->append('Slider:', new Slider(0, 100), 1)
            ->append('Spinbox:', new Spinbox(0, 100), 0);

        $this->assertSame(6, $form->numChildren());
    }

    // ========================================================================
    // FLUENT CHAINING TESTS
    // ========================================================================

    public function testBoxFluentChaining(): void
    {
        $box = new Box(padded: true);
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $result = $box
            ->append($button1, 1)
            ->append($button2, 0)
            ->setPadded(false);

        $this->assertSame($box, $result);
    }

    public function testGridFluentChaining(): void
    {
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $result = $grid
            ->setPadded(true)
            ->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill)
            ->append($button2, 1, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertSame($grid, $result);
    }

    public function testFormFluentChaining(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();

        $result = $form
            ->setPadded(true)
            ->append('Field 1:', $entry1, 1)
            ->append('Field 2:', $entry2, 1);

        $this->assertSame($form, $result);
    }

    public function testTabFluentChaining(): void
    {
        $tab = new Tab();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $result = $tab
            ->append('Tab 1', $button1)
            ->append('Tab 2', $button2)
            ->setSelected(1);

        $this->assertSame($tab, $result);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function testEmptyContainersConstruct(): void
    {
        $box = new Box();
        $grid = new Grid();
        $form = new Form();
        $tab = new Tab();

        $this->assertFalse(\FFI::isNull($box->handle()));
        $this->assertFalse(\FFI::isNull($grid->handle()));
        $this->assertFalse(\FFI::isNull($form->handle()));
        $this->assertFalse(\FFI::isNull($tab->handle()));
    }

    public function testContainerWithDestroyedChild(): void
    {
        $box = new Box();
        $button = new Button('Button');

        $box->append($button);
        // libui requires removing child from parent before destroying
        $box->delete(0);
        $button->destroy();

        // The container should still be valid
        $this->assertFalse(\FFI::isNull($box->handle()));
    }
}
