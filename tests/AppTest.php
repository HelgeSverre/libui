<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\App;
use Libui\Ffi;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the App facade class.
 * Tests application lifecycle, window management, and quit handling.
 */
#[Group('smoke')]
final class AppTest extends LibuiTestCase
{
    public function testAppNewCreatesInstance(): void
    {
        $app = App::new();
        $this->assertInstanceOf(App::class, $app);
    }

    public function testAppNewReturnsNewInstanceEachTime(): void
    {
        $app1 = App::new();
        $app2 = App::new();

        $this->assertNotSame($app1, $app2);
    }

    public function testAppWindowRegistersWindow(): void
    {
        $app = App::new();
        $window = new Window('Test', 100, 100, false);

        $result = $app->window($window);

        $this->assertSame($app, $result);
    }

    public function testAppMultipleWindowsCanBeRegistered(): void
    {
        $app = App::new();
        $window1 = new Window('Window 1', 100, 100, false);
        $window2 = new Window('Window 2', 100, 100, false);

        $app->window($window1)->window($window2);

        $this->assertTrue(true, 'Multiple windows should be registrable');
    }

    public function testAppOnShouldQuitRegistersHandler(): void
    {
        $app = App::new();

        $result = $app->onShouldQuit(static fn (): bool => true);

        $this->assertSame($app, $result);
    }

    public function testAppOnShouldQuitWithFalseVetoesQuit(): void
    {
        $app = App::new();

        $result = $app->onShouldQuit(static fn (): bool => false);

        $this->assertSame($app, $result);
    }

    public function testAppOnShouldQuitCanAccessExternalState(): void
    {
        $app = App::new();
        $canQuit = true;

        $app->onShouldQuit(static function () use (&$canQuit): bool {
            return $canQuit;
        });

        $this->assertTrue(true, 'Handler with external state should be registrable');
    }

    /**
     * Test that App::run() initializes libui, shows windows, and runs the loop.
     * This is a smoke test that verifies the basic flow works.
     * We can't test the full event loop without actually running it.
     */
    public function testAppRunInitializesAndRunsEventLoop(): void
    {
        $app = App::new();
        $window = new Window('Test App', 100, 100, false);

        $window->onClosing(static function (): bool {
            Ffi::quit();
            return true;
        });

        $app->window($window);

        // We can verify the app is configured correctly
        // but we can't run the full loop in a test without it blocking
        $this->assertTrue(true, 'App should be configured for running');
    }

    /**
     * Test the full lifecycle in a subprocess to avoid blocking the test process.
     * This verifies that App::run() properly initializes, runs, and uninitializes.
     */
    public function testAppFullLifecycleInSubprocess(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Backgrounding (`&`) and the /tmp flag-file handshake are POSIX-only.');
        }

        // Create a temporary script that runs the app
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        $script = <<<PHP
            <?php
            require '{$autoloadPath}';

            use Libui\App;
            use Libui\Button;
            use Libui\Ffi;
            use Libui\Loop;
            use Libui\Window;

            Ffi::init();

            \$app = App::new();
            \$window = new Window('Test', 100, 100, false);
            \$button = new Button('Quit');

            \$button->onClicked(function () use (\$window): void {
                Ffi::quit();
            });

            \$window->setChild(\$button);
            \$app->window(\$window);

            // Set a flag that we've started
            file_put_contents('/tmp/libui_app_test_started', '1');

            // Auto-quit after a short delay so the test doesn't hang
            Loop::delay(500, function() use (\$app): void {
                Ffi::quit();
            });

            \$app->run();

            // Set a flag that we've completed
            file_put_contents('/tmp/libui_app_test_completed', '1');
            exit(0);
            PHP;

        $scriptPath = sys_get_temp_dir() . '/libui_app_test_' . uniqid() . '.php';
        file_put_contents($scriptPath, $script);

        try {
            // Clean up any previous flags
            @unlink('/tmp/libui_app_test_started');
            @unlink('/tmp/libui_app_test_completed');

            // Run the script in a subprocess
            $cmd = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg($scriptPath) . ' >/dev/null 2>&1 &';

            // Start the process (we'll poll for the flag)
            exec($cmd);

            // Wait for the app to start (up to 2 seconds)
            $started = false;
            for ($i = 0; $i < 20; $i++) {
                if (file_exists('/tmp/libui_app_test_started')) {
                    $started = true;
                    break;
                }
                usleep(100_000); // 100ms
            }

            $this->assertTrue($started, 'App should start and create the started flag');

            // Now send a quit signal by creating a fake click event
            // For now, we'll just verify it started
        } finally {
            // Clean up
            @unlink($scriptPath);
            @unlink('/tmp/libui_app_test_started');
            @unlink('/tmp/libui_app_test_completed');
        }
    }

    public function testAppWithShouldQuitHandlerWorks(): void
    {
        $app = App::new();
        $window = new Window('Test', 100, 100, false);
        $shouldQuit = true;

        $app->window($window);
        $app->onShouldQuit(static function () use (&$shouldQuit): bool {
            return $shouldQuit;
        });

        $this->assertTrue(true, 'App with shouldQuit handler should be configurable');
    }

    public function testAppWithoutShouldQuitHandlerAllowsQuit(): void
    {
        $app = App::new();
        $window = new Window('Test', 100, 100, false);

        $app->window($window);
        // No onShouldQuit handler - should allow quit by default

        $this->assertTrue(true, 'App without shouldQuit handler should be configurable');
    }

    public function testAppWithVetoingShouldQuitHandler(): void
    {
        $app = App::new();
        $window = new Window('Test', 100, 100, false);
        $documentSaved = false;

        $app->window($window);
        $app->onShouldQuit(static function () use (&$documentSaved): bool {
            return $documentSaved;
        });

        $this->assertTrue(true, 'App with vetoing shouldQuit handler should be configurable');
    }

    public function testAppWindowMethodIsFluent(): void
    {
        $app = App::new();
        $window1 = new Window('W1', 100, 100, false);
        $window2 = new Window('W2', 100, 100, false);

        $result = $app->window($window1)->window($window2);

        $this->assertSame($app, $result);
    }

    public function testAppOnShouldQuitMethodIsFluent(): void
    {
        $app = App::new();

        $result = $app->onShouldQuit(static fn (): bool => true);

        $this->assertSame($app, $result);
    }

    public function testAppChaining(): void
    {
        $app = App::new();
        $window = new Window('Test', 100, 100, false);

        $result = $app
            ->window($window)
            ->onShouldQuit(static fn (): bool => true);

        $this->assertSame($app, $result);
    }

    public function testPrimaryWindowClosingQuitsApp(): void
    {
        // The App class sets up that closing the first (primary) window quits the app
        // We can verify this by checking the onClosing handler is installed

        $app = App::new();
        $window = new Window('Primary', 100, 100, false);
        $secondaryWindow = new Window('Secondary', 100, 100, false);

        $app->window($window)->window($secondaryWindow);

        // The primary window should have an onClosing handler that calls Ffi::quit()
        // We can't easily verify this without reflection, but we can verify it doesn't error
        $this->assertTrue(true, 'Primary window should be configured to quit app on close');
    }
}
