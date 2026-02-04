<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Async\AsyncDatabase;
use Fw\Async\Deferred;
use Fw\Async\EventLoop;
use Fw\Core\Request;
use Fw\Database\Connection;
use Fw\Lifecycle\Component;
use Fw\Lifecycle\Hook;
use Fw\Lifecycle\RequestFiber;
use PHPUnit\Framework\TestCase;

/**
 * Stub Application for lifecycle integration tests
 */
class LifecycleAppStub
{
    public ?object $db = null;
    public ?object $view = null;
    public LifecycleResponseStub $response;
    public LifecycleLoggerStub $log;

    public function __construct()
    {
        $this->response = new LifecycleResponseStub();
        $this->log = new LifecycleLoggerStub();
    }
}

class LifecycleResponseStub
{
    public function setHeader(string $name, string $value): self
    {
        return $this;
    }
}

class LifecycleLoggerStub
{
    public function error(string $message, array $context = []): void
    {
    }

    public function info(string $message, array $context = []): void
    {
    }
}

final class LifecycleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventLoop::reset();
        Connection::reset();

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'HTTP_HOST' => 'localhost',
        ];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        EventLoop::reset();
        Connection::reset();
        parent::tearDown();
    }

    private function createMockApp(): LifecycleAppStub
    {
        return new LifecycleAppStub();
    }

    public function testHookEnumInOrder(): void
    {
        $expectedOrder = [
            Hook::BOOTING,
            Hook::BOOTED,
            Hook::BEFORE_REQUEST,
            Hook::AFTER_REQUEST,
            Hook::BEFORE_FETCH,
            Hook::FETCH,
            Hook::AFTER_FETCH,
            Hook::BEFORE_RESPONSE,
            Hook::AFTER_RESPONSE,
        ];

        $this->assertEquals($expectedOrder, Hook::inOrder());
    }

    public function testHookProperties(): void
    {
        $this->assertTrue(Hook::BOOTING->isInitializationHook());
        $this->assertTrue(Hook::BOOTED->isInitializationHook());
        $this->assertFalse(Hook::BEFORE_REQUEST->isInitializationHook());

        $this->assertTrue(Hook::BEFORE_REQUEST->isRequestHook());
        $this->assertTrue(Hook::AFTER_REQUEST->isRequestHook());
        $this->assertFalse(Hook::FETCH->isRequestHook());

        $this->assertTrue(Hook::BEFORE_FETCH->isDataHook());
        $this->assertTrue(Hook::FETCH->isDataHook());
        $this->assertTrue(Hook::AFTER_FETCH->isDataHook());
        $this->assertFalse(Hook::BEFORE_RESPONSE->isDataHook());

        $this->assertTrue(Hook::BEFORE_RESPONSE->isResponseHook());
        $this->assertTrue(Hook::AFTER_RESPONSE->isResponseHook());
        $this->assertFalse(Hook::FETCH->isResponseHook());

        $this->assertTrue(Hook::FETCH->canSuspend());
        $this->assertFalse(Hook::BOOTING->canSuspend());
    }

    public function testFullLifecycleWithAsyncDataFetching(): void
    {
        $app = $this->createMockApp();
        $request = new Request();
        $loop = EventLoop::getInstance();

        // Create deferreds to simulate async data fetching
        $userDeferred = new Deferred();
        $postsDeferred = new Deferred();

        $component = new DataFetchingComponent($app, $request, $userDeferred, $postsDeferred);
        $fiber = new RequestFiber($app, $request, $component);

        // Start the fiber
        $fiber->start();

        // Fiber should be suspended waiting for data
        $this->assertTrue($fiber->isSuspended());
        $this->assertFalse($fiber->isCompleted());

        // Simulate async data resolution
        $userDeferred->resolve(['id' => 1, 'name' => 'John']);
        $loop->tick();

        // Still suspended waiting for posts
        $this->assertTrue($fiber->isSuspended());

        $postsDeferred->resolve([
            ['id' => 1, 'title' => 'First Post'],
            ['id' => 2, 'title' => 'Second Post'],
        ]);
        $loop->tick();

        // Now should be completed
        $this->assertFalse($fiber->isSuspended());
        $this->assertTrue($fiber->isCompleted());
        $this->assertNull($fiber->getError());

        // Verify output
        $output = $fiber->getOutput();
        $this->assertStringContainsString('User: John', $output);
        $this->assertStringContainsString('Posts: 2', $output);
    }

    public function testConcurrentDeferredResolution(): void
    {
        $app = $this->createMockApp();
        $request = new Request();
        $loop = EventLoop::getInstance();

        // Create multiple deferreds
        $deferreds = [];
        for ($i = 0; $i < 5; $i++) {
            $deferreds[] = new Deferred();
        }

        $component = new MultipleDeferredComponent($app, $request, $deferreds);
        $fiber = new RequestFiber($app, $request, $component);

        $fiber->start();

        // Resolve all deferreds
        foreach ($deferreds as $i => $deferred) {
            $deferred->resolve("result_$i");
        }

        // Process all resolutions
        for ($i = 0; $i < count($deferreds); $i++) {
            $loop->tick();
        }

        $this->assertTrue($fiber->isCompleted());
        $this->assertEquals('Results: result_0,result_1,result_2,result_3,result_4', $fiber->getOutput());
    }

    public function testErrorDuringAsyncFetch(): void
    {
        $app = $this->createMockApp();
        $request = new Request();
        $loop = EventLoop::getInstance();

        $deferred = new Deferred();
        $component = new FailingAsyncComponent($app, $request, $deferred);
        $fiber = new RequestFiber($app, $request, $component);

        $fiber->start();
        $this->assertTrue($fiber->isSuspended());

        // Reject the deferred
        $deferred->reject(new \RuntimeException('Database connection failed'));
        $loop->tick();

        $this->assertTrue($fiber->isCompleted());
        $this->assertNotNull($fiber->getError());
        $this->assertEquals('Database connection failed', $fiber->getError()->getMessage());
        $this->assertTrue($component->errorHandled);
        $this->assertTrue($component->cleanupCalled);
    }

    public function testAsyncDatabaseWrapper(): void
    {
        // Create in-memory SQLite database
        $db = Connection::getInstance([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Create test table
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $db->insert('users', ['name' => 'Alice']);
        $db->insert('users', ['name' => 'Bob']);

        $asyncDb = new AsyncDatabase($db);
        $loop = EventLoop::getInstance();

        $result = null;

        $fiber = new \Fiber(function () use ($asyncDb, &$result) {
            $deferred = $asyncDb->fetchAll('SELECT * FROM users ORDER BY name');
            $result = $deferred->await();
        });

        $fiber->start();

        // Fiber suspends waiting for deferred
        $this->assertTrue($fiber->isSuspended());

        // Process the deferred callback
        $loop->tick();

        // Now the deferred resolves and resumes the fiber
        $loop->tick();

        // Result should now be set
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testEventLoopWithMultipleFibers(): void
    {
        $loop = EventLoop::getInstance();
        $results = [];

        $fiber1 = new \Fiber(function () use ($loop, &$results) {
            $deferred = new Deferred();
            $loop->defer(fn() => $deferred->resolve('fiber1'));
            $results[] = $deferred->await();
        });

        $fiber2 = new \Fiber(function () use ($loop, &$results) {
            $deferred = new Deferred();
            $loop->defer(fn() => $deferred->resolve('fiber2'));
            $results[] = $deferred->await();
        });

        $fiber1->start();
        $fiber2->start();

        $this->assertTrue($fiber1->isSuspended());
        $this->assertTrue($fiber2->isSuspended());

        // Process deferred callbacks
        $loop->tick();
        $loop->tick();

        $this->assertFalse($fiber1->isSuspended());
        $this->assertFalse($fiber2->isSuspended());
        $this->assertContains('fiber1', $results);
        $this->assertContains('fiber2', $results);
    }
}

/**
 * Component that fetches data asynchronously
 */
class DataFetchingComponent extends Component
{
    private Deferred $userDeferred;
    private Deferred $postsDeferred;
    private ?array $user = null;
    private array $posts = [];

    public function __construct(object $app, Request $request, Deferred $userDeferred, Deferred $postsDeferred)
    {
        parent::__construct($app, $request);
        $this->userDeferred = $userDeferred;
        $this->postsDeferred = $postsDeferred;
    }

    public function fetch(): void
    {
        $this->user = $this->await($this->userDeferred);
        $this->posts = $this->await($this->postsDeferred);
    }

    public function render(): string
    {
        return sprintf(
            'User: %s, Posts: %d',
            $this->user['name'] ?? 'unknown',
            count($this->posts)
        );
    }
}

/**
 * Component that awaits multiple deferreds
 */
class MultipleDeferredComponent extends Component
{
    /** @var Deferred[] */
    private array $deferreds;
    private array $results = [];

    public function __construct(object $app, Request $request, array $deferreds)
    {
        parent::__construct($app, $request);
        $this->deferreds = $deferreds;
    }

    public function fetch(): void
    {
        foreach ($this->deferreds as $deferred) {
            $this->results[] = $this->await($deferred);
        }
    }

    public function render(): string
    {
        return 'Results: ' . implode(',', $this->results);
    }
}

/**
 * Component that handles errors during async fetch
 */
class FailingAsyncComponent extends Component
{
    private Deferred $deferred;
    public bool $errorHandled = false;
    public bool $cleanupCalled = false;

    public function __construct(object $app, Request $request, Deferred $deferred)
    {
        parent::__construct($app, $request);
        $this->deferred = $deferred;
    }

    public function fetch(): void
    {
        $this->await($this->deferred);
    }

    public function error(\Throwable $e): void
    {
        $this->errorHandled = true;
        parent::error($e);
    }

    public function afterResponse(): void
    {
        $this->cleanupCalled = true;
    }

    public function render(): string
    {
        return 'Should not render';
    }
}
