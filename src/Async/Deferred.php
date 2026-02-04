<?php

declare(strict_types=1);

namespace Fw\Async;

use Fiber;
use Throwable;

/**
 * Promise-like deferred value for async operations.
 *
 * Represents a value that will be available later. Fibers can await()
 * this value, suspending until it is resolved or rejected.
 */
final class Deferred
{
    private mixed $value = null;
    private ?Throwable $error = null;
    private bool $resolved = false;

    /** @var array<Fiber> */
    private array $waiting = [];

    /**
     * Resolve the deferred with a value.
     *
     * @throws \LogicException If already resolved
     */
    public function resolve(mixed $value): void
    {
        if ($this->resolved) {
            throw new \LogicException('Deferred already resolved');
        }

        $this->value = $value;
        $this->resolved = true;

        // Resume all waiting Fibers
        foreach ($this->waiting as $fiber) {
            EventLoop::getInstance()->defer(fn() => $fiber->resume($value));
        }

        $this->waiting = [];
    }

    /**
     * Reject the deferred with an exception.
     *
     * @throws \LogicException If already resolved
     */
    public function reject(Throwable $error): void
    {
        if ($this->resolved) {
            throw new \LogicException('Deferred already resolved');
        }

        $this->error = $error;
        $this->resolved = true;

        // Resume all waiting Fibers with exception
        foreach ($this->waiting as $fiber) {
            EventLoop::getInstance()->defer(fn() => $fiber->throw($error));
        }

        $this->waiting = [];
    }

    /**
     * Await the result (suspends current Fiber until resolved).
     *
     * @throws Throwable If the deferred was rejected
     * @throws \LogicException If called outside of a Fiber
     */
    public function await(): mixed
    {
        if ($this->resolved) {
            if ($this->error !== null) {
                throw $this->error;
            }
            return $this->value;
        }

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            throw new \LogicException('Cannot await outside of a Fiber');
        }

        $this->waiting[] = $fiber;

        return Fiber::suspend();
    }

    /**
     * Check if the deferred has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Check if the deferred was rejected with an error.
     */
    public function isRejected(): bool
    {
        return $this->resolved && $this->error !== null;
    }

    /**
     * Check if the deferred was fulfilled successfully.
     */
    public function isFulfilled(): bool
    {
        return $this->resolved && $this->error === null;
    }

    /**
     * Get the resolved value (throws if not resolved or rejected).
     *
     * @throws \LogicException If not resolved
     * @throws Throwable If rejected
     */
    public function getValue(): mixed
    {
        if (!$this->resolved) {
            throw new \LogicException('Deferred not yet resolved');
        }

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->value;
    }

    /**
     * Get the error if rejected.
     */
    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * Create a pre-resolved deferred.
     */
    public static function resolved(mixed $value): self
    {
        $deferred = new self();
        $deferred->resolve($value);
        return $deferred;
    }

    /**
     * Create a pre-rejected deferred.
     */
    public static function rejected(Throwable $error): self
    {
        $deferred = new self();
        $deferred->reject($error);
        return $deferred;
    }

    /**
     * Await all deferreds and return their values.
     *
     * @param array<Deferred> $deferreds
     * @return array<mixed>
     */
    public static function all(array $deferreds): array
    {
        $results = [];

        foreach ($deferreds as $key => $deferred) {
            $results[$key] = $deferred->await();
        }

        return $results;
    }

    /**
     * Await the first deferred to resolve.
     *
     * @param array<Deferred> $deferreds
     */
    public static function race(array $deferreds): mixed
    {
        if (empty($deferreds)) {
            throw new \InvalidArgumentException('Cannot race empty array of deferreds');
        }

        $result = new self();

        foreach ($deferreds as $deferred) {
            EventLoop::getInstance()->defer(function () use ($deferred, $result) {
                if ($result->isResolved()) {
                    return;
                }

                try {
                    if ($deferred->isResolved()) {
                        if ($deferred->isRejected()) {
                            $result->reject($deferred->getError());
                        } else {
                            $result->resolve($deferred->getValue());
                        }
                    }
                } catch (Throwable $e) {
                    if (!$result->isResolved()) {
                        $result->reject($e);
                    }
                }
            });
        }

        return $result->await();
    }
}
