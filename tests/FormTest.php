<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Entry;
use Libui\Form;

/**
 * Covers the hand-written Form facade: $fields ordered-list tracking, the
 * delete(int) override that keeps it in sync, and duplicate-label handling in
 * values()/setValues().
 */
final class FormTest extends LibuiTestCase
{
    public function testAppendAndValues(): void
    {
        $form = new Form();
        $name = new Entry()->setText('Helge');
        $email = new Entry()->setText('helge@example.com');

        $form->append('Name', $name);
        $form->append('Email', $email);

        $this->assertSame(2, $form->numChildren());
        $this->assertSame(['Name' => 'Helge', 'Email' => 'helge@example.com'], $form->values());
    }

    public function testSetValuesIsPartialAndIgnoresUnknownLabels(): void
    {
        $form = new Form();
        $name = new Entry();
        $email = new Entry();
        $form->append('Name', $name);
        $form->append('Email', $email);

        $form->setValues(['Name' => 'Ada', 'Unknown' => 'nope']);

        $this->assertSame('Ada', $name->value());
        $this->assertSame('', $email->value());
    }

    public function testDuplicateLabelsAreBothAddressable(): void
    {
        $form = new Form();
        $first = new Entry();
        $second = new Entry();

        // Two fields collapsing to one map entry was the old bug.
        $form->append('Tag', $first);
        $form->append('Tag', $second);

        $this->assertSame(2, $form->numChildren());

        // setValues must reach EVERY field with the matching label, not just one.
        $form->setValues(['Tag' => 'shared']);
        $this->assertSame('shared', $first->value());
        $this->assertSame('shared', $second->value());
    }

    public function testDeleteMiddleKeepsFieldsInSync(): void
    {
        $form = new Form();
        $a = new Entry()->setText('a');
        $b = new Entry()->setText('b');
        $c = new Entry()->setText('c');
        $form->append('A', $a);
        $form->append('B', $b);
        $form->append('C', $c);

        // Delete the middle field; the inherited delete(int) must also splice $fields.
        $form->delete(1);

        $this->assertSame(2, $form->numChildren());
        $this->assertSame(['A' => 'a', 'C' => 'c'], $form->values());
    }
}
