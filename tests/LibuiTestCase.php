<?php

declare(strict_types=1);

namespace Libui\Tests;

use Libui\Ffi;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that touch the live libui library.
 *
 * libui's uiInit() may run only once per process; Ffi::init() is idempotent, so
 * every test class can safely ensure initialisation here without conflicting
 * with the others that share the same PHPUnit process.
 */
abstract class LibuiTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Ffi::init();
    }
}
