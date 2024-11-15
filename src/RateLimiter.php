<?php
declare(strict_types=1);

namespace Fyre\Security;

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
        'identifier' => null,
        'skipCheck' => null,
        'errorResponse' => null,
    ];

    protected CacheManager $cacheManager;

    protected int $calls = 0;

    protected Container $container;

    protected array $options;

    protected int $reset = 0;

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

        $this->options = array_replace_recursive(static::$defaults, $options);

        $this->options['identifier'] ??= fn(ServerRequest $request): string => $request->getServer('REMOTE_ADDR');
        $this->options['errorResponse'] ??= function(ServerRequest $request, ClientResponse $response): ClientResponse {
            $contentType = $request->negotiate('content', ['text/html', 'application/json']);

            return match ($contentType) {
                'application/json' => $response->setJson([
                    'message' => $this->options['message'],
                ]),
                default => $response
                    ->setContentType('text/plain')
                    ->setBody($this->options['message'])
            };
        };

        if (!$this->cacheManager->hasConfig($this->options['cacheConfig'])) {
            $this->cacheManager->setConfig($this->options['cacheConfig'], [
                'className' => FileCacher::class,
                'prefix' => $this->options['cacheConfig'].':',
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
        if (!$this->options['headers']) {
            return $response;
        }

        $remaining = max(0, $this->options['limit'] - $this->calls);

        return $response
            ->setHeader($this->options['headers']['limit'], (string) $this->options['limit'])
            ->setHeader($this->options['headers']['remaining'], (string) $remaining)
            ->setHeader($this->options['headers']['reset'], (string) $this->reset);
    }

    /**
     * Determine whether the rate limit has been reached for a request.
     *
     * @param ServerRequest $request The ServerRequest.
     * @return bool TRUE if the rate limit has not been reached, otherwise FALSE.
     */
    public function checkLimit(ServerRequest $request): bool
    {
        if ($this->options['skipCheck'] && $this->options['skipCheck']($request) === true) {
            return true;
        }

        $key = $this->options['identifier']($request);
        $expire = (int) $this->options['period'];
        $now = time();

        $cacher = $this->cacheManager->use($this->options['cacheConfig']);
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

        return $this->calls <= $this->options['limit'];
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

        return $this->options['errorResponse']($request, $response);
    }
}
