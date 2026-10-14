<?php

declare(strict_types=1);

namespace IPTrust\SDK;

/**
 * An outgoing HTTP request, as handed to an {@see HttpTransport}.
 */
class HttpRequest
{
    /** @var string "GET" or "POST". */
    public $method = "GET";

    /** @var string Absolute URL. */
    public $url = "";

    /** @var array<string, string> Header name => value. */
    public $headers = [];

    /** @var string|null Request body, already encoded. */
    public $body = null;

    /** @var float Total timeout in seconds. 0 means none. */
    public $timeout = 0.0;

    /** @var float Connection timeout in seconds. 0 means none. */
    public $connectTimeout = 0.0;

    /**
     * @var resource|null When set, a 2xx response body is written to this
     * stream instead of being returned in `HttpResponse::$body`. A non-2xx
     * body is still returned in `HttpResponse::$body` and nothing is written.
     */
    public $sink = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $method, string $url, array $headers = [], ?string $body = null)
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }
}

/**
 * An HTTP response returned by an {@see HttpTransport}.
 */
class HttpResponse
{
    /** @var int HTTP status code. */
    public $status;

    /** @var string Response body. Empty for a 2xx response that was written to a sink. */
    public $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status <= 299;
    }
}

/**
 * Sends HTTP requests on behalf of the {@see Client}. Implement this to plug
 * in a different HTTP library or to fake responses in tests.
 */
interface HttpTransport
{
    /**
     * Send the request and return the response, whatever its status. Throw
     * an {@see IPTrustException} with status 0 if no response was received.
     *
     * @throws IPTrustException
     */
    public function send(HttpRequest $request): HttpResponse;
}

/**
 * The default transport, built on ext-curl.
 */
class CurlTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new IPTrustException("Failed to initialise cURL", 0, ErrorCodes::REQUEST_FAILED);
        }

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ": " . $value;
        }

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
        ];
        if ($request->method === "POST") {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $request->body ?? "";
        }
        if ($request->timeout > 0) {
            $options[CURLOPT_TIMEOUT_MS] = (int) ceil($request->timeout * 1000);
        }
        if ($request->connectTimeout > 0) {
            $options[CURLOPT_CONNECTTIMEOUT_MS] = (int) ceil($request->connectTimeout * 1000);
        }

        $errorBody = "";
        $sink = $request->sink;
        if ($sink !== null) {
            // Route 2xx bodies to the sink and keep anything else in memory
            // so a failed download does not leave an error page in the file.
            $options[CURLOPT_WRITEFUNCTION] = function ($ch, string $chunk) use ($sink, &$errorBody): int {
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($status >= 200 && $status <= 299) {
                    $written = fwrite($sink, $chunk);
                    return $written === false ? -1 : strlen($chunk);
                }
                if (strlen($errorBody) < IPTrustException::MAX_BODY) {
                    $errorBody .= $chunk;
                }
                return strlen($chunk);
            };
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if ($result === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno === 28) { // CURLE_OPERATION_TIMEDOUT
                throw new IPTrustException(
                    "IP Trust API request timed out: " . $error,
                    0,
                    ErrorCodes::TIMEOUT
                );
            }
            throw new IPTrustException(
                "IP Trust API request failed: " . $error . " (cURL error " . $errno . ")",
                0,
                ErrorCodes::REQUEST_FAILED
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($sink !== null) {
            return new HttpResponse($status, $errorBody);
        }
        return new HttpResponse($status, is_string($result) ? $result : "");
    }
}
