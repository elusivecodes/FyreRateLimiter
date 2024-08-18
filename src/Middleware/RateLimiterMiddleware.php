<?php
declare(strict_types=1);

namespace Fyre\Security\Middleware;

use Fyre\Middleware\Middleware;
use Fyre\Middleware\RequestHandler;
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
     * @param array $options Options for the middleware.
     */
    public function __construct(array $options = [])
    {
        $this->limiter = new RateLimiter($options);
    }

    /**
     * Process a ServerRequest.
     *
     * @param ServerRequest $request The ServerRequest.
     * @param RequestHandler $handler The RequestHandler.
     * @return ClientResponse The ClientResponse.
     */
    public function process(ServerRequest $request, RequestHandler $handler): ClientResponse
    {
        if (!$this->limiter->checkLimit($request)) {
            return $this->limiter->errorResponse($request);
        }

        $response = $handler->handle($request);

        return $this->limiter->addHeaders($response);
    }
}
