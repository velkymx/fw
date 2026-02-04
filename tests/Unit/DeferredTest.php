<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fiber;
use Fw\Async\Deferred;
use Fw\Async\EventLoop;
use PHPUnit\Framework\TestCase;

final class DeferredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventLoop::reset();
    }

    protected function tearDown(): void
    {
        EventLoop::reset();
        parent::tearDown();
    }

    public function testResolveSetsFulfilledState(): void
    {
        $deferred = new Deferred();

        $this->assertFalse($deferred->isResolved());
        $this->assertFalse($deferred->isFulfilled());
        $this->assertFalse($deferred->isRejected());

        $deferred->resolve('test value');

        $this->assertTrue($deferred->isResolved());
        $this->assertTrue($deferred->isFulfilled());
        $this->assertFalse($deferred->isRejected());
        $this->assertEquals('test value', $deferred->getValue());
    }

    public function testRejectSetsRejectedState(): void
    {
        $deferred = new Deferred();
        $error = new \RuntimeException('Test error');

        $deferred->reject($error);

        $this->assertTrue($deferred->isResolved());
        $this->assertFalse($deferred->isFulfilled());
        $this->assertTrue($deferred->isRejected());
        $this->assertSame($error, $deferred->getError());
    }

    public function testResolveAfterResolveThrowsException(): void
    {
        $deferred = new Deferred();
        $deferred->resolve('first');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Deferred already resolved');

        $deferred->resolve('second');
    }

    public function testRejectAfterResolveThrowsException(): void
    {
        $deferred = new Deferred();
        $deferred->resolve('value');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Deferred already resolved');

        $deferred->reject(new \Exception('error'));
    }

    public function testAwaitReturnsImmediatelyIfAlreadyResolved(): void
    {
        $deferred = new Deferred();
        $deferred->resolve('immediate value');

        $fiber = new Fiber(function () use ($deferred) {
            return $deferred->await();
        });

        $fiber->start();

        // When Fiber completes without suspending, use getReturn() for the value
        $this->assertTrue($fiber->isTerminated());
        $this->assertEquals('immediate value', $fiber->getReturn());
    }

    public function testAwaitThrowsImmediatelyIfAlreadyRejected(): void
    {
        $deferred = new Deferred();
        $deferred->reject(new \RuntimeException('Test error'));

        $fiber = new Fiber(function () use ($deferred) {
            return $deferred->await();
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $fiber->start();
    }

    public function testAwaitOutsideFiberThrowsException(): void
    {
        $deferred = new Deferred();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot await outside of a Fiber');

        $deferred->await();
    }

    public function testAwaitSuspendsFiberUntilResolved(): void
    {
        $deferred = new Deferred();
        $result = null;

        $fiber = new Fiber(function () use ($deferred, &$result) {
            $result = $deferred->await();
        });

        $fiber->start();
        $this->assertTrue($fiber->isSuspended());
        $this->assertNull($result);

        $deferred->resolve('async value');

        // Process the deferred callback
        EventLoop::getInstance()->tick();

        $this->assertFalse($fiber->isSuspended());
        $this->assertEquals('async value', $result);
    }

    public function testAwaitThrowsWhenRejected(): void
    {
        $deferred = new Deferred();
        $caught = null;

        $fiber = new Fiber(function () use ($deferred, &$caught) {
            try {
                $deferred->await();
            } catch (\Throwable $e) {
                $caught = $e;
            }
        });

        $fiber->start();
        $deferred->reject(new \RuntimeException('Async error'));
        EventLoop::getInstance()->tick();

        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertEquals('Async error', $caught->getMessage());
    }

    public function testGetValueThrowsIfNotResolved(): void
    {
        $deferred = new Deferred();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Deferred not yet resolved');

        $deferred->getValue();
    }

    public function testGetValueThrowsIfRejected(): void
    {
        $deferred = new Deferred();
        $deferred->reject(new \RuntimeException('Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error');

        $deferred->getValue();
    }

    public function testResolvedFactoryCreatesPreResolvedDeferred(): void
    {
        $deferred = Deferred::resolved('pre-resolved');

        $this->assertTrue($deferred->isResolved());
        $this->assertTrue($deferred->isFulfilled());
        $this->assertEquals('pre-resolved', $deferred->getValue());
    }

    public function testRejectedFactoryCreatesPreRejectedDeferred(): void
    {
        $error = new \RuntimeException('Pre-rejected');
        $deferred = Deferred::rejected($error);

        $this->assertTrue($deferred->isResolved());
        $this->assertTrue($deferred->isRejected());
        $this->assertSame($error, $deferred->getError());
    }

    public function testAllAwaitsMultipleDeferreds(): void
    {
        $deferred1 = Deferred::resolved('first');
        $deferred2 = Deferred::resolved('second');
        $deferred3 = Deferred::resolved('third');

        $fiber = new Fiber(function () use ($deferred1, $deferred2, $deferred3) {
            return Deferred::all([$deferred1, $deferred2, $deferred3]);
        });

        $fiber->start();

        // When Fiber completes without suspending, use getReturn() for the value
        $this->assertTrue($fiber->isTerminated());
        $this->assertEquals(['first', 'second', 'third'], $fiber->getReturn());
    }

    public function testAllPreservesArrayKeys(): void
    {
        $deferreds = [
            'a' => Deferred::resolved('alpha'),
            'b' => Deferred::resolved('beta'),
        ];

        $fiber = new Fiber(function () use ($deferreds) {
            return Deferred::all($deferreds);
        });

        $fiber->start();

        // When Fiber completes without suspending, use getReturn() for the value
        $this->assertTrue($fiber->isTerminated());
        $this->assertEquals(['a' => 'alpha', 'b' => 'beta'], $fiber->getReturn());
    }

    public function testMultipleWaitersAllReceiveValue(): void
    {
        $deferred = new Deferred();
        $results = [];

        $fiber1 = new Fiber(function () use ($deferred, &$results) {
            $results[] = $deferred->await();
        });

        $fiber2 = new Fiber(function () use ($deferred, &$results) {
            $results[] = $deferred->await();
        });

        $fiber1->start();
        $fiber2->start();

        $this->assertTrue($fiber1->isSuspended());
        $this->assertTrue($fiber2->isSuspended());

        $deferred->resolve('shared value');
        EventLoop::getInstance()->tick();
        EventLoop::getInstance()->tick();

        $this->assertEquals(['shared value', 'shared value'], $results);
    }
}
