<?php

declare(strict_types=1);

namespace Libui\Exception;

/**
 * Thrown when a Menu is created after a Window already exists.
 *
 * libui requires every menu to be built BEFORE the first window; violating this
 * silently breaks the menu bar (and can crash). This is a programmer error, so it
 * extends LogicException.
 */
final class MenuOrderException extends \LogicException {}
