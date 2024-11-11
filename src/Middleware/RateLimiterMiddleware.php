<?php
declare(strict_types=1);

namespace Fyre\Security\Middleware;

use Closure;
use Fyre\Container\Container;
use Fyre\Middleware\Middleware;
use Fyre\Security\RateLimiter;
use Fyre\Server\ClientResponse;
use Fyre\Server\ServerRequest;

/**
 * RateLimiterMiddleware
 */
class RateLimiterMiddleware extends Middleware
{
    protected RateLimiter $limiter;

    /**
     * New RateLimiterMiddleware constructor.
     *
     * @param RateLimiter $limiter The RateLimiter.
     */
    public function __construct(Container $container, array $options = [])
    {
        $this->limiter = $container->build(RateLimiter::class, ['options' => $options]);
    }

    /**
     * Process a ServerRequest.
     *
     * @param ServerRequest $request The ServerRequest.
     * @param Closure $next The next handler.
     * @return ClientResponse The ClientResponse.
     */
    public function handle(ServerRequest $request, Closure $next): ClientResponse
    {
        if (!$this->limiter->checkLimit($request)) {
            return $this->limiter->errorResponse($request);
        }

        return $this->limiter->addHeaders($next($request));
    }
}
