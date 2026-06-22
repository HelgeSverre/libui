<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Button;
use Libui\Ffi;
use Libui\Window;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests pinning behaviour the code generator must keep emitting.
 *
 * These guard two bugs that `composer regen` previously (re)introduced:
 *   1. scalar out-pointer params (double/int pointers) passed without
 *      \FFI::addr(), which is a hard FFI type error at call time;
 *   2. user callbacks invoked from C without a try/catch, so a thrown exception
 *      would unwind into libui's trampoline and fatal the process.
 */
#[Group('smoke')]
final class GeneratorRegressionTest extends LibuiTestCase
{
    public function testGeneratedPhpSourcesAreSyntaxValid(): void
    {
        $directory = new \RecursiveDirectoryIterator(Ffi::root() . '/src/Generated');
        $iterator = new \RecursiveIteratorIterator($directory);
        $failures = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
            $output = [];
            exec($command, $output, $status);
            if ($status !== 0) {
                $failures[] = $file->getPathname() . "\n" . implode("\n", $output);
            }
        }

        $this->assertSame([], $failures);
    }

    public function testWindowContentSizeOutPointerRoundTrip(): void
    {
        $window = new Window('Regression', 320, 240, false);
        $window->setContentSize(300, 200);

        $ffi = Ffi::get();
        $width = $ffi->new('int');
        $height = $ffi->new('int');

        // Would throw "expecting 'int*', found 'int'" if the generator regressed.
        $window->contentSize($width, $height);

        $this->assertSame(300, $width->cdata);
        $this->assertSame(200, $height->cdata);
    }

    public function testWindowPositionOutPointerDoesNotThrow(): void
    {
        $window = new Window('Regression', 100, 100, false);

        $ffi = Ffi::get();
        $x = $ffi->new('int');
        $y = $ffi->new('int');

        $window->position($x, $y);

        $this->assertIsInt($x->cdata);
        $this->assertIsInt($y->cdata);
    }

    public function testEveryGeneratedCallbackSetterIsGuarded(): void
    {
        // Every generated on*() that takes a callable hands a closure to C, where a
        // thrown exception escaping the trampoline is a hard fatal — so the generator
        // MUST wrap each in try/catch. Assert it for ALL of them, not just Button.
        $unguarded = [];

        foreach (glob(Ffi::root() . '/src/Generated/*.php') as $file) {
            $source = (string) file_get_contents($file);
            // Capture each `public function onXxx(...) { ... }` method body (the
            // closing brace is at 4-space indent; closures inside use 8+).
            preg_match_all('/public function (on\w+)\([^{]*\{(.*?)\n    \}/s', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as [$whole, $name, $body]) {
                if (! str_contains($whole, 'callable')) {
                    continue; // only callback setters
                }
                if (! str_contains($body, 'try {') || ! str_contains($body, 'catch (\\Throwable')) {
                    $unguarded[] = basename($file) . "::{$name}()";
                }
            }
        }

        $this->assertSame([], $unguarded, 'these generated callback setters are missing a try/catch guard');
    }

    public function testButtonCallbackRegistersWithoutError(): void
    {
        $button = new Button('Click');
        $result = $button->onClicked(static function (): void {
            throw new \RuntimeException('should never escape into C');
        });

        // Registration returns $this for chaining; the throw above is only reached
        // on a real click, where the generated guard catches it.
        $this->assertSame($button, $result);
    }

    public function testRegisteringSecondHandlerSupersedesTheFirst(): void
    {
        // Registering a second on*() handler supersedes the first at the C level,
        // but the prior trampoline stays retained (it is never unregistered). Assert
        // that the retained-callback list grows by one per registration.
        $button = new Button('Click');

        $before = count(Button::retainedCallbacks());
        $button->onClicked(static fn (): null => null);
        $afterFirst = count(Button::retainedCallbacks());
        $button->onClicked(static fn (): null => null);
        $afterSecond = count(Button::retainedCallbacks());

        $this->assertSame($before + 1, $afterFirst, 'first onClicked() should retain one trampoline');
        $this->assertSame($afterFirst + 1, $afterSecond, 'second onClicked() retains another (prior one is not freed)');
    }

    public function testGeneratedDocblocksAreClean(): void
    {
        // Content guard for the generated docblocks: pins the doc-sanitizing fixes
        // (no dangling-comma @see/@param, no TODO/[Default leaks, no bare @see, and
        // DateTimePicker factory summaries mention their own widget) so a future
        // regen can't silently re-introduce them.
        $danglingTags = [];
        $todoLeaks = [];
        $defaultLeaks = [];
        $bareSeeTags = [];

        $directory = new \RecursiveDirectoryIterator(Ffi::root() . '/src/Generated');
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $base = $file->getFilename();

            // A `@see`/`@param` whose target ends in a comma is a parse artefact.
            if (preg_match('/@(?:see|param)\b[^\n]*,\s*$/m', $source)) {
                $danglingTags[] = $base;
            }
            if (preg_match('/\bTODO:/', $source)) {
                $todoLeaks[] = $base;
            }
            if (preg_match('/\[Default/i', $source)) {
                $defaultLeaks[] = $base;
            }
            // Bare `@see uiFn` resolves to no PHP symbol; the generator emits a
            // prose `libui: uiFn` label instead.
            if (preg_match('/@see\s+ui[A-Za-z0-9_]+/', $source)) {
                $bareSeeTags[] = $base;
            }
        }

        $this->assertSame([], $danglingTags, 'dangling-comma @see/@param in generated docblocks');
        $this->assertSame([], $todoLeaks, 'literal TODO: leaked into a generated docblock');
        $this->assertSame([], $defaultLeaks, '[Default ...] fragment leaked into a generated docblock');
        $this->assertSame([], $bareSeeTags, 'bare @see uiFn (use prose "libui: uiFn" instead)');

        // DateTimePicker factory summaries must each describe their own widget
        // (the header comments are rotated; doc_overrides corrects them).
        $picker = (string) file_get_contents(Ffi::root() . '/src/Generated/DateTimePicker.php');
        $this->assertMatchesRegularExpression(
            '/Creates a new date and time picker\..*public function __construct/s',
            $picker,
            'uiNewDateTimePicker summary should describe the date-and-time picker',
        );
        $this->assertMatchesRegularExpression(
            '/Creates a new date picker\..*public static function dateOnly/s',
            $picker,
            'dateOnly() summary should describe the date picker',
        );
        $this->assertMatchesRegularExpression(
            '/Creates a new time picker\..*public static function timeOnly/s',
            $picker,
            'timeOnly() summary should describe the time picker',
        );
    }
}
