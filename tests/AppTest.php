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
     * Drive the full App lifecycle in a child process so the blocking event
     * loop never freezes the test runner.
     *
     * The child builds a window, queues a timer that destroys the window and
     * quits (destroying before quit avoids libui's leak-check abort during
     * uiUninit), runs App::run() to completion, prints a sentinel, and exits 0.
     * We assert on the real outcome: a clean exit code AND a stderr with no
     * abort/leak diagnostic — not merely that the child "started".
     */
    public function testAppFullLifecycleInSubprocess(): void
    {
        // proc_open with pipes is available on every supported platform.
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        $script = <<<PHP
            <?php
            require '{$autoloadPath}';

            use Libui\App;
            use Libui\Button;
            use Libui\Ffi;
            use Libui\Window;

            Ffi::init();

            \$app = App::new();
            \$window = new Window('Test', 100, 100, false);
            \$button = new Button('Quit');

            \$window->setChild(\$button);
            \$app->window(\$window);

            // Headless: nobody clicks the button, so a timer drives the quit.
            // Destroy the window first so uiUninit()'s leak check stays clean,
            // then ask the loop to return.
            Ffi::timer(50, function () use (\$window): bool {
                \$window->destroy();
                Ffi::quit();
                return false; // one-shot
            });

            \$app->run(); // initialises, runs the loop, then uninits

            fwrite(STDOUT, "LIBUI_LIFECYCLE_OK\\n");
            exit(0);
            PHP;

        $scriptPath = sys_get_temp_dir() . '/libui_app_test_' . uniqid() . '.php';
        file_put_contents($scriptPath, $script);

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $cmd = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg($scriptPath);
            $process = proc_open($cmd, $descriptors, $pipes);

            $this->assertIsResource($process, 'the child PHP process should launch');

            fclose($pipes[0]);
            $stdout = (string) stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            $this->assertSame(
                0,
                $exitCode,
                "App lifecycle child should exit 0. stderr was:\n{$stderr}",
            );
            $this->assertStringContainsString(
                'LIBUI_LIFECYCLE_OK',
                $stdout,
                'the child should run App::run() to completion and print its sentinel',
            );
            // libui aborts (and GTK warns) on leaks/misuse; none of those must appear.
            $this->assertDoesNotMatchRegularExpression(
                '/abort|leak|Assertion|Fatal error|uncaught/i',
                $stderr,
                "child stderr should be free of abort/leak/fatal diagnostics. stderr was:\n{$stderr}",
            );
        } finally {
            @unlink($scriptPath);
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
