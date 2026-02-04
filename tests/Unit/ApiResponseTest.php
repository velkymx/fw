<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Http\ApiResponse;
use Fw\Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    public function testSuccessReturnsCorrectStructure(): void
    {
        $api = new ApiResponse();

        $result = $api->success(['id' => 1, 'name' => 'Test']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('timestamp', $result['meta']);
        $this->assertEquals(['id' => 1, 'name' => 'Test'], $result['data']);
    }

    public function testSuccessDefaultStatusIs200(): void
    {
        $api = new ApiResponse();
        $api->success(['test' => true]);

        $this->assertEquals(200, $api->getStatus());
    }

    public function testSuccessWithCustomStatus(): void
    {
        $api = new ApiResponse();
        $api->success(['test' => true], 201);

        $this->assertEquals(201, $api->getStatus());
    }

    public function testMessageReturnsMessageInData(): void
    {
        $api = new ApiResponse();

        $result = $api->message('Operation completed');

        $this->assertEquals('Operation completed', $result['data']['message']);
    }

    public function testCreatedReturns201Status(): void
    {
        $api = new ApiResponse();
        $api->created(['id' => 1]);

        $this->assertEquals(201, $api->getStatus());
    }

    public function testCreatedWithLocationHeader(): void
    {
        $api = new ApiResponse();
        $api->created(['id' => 1], '/api/resources/1');

        $headers = $api->getHeaders();
        $this->assertArrayHasKey('Location', $headers);
        $this->assertEquals('/api/resources/1', $headers['Location']);
    }

    public function testNoContentReturns204Status(): void
    {
        $api = new ApiResponse();
        $api->noContent();

        $this->assertEquals(204, $api->getStatus());
    }

    public function testPaginatedReturnsCorrectStructure(): void
    {
        $api = new ApiResponse();
        $items = [['id' => 1], ['id' => 2]];

        $result = $api->paginated($items, 50, 2, 10, '/api/items');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result['meta']);
        $this->assertArrayHasKey('links', $result);

        $pagination = $result['meta']['pagination'];
        $this->assertEquals(50, $pagination['total']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(5, $pagination['total_pages']);
    }

    public function testPaginatedLinksIncludePrevAndNext(): void
    {
        $api = new ApiResponse();

        $result = $api->paginated([], 50, 3, 10, '/api/items');

        $links = $result['links'];
        $this->assertArrayHasKey('self', $links);
        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('last', $links);
        $this->assertArrayHasKey('prev', $links);
        $this->assertArrayHasKey('next', $links);
    }

    public function testPaginatedFirstPageHasNoPreview(): void
    {
        $api = new ApiResponse();

        $result = $api->paginated([], 50, 1, 10, '/api/items');

        $this->assertArrayNotHasKey('prev', $result['links']);
        $this->assertArrayHasKey('next', $result['links']);
    }

    public function testPaginatedLastPageHasNoNext(): void
    {
        $api = new ApiResponse();

        $result = $api->paginated([], 50, 5, 10, '/api/items');

        $this->assertArrayHasKey('prev', $result['links']);
        $this->assertArrayNotHasKey('next', $result['links']);
    }

    public function testErrorReturnsRFC9457Structure(): void
    {
        $api = new ApiResponse();

        $result = $api->error('Bad Request', 400, 'Invalid input');

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('detail', $result);

        $this->assertEquals('Bad Request', $result['title']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('Invalid input', $result['detail']);
    }

    public function testErrorGeneratesTypeUrl(): void
    {
        $api = new ApiResponse();

        $result = $api->error('Not Found', 404);

        $this->assertStringContainsString('/errors/not-found', $result['type']);
    }

    public function testErrorWithCustomType(): void
    {
        $api = new ApiResponse();

        $result = $api->error('Custom Error', 400, null, 'https://example.com/errors/custom');

        $this->assertEquals('https://example.com/errors/custom', $result['type']);
    }

    public function testErrorWithInstance(): void
    {
        $api = new ApiResponse();

        $result = $api->error('Not Found', 404, 'User not found', null, '/api/users/123');

        $this->assertEquals('/api/users/123', $result['instance']);
    }

    public function testErrorWithExtensions(): void
    {
        $api = new ApiResponse();

        $result = $api->error('Validation Failed', 422, 'Invalid data', null, null, [
            'errors' => ['email' => ['Invalid email format']],
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(['email' => ['Invalid email format']], $result['errors']);
    }

    public function testErrorSetsContentTypeToProblemJson(): void
    {
        $api = new ApiResponse();
        $api->error('Bad Request', 400);

        $headers = $api->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/problem+json', $headers['Content-Type']);
    }

    public function testBadRequestReturns400(): void
    {
        $api = new ApiResponse();
        $api->badRequest('Invalid input');

        $this->assertEquals(400, $api->getStatus());
    }

    public function testUnauthorizedReturns401(): void
    {
        $api = new ApiResponse();
        $api->unauthorized();

        $this->assertEquals(401, $api->getStatus());
    }

    public function testUnauthorizedSetsWwwAuthenticateHeader(): void
    {
        $api = new ApiResponse();
        $api->unauthorized();

        $headers = $api->getHeaders();
        $this->assertArrayHasKey('WWW-Authenticate', $headers);
        $this->assertEquals('Bearer', $headers['WWW-Authenticate']);
    }

    public function testForbiddenReturns403(): void
    {
        $api = new ApiResponse();
        $api->forbidden();

        $this->assertEquals(403, $api->getStatus());
    }

    public function testNotFoundReturns404(): void
    {
        $api = new ApiResponse();
        $api->notFound();

        $this->assertEquals(404, $api->getStatus());
    }

    public function testValidationErrorReturns422(): void
    {
        $api = new ApiResponse();
        $result = $api->validationError(['email' => ['Invalid email']]);

        $this->assertEquals(422, $api->getStatus());
        $this->assertArrayHasKey('errors', $result);
    }

    public function testTooManyRequestsReturns429(): void
    {
        $api = new ApiResponse();
        $api->tooManyRequests(60);

        $this->assertEquals(429, $api->getStatus());
    }

    public function testTooManyRequestsSetsRetryAfterHeader(): void
    {
        $api = new ApiResponse();
        $api->tooManyRequests(120);

        $headers = $api->getHeaders();
        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertEquals('120', $headers['Retry-After']);
    }

    public function testServerErrorReturns500(): void
    {
        $api = new ApiResponse();
        $api->serverError();

        $this->assertEquals(500, $api->getStatus());
    }

    public function testWithBaseUriAffectsTypeGeneration(): void
    {
        $api = new ApiResponse();
        $api->withBaseUri('https://api.myapp.com');

        $result = $api->error('Not Found', 404);

        $this->assertStringStartsWith('https://api.myapp.com/errors/', $result['type']);
    }

    public function testHeaderSetsResponseHeader(): void
    {
        $api = new ApiResponse();
        $api->header('X-Custom', 'value');

        $headers = $api->getHeaders();
        $this->assertArrayHasKey('X-Custom', $headers);
        $this->assertEquals('value', $headers['X-Custom']);
    }

    public function testLinkCreatesHateoasLink(): void
    {
        $link = ApiResponse::link('/api/users/1', 'self', 'GET');

        $this->assertEquals('/api/users/1', $link['href']);
        $this->assertEquals('self', $link['rel']);
        $this->assertEquals('GET', $link['method']);
    }

    public function testLinksCreatesMultipleLinks(): void
    {
        $links = ApiResponse::links([
            'self' => '/api/users/1',
            'posts' => '/api/users/1/posts',
        ]);

        $this->assertCount(2, $links);
        $this->assertEquals('self', $links[0]['rel']);
        $this->assertEquals('posts', $links[1]['rel']);
    }

    public function testMakeCreatesNewInstance(): void
    {
        $api = ApiResponse::make();

        $this->assertInstanceOf(ApiResponse::class, $api);
    }
}
