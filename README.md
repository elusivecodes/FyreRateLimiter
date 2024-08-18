# FyreRateLimiter

**FyreRateLimiter** is a free, open-source rate limiting library for *PHP*.


## Table Of Contents
- [Installation](#installation)
- [Rate Limiter Creation](#rate-limiter-creation)
- [Methods](#methods)
- [Middleware](#middleware)



## Installation

**Using Composer**

```
composer require fyre/ratelimiter
```

In PHP:

```php
use Fyre\Security\RateLimiter;
```

## Rate Limiter Creation

- `$options` is an array containing options for the *RateLimiter*.
    - `cacheConfig` is a string representing the configuration key for the [*Cache*](https://github.com/elusivecodes/FyreCache), and will default to "*ratelimiter*".
    - `limit` is a number representing the maximum number of requests that can be made within the period, and will default to *60*.
    - `period` is a number representing the number of seconds per rate limiting period, and will default to *60*.
    - `message` is a string representing the rate limit error message, and will default to "*Rate limit exceeded*".
    - `headers` is an array containing the rate limit headers.
        - `limit` is a string representing the rate limit header, and will default to "*X-RateLimit-Limit*".
        - `remaining` is a string representing the rate limit remaining header, and will default to "*X-RateLimit-Remaining*".
        - `reset` is a string representing the rate limit reset header, and will default to "*X-RateLimit-Reset*".
    - `identifier` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) as the first argument, and should return a string representing the client identifier.
    - `skipCheck` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) as the first argument, and can return *true* to skip rate limit checks for the request.
    - `errorResponse` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) and a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses) as the arguments, and should return a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses).

```php
$limiter = new RateLimiter($options);
```

If the `identifier` callback is omitted, it will default to using the `$_SERVER['REMOTE_ADDR']`.

If the `errorResponse` callback is omitted, it will default to negotiating a json or plaintext response containing the `message` option.

## Methods

**Add Headers**

Add rate limit headers to a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses).

- `$response` is a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses).

```php
$response = $limiter->addHeaders($response);
```

**Check Limit**

Check rate limits.

- `$request` is the [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests).

```php
$result = $limiter->checkLimit($request);
```

**Error Response**

Generate an error response.

- `$request` is the [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests).

```php
$response = $limiter->errorResponse($request);
```


## Middleware

```php
use Fyre\Security\Middleware\RateLimiterMiddleware;
```

- `$options` is an array containing options for the *RateLimiter*.
    - `cacheConfig` is a string representing the configuration key for the [*Cache*](https://github.com/elusivecodes/FyreCache), and will default to "*ratelimiter*".
    - `limit` is a number representing the maximum number of requests that can be made within the period, and will default to *60*.
    - `period` is a number representing the number of seconds per rate limiting period, and will default to *60*.
    - `message` is a string representing the rate limit error message, and will default to "*Rate limit exceeded*".
    - `headers` is an array containing the rate limit headers.
        - `limit` is a string representing the rate limit header, and will default to "*X-RateLimit-Limit*".
        - `remaining` is a string representing the rate limit remaining header, and will default to "*X-RateLimit-Remaining*".
        - `reset` is a string representing the rate limit reset header, and will default to "*X-RateLimit-Reset*".
    - `identifier` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) as the first argument, and should return a string representing the client identifier.
    - `skipCheck` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) as the first argument, and can return *true* to skip rate limit checks for the request.
    - `errorResponse` is a *Closure* that accepts a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests) and a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses) as the arguments, and should return a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses).

```php
$middleware = new RateLimiterMiddleware($options);
```

If the `identifier` callback is omitted, it will default to using the `$_SERVER['REMOTE_ADDR']`.

If the `errorResponse` callback is omitted, it will default to negotiating a json or plaintext response containing the `message` option.

**Process**

- `$request` is a [*ServerRequest*](https://github.com/elusivecodes/FyreServer#server-requests).
- `$handler` is a [*RequestHandler*](https://github.com/elusivecodes/FyreMiddleware#request-handlers).

```php
$response = $middleware->process($request, $handler);
```

This method will return a [*ClientResponse*](https://github.com/elusivecodes/FyreServer#client-responses).