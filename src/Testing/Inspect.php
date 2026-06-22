<?php

declare(strict_types=1);

namespace Libui\Testing;

use Libui\Control;

/**
 * Read-only introspection over what libui actually exposes headlessly.
 *
 * These helpers wrap the public `uiControl` verbs already on {@see Control}
 * so tests read intent-revealing assertions (`Inspect::isVisible($w)`) instead
 * of the raw getters.
 *
 * What this CAN observe without a shown window / running event loop:
 *  - A control's enabled flag ({@see Control::enabled()}).
 *  - A control's visibility flag ({@see Control::visible()}) — note libui
 *    reports the control's own show/hide state; a control may report visible
 *    while its toplevel window is not on screen.
 *  - Whether a control is a toplevel ({@see Control::toplevel()}).
 *  - That a registration call retained a trampoline, via the delta helper
 *    {@see Inspect::callbacksRegisteredBy()}.
 *
 * What this CANNOT do (libui exposes no C API for it, so the harness will not
 * fake it): enumerate a container's children, map a retained callback back to
 * the widget/signal that registered it, read rendered geometry, or fire native
 * events. For event assertions, register a {@see CallbackSpy} and invoke it.
 */
final class Inspect
{
    /**
     * Whether the control's own visibility flag is set.
     */
    public static function isVisible(Control $control): bool
    {
        return $control->visible();
    }

    /**
     * Whether the control is enabled for user interaction.
     */
    public static function isEnabled(Control $control): bool
    {
        return $control->enabled();
    }

    /**
     * Whether the control is a toplevel widget (e.g. a Window).
     */
    public static function isToplevel(Control $control): bool
    {
        return $control->toplevel();
    }

    /**
     * Count the event trampolines retained while running $register.
     *
     * libui offers no per-widget callback list — {@see Control::keep()} appends
     * to one process-global list — so an absolute count is meaningless. This
     * measures the delta around a registration call instead, which is the only
     * sound use: assert that wiring up a handler retained a trampoline, e.g.
     *
     *     $n = Inspect::callbacksRegisteredBy(fn () => $button->onClicked($spy));
     *     $this->assertSame(1, $n);
     */
    public static function callbacksRegisteredBy(callable $register): int
    {
        $before = \count(Control::retainedCallbacks());
        $register();

        return \count(Control::retainedCallbacks()) - $before;
    }
}
