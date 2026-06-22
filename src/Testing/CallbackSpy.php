<?php

declare(strict_types=1);

namespace Libui\Testing;

/**
 * A callable test double that records every invocation.
 *
 * Pass an instance anywhere a libui event handler is expected
 * (`onClicked`, `onChanged`, `onHeaderClicked`, ...). Because it is itself
 * invokable via {@see CallbackSpy::__invoke()}, libui (through the retained
 * trampoline) calls it like any closure, and the spy records the arguments
 * and bumps its call count. Tests can then assert the handler fired without
 * running `uiMain()`.
 *
 * This is fully headless: it never touches FFI or the event loop. It only
 * observes calls that are dispatched to it. It cannot, on its own, *trigger*
 * a native event — a test must invoke the spy directly (or arrange for libui
 * to) to simulate one.
 */
final class CallbackSpy
{
    /**
     * Recorded argument lists, one entry per invocation, in call order.
     *
     * @var list<array<int, mixed>>
     */
    private array $calls = [];

    /**
     * @param callable|null $inner Optional delegate invoked after recording;
     *        its return value is propagated to the caller.
     */
    public function __construct(
        private $inner = null,
    ) {}

    /**
     * Records the invocation and forwards to the optional delegate.
     *
     * @param mixed ...$args Arguments libui (or the test) passed to the handler
     * @return mixed The delegate's return value, or null when no delegate is set
     */
    public function __invoke(mixed ...$args): mixed
    {
        $this->calls[] = $args;

        if ($this->inner !== null) {
            return ($this->inner)(...$args);
        }

        return null;
    }

    /**
     * Number of times this spy has been invoked.
     */
    public function count(): int
    {
        return \count($this->calls);
    }

    /**
     * Whether this spy has been invoked at least once.
     */
    public function called(): bool
    {
        return $this->calls !== [];
    }

    /**
     * All recorded argument lists, in call order.
     *
     * @return list<array<int, mixed>>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * The argument list captured for a single invocation.
     *
     * @param int $index Zero-based invocation index (negative indexes from the end)
     * @return array<int, mixed> The arguments passed to that invocation
     * @throws \OutOfRangeException When no invocation exists at $index
     */
    public function argsOf(int $index): array
    {
        $normalized = $index < 0 ? \count($this->calls) + $index : $index;

        if (! \array_key_exists($normalized, $this->calls)) {
            throw new \OutOfRangeException(
                \sprintf('No recorded call at index %d (recorded %d).', $index, \count($this->calls)),
            );
        }

        return $this->calls[$normalized];
    }

    /**
     * The arguments of the most recent invocation.
     *
     * @return array<int, mixed>
     * @throws \OutOfRangeException When the spy has never been invoked
     */
    public function lastArgs(): array
    {
        return $this->argsOf(-1);
    }

    /**
     * Forgets all recorded invocations.
     */
    public function reset(): void
    {
        $this->calls = [];
    }
}
