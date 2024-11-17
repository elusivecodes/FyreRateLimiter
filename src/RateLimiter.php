<?php
declare(strict_types=1);

namespace Fyre\Security;

use Closure;
use Fyre\Cache\CacheManager;
use Fyre\Cache\Handlers\FileCacher;
use Fyre\Container\Container;
use Fyre\Server\ClientResponse;
use Fyre\Server\ServerRequest;

use function array_replace_recursive;
use function max;
use function time;

/**
 * RateLimiter
 */
class RateLimiter
{
    protected static array $defaults = [
        'cacheConfig' => 'ratelimiter',
        'limit' => 60,
        'period' => 60,
        'message' => 'Rate limit exceeded',
        'headers' => [
            'limit' => 'X-RateLimit-Limit',
            'remaining' => 'X-RateLimit-Remaining',
            'reset' => 'X-RateLimit-Reset',
        ],
        'skipCheck' => null,
    ];

    protected string $cacheConfig;

    protected CacheManager $cacheManager;

    protected int $calls = 0;

    protected Container $container;

    protected Closure $errorRenderer;

    protected array|null $headers = null;

    protected Closure $identifier;

    protected int $limit;

    protected string $message;

    protected int $period;

    protected int $reset = 0;

    protected Closure|null $skipCheck;

    /**
     * New RateLimiter constructor.
     *
     * @param Container $container The Container.
     * @param CacheManager $cacheManager The CacheManager.
     * @param array $options Options for the RateLimiter.
     */
    public function __construct(Container $container, CacheManager $cacheManager, array $options = [])
    {
        $this->container = $container;
        $this->cacheManager = $cacheManager;

        $options = array_replace_recursive(static::$defaults, $options);

        $this->cacheConfig = $options['cacheConfig'];
        $this->limit = $options['limit'];
        $this->period = $options['period'];
        $this->message = $options['message'];
        $this->headers = $options['headers'];
        $this->identifier = $options['identifier'] ?? fn(ServerRequest $request): string => $request->getServer('REMOTE_ADDR');
        $this->errorRenderer = $options['errorRenderer'] ?? function(ServerRequest $request, ClientResponse $response): ClientResponse {
            $contentType = $request->negotiate('content', ['text/html', 'application/json']);

            return match ($contentType) {
                'application/json' => $response->setJson([
                    'message' => $this->message,
                ]),
                default => $response
                    ->setContentType('text/plain')
                    ->setBody($this->message)
            };
        };
        $this->skipCheck = $options['skipCheck'];

        if (!$this->cacheManager->hasConfig($this->cacheConfig)) {
            $this->cacheManager->setConfig($this->cacheConfig, [
                'className' => FileCacher::class,
                'prefix' => $this->cacheConfig.':',
            ]);
        }
    }

    /**
     * Add rate limit headers to a ClientResponse.
     *
     * @param ClientResponse $response The ClientResponse.
     * @return ClientResponse The new ClientResponse.
     */
    public function addHeaders(ClientResponse $response): ClientResponse
    {
        if (!$this->headers) {
            return $response;
        }

        $remaining = max(0, $this->limit - $this->calls);

        return $response
            ->setHeader($this->headers['limit'], (string) $this->limit)
            ->setHeader($this->headers['remaining'], (string) $remaining)
            ->setHeader($this->headers['reset'], (string) $this->reset);
    }

    /**
     * Determine whether the rate limit has been reached for a request.
     *
     * @param ServerRequest $request The ServerRequest.
     * @return bool TRUE if the rate limit has not been reached, otherwise FALSE.
     */
    public function checkLimit(ServerRequest $request): bool
    {
        if ($this->skipCheck && $this->container->call($this->skipCheck, ['request' => $request]) === true) {
            return true;
        }

        $key = $this->container->call($this->identifier, ['request' => $request]);
        $expire = (int) $this->period;
        $now = time();

        $cacher = $this->cacheManager->use($this->cacheConfig);
        $data = $cacher->get($key);

        if ($data === null || $now > $data[1]) {
            $this->calls = 0;
            $this->reset = $now + $expire;
        } else {
            [$this->calls, $this->reset] = $data;
        }

        $this->calls++;
        $expire = $this->reset - $now;

        $cacher->save($key, [$this->calls, $this->reset], $expire);

        return $this->calls <= $this->limit;
    }

    /**
     * Generate an error response.
     *
     * @param ServerRequest $request The ServerRequest.
     * @return ClientResponse The ClientResponse.
     */
    public function errorResponse(ServerRequest $request): ClientResponse
    {
        $response = $this->container->build(ClientResponse::class)
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) ($this->reset - time()));

        $response = $this->addHeaders($response);

        return $this->container->call($this->errorRenderer, [
            'request' => $request,
            'response' => $response,
        ]);
    }
}
