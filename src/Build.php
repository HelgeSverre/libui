<?php

declare(strict_types=1);

namespace Libui;

/**
 * Declarative, fluent constructors for the common container widgets. Layers over
 * the existing public APIs of {@see Box}, {@see Form} and {@see Window} to cut the
 * positional append() boilerplate of an imperative layout.
 *
 * Example:
 *
 *     use Libui\Build;
 *
 *     $form = Build::form([
 *         'Name'  => new Entry(),
 *         'Email' => new Entry(),
 *     ]);
 *
 *     $layout = Build::vbox(
 *         new Label('Welcome'),
 *         $form,
 *         Build::stretchy(new MultilineEntry()), // grows to fill the box
 *     );
 *
 *     $window = Build::window('Demo', 640, 480, $layout);
 *     $window->run();
 *
 * All children of a box may be passed as a bare {@see Control} (non-stretchy) or
 * wrapped to mark them stretchy via {@see Build::stretchy()}.
 */
final class Build
{
    /**
     * Marks a control as stretchy for {@see Build::vbox()} / {@see Build::hbox()}.
     * The returned shape is an internal marker consumed by those methods — treat
     * it as opaque.
     *
     * @return array{stretchy: true, control: Control}
     */
    public static function stretchy(Control $control): array
    {
        return ['stretchy' => true, 'control' => $control];
    }

    /**
     * Padded vertical box with each child appended in order. A child is either a
     * bare {@see Control} (non-stretchy) or a {@see Build::stretchy()} marker.
     * Padding is on (the sensible layout default); for an unpadded box use
     * `new Box(false)` directly.
     *
     * @param Control|array{stretchy: true, control: Control} ...$children
     */
    public static function vbox(Control|array ...$children): Box
    {
        return self::fill(new Box(true), $children);
    }

    /**
     * Padded horizontal box with each child appended in order. A child is either
     * a bare {@see Control} (non-stretchy) or a {@see Build::stretchy()} marker.
     * For an unpadded box use `Box::horizontal(false)` directly.
     *
     * @param Control|array{stretchy: true, control: Control} ...$children
     */
    public static function hbox(Control|array ...$children): Box
    {
        return self::fill(Box::horizontal(true), $children);
    }

    /**
     * Labelled form built from a `title => control` map, in iteration order.
     *
     * @param array<string, Control> $fields
     */
    public static function form(array $fields): Form
    {
        $form = new Form();
        $form->setPadded(true);
        foreach ($fields as $title => $control) {
            $form->append($title, $control);
        }

        return $form;
    }

    /**
     * New top-level window with its single child set. Margins are on by default.
     */
    public static function window(
        string $title,
        int $width,
        int $height,
        Control $child,
        bool $margined = true,
    ): Window {
        $window = new Window($title, $width, $height);
        $window->setMargined($margined);
        $window->setChild($child);

        return $window;
    }

    /**
     * Append each child to $box, honouring the {@see Build::stretchy()} marker.
     *
     * @param array<int, Control|array{stretchy: true, control: Control}> $children
     */
    private static function fill(Box $box, array $children): Box
    {
        foreach ($children as $child) {
            if ($child instanceof Control) {
                $box->append($child);
            } else {
                $box->appendStretchy($child['control']);
            }
        }

        return $box;
    }
}
