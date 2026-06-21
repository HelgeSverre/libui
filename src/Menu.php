<?php

declare(strict_types=1);

namespace Libui;

use Libui\Exception\MenuOrderException;

/**
 * Menu widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Menu.
 *
 * Enforces libui's "menus before the first window" rule and adds an inline
 * onClick variant on the append helpers.
 */
class Menu extends Generated\Menu
{
    public function __construct(string $name)
    {
        if (Window::menusLocked()) {
            throw new MenuOrderException(
                "Menu '{$name}' was created after a Window already exists. libui requires "
                . 'every menu to be built BEFORE the first window. Move all `new Menu(...)` '
                . 'calls above your first `new Window(...)`.',
            );
        }
        parent::__construct($name);
    }

    /** Append a clickable item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendItem(string $name, ?callable $onClick = null): MenuItem
    {
        $item = MenuItem::fromGenerated(parent::appendItem($name));
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }

    /** Append a check item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendCheckItem(string $name, ?callable $onClick = null): MenuItem
    {
        $item = MenuItem::fromGenerated(parent::appendCheckItem($name));
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }

    /**
     * The platform Quit item, as a hand-wrapped {@see MenuItem} so `onClick()` is
     * available like every other append helper.
     */
    public function appendQuitItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendQuitItem());
    }

    /** The platform Preferences item, as a hand-wrapped {@see MenuItem}. */
    public function appendPreferencesItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendPreferencesItem());
    }

    /** The platform About item, as a hand-wrapped {@see MenuItem}. */
    public function appendAboutItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendAboutItem());
    }
}
