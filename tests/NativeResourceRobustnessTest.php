<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Draw\Path;
use Libui\Ffi;
use Libui\Generated\Enum\TableValueType;
use Libui\Table;
use Libui\TableModel;
use Libui\TableModelDelegate;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\OpenTypeFeatures;
use Libui\Text\TextLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class NativeResourceRobustnessTest extends TestCase
{
    protected function setUp(): void
    {
        Ffi::init();
    }

    protected function tearDown(): void
    {
        Ffi::uninit();
    }

    /** @return list<array{string,int,int,class-string<\Throwable>}> */
    public static function textOperations(): array
    {
        return [
            ['insert', -1, 0, \OutOfBoundsException::class],
            ['insert', 100, 0, \OutOfBoundsException::class],
            ['insert', 2, 0, \InvalidArgumentException::class],
            ['delete', 0, 100, \OutOfBoundsException::class],
            ['delete', 5, 1, \InvalidArgumentException::class],
            ['delete', 1, 2, \InvalidArgumentException::class],
            ['attribute', 0, 100, \OutOfBoundsException::class],
            ['attribute', 1, 2, \InvalidArgumentException::class],
        ];
    }

    #[DataProvider('textOperations')]
    public function testRejectedTextRangesPreserveTheNativeString(string $operation, int $start, int $end, string $exception): void
    {
        $text = new AttributedString('A😀Z');
        try {
            match ($operation) {
                'insert' => $text->insert('x', $start),
                'delete' => $text->delete_($start, $end),
                default => $text->setAttribute(Attribute::size(14), $start, $end),
            };
            $this->fail('Invalid byte range accepted');
        } catch (\Throwable $error) {
            $this->assertInstanceOf($exception, $error);
        }
        $this->assertSame('A😀Z', Ffi::get()->uiAttributedStringString($text->handle()));
    }

    public function testUnicodeEditsUseByteOffsetsAndAllowEmptyRanges(): void
    {
        $text = new AttributedString('A😀Z');
        $text->insert('👩‍💻', 5)->delete_(1, 5)->delete_(0, 0);
        $this->assertSame('A👩‍💻Z', Ffi::get()->uiAttributedStringString($text->handle()));
        $this->assertSame(strlen('A👩‍💻Z'), $text->len());
    }

    /** @return list<array{string}> */
    public static function invalidText(): array
    {
        return [["a\0b"], ["\xFF"], ["\xF0\x9F"]];
    }

    #[DataProvider('invalidText')]
    public function testInvalidInputCannotBeSilentlyTruncatedOrNormalized(string $invalid): void
    {
        $text = new AttributedString('safe');
        try {
            $text->appendUnattributed($invalid);
            $this->fail('Invalid text accepted');
        } catch (\InvalidArgumentException) {
            $this->assertSame('safe', Ffi::get()->uiAttributedStringString($text->handle()));
        }
        $this->expectException(\InvalidArgumentException::class);
        new AttributedString($invalid); // Failed constructors must also destruct safely.
    }

    public function testTransferredAttributesCannotBeReusedOrFreedTwice(): void
    {
        $text = new AttributedString('abc');
        $text->setAttribute(Attribute::size(14), 0, 1);
        $attribute = Attribute::size(14);
        $text->setAttribute($attribute, 1, 2); // Native equal-value merge consumes this pointer.
        $attribute->free(); // Ownership belongs to native text now.
        try {
            $text->setAttribute($attribute, 2, 3);
            $this->fail('Consumed native attribute was reused');
        } catch (\LogicException) {
            $this->assertSame(3, $text->len());
        }
        $text->free();
    }

    public function testUnusedAttributesAndDrawingResourcesAreReleasedBeforeUninit(): void
    {
        $attribute = Attribute::size(14);
        $text = new AttributedString('text');
        $layout = new TextLayout($text);
        $path = new Path();
        $features = new OpenTypeFeatures();
        Ffi::uninit(); // Previously leaked unused attributes and live drawing resources.
        foreach ([$attribute, $text, $layout, $path, $features] as $resource) {
            try {
                $resource->handle();
                $this->fail('A freed native handle escaped');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
            $resource->free();
        }
    }

    public function testAttributedStringCannotBeFreedUnderALiveLayout(): void
    {
        $text = new AttributedString('layout source');
        $layout = new TextLayout($text);
        try {
            $text->free();
            $this->fail('A string was freed while its native layout still used it');
        } catch (\LogicException) {
            $this->assertFalse(\FFI::isNull($layout->handle()));
        }
        $layout->free();
        $text->free();
        $this->expectException(\LogicException::class);
        $text->handle();
    }

    public function testTextLayoutSettersRebuildTheNativeLayout(): void
    {
        $text = new AttributedString(str_repeat('wrap ', 20));
        $layout = new TextLayout($text, new \Libui\Text\FontDescriptor(size: 10), width: 400);
        [$wideWidth, $wideHeight] = $layout->extents();
        $layout->setWidth(50);
        [$narrowWidth, $narrowHeight] = $layout->extents();
        $this->assertLessThan($wideWidth, $narrowWidth);
        $this->assertGreaterThan($wideHeight, $narrowHeight);

        $smallHeight = new TextLayout(new AttributedString('M'), new \Libui\Text\FontDescriptor(size: 8))->height();
        $fontLayout = new TextLayout(new AttributedString('M'), new \Libui\Text\FontDescriptor(size: 8));
        $fontLayout->setFont(new \Libui\Text\FontDescriptor(size: 32));
        $this->assertGreaterThan($smallHeight, $fontLayout->height());
    }

    /** @return list<array{string}> */
    public static function resourceKinds(): array
    {
        return [['text'], ['layout'], ['path'], ['features']];
    }

    #[DataProvider('resourceKinds')]
    public function testNativeOperationsRejectFreedResources(string $kind): void
    {
        $resource = match ($kind) {
            'text' => new AttributedString('text'),
            'layout' => new TextLayout(new AttributedString('text')),
            'path' => new Path(),
            default => new OpenTypeFeatures(),
        };
        $resource->free();
        $this->expectException(\LogicException::class);
        match ($kind) {
            'text' => $resource->len(),
            'layout' => $resource->extents(),
            'path' => $resource->addRectangle(0, 0, 10, 10),
            default => $resource->add('liga', 1),
        };
    }

    private function model(): TableModel
    {
        return new TableModel(new class extends TableModelDelegate {
            public function numColumns(): int
            {
                return 1;
            }

            public function columnType(int $column): TableValueType
            {
                return TableValueType::String;
            }

            public function numRows(): int
            {
                return 0;
            }

            public function cellValue(int $row, int $column): string
            {
                return '';
            }
        });
    }

    public function testModelCannotBeFreedBeforeTheLastTableIsDestroyed(): void
    {
        $model = $this->model();
        $first = new Table($model);
        $second = new Table($model);
        $first->destroy();
        try {
            $model->free();
            $this->fail('Model was freed under a live table');
        } catch (\LogicException) {
            $this->assertFalse(\FFI::isNull($model->handle()));
        }
        $second->destroy();
        $model->free();
        $model->free();
        $this->expectException(\LogicException::class);
        $model->rowChanged(0);
    }

    public function testShutdownDestroysTablesBeforeFreeingTheirModels(): void
    {
        $model = $this->model();
        $table = new Table($model);
        Ffi::uninit();
        $this->assertTrue($table->isDestroyed());
        $this->expectException(\LogicException::class);
        $model->handle();
    }

    public function testWindowDraggingOutsideAMouseDownCannotEnterNativeCode(): void
    {
        $area = new Area(new class extends AreaDelegate {});
        $this->expectException(\LogicException::class);
        $area->beginUserWindowMove();
    }

    public function testCapturedDrawingContextExpiresWhenTheCallbackReturns(): void
    {
        $delegate = new class extends AreaDelegate {
            public ?\Libui\Draw\DrawContext $captured = null;

            public function draw(\Libui\Draw\DrawContext $context, \Libui\Draw\Params\AreaDrawParams $params): void
            {
                $this->captured = $context;
            }
        };
        $callbackIndex = \count(\Libui\Control::retainedCallbacks());
        $area = new Area($delegate);
        $draw = \Libui\Control::retainedCallbacks()[$callbackIndex];
        $params = Ffi::new('uiAreaDrawParams');
        // The callback only retains this token; it performs no native drawing.
        $context = Ffi::get()->cast('uiDrawContext *', 1);
        // Context is the first pointer field in uiAreaDrawParams.
        \FFI::memcpy(\FFI::addr($params), \FFI::addr($context), \FFI::sizeof($context));
        $draw(null, $area->handle(), $params);
        $this->assertInstanceOf(\Libui\Draw\DrawContext::class, $delegate->captured);
        $this->expectException(\LogicException::class);
        $delegate->captured->save();
    }

    public function testPathMustBeFinalizedBeforeDrawingAndCannotBeChangedAfterward(): void
    {
        $path = new Path();
        $path->addRectangle(0, 0, 10, 10);
        try {
            $path->drawingHandle();
            $this->fail('Unfinalized path accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $path->end()->end();
        $this->assertFalse(\FFI::isNull($path->drawingHandle()));
        $this->expectException(\LogicException::class);
        $path->addRectangle(1, 1, 2, 2);
    }

    public function testResourceConstructionAndLoopOperationsRequireInitialization(): void
    {
        Ffi::uninit();
        try {
            new \Libui\Button('before init');
            $this->fail('Uninitialized native construction accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        try {
            new AttributedString('before init');
            $this->fail('Uninitialized resource allocation accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(\LogicException::class);
        Ffi::main();
    }

    public function testRandomizedUnicodeEditingPreservesNativeContents(): void
    {
        \mt_srand(481);
        $text = new AttributedString('A😀Z');
        $expected = 'A😀Z';
        $pool = ['a', 'é', '👩‍💻', '🇳🇴', "\n"];
        for ($step = 0; $step < 200; ++$step) {
            $characters = \preg_split('//u', $expected, -1, PREG_SPLIT_NO_EMPTY);
            $offsets = [0];
            foreach ($characters as $character) {
                $offsets[] = $offsets[\count($offsets) - 1] + \strlen($character);
            }
            $start = $offsets[\mt_rand(0, \count($offsets) - 1)];
            if (\mt_rand(0, 1) === 0) {
                $value = $pool[\mt_rand(0, \count($pool) - 1)];
                $text->insert($value, $start);
                $expected = \substr($expected, 0, $start) . $value . \substr($expected, $start);
            } else {
                $end = $offsets[\mt_rand(0, \count($offsets) - 1)];
                [$start, $end] = [\min($start, $end), \max($start, $end)];
                $text->delete_($start, $end);
                $expected = \substr($expected, 0, $start) . \substr($expected, $end);
            }
            $this->assertSame($expected, Ffi::get()->uiAttributedStringString($text->handle()), 'Step ' . $step);
            $this->assertSame(\strlen($expected), $text->len());
        }
    }

    /** @return list<array{string}> */
    public static function cloneKinds(): array
    {
        return [['control'], ['text'], ['path'], ['features'], ['attribute'], ['layout'], ['image'], ['model']];
    }

    #[DataProvider('cloneKinds')]
    public function testNativeOwnersCannotBeClonedIntoAliasedPointers(string $kind): void
    {
        $resource = match ($kind) {
            'control' => new \Libui\Button('clone'),
            'text' => new AttributedString('clone'),
            'path' => new Path(),
            'features' => new OpenTypeFeatures(),
            'attribute' => Attribute::size(14),
            'layout' => new TextLayout(new AttributedString('layout')),
            'image' => new \Libui\Image(1, 1),
            default => $this->model(),
        };
        $this->expectException(\Error::class);
        $this->fail('Unexpected clone: ' . (clone $resource)::class);
    }

    public function testShutdownInsideAnActiveNativeLoopIsRejected(): void
    {
        $rejected = false;
        Ffi::timer(10, static function () use (&$rejected): bool {
            try {
                Ffi::uninit();
            } catch (\LogicException) {
                $rejected = true;
            }
            Ffi::quit();
            return false;
        });
        Ffi::main();
        $this->assertTrue($rejected);
        $this->assertTrue(Ffi::isInitialized());
    }

    public function testNestedNativeMainLoopsAreRejected(): void
    {
        $rejected = false;
        Ffi::timer(10, static function () use (&$rejected): bool {
            try {
                Ffi::main();
            } catch (\LogicException) {
                $rejected = true;
            }
            Ffi::quit();
            return false;
        });
        Ffi::main();
        $this->assertTrue($rejected);
    }
}
