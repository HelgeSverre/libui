<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Dialogs;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the parent-bound Dialogs facade. Methods that pop a real dialog are
 * never invoked (they would block); we assert structure via reflection.
 */
#[Group('smoke')]
final class DialogsTest extends LibuiTestCase
{
    public function testDialogsForReturnsInstanceBoundToWindow(): void
    {
        $window = new Window('W', 100, 100, false);
        $this->assertInstanceOf(Dialogs::class, Dialogs::for($window));
    }

    public function testWindowDialogsReturnsDialogsFacade(): void
    {
        $window = new Window('W', 100, 100, false);
        $this->assertInstanceOf(Dialogs::class, $window->dialogs());
    }

    public function testDialogsConstructorAcceptsWindow(): void
    {
        $window = new Window('W', 100, 100, false);
        $this->assertInstanceOf(Dialogs::class, new Dialogs($window));
    }

    public function testDialogsMethodsExistWithParentlessSignatures(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            new \ReflectionClass(Dialogs::class)->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        foreach (['msgBox', 'error', 'openFile', 'openFolder', 'saveFile'] as $name) {
            $this->assertContains($name, $methods, "Dialogs should expose {$name}()");
        }

        foreach (['openFile', 'openFolder', 'saveFile'] as $name) {
            $method = new \ReflectionMethod(Dialogs::class, $name);
            $this->assertSame(
                0,
                $method->getNumberOfRequiredParameters(),
                "{$name} should require zero parameters (parent is bound)",
            );
        }
    }

    public function testOpenFileReturnsNullableString(): void
    {
        foreach (['openFile', 'openFolder', 'saveFile'] as $name) {
            $method = new \ReflectionMethod(Dialogs::class, $name);
            $type = $method->getReturnType();

            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame('string', $type->getName());
            $this->assertTrue($type->allowsNull(), "{$name} should return ?string");
        }
    }
}
