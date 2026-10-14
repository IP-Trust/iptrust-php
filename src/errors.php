<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use Exception;

/**
 * Error codes you may encounter. The SDK-internal ones are produced by this
 * library when a request never reaches the API or the response cannot be
 * read. The others are returned by the API in the `error.error_code` field.
 */
final class ErrorCodes
{
    /** (status 0, internal) The HTTP request could not be made, e.g. DNS or connection failure. */
    const REQUEST_FAILED = "request_failed";

    /** (status 0, internal) The request exceeded the configured timeout. */
    const TIMEOUT = "timeout";

    /** (internal) The API responded, but the body was not the JSON we expected. */
    const INVALID_RESPONSE = "invalid_response";

    /** (400) Invalid IP address, or unknown database type or format. */
    const BAD_REQUEST = "bad_request";

    /** (401) Missing or invalid API key. */
    const TOKEN_INVALID = "token_invalid";

    /** (403) Your plan does not include this resource. */
    const FORBIDDEN = "forbidden";

    /** (404) Database not published in that format. */
    const NOT_FOUND = "not_found";

    /** (429) Quota or rate limit exceeded. */
    const REQUEST_LIMIT_EXCEEDED = "request_limit_exceeded";

    /** (500) Server error. Retry, or contact support quoting the error ID. */
    const INTERNAL_SERVER_ERROR = "internal_server_error";
}

/**
 * Thrown when the IP Trust API returns a non-2xx response, when the request
 * cannot be made at all, or when the response cannot be parsed.
 *
 * ```php
 * try {
 *     $client->lookupIp("not-an-ip");
 * } catch (IPTrustException $e) {
 *     $e->status;    // 400
 *     $e->errorCode; // "bad_request"
 *     $e->detail;    // human-readable message from the API
 * }
 * ```
 */
class IPTrustException extends Exception
{
    /**
     * @var int HTTP status code of the failed response, or 0 when the request
     * never completed (see `errorCode`: "request_failed" or "timeout").
     */
    public $status = 0;

    /**
     * @var string|null Machine-readable error code, e.g. "token_invalid". See
     * `ErrorCodes`. Null if the API returned a body in an unrecognised format.
     */
    public $errorCode = null;

    /** @var string|null Human-friendly description from the API, if present. */
    public $detail = null;

    /** @var string|null Correlation ID set on internal errors (5xx). Quote it when contacting support. */
    public $errorId = null;

    /** @var mixed Extra structured data attached to the error, if present. */
    public $data = null;

    /**
     * @var string Raw response body (truncated) for errors the API did not
     * describe in its usual JSON format. Empty otherwise.
     */
    public $body = "";

    /**
     * @param int $status HTTP status, or 0 when no response was received.
     * @param string|null $errorCode
     */
    public function __construct(string $message, int $status = 0, ?string $errorCode = null, ?Exception $previous = null)
    {
        parent::__construct($message, $status, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
    }

    /** HTTP status code of the failed response, or 0 when the request never completed. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** Machine-readable error code, see `ErrorCodes`. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Human-friendly description from the API, if present. */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /** Correlation ID set on internal errors (5xx). */
    public function getErrorId(): ?string
    {
        return $this->errorId;
    }

    /** True when the request timed out before a response arrived. */
    public function isTimeout(): bool
    {
        return $this->errorCode === ErrorCodes::TIMEOUT;
    }

    /** True when the request never reached the API (timeout, DNS, connection failure). */
    public function isRequestError(): bool
    {
        return $this->status === 0;
    }

    /** @internal Maximum number of bytes of an error body that are kept. */
    const MAX_BODY = 4096;

    /**
     * Build an exception from a failed HTTP response.
     *
     * @internal
     */
    public static function fromResponse(int $status, string $body): IPTrustException
    {
        $body = substr($body, 0, self::MAX_BODY);
        $decoded = json_decode($body, true);

        $code = null;
        $detail = null;
        $errorId = null;
        $data = null;
        $rawBody = "";

        if (is_array($decoded) && isset($decoded["error"]) && is_array($decoded["error"])
            && isset($decoded["error"]["error_code"]) && is_string($decoded["error"]["error_code"])) {
            $err = $decoded["error"];
            $code = $err["error_code"];
            $detail = isset($err["detail"]) && is_string($err["detail"]) ? $err["detail"] : null;
            $errorId = isset($err["error_id"]) && is_string($err["error_id"]) ? $err["error_id"] : null;
            $data = isset($err["data"]) ? $err["data"] : null;
        } else {
            $rawBody = trim($body);
            if (strlen($rawBody) > 200) {
                $rawBody = substr($rawBody, 0, 200);
            }
        }

        $summary = $detail ?? $code ?? $rawBody;
        if ($summary === "") {
            $summary = Util::statusText($status);
        }
        $message = "IP Trust API request failed with status " . $status;
        if ($summary !== "") {
            $message .= ": " . $summary;
        }

        $e = new self($message, $status, $code);
        $e->detail = $detail;
        $e->errorId = $errorId;
        $e->data = $data;
        $e->body = $rawBody;
        return $e;
    }
}
