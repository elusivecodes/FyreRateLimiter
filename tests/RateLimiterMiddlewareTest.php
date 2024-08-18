<?php
declare(strict_types=1);

namespace Tests;

use Fyre\Cache\Cache;
use Fyre\Cache\Handlers\FileCacher;
use Fyre\Middleware\MiddlewareQueue;
use Fyre\Middleware\RequestHandler;
use Fyre\Security\Middleware\RateLimiterMiddleware;
use Fyre\Server\ClientResponse;
use Fyre\Server\ServerRequest;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function time;

final class RateLimiterMiddlewareTest extends TestCase
{
    public function testCheckRateLimit(): void
    {
        $middleware = new RateLimiterMiddleware([
            'limit' => 10,
            'period' => 10,
        ]);

        $queue = new MiddlewareQueue();
        $queue->add($middleware);

        $handler = new RequestHandler($queue);
        $request = new ServerRequest([
            'globals' => [
                'server' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
            ],
        ]);

        $response = $handler->handle($request);

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-RateLimit-Limit')
        );

        $this->assertSame(
            '9',
            $response->getHeaderValue('X-RateLimit-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-RateLimit-Reset')
        );
    }

    public function testError(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                        'HTTP_ACCEPT' => 'text/html',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            429,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-RateLimit-Limit')
        );

        $this->assertSame(
            '0',
            $response->getHeaderValue('X-RateLimit-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-RateLimit-Reset')
        );

        $this->assertSame(
            'Rate limit exceeded',
            $response->getBody()
        );
    }

    public function testErrorJson(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                        'HTTP_ACCEPT' => 'application/json',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            429,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-RateLimit-Limit')
        );

        $this->assertSame(
            '0',
            $response->getHeaderValue('X-RateLimit-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-RateLimit-Reset')
        );

        $this->assertSame(
            [
                'message' => 'Rate limit exceeded',
            ],
            json_decode($response->getBody(), true)
        );
    }

    public function testErrorMessage(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
                'message' => 'Too many requests',
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                        'HTTP_ACCEPT' => 'text/html',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            429,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-RateLimit-Limit')
        );

        $this->assertSame(
            '0',
            $response->getHeaderValue('X-RateLimit-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-RateLimit-Reset')
        );

        $this->assertSame(
            'Too many requests',
            $response->getBody()
        );
    }

    public function testErrorResponse(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
                'errorResponse' => fn(ServerRequest $request, ClientResponse $response): ClientResponse => $response->setBody('<h1>Too many requests</h1>'),
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                        'HTTP_ACCEPT' => 'text/html',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            429,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-RateLimit-Limit')
        );

        $this->assertSame(
            '0',
            $response->getHeaderValue('X-RateLimit-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-RateLimit-Reset')
        );

        $this->assertSame(
            '<h1>Too many requests</h1>',
            $response->getBody()
        );
    }

    public function testHeaders(): void
    {
        $middleware = new RateLimiterMiddleware([
            'limit' => 10,
            'period' => 10,
            'headers' => [
                'limit' => 'X-Test-Limit',
                'remaining' => 'X-Test-Remaining',
                'reset' => 'X-Test-Reset',
            ],
        ]);

        $queue = new MiddlewareQueue();
        $queue->add($middleware);

        $handler = new RequestHandler($queue);
        $request = new ServerRequest([
            'globals' => [
                'server' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
            ],
        ]);

        $response = $handler->handle($request);

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            '10',
            $response->getHeaderValue('X-Test-Limit')
        );

        $this->assertSame(
            '9',
            $response->getHeaderValue('X-Test-Remaining')
        );

        $this->assertGreaterThan(
            time(),
            $response->getHeaderValue('X-Test-Reset')
        );
    }

    public function testSetIdentifier(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
                'identifier' => fn(ServerRequest $request): string => 'user'.$i,
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            200,
            $response->getStatusCode()
        );
    }

    public function testSkipCheck(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $middleware = new RateLimiterMiddleware([
                'limit' => 10,
                'period' => 10,
                'skipCheck' => fn(ServerRequest $request): bool => true,
            ]);

            $queue = new MiddlewareQueue();
            $queue->add($middleware);

            $handler = new RequestHandler($queue);
            $request = new ServerRequest([
                'globals' => [
                    'server' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                    ],
                ],
            ]);

            $response = $handler->handle($request);
        }

        $this->assertSame(
            200,
            $response->getStatusCode()
        );
    }

    protected function setUp(): void
    {
        Cache::clear();
        Cache::setConfig('ratelimiter', [
            'className' => FileCacher::class,
            'path' => 'cache',
            'prefix' => 'ratelimiter.',
        ]);
    }

    protected function tearDown(): void
    {
        Cache::use('ratelimiter')->empty();
        rmdir('cache');
    }
}
