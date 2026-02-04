<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use DateTimeImmutable;
use Fw\Events\DomainEvent;
use Fw\Events\Event;
use Fw\Events\EventDispatcher;
use Fw\Events\EventSubscriber;
use PHPUnit\Framework\TestCase;

// Test events
final readonly class TestUserCreated extends DomainEvent
{
    public function __construct(
        public string $userId,
        public string $email
    ) {
        parent::__construct();
    }
}

final readonly class TestUserDeleted extends DomainEvent
{
    public function __construct(
        public string $userId
    ) {
        parent::__construct();
    }
}

final readonly class TestOrderPlaced extends DomainEvent
{
    public function __construct(
        public string $orderId
    ) {
        parent::__construct();
    }
}

// Test listener class
final class TestUserListener
{
    public array $handled = [];

    public function handle(TestUserCreated $event): void
    {
        $this->handled[] = $event;
    }
}

// Test subscriber
final class TestUserSubscriber implements EventSubscriber
{
    public array $createdEvents = [];
    public array $deletedEvents = [];

    public function subscribe(EventDispatcher $dispatcher): void
    {
        $dispatcher->listen(TestUserCreated::class, [$this, 'onUserCreated']);
        $dispatcher->listen(TestUserDeleted::class, [$this, 'onUserDeleted']);
    }

    public function onUserCreated(TestUserCreated $event): void
    {
        $this->createdEvents[] = $event;
    }

    public function onUserDeleted(TestUserDeleted $event): void
    {
        $this->deletedEvents[] = $event;
    }
}

final class EventDispatcherTest extends TestCase
{
    // ==========================================
    // DOMAIN EVENT TESTS
    // ==========================================

    public function testDomainEventHasEventId(): void
    {
        $event = new TestUserCreated('user-1', 'john@example.com');

        $this->assertNotEmpty($event->eventId);
        $this->assertEquals(36, strlen($event->eventId)); // UUID format
    }

    public function testDomainEventHasOccurredAt(): void
    {
        $before = new DateTimeImmutable();
        $event = new TestUserCreated('user-1', 'john@example.com');
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredAt());
        $this->assertLessThanOrEqual($after, $event->occurredAt());
    }

    public function testDomainEventName(): void
    {
        $event = new TestUserCreated('user-1', 'john@example.com');

        $this->assertEquals('TestUserCreated', $event->eventName());
    }

    // ==========================================
    // LISTENER TESTS
    // ==========================================

    public function testListenWithCallable(): void
    {
        $dispatcher = new EventDispatcher();
        $handled = [];

        $dispatcher->listen(TestUserCreated::class, function (TestUserCreated $event) use (&$handled) {
            $handled[] = $event;
        });

        $event = new TestUserCreated('user-1', 'john@example.com');
        $dispatcher->dispatch($event);

        $this->assertCount(1, $handled);
        $this->assertSame($event, $handled[0]);
    }

    public function testListenWithListenerClass(): void
    {
        $listener = new TestUserListener();
        $dispatcher = new EventDispatcher(fn($class) => $listener);

        $dispatcher->listen(TestUserCreated::class, TestUserListener::class);

        $event = new TestUserCreated('user-1', 'john@example.com');
        $dispatcher->dispatch($event);

        $this->assertCount(1, $listener->handled);
        $this->assertSame($event, $listener->handled[0]);
    }

    public function testListenWithListenerInstance(): void
    {
        $listener = new TestUserListener();
        $dispatcher = new EventDispatcher();

        $dispatcher->listen(TestUserCreated::class, $listener);

        $event = new TestUserCreated('user-1', 'john@example.com');
        $dispatcher->dispatch($event);

        $this->assertCount(1, $listener->handled);
    }

    public function testMultipleListenersForSameEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $log = [];

        $dispatcher->listen(TestUserCreated::class, function () use (&$log) {
            $log[] = 'first';
        });

        $dispatcher->listen(TestUserCreated::class, function () use (&$log) {
            $log[] = 'second';
        });

        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));

        $this->assertEquals(['first', 'second'], $log);
    }

    public function testDispatchToCorrectListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $userCreated = [];
        $orderPlaced = [];

        $dispatcher->listen(TestUserCreated::class, function ($e) use (&$userCreated) {
            $userCreated[] = $e;
        });

        $dispatcher->listen(TestOrderPlaced::class, function ($e) use (&$orderPlaced) {
            $orderPlaced[] = $e;
        });

        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));
        $dispatcher->dispatch(new TestOrderPlaced('order-1'));

        $this->assertCount(1, $userCreated);
        $this->assertCount(1, $orderPlaced);
    }

    // ==========================================
    // WILDCARD LISTENER TESTS
    // ==========================================

    public function testWildcardListener(): void
    {
        $dispatcher = new EventDispatcher();
        $handled = [];

        $dispatcher->listen('Fw\Tests\Unit\TestUser*', function ($event) use (&$handled) {
            $handled[] = $event;
        });

        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));
        $dispatcher->dispatch(new TestUserDeleted('user-1'));
        $dispatcher->dispatch(new TestOrderPlaced('order-1')); // Should not match

        $this->assertCount(2, $handled);
        $this->assertInstanceOf(TestUserCreated::class, $handled[0]);
        $this->assertInstanceOf(TestUserDeleted::class, $handled[1]);
    }

    // ==========================================
    // SUBSCRIBER TESTS
    // ==========================================

    public function testSubscribe(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new TestUserSubscriber();

        $dispatcher->subscribe($subscriber);

        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));
        $dispatcher->dispatch(new TestUserDeleted('user-1'));

        $this->assertCount(1, $subscriber->createdEvents);
        $this->assertCount(1, $subscriber->deletedEvents);
    }

    public function testSubscribeWithClassName(): void
    {
        $subscriber = new TestUserSubscriber();
        $dispatcher = new EventDispatcher(fn($class) => $subscriber);

        $dispatcher->subscribe(TestUserSubscriber::class);

        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));

        $this->assertCount(1, $subscriber->createdEvents);
    }

    // ==========================================
    // DISPATCH ALL TESTS
    // ==========================================

    public function testDispatchAll(): void
    {
        $dispatcher = new EventDispatcher();
        $handled = [];

        $dispatcher->listen(TestUserCreated::class, function ($e) use (&$handled) {
            $handled[] = $e;
        });

        $events = [
            new TestUserCreated('user-1', 'john@example.com'),
            new TestUserCreated('user-2', 'jane@example.com'),
        ];

        $dispatcher->dispatchAll($events);

        $this->assertCount(2, $handled);
    }

    // ==========================================
    // HAS LISTENERS TESTS
    // ==========================================

    public function testHasListeners(): void
    {
        $dispatcher = new EventDispatcher();

        $this->assertFalse($dispatcher->hasListeners(TestUserCreated::class));

        $dispatcher->listen(TestUserCreated::class, fn() => null);

        $this->assertTrue($dispatcher->hasListeners(TestUserCreated::class));
        $this->assertFalse($dispatcher->hasListeners(TestOrderPlaced::class));
    }

    public function testHasListenersWithWildcard(): void
    {
        $dispatcher = new EventDispatcher();

        $dispatcher->listen('Fw\Tests\Unit\TestUser*', fn() => null);

        $this->assertTrue($dispatcher->hasListeners(TestUserCreated::class));
        $this->assertTrue($dispatcher->hasListeners(TestUserDeleted::class));
        $this->assertFalse($dispatcher->hasListeners(TestOrderPlaced::class));
    }

    // ==========================================
    // FORGET TESTS
    // ==========================================

    public function testForgetRemovesListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $handled = [];

        $dispatcher->listen(TestUserCreated::class, function () use (&$handled) {
            $handled[] = 'called';
        });

        $dispatcher->forget(TestUserCreated::class);
        $dispatcher->dispatch(new TestUserCreated('user-1', 'john@example.com'));

        $this->assertEmpty($handled);
    }

    public function testForgetAllRemovesAllListeners(): void
    {
        $dispatcher = new EventDispatcher();

        $dispatcher->listen(TestUserCreated::class, fn() => null);
        $dispatcher->listen(TestOrderPlaced::class, fn() => null);

        $dispatcher->forget();

        $this->assertFalse($dispatcher->hasListeners(TestUserCreated::class));
        $this->assertFalse($dispatcher->hasListeners(TestOrderPlaced::class));
    }

    // ==========================================
    // GET LISTENERS TESTS
    // ==========================================

    public function testGetListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $callback1 = fn() => null;
        $callback2 = fn() => null;

        $dispatcher->listen(TestUserCreated::class, $callback1);
        $dispatcher->listen(TestUserCreated::class, $callback2);

        $listeners = $dispatcher->getListeners();

        $this->assertArrayHasKey(TestUserCreated::class, $listeners);
        $this->assertCount(2, $listeners[TestUserCreated::class]);
    }

    // ==========================================
    // FLUENT INTERFACE TESTS
    // ==========================================

    public function testListenReturnsFluentInterface(): void
    {
        $dispatcher = new EventDispatcher();

        $result = $dispatcher->listen(TestUserCreated::class, fn() => null);

        $this->assertSame($dispatcher, $result);
    }

    public function testSubscribeReturnsFluentInterface(): void
    {
        $dispatcher = new EventDispatcher();

        $result = $dispatcher->subscribe(new TestUserSubscriber());

        $this->assertSame($dispatcher, $result);
    }

    public function testForgetReturnsFluentInterface(): void
    {
        $dispatcher = new EventDispatcher();

        $result = $dispatcher->forget();

        $this->assertSame($dispatcher, $result);
    }
}
