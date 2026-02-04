<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Async\EventLoop;
use PHPUnit\Framework\TestCase;

final class EventLoopTest extends TestCase
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

    public function testGetInstanceReturnsSingleton(): void
    {
        $loop1 = EventLoop::getInstance();
        $loop2 = EventLoop::getInstance();

        $this->assertSame($loop1, $loop2);
    }

    public function testResetClearsSingleton(): void
    {
        $loop1 = EventLoop::getInstance();
        EventLoop::reset();
        $loop2 = EventLoop::getInstance();

        $this->assertNotSame($loop1, $loop2);
    }

    public function testDeferExecutesCallbackOnTick(): void
    {
        $loop = EventLoop::getInstance();
        $executed = false;

        $loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->assertFalse($executed);
        $this->assertEquals(1, $loop->getDeferredCount());

        $loop->tick();

        $this->assertTrue($executed);
        $this->assertEquals(0, $loop->getDeferredCount());
    }

    public function testMultipleDeferredCallbacksExecuteInOrder(): void
    {
        $loop = EventLoop::getInstance();
        $order = [];

        $loop->defer(function () use (&$order) {
            $order[] = 1;
        });
        $loop->defer(function () use (&$order) {
            $order[] = 2;
        });
        $loop->defer(function () use (&$order) {
            $order[] = 3;
        });

        $loop->tick();

        $this->assertEquals([1, 2, 3], $order);
    }

    public function testTimerFiresAfterDelay(): void
    {
        $loop = EventLoop::getInstance();
        $fired = false;

        $loop->addTimer(0.01, function () use (&$fired) {
            $fired = true;
        });

        $this->assertFalse($fired);
        $this->assertEquals(1, $loop->getTimerCount());

        // Wait for timer to expire
        usleep(15000);
        $loop->tick();

        $this->assertTrue($fired);
        $this->assertEquals(0, $loop->getTimerCount());
    }

    public function testCancelTimerPreventsExecution(): void
    {
        $loop = EventLoop::getInstance();
        $fired = false;

        $timerId = $loop->addTimer(0.01, function () use (&$fired) {
            $fired = true;
        });

        $loop->cancelTimer($timerId);

        usleep(15000);
        $loop->tick();

        $this->assertFalse($fired);
        $this->assertEquals(0, $loop->getTimerCount());
    }

    public function testRunExecutesUntilNoWork(): void
    {
        $loop = EventLoop::getInstance();
        $count = 0;

        $loop->defer(function () use ($loop, &$count) {
            $count++;
            if ($count < 3) {
                $loop->defer(function () use ($loop, &$count) {
                    $count++;
                    if ($count < 3) {
                        $loop->defer(function () use (&$count) {
                            $count++;
                        });
                    }
                });
            }
        });

        $loop->run();

        $this->assertEquals(3, $count);
    }

    public function testStopHaltsEventLoop(): void
    {
        $loop = EventLoop::getInstance();
        $count = 0;

        $loop->defer(function () use ($loop, &$count) {
            $count++;
            $loop->defer(function () use ($loop, &$count) {
                $count++;
                $loop->stop();
                $loop->defer(function () use (&$count) {
                    $count++; // Should not execute
                });
            });
        });

        $loop->run();

        $this->assertEquals(2, $count);
    }

    public function testSuspendOutsideFiberThrowsException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot suspend outside of a Fiber');

        EventLoop::suspend();
    }

    public function testIsRunningReturnsTrueWhileRunning(): void
    {
        $loop = EventLoop::getInstance();
        $wasRunning = false;

        $loop->defer(function () use ($loop, &$wasRunning) {
            $wasRunning = $loop->isRunning();
        });

        $this->assertFalse($loop->isRunning());
        $loop->run();
        $this->assertTrue($wasRunning);
        $this->assertFalse($loop->isRunning());
    }
}
