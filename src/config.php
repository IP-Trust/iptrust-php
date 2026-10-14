<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use InvalidArgumentException;

/**
 * Configuration for a {@see Client}. All setters return `$this` so they can
 * be chained:
 *
 * ```php
 * $config = (new ClientConfig())
 *     ->setApiKey("your-api-key")
 *     ->setTimeout(5);
 * $client = new Client($config);
 * ```
 */
class ClientConfig
{
    /** @var string Your IP Trust API key. */
    public $apiKey = "";

    /** @var string API base URL, without a trailing slash. */
    public $baseUrl = Client::DEFAULT_BASE_URL;

    /** @var float Total request timeout in seconds. 0 disables it. */
    public $timeout = Client::DEFAULT_TIMEOUT;

    /** @var float Connection timeout in seconds. 0 disables it. */
    public $connectTimeout = Client::DEFAULT_CONNECT_TIMEOUT;

    /** @var string Value of the User-Agent header. */
    public $userAgent = Client::DEFAULT_USER_AGENT;

    /** @var array<string, string> Extra headers sent with every API request. */
    public $headers = [];

    /** @var HttpTransport|null Transport used for HTTP requests. Null means the built-in cURL transport. */
    public $transport = null;

    public function __construct()
    {
    }

    /**
     * Set the API key. Get one from https://dashboard.iptrust.co/.
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Override the API base URL. Useful for tests and proxies. Trailing
     * slashes are removed; any path is kept.
     *
     * @throws InvalidArgumentException if the value is not an absolute http(s) URL.
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || empty($parts["scheme"]) || empty($parts["host"])
            || !in_array(strtolower($parts["scheme"]), ["http", "https"], true)) {
            throw new InvalidArgumentException(
                "Invalid argument '" . $baseUrl . "' to setBaseUrl, it must be an absolute http(s) URL."
            );
        }
        $this->baseUrl = rtrim($baseUrl, "/");
        return $this;
    }

    /**
     * Total time allowed for a request, in seconds. Defaults to 10. Set to 0
     * to disable. Not applied to the file transfer in `downloadDatabase`.
     */
    public function setTimeout(float $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException("Timeout must be zero or positive.");
        }
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Time allowed to establish the connection, in seconds. Defaults to 10.
     * Set to 0 to disable.
     */
    public function setConnectTimeout(float $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException("Connect timeout must be zero or positive.");
        }
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Replace the default User-Agent header ("iptrust-php/<version>").
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Add a header to every API request. Setting the same name twice replaces
     * the earlier value. The `X-API-Key` header cannot be overridden.
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Use a custom HTTP transport instead of the built-in cURL one. Useful
     * for tests or for routing requests through an existing HTTP client.
     */
    public function setTransport(HttpTransport $transport): self
    {
        $this->transport = $transport;
        return $this;
    }
}
