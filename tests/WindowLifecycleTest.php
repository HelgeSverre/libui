<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\App;
use Libui\Ffi;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class WindowLifecycleTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNativeCloseAllowsDestroyInAfterCloseCleanup(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Uses a native Windows close message.');
        }

        Ffi::init();
        $window = new Window('Native closing test', 100, 100);
        $user32 = \FFI::cdef(
            'int PostMessageW(uintptr_t hwnd, unsigned int message, uintptr_t wparam, intptr_t lparam);',
            'user32.dll',
        );
        Ffi::timer(10, static function () use ($user32, $window): bool {
            $hwnd = Ffi::get()->uiControlHandle($window->asControl());
            // @phpstan-ignore-next-line dynamic Windows API call on a local FFI handle
            $user32->PostMessageW($hwnd, 0x0010, 0, 0); // WM_CLOSE
            return false;
        });

        $window->run(static function () use ($window): void {
            $window->destroy();
        });

        $this->assertTrue($window->isDestroyed());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWindowRunDestroysControlsWhenQuitWithoutClosing(): void
    {
        Ffi::init();
        $window = new Window('Lifecycle test', 100, 100);
        Ffi::timer(10, static function (): bool {
            Ffi::quit();
            return false;
        });

        $cleanup = 0;
        $window->onClose(static function () use (&$cleanup): void {
            ++$cleanup;
        });
        $destroyedBeforeCleanup = false;
        $window->run(static function () use ($window, &$destroyedBeforeCleanup): void {
            $destroyedBeforeCleanup = $window->isDestroyed();
        });

        $this->assertTrue($destroyedBeforeCleanup);
        $this->assertSame(1, $cleanup);
        $window->destroy(); // Must be safe after run() has already freed it.
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppRunDestroysAllWindowsWhenQuitWithoutClosing(): void
    {
        Ffi::init();
        $first = new Window('First', 100, 100);
        $second = new Window('Second', 100, 100);
        Ffi::timer(10, static function (): bool {
            Ffi::quit();
            return false;
        });

        $cleanup = 0;
        $first->onClose(static function () use (&$cleanup): void {
            ++$cleanup;
        });
        $second->onClose(static function () use (&$cleanup): void {
            ++$cleanup;
        });
        App::new()->window($first)->window($second)->run();

        $this->assertTrue($first->isDestroyed());
        $this->assertTrue($second->isDestroyed());
        $this->assertSame(2, $cleanup);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAppRunsWindowCleanupBeforeNativeRecursiveDestruction(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Uses a native Windows close message.');
        }
        Ffi::init();
        $window = new Window('App native close', 100, 100);
        $child = new \Libui\Button('child');
        $window->setChild($child);
        $cleanup = 0;
        $window->onClose(static function () use (&$cleanup, $child): void {
            if ($child->text() === 'child') {
                ++$cleanup;
            }
        });
        $user32 = \FFI::cdef(
            'int PostMessageW(uintptr_t hwnd, unsigned int message, uintptr_t wparam, intptr_t lparam);',
            'user32.dll',
        );
        Ffi::timer(10, static function () use ($window, $user32): bool {
            $hwnd = Ffi::get()->uiControlHandle($window->asControl());
            // @phpstan-ignore-next-line dynamic Windows API call on a local FFI handle
            $user32->PostMessageW($hwnd, 0x0010, 0, 0);
            return false;
        });
        App::new()->window($window)->run();
        $this->assertSame(1, $cleanup);
        $this->assertTrue($child->isDestroyed());
        $child->destroy();
    }
}
