<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Async\Deferred;
use Fw\Async\EventLoop;
use Fw\Core\Request;
use Fw\Lifecycle\Component;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventLoop::reset();

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
        parent::tearDown();
    }

    private function createMockComponent(): TestComponent
    {
        $app = new ApplicationStub();
        $request = new Request();

        return new TestComponent($app, $request);
    }

    public function testSetAndGetData(): void
    {
        $component = $this->createMockComponent();

        $component->publicSet('name', 'John');
        $component->publicSet('age', 30);

        $this->assertEquals('John', $component->publicGet('name'));
        $this->assertEquals(30, $component->publicGet('age'));
        $this->assertNull($component->publicGet('nonexistent'));
        $this->assertEquals('default', $component->publicGet('nonexistent', 'default'));
    }

    public function testHasData(): void
    {
        $component = $this->createMockComponent();

        $component->publicSet('exists', 'value');
        $component->publicSet('null_value', null);

        $this->assertTrue($component->publicHas('exists'));
        $this->assertTrue($component->publicHas('null_value')); // array_key_exists
        $this->assertFalse($component->publicHas('not_exists'));
    }

    public function testAllAndClearData(): void
    {
        $component = $this->createMockComponent();

        $component->publicSet('a', 1);
        $component->publicSet('b', 2);

        $this->assertEquals(['a' => 1, 'b' => 2], $component->publicAll());

        $component->publicClear();

        $this->assertEquals([], $component->publicAll());
    }

    public function testMergeData(): void
    {
        $component = $this->createMockComponent();

        $component->publicSet('a', 1);
        $component->publicMerge(['b' => 2, 'c' => 3]);

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $component->publicAll());
    }

    public function testSetAndGetParams(): void
    {
        $component = $this->createMockComponent();

        $component->setParams(['id' => '123', 'slug' => 'test-post']);

        $this->assertEquals('123', $component->param('id'));
        $this->assertEquals('test-post', $component->param('slug'));
        $this->assertNull($component->param('missing'));
        $this->assertEquals('default', $component->param('missing', 'default'));
    }

    public function testErrorHandling(): void
    {
        $component = $this->createMockComponent();

        $this->assertFalse($component->hasErrors());
        $this->assertEquals([], $component->getErrors());

        $error1 = new \RuntimeException('First error');
        $error2 = new \InvalidArgumentException('Second error');

        $component->error($error1);
        $component->error($error2);

        $this->assertTrue($component->hasErrors());
        $this->assertCount(2, $component->getErrors());
        $this->assertSame($error1, $component->getErrors()[0]);
        $this->assertSame($error2, $component->getErrors()[1]);
    }

    public function testLifecycleHooksAreCalled(): void
    {
        $component = $this->createMockComponent();

        $component->booting();
        $this->assertTrue($component->bootingCalled);

        $component->booted();
        $this->assertTrue($component->bootedCalled);

        $component->beforeRequest();
        $this->assertTrue($component->beforeRequestCalled);

        $component->afterRequest();
        $this->assertTrue($component->afterRequestCalled);

        $component->beforeFetch();
        $this->assertTrue($component->beforeFetchCalled);

        $component->fetch();
        $this->assertTrue($component->fetchCalled);

        $component->afterFetch();
        $this->assertTrue($component->afterFetchCalled);

        $component->beforeResponse();
        $this->assertTrue($component->beforeResponseCalled);

        $component->afterResponse();
        $this->assertTrue($component->afterResponseCalled);
    }

    public function testRenderReturnsString(): void
    {
        $component = $this->createMockComponent();
        $component->publicSet('content', 'Hello World');

        $output = $component->render();

        $this->assertEquals('Rendered: Hello World', $output);
    }
}

/**
 * Stub Application for testing (avoids final class mocking issue)
 */
class ApplicationStub
{
    public ?object $db = null;
    public ?object $view = null;
    public ?object $response = null;
    public ?object $log = null;
}

/**
 * Test implementation of Component
 */
class TestComponent extends Component
{
    public bool $bootingCalled = false;
    public bool $bootedCalled = false;
    public bool $beforeRequestCalled = false;
    public bool $afterRequestCalled = false;
    public bool $beforeFetchCalled = false;
    public bool $fetchCalled = false;
    public bool $afterFetchCalled = false;
    public bool $beforeResponseCalled = false;
    public bool $afterResponseCalled = false;

    public function booting(): void
    {
        $this->bootingCalled = true;
    }

    public function booted(): void
    {
        $this->bootedCalled = true;
    }

    public function beforeRequest(): void
    {
        $this->beforeRequestCalled = true;
    }

    public function afterRequest(): void
    {
        $this->afterRequestCalled = true;
    }

    public function beforeFetch(): void
    {
        $this->beforeFetchCalled = true;
    }

    public function fetch(): void
    {
        $this->fetchCalled = true;
    }

    public function afterFetch(): void
    {
        $this->afterFetchCalled = true;
    }

    public function beforeResponse(): void
    {
        $this->beforeResponseCalled = true;
    }

    public function afterResponse(): void
    {
        $this->afterResponseCalled = true;
    }

    public function render(): string
    {
        return 'Rendered: ' . ($this->get('content') ?? '');
    }

    // Public accessors for testing protected methods
    public function publicSet(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function publicGet(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function publicHas(string $key): bool
    {
        return $this->has($key);
    }

    public function publicAll(): array
    {
        return $this->all();
    }

    public function publicClear(): void
    {
        $this->clear();
    }

    public function publicMerge(array $data): void
    {
        $this->merge($data);
    }
}
