<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Checkbox;
use Libui\Color;
use Libui\ColorButton;
use Libui\DateTimePicker;
use Libui\EditableCombobox;
use Libui\Entry;
use Libui\Ffi;
use Libui\FontButton;
use Libui\Form;
use Libui\Generated\Enum\Align;
use Libui\Generated\Enum\At;
use Libui\Grid;
use Libui\Group;
use Libui\Menu;
use Libui\RadioButtons;
use Libui\Separator;
use Libui\Tab;

/**
 * Comprehensive widget tests for all untested widgets.
 * Tests construction, property access, and basic functionality.
 */
#[Group('smoke')]
final class AAAWidgetTest extends LibuiTestCase
{
    // ========================================================================
    // CONSTRUCTION TESTS
    // ========================================================================

    public function testRadioButtonsConstructsSuccessfully(): void
    {
        $radio = new RadioButtons();
        $this->assertFalse(\FFI::isNull($radio->handle()));
    }

    public function testTabConstructsSuccessfully(): void
    {
        $tab = new Tab();
        $this->assertFalse(\FFI::isNull($tab->handle()));
    }

    public function testGridConstructsSuccessfully(): void
    {
        $grid = new Grid();
        $this->assertFalse(\FFI::isNull($grid->handle()));
    }

    public function testFormConstructsSuccessfully(): void
    {
        $form = new Form();
        $this->assertFalse(\FFI::isNull($form->handle()));
    }

    public function testDateTimePickerConstructsSuccessfully(): void
    {
        $picker = new DateTimePicker();
        $this->assertFalse(\FFI::isNull($picker->handle()));
    }

    public function testDateTimePickerValueRoundTrips(): void
    {
        $picker = new DateTimePicker();
        $picker->setValue(new \DateTimeImmutable('2026-06-21 14:30:45'));

        $got = $picker->getValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $got);
        $this->assertSame('2026-06-21 14:30:45', $got->format('Y-m-d H:i:s'));
    }

    public function testDateTimePickerDateOnlyFactory(): void
    {
        $picker = DateTimePicker::dateOnly();
        $this->assertFalse(\FFI::isNull($picker->handle()));
    }

    public function testDateTimePickerTimeOnlyFactory(): void
    {
        $picker = DateTimePicker::timeOnly();
        $this->assertFalse(\FFI::isNull($picker->handle()));
    }

    public function testColorButtonConstructsSuccessfully(): void
    {
        $button = new ColorButton();
        $this->assertFalse(\FFI::isNull($button->handle()));
    }

    public function testFontButtonConstructsSuccessfully(): void
    {
        $button = new FontButton();
        $this->assertFalse(\FFI::isNull($button->handle()));
    }

    public function testFontButtonGetFontReturnsTypedDescriptor(): void
    {
        $button = new FontButton();
        $font = $button->getFont();

        $this->assertInstanceOf(\Libui\Text\FontDescriptor::class, $font);
        $this->assertNotSame('', $font->family()); // a default font is selected
        $this->assertGreaterThan(0.0, $font->size());
    }

    public function testEditableComboboxConstructsSuccessfully(): void
    {
        $combo = new EditableCombobox();
        $this->assertFalse(\FFI::isNull($combo->handle()));
    }

    /** @runInSeparateProcess */
    public function testMenuConstructsSuccessfully(): void
    {
        $menu = new Menu('Test Menu');
        $this->assertFalse(\FFI::isNull($menu->handle()));
    }

    /** @runInSeparateProcess */
    public function testMenuItemConstructsSuccessfully(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendItem('Test Item');
        $this->assertFalse(\FFI::isNull($item->handle()));
    }

    public function testSeparatorConstructsSuccessfully(): void
    {
        $separator = new Separator();
        $this->assertFalse(\FFI::isNull($separator->handle()));
    }

    public function testSeparatorVerticalFactory(): void
    {
        $separator = Separator::vertical();
        $this->assertFalse(\FFI::isNull($separator->handle()));
    }

    // ========================================================================
    // RADIO BUTTONS TESTS
    // ========================================================================

    public function testRadioButtonsAppend(): void
    {
        $radio = new RadioButtons();
        $result = $radio->append('Option 1');

        $this->assertSame($radio, $result);
    }

    public function testRadioButtonsAppendMultipleItems(): void
    {
        $radio = new RadioButtons();
        $radio->append('Option 1')->append('Option 2')->append('Option 3');

        $this->assertTrue(true, 'Multiple items should be appenable');
    }

    public function testRadioButtonsSelectedDefault(): void
    {
        $radio = new RadioButtons();
        $radio->append('Option 1')->append('Option 2');

        $selected = $radio->selected();
        $this->assertIsInt($selected);
        // Default selection is -1 (none selected)
        $this->assertSame(-1, $selected);
    }

    public function testRadioButtonsSetSelected(): void
    {
        $radio = new RadioButtons();
        $radio->append('Option 1')->append('Option 2')->append('Option 3');

        $result = $radio->setSelected(1);
        $this->assertSame($radio, $result);
        $this->assertSame(1, $radio->selected());
    }

    public function testRadioButtonsOnSelected(): void
    {
        $radio = new RadioButtons();
        $result = $radio->onSelected(static function (): void {});

        $this->assertSame($radio, $result);
    }

    // ========================================================================
    // TAB TESTS
    // ========================================================================

    public function testTabAppend(): void
    {
        $tab = new Tab();
        $button = new Button('Tab 1');

        $result = $tab->append('Tab One', $button);

        $this->assertSame($tab, $result);
    }

    public function testTabAppendMultiplePages(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');

        $tab->append('First', $button1)->append('Second', $button2);

        $this->assertSame(2, $tab->numPages());
    }

    public function testTabSelectedDefault(): void
    {
        $tab = new Tab();
        $button = new Button('Tab 1');
        $tab->append('First', $button);

        $this->assertSame(0, $tab->selected());
    }

    public function testTabSetSelected(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');
        $tab->append('First', $button1)->append('Second', $button2);

        $result = $tab->setSelected(1);
        $this->assertSame($tab, $result);
        $this->assertSame(1, $tab->selected());
    }

    public function testTabDelete(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');
        $tab->append('First', $button1)->append('Second', $button2);

        $this->assertSame(2, $tab->numPages());

        $result = $tab->delete(0);
        $this->assertSame($tab, $result);
        $this->assertSame(1, $tab->numPages());
    }

    public function testTabMargined(): void
    {
        $tab = new Tab();
        $button = new Button('Tab 1');
        $tab->append('First', $button);

        $margined = $tab->margined(0);
        $this->assertIsBool($margined);
    }

    public function testTabSetMargined(): void
    {
        $tab = new Tab();
        $button = new Button('Tab 1');
        $tab->append('First', $button);

        $result = $tab->setMargined(0, true);
        $this->assertSame($tab, $result);
    }

    public function testTabInsertAt(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');
        $tab->append('First', $button1);

        $result = $tab->insertAt('Second', 0, $button2);
        $this->assertSame($tab, $result);
        $this->assertSame(2, $tab->numPages());
    }

    public function testTabOnSelected(): void
    {
        $tab = new Tab();
        $result = $tab->onSelected(static function (): void {});

        $this->assertSame($tab, $result);
    }

    // ========================================================================
    // GRID TESTS
    // ========================================================================

    public function testGridAppend(): void
    {
        $grid = new Grid();
        $button = new Button('Button');

        $result = $grid->append($button, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertSame($grid, $result);
    }

    public function testGridPaddedDefault(): void
    {
        $grid = new Grid();
        $padded = $grid->padded();
        $this->assertIsBool($padded);
    }

    public function testGridSetPadded(): void
    {
        $grid = new Grid();
        $result = $grid->setPadded(true);

        $this->assertSame($grid, $result);
        $this->assertTrue($grid->padded());
    }

    public function testGridSetPaddedToFalse(): void
    {
        $grid = new Grid();
        $grid->setPadded(true);
        $result = $grid->setPadded(false);

        $this->assertSame($grid, $result);
        $this->assertFalse($grid->padded());
    }

    public function testGridInsertAt(): void
    {
        $grid = new Grid();
        $button1 = new Button('First');
        $button2 = new Button('Second');

        $grid->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $result = $grid->insertAt($button2, $button1, At::Bottom, 1, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertSame($grid, $result);
    }

    // ========================================================================
    // FORM TESTS
    // ========================================================================

    public function testFormAppend(): void
    {
        $form = new Form();
        $entry = new Entry();

        $result = $form->append('Name:', $entry, 0);

        $this->assertSame($form, $result);
    }

    public function testFormAppendMultipleFields(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();

        $form->append('First:', $entry1, 0)->append('Second:', $entry2, 0);

        $this->assertSame(2, $form->numChildren());
    }

    public function testFormNumChildrenEmpty(): void
    {
        $form = new Form();
        $this->assertSame(0, $form->numChildren());
    }

    public function testFormDelete(): void
    {
        $form = new Form();
        $entry = new Entry();
        $form->append('Field:', $entry, 0);

        $this->assertSame(1, $form->numChildren());

        $result = $form->delete(0);
        $this->assertSame($form, $result);
        $this->assertSame(0, $form->numChildren());
    }

    public function testFormPaddedDefault(): void
    {
        $form = new Form();
        $padded = $form->padded();
        $this->assertIsBool($padded);
    }

    public function testFormSetPadded(): void
    {
        $form = new Form();
        $result = $form->setPadded(true);

        $this->assertSame($form, $result);
        $this->assertTrue($form->padded());
    }

    // ========================================================================
    // COLOR BUTTON TESTS
    // ========================================================================

    public function testColorButtonSetColor(): void
    {
        $button = new ColorButton();
        $result = $button->setColor(1.0, 0.5, 0.25, 1.0); // RGBA

        $this->assertSame($button, $result);
    }

    public function testColorButtonSetColorFromColorRoundTrips(): void
    {
        $button = new ColorButton();
        $result = $button->setColor(Color::rgb(0x80_4020, 0.5));
        $got = $button->getColor();

        $this->assertSame($button, $result);
        $this->assertInstanceOf(Color::class, $got);
        $this->assertEqualsWithDelta(0x80 / 255, $got->r, 1e-6);
        $this->assertEqualsWithDelta(0x40 / 255, $got->g, 1e-6);
        $this->assertEqualsWithDelta(0x20 / 255, $got->b, 1e-6);
        $this->assertEqualsWithDelta(0.5, $got->a, 1e-6);
    }

    public function testColorButtonOnChanged(): void
    {
        $button = new ColorButton();
        $result = $button->onChanged(static function (): void {});

        $this->assertSame($button, $result);
    }

    // ========================================================================
    // FONT BUTTON TESTS
    // ========================================================================

    public function testFontButtonOnChanged(): void
    {
        $button = new FontButton();
        $result = $button->onChanged(static function (): void {});

        $this->assertSame($button, $result);
    }

    // ========================================================================
    // EDITABLE COMBOBOX TESTS
    // ========================================================================

    public function testEditableComboboxAppend(): void
    {
        $combo = new EditableCombobox();
        $result = $combo->append('Option 1');

        $this->assertSame($combo, $result);
    }

    public function testEditableComboboxText(): void
    {
        $combo = new EditableCombobox();
        $combo->append('Option 1');

        $text = $combo->text();
        $this->assertIsString($text);
    }

    public function testEditableComboboxSetText(): void
    {
        $combo = new EditableCombobox();
        $result = $combo->setText('New text');

        $this->assertSame($combo, $result);
    }

    // ========================================================================
    // SEPARATOR TESTS
    // ========================================================================

    public function testSeparatorConstructs(): void
    {
        $separator = new Separator();
        $this->assertFalse(\FFI::isNull($separator->handle()));
    }

    public function testSeparatorVerticalConstructs(): void
    {
        $separator = Separator::vertical();
        $this->assertFalse(\FFI::isNull($separator->handle()));
    }

    // ========================================================================
    // MENU TESTS
    // Menus must be created before any Window is shown, which can finalize menus.
    // We mark these to run in separate processes to avoid this issue.
    // ========================================================================

    /** @runInSeparateProcess */
    public function testMenuAppendItem(): void
    {
        $menu = new Menu('Test Menu');

        $item = $menu->appendItem('Test Item');

        $this->assertInstanceOf(\Libui\Generated\MenuItem::class, $item);
        $this->assertFalse(\FFI::isNull($item->handle()));
    }

    /** @runInSeparateProcess */
    public function testMenuAppendSeparator(): void
    {
        $menu = new Menu('Test Menu');

        $result = $menu->appendSeparator();

        $this->assertSame($menu, $result);
    }

    /** @runInSeparateProcess */
    public function testMenuAppendQuitItem(): void
    {
        $menu = new Menu('Test Menu');

        $item = $menu->appendQuitItem();

        $this->assertInstanceOf(\Libui\Generated\MenuItem::class, $item);
        $this->assertFalse(\FFI::isNull($item->handle()));
    }

    /** @runInSeparateProcess */
    public function testMenuAppendPreferencesItem(): void
    {
        $menu = new Menu('Test Menu');

        $item = $menu->appendPreferencesItem();

        $this->assertInstanceOf(\Libui\Generated\MenuItem::class, $item);
        $this->assertFalse(\FFI::isNull($item->handle()));
    }

    /** @runInSeparateProcess */
    public function testMenuAppendAboutItem(): void
    {
        $menu = new Menu('Test Menu');

        $item = $menu->appendAboutItem();

        $this->assertInstanceOf(\Libui\Generated\MenuItem::class, $item);
        $this->assertFalse(\FFI::isNull($item->handle()));
    }

    // Menu doesn't have onClicked - MenuItem does

    // ========================================================================
    // MENU ITEM TESTS
    // ========================================================================

    /** @runInSeparateProcess */
    public function testMenuItemSetChecked(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendCheckItem('Test');
        $result = $item->setChecked(true);

        $this->assertSame($item, $result);
    }

    /** @runInSeparateProcess */
    public function testMenuItemChecked(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendCheckItem('Test');
        $item->setChecked(true);

        $checked = $item->checked();
        $this->assertIsBool($checked);
    }

    /** @runInSeparateProcess */
    public function testMenuItemEnable(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendItem('Test');
        $result = $item->enable();

        $this->assertSame($item, $result);
    }

    /** @runInSeparateProcess */
    public function testMenuItemDisable(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendItem('Test');
        $result = $item->disable();

        $this->assertSame($item, $result);
    }

    /** @runInSeparateProcess */
    public function testMenuItemOnClicked(): void
    {
        $menu = new Menu('Test Menu');
        $item = $menu->appendItem('Test');
        $result = $item->onClicked(static function (): void {});

        $this->assertSame($item, $result);
    }

    // ========================================================================
    // ROUND-TRIP TESTS FOR NEW WIDGETS
    // ========================================================================

    public function testColorButtonSetColorRoundTrip(): void
    {
        $button = new ColorButton();

        // Set color
        $button->setColor(1.0, 0.5, 0.25, 1.0);

        // Read color back (using FFI directly for now since color() needs CData pointers)
        $ffi = Ffi::get();
        $r = $ffi->new('double');
        $g = $ffi->new('double');
        $b = $ffi->new('double');
        $a = $ffi->new('double');

        $button->color($r, $g, $b, $a);

        // Verify the values were set (approximately)
        $this->assertEqualsWithDelta(1.0, $r->cdata, 0.01);
        $this->assertEqualsWithDelta(0.5, $g->cdata, 0.01);
        $this->assertEqualsWithDelta(0.25, $b->cdata, 0.01);
        $this->assertEqualsWithDelta(1.0, $a->cdata, 0.01);
    }

    public function testRadioButtonsSelectedRoundTrip(): void
    {
        $radio = new RadioButtons();
        $radio->append('Option 1')->append('Option 2')->append('Option 3');

        $radio->setSelected(2);
        $this->assertSame(2, $radio->selected());

        $radio->setSelected(0);
        $this->assertSame(0, $radio->selected());
    }

    public function testTabSelectedRoundTrip(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');
        $button3 = new Button('Tab 3');

        $tab
            ->append('First', $button1)
            ->append('Second', $button2)
            ->append('Third', $button3);

        $tab->setSelected(2);
        $this->assertSame(2, $tab->selected());

        $tab->setSelected(1);
        $this->assertSame(1, $tab->selected());
    }

    public function testFormNumChildrenRoundTrip(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();
        $entry3 = new Entry();

        $this->assertSame(0, $form->numChildren());

        $form->append('Field 1:', $entry1, 0);
        $this->assertSame(1, $form->numChildren());

        $form->append('Field 2:', $entry2, 0);
        $this->assertSame(2, $form->numChildren());

        $form->append('Field 3:', $entry3, 0);
        $this->assertSame(3, $form->numChildren());
    }

    public function testGridPaddedRoundTrip(): void
    {
        $grid = new Grid();

        $grid->setPadded(true);
        $this->assertTrue($grid->padded());

        $grid->setPadded(false);
        $this->assertFalse($grid->padded());
    }

    public function testEditableComboboxTextRoundTrip(): void
    {
        $combo = new EditableCombobox();

        $combo->setText('Hello World');
        $this->assertSame('Hello World', $combo->text());

        $combo->setText('Goodbye');
        $this->assertSame('Goodbye', $combo->text());
    }

    // ========================================================================
    // FACTORY METHODS TESTS
    // ========================================================================

    public function testAllFactoryMethodsReturnValidHandles(): void
    {
        $this->assertFalse(\FFI::isNull(DateTimePicker::dateOnly()->handle()));
        $this->assertFalse(\FFI::isNull(DateTimePicker::timeOnly()->handle()));
        $this->assertFalse(\FFI::isNull(Separator::vertical()->handle()));
    }

    // ========================================================================
    // CHAINING TESTS
    // ========================================================================

    public function testRadioButtonsChaining(): void
    {
        $radio = new RadioButtons();
        $result = $radio->append('Option 1')->append('Option 2')->setSelected(1);

        $this->assertSame($radio, $result);
        $this->assertSame(1, $radio->selected());
    }

    public function testTabChaining(): void
    {
        $tab = new Tab();
        $button1 = new Button('Tab 1');
        $button2 = new Button('Tab 2');

        $result = $tab
            ->append('First', $button1)
            ->append('Second', $button2)
            ->setSelected(1);

        $this->assertSame($tab, $result);
        $this->assertSame(2, $tab->numPages());
    }

    public function testGridChaining(): void
    {
        $grid = new Grid();
        $button = new Button('Button');

        $result = $grid->append($button, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill)
            ->setPadded(true);

        $this->assertSame($grid, $result);
        $this->assertTrue($grid->padded());
    }

    public function testFormChaining(): void
    {
        $form = new Form();
        $entry1 = new Entry();
        $entry2 = new Entry();

        $result = $form
            ->append('Field 1:', $entry1, 0)
            ->append('Field 2:', $entry2, 0)
            ->setPadded(true);

        $this->assertSame($form, $result);
        $this->assertSame(2, $form->numChildren());
    }

    public function testColorButtonChaining(): void
    {
        $button = new ColorButton();
        $result = $button->setColor(1.0, 0.5, 0.25, 1.0)->onChanged(static function (): void {});

        $this->assertSame($button, $result);
    }

    public function testEditableComboboxChaining(): void
    {
        $combo = new EditableCombobox();
        $result = $combo->append('Option 1')->append('Option 2')->setText('Selected');

        $this->assertSame($combo, $result);
    }

    // ========================================================================
    // WIDGET HIERARCHY TESTS
    // ========================================================================

    public function testTabWithChildControls(): void
    {
        $tab = new Tab();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $tab->append('Tab 1', $button1);
        $tab->append('Tab 2', $button2);

        $this->assertSame(2, $tab->numPages());
    }

    public function testFormWithChildControls(): void
    {
        $form = new Form();
        $entry = new Entry();
        $checkbox = new Checkbox('Agree');

        $form->append('Name:', $entry, 0);
        $form->append('Agreement:', $checkbox, 0);

        $this->assertSame(2, $form->numChildren());
    }

    public function testGridWithChildControls(): void
    {
        $grid = new Grid();
        $button1 = new Button('Button 1');
        $button2 = new Button('Button 2');

        $grid->append($button1, 0, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);
        $grid->append($button2, 1, 0, 1, 1, 0, Align::Fill, 0, Align::Fill);

        $this->assertTrue(true, 'Grid with children should work');
    }
}
