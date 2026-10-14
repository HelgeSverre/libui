<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Box;
use Libui\Button;
use Libui\Control;
use Libui\Ffi;
use Libui\Form;
use Libui\Grid;
use Libui\Group;
use Libui\Menu;
use Libui\Tab;
use Libui\Window;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OwnershipRobustnessTest extends TestCase
{
    protected function setUp(): void
    {
        Ffi::init();
    }

    protected function tearDown(): void
    {
        Ffi::uninit();
    }

    /** @return list<array{string}> */
    public static function containerKinds(): array
    {
        return [['box'], ['form'], ['tab'], ['grid'], ['group'], ['window']];
    }

    private function container(string $kind): Control
    {
        return match ($kind) {
            'form' => new Form(),
            'tab' => new Tab(),
            'grid' => new Grid(),
            'group' => new Group('Test'),
            'window' => new Window('Test', 100, 100),
            default => new Box(),
        };
    }

    private function attach(Control $parent, Control $child): void
    {
        match (true) {
            $parent instanceof Form => $parent->append('Field', $child),
            $parent instanceof Tab => $parent->append('Page', $child),
            $parent instanceof Grid => $parent->place($child, 0, 0),
            $parent instanceof Group, $parent instanceof Window => $parent->setChild($child),
            $parent instanceof Box => $parent->append($child),
            default => throw new \LogicException('Unknown test container'),
        };
    }

    #[DataProvider('containerKinds')]
    public function testParentDestructionInvalidatesEveryDescendant(string $kind): void
    {
        $parent = $this->container($kind);
        $nested = new Box();
        $child = new Button('live');
        $nested->append($child);
        $this->attach($parent, $nested);
        $parent->destroy();
        $this->assertTrue($nested->isDestroyed());
        $this->assertTrue($child->isDestroyed());
        $child->destroy(); // Previously segfaulted after native recursive destruction.
        $this->expectException(\LogicException::class);
        $child->text();
    }

    public function testAttachedControlMustBeDetachedBeforeDestruction(): void
    {
        $parent = new Box();
        $child = new Button('still live');
        $parent->append($child);
        try {
            $child->destroy();
            $this->fail('Attached destruction must be rejected');
        } catch (\LogicException) {
            $this->assertFalse($child->isDestroyed());
        }
        $this->assertSame('still live', $child->text());
        $parent->delete(0);
        $child->destroy();
        $this->assertTrue($child->isDestroyed());
    }

    public function testDuplicateParentAndCyclesAreRejectedWithoutChangingOwnership(): void
    {
        $first = new Box();
        $second = new Box();
        $child = new Button('child');
        $first->append($child);
        try {
            $second->append($child);
            $this->fail('A child cannot have two parents');
        } catch (\LogicException) {
            $this->assertSame(0, $second->numChildren());
        }
        $nested = new Box();
        $second->append($nested);
        try {
            $nested->append($second);
            $this->fail('An ancestor cannot become its descendant');
        } catch (\LogicException) {
            $this->assertSame(0, $nested->numChildren());
        }
        $first->delete(0);
        $nested->append($child);
        $second->destroy();
        $this->assertTrue($child->isDestroyed());
    }

    /** @return list<array{string}> */
    public static function indexedKinds(): array
    {
        return [['box'], ['form'], ['tab']];
    }

    #[DataProvider('indexedKinds')]
    public function testInvalidDeleteDoesNotAbortOrMutateTheContainer(string $kind): void
    {
        $parent = match ($kind) {
            'form' => new Form(),
            'tab' => new Tab(),
            default => new Box(),
        };
        foreach ([-1, 0, PHP_INT_MAX] as $index) {
            try {
                $parent->delete($index);
                $this->fail('Invalid index accepted');
            } catch (\OutOfBoundsException) {
                $this->addToAssertionCount(1);
            }
        }
        $child = new Button('detached');
        $this->attach($parent, $child);
        $parent->delete(0);
        $other = new Box();
        $other->append($child);
        $parent->destroy();
        $this->assertSame('detached', $child->text());
    }

    public function testReplacingAndClearingChildrenLeavesOldChildrenReusable(): void
    {
        foreach ([new Group('Group'), new Window('Window', 100, 100)] as $parent) {
            $first = new Button('first');
            $second = new Button('second');
            $parent->setChild($first)->setChild($first)->setChild($second)->setChild(null);
            $parent->destroy();
            $this->assertSame('first', $first->text());
            $this->assertSame('second', $second->text());
            $first->destroy();
            $second->destroy();
        }
    }

    public function testTabInsertionKeepsNativeAndPhpOwnershipOrderAligned(): void
    {
        $tab = new Tab();
        $first = new Button('first');
        $inserted = new Button('inserted');
        $tab->append('first', $first)->insertAt('inserted', 0, $inserted)->delete(0);
        $tab->destroy();
        $this->assertTrue($first->isDestroyed());
        $this->assertSame('inserted', $inserted->text());
        $this->expectException(\LogicException::class);
        $first->handle();
    }

    public function testRejectedFormAppendDoesNotLeaveAPhantomValueField(): void
    {
        $first = new Form();
        $second = new Form();
        $entry = new \Libui\Entry();
        $first->append('first', $entry);
        try {
            $second->append('phantom', $entry);
            $this->fail('Duplicate adoption accepted');
        } catch (\LogicException) {
            $this->assertSame([], $second->values());
        }
    }

    public function testMenusCannotBeUsedAsControlsAndBecomeInvalidAfterShutdown(): void
    {
        $menu = new Menu('Audit');
        $item = $menu->appendItem('Item');
        foreach (['destroy', 'asControl', 'show'] as $method) {
            try {
                $menu->$method();
                $this->fail('A menu was treated as uiControl');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        Ffi::uninit();
        $this->assertTrue($menu->isDestroyed());
        $this->assertTrue($item->isDestroyed());
    }

    public function testRepeatedShutdownIsSafeAndAClosedSessionCannotRestart(): void
    {
        $window = new Window('Cycle', 100, 100);
        $child = new Button('child');
        $window->setChild($child);
        for ($iteration = 0; $iteration < 5; ++$iteration) {
            Ffi::uninit();
        }
        $this->assertTrue($window->isDestroyed());
        $this->assertTrue($child->isDestroyed());
        $child->destroy();
        $this->expectException(\LogicException::class);
        Ffi::init();
    }

    public function testComboboxIndicesAreCheckedBeforeNativeArrayAccess(): void
    {
        $combo = new \Libui\Combobox();
        foreach ([-1, 0, PHP_INT_MAX] as $index) {
            try {
                $combo->delete($index);
                $this->fail('Invalid combobox deletion accepted');
            } catch (\OutOfBoundsException) {
                $this->addToAssertionCount(1);
            }
        }
        $combo->insertAt(0, 'first')->insertAt(1, 'second')->delete(0);
        $this->assertSame(1, $combo->numItems());
        $this->expectException(\OutOfBoundsException::class);
        $combo->insertAt(2, 'gap');
    }

    public function testTabMarginsCannotAccessANonexistentPage(): void
    {
        $tab = new Tab();
        try {
            $tab->margined(0);
            $this->fail('Invalid page accepted');
        } catch (\OutOfBoundsException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(\OutOfBoundsException::class);
        $tab->setMargined(-1, true);
    }
}
