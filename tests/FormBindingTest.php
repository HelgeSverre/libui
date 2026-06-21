<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Checkbox;
use Libui\Combobox;
use Libui\Entry;
use Libui\Form;
use Libui\HasValue;
use Libui\Spinbox;

/**
 * The HasValue interface across input widgets and Form's bulk values()/setValues()
 * binding (keyed by field label).
 */
final class FormBindingTest extends LibuiTestCase
{
    public function testInputWidgetsImplementHasValue(): void
    {
        $this->assertInstanceOf(HasValue::class, new Entry());
        $this->assertInstanceOf(HasValue::class, new \Libui\MultilineEntry());
        $this->assertInstanceOf(HasValue::class, new \Libui\EditableCombobox());
        $this->assertInstanceOf(HasValue::class, new Checkbox('x'));
        $this->assertInstanceOf(HasValue::class, new Spinbox(0, 100));
        $this->assertInstanceOf(HasValue::class, new \Libui\Slider(0, 100));
        $this->assertInstanceOf(HasValue::class, new Combobox());
        $this->assertInstanceOf(HasValue::class, new \Libui\RadioButtons());
        $this->assertInstanceOf(HasValue::class, new \Libui\ColorButton());
    }

    public function testEntryValueRoundTrip(): void
    {
        $entry = new Entry();
        $entry->setValue('hello');
        $this->assertSame('hello', $entry->value());
    }

    public function testCheckboxAndSpinboxCoerceValues(): void
    {
        $check = new Checkbox('on');
        $check->setValue(true);
        $this->assertTrue($check->value());

        $spin = new Spinbox(0, 100);
        $spin->setValue(42);
        $this->assertSame(42, $spin->value());
    }

    public function testFormBulkSetAndGetValuesByLabel(): void
    {
        $name = new Entry();
        $agree = new Checkbox('I agree');
        $age = new Spinbox(0, 120);

        $form = new Form();
        $form
            ->append('Name', $name)
            ->append('Agree', $agree)
            ->append('Age', $age);

        $form->setValues(['Name' => 'Ada', 'Agree' => true, 'Age' => 36]);

        $this->assertSame(
            ['Name' => 'Ada', 'Agree' => true, 'Age' => 36],
            $form->values(),
        );
    }

    public function testSetValuesIgnoresUnknownLabels(): void
    {
        $form = new Form();
        $form->append('Name', new Entry());

        $form->setValues(['Name' => 'Bob', 'Nonexistent' => 'x']); // must not throw

        $this->assertSame(['Name' => 'Bob'], $form->values());
    }
}
