<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

use IPTrust\SDK\HttpRequest;
use IPTrust\SDK\HttpResponse;
use IPTrust\SDK\HttpTransport;
use IPTrust\SDK\IPTrustException;
use LogicException;

/**
 * Test transport that replays queued responses and records every request.
 */
final class FakeTransport implements HttpTransport
{
    /** @var array<int, HttpResponse|IPTrustException> */
    private $queue = [];

    /** @var HttpRequest[] */
    public $requests = [];

    public static function json($body, int $status = 200): HttpResponse
    {
        return new HttpResponse($status, json_encode($body));
    }

    public function reply(HttpResponse $response): self
    {
        $this->queue[] = $response;
        return $this;
    }

    public function replyJson($body, int $status = 200): self
    {
        return $this->reply(self::json($body, $status));
    }

    public function fail(IPTrustException $e): self
    {
        $this->queue[] = $e;
        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if (count($this->queue) === 0) {
            throw new LogicException("FakeTransport: no response queued for " . $request->method . " " . $request->url);
        }
        $next = array_shift($this->queue);
        if ($next instanceof IPTrustException) {
            throw $next;
        }
        if ($request->sink !== null) {
            if ($next->isSuccess()) {
                fwrite($request->sink, $next->body);
                return new HttpResponse($next->status, "");
            }
            return $next;
        }
        return $next;
    }

    public function last(): HttpRequest
    {
        if (count($this->requests) === 0) {
            throw new LogicException("FakeTransport: no requests were made");
        }
        return $this->requests[count($this->requests) - 1];
    }

    public function callCount(): int
    {
        return count($this->requests);
    }
}
