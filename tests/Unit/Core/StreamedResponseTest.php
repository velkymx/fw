<?php

declare(strict_types=1);

namespace Fw\Tests\Unit\Core;

use Fw\Core\StreamedResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StreamedResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsDefaults(): void
    {
        $response = new StreamedResponse(fn() => null);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Type', $response->getHeaders());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function constructorAcceptsCustomStatusCode(): void
    {
        $response = new StreamedResponse(fn() => null, 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function constructorAcceptsCustomHeaders(): void
    {
        $response = new StreamedResponse(fn() => null, 200, [
            'X-Custom-Header' => 'value',
        ]);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertSame('value', $headers['X-Custom-Header']);
    }

    #[Test]
    public function customHeadersOverrideDefaults(): void
    {
        $response = new StreamedResponse(fn() => null, 200, [
            'Content-Type' => 'application/json',
        ]);

        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function includesNginxBufferingHeader(): void
    {
        $response = new StreamedResponse(fn() => null);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Accel-Buffering', $headers);
        $this->assertSame('no', $headers['X-Accel-Buffering']);
    }

    #[Test]
    public function isStreamedReturnsTrue(): void
    {
        $response = new StreamedResponse(fn() => null);

        $this->assertTrue($response->isStreamed());
    }

    #[Test]
    public function callbackIsStoredAndCanBeAccessed(): void
    {
        $executed = false;
        $callback = function() use (&$executed) {
            $executed = true;
            echo 'output';
        };

        // Create response but don't send it
        $response = new StreamedResponse($callback);

        // Just verify it was created correctly
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($executed); // Callback not executed until send()
    }
}
