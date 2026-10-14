<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use InvalidArgumentException;

/**
 * Client for the IP Trust API.
 *
 * ```php
 * use IPTrust\SDK\Client;
 *
 * $client = new Client("your-api-key"); // or new Client() to read IPTRUST_API_KEY
 * $result = $client->lookupIp("9.9.9.9");
 * echo $result->location->country ?? "unknown";
 * ```
 *
 * Every method throws {@see IPTrustException} on a non-2xx response, a
 * transport failure, or an unreadable response body.
 */
class Client
{
    /** SDK version, sent in the User-Agent header. */
    const VERSION = "0.1.0";

    /** Production API endpoint. */
    const DEFAULT_BASE_URL = "https://api.iptrust.co";

    /** Default total request timeout in seconds. */
    const DEFAULT_TIMEOUT = 10.0;

    /** Default connection timeout in seconds. */
    const DEFAULT_CONNECT_TIMEOUT = 10.0;

    /** Default User-Agent header. */
    const DEFAULT_USER_AGENT = "iptrust-php/" . self::VERSION;

    /** @var ClientConfig */
    private $config;

    /** @var HttpTransport */
    private $transport;

    /**
     * @param ClientConfig|string|null $config A {@see ClientConfig}, or just
     * the API key as a string. When null or an empty key, the API key is read
     * from the `IPTRUST_API_KEY` environment variable.
     *
     * @throws InvalidArgumentException if no API key is available.
     */
    public function __construct($config = null)
    {
        if ($config === null) {
            $config = new ClientConfig();
        } elseif (is_string($config)) {
            $config = (new ClientConfig())->setApiKey($config);
        } elseif (!($config instanceof ClientConfig)) {
            throw new InvalidArgumentException(
                "Client expects a ClientConfig, an API key string, or null."
            );
        }

        $apiKey = trim($config->apiKey);
        if ($apiKey === "") {
            $env = getenv("IPTRUST_API_KEY");
            $apiKey = is_string($env) ? trim($env) : "";
        }
        if ($apiKey === "") {
            throw new InvalidArgumentException(
                "An API key is required. Pass it to the Client or set IPTRUST_API_KEY."
            );
        }

        $this->config = clone $config;
        $this->config->apiKey = $apiKey;
        $this->transport = $config->transport ?? new CurlTransport();
    }

    /** The configuration this client was created with (a copy). */
    public function getConfig(): ClientConfig
    {
        return clone $this->config;
    }

    /**
     * Look up intelligence for an IPv4 or IPv6 address.
     *
     * @throws IPTrustException on a non-2xx response: 400 invalid IP, 401 bad
     * key, 403 no access, 429 quota exceeded, 500 server error.
     * @throws InvalidArgumentException if `$ip` is empty.
     */
    public function lookupIp(string $ip): IPResponse
    {
        $ip = trim($ip);
        if ($ip === "") {
            throw new InvalidArgumentException("An IP address is required.");
        }
        $data = $this->request("GET", "/ip/" . rawurlencode($ip));
        return IPResponse::fromArray($data);
    }

    /**
     * List the databases available for download, including ones your plan
     * does not include (see `Database::$entitled`).
     *
     * @return Database[]
     * @throws IPTrustException
     */
    public function listDatabases(): array
    {
        $data = $this->request("GET", "/database");
        $out = [];
        foreach (Util::optArray($data, "databases") ?? [] as $db) {
            if (is_array($db)) {
                $out[] = Database::fromArray($db);
            }
        }
        return $out;
    }

    /**
     * Generate a temporary signed URL for downloading a database file. The
     * URL is valid for about an hour; check `DatabaseDownload::$expires_at`.
     *
     * @param string $type One of the `DatabaseType` constants.
     * @param string $format One of the `DatabaseFormat` constants.
     * @throws IPTrustException 400 unknown type/format, 403 plan excludes
     * this database, 404 not published in that format.
     */
    public function createDatabaseDownload(string $type, string $format): DatabaseDownload
    {
        $data = $this->request("POST", "/database/download", ["type" => $type, "format" => $format]);
        return DatabaseDownload::fromArray($data);
    }

    /**
     * Request a signed URL and download the file in one step.
     *
     * `$destination` is either a file path, which is created or truncated,
     * or an open writable stream resource. On failure a path is removed again
     * and nothing is written to a stream. The client timeout is not applied
     * to the file transfer, only the connect timeout.
     *
     * ```php
     * $dl = $client->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, "/var/db/geo.mmdb");
     * echo $dl->filename, " ", $dl->size_bytes, " bytes";
     * ```
     *
     * @param string $type One of the `DatabaseType` constants.
     * @param string $format One of the `DatabaseFormat` constants.
     * @param string|resource $destination File path or writable stream.
     * @throws IPTrustException
     * @throws InvalidArgumentException if `$destination` is neither a string nor a resource.
     */
    public function downloadDatabase(string $type, string $format, $destination): DatabaseDownload
    {
        $download = $this->createDatabaseDownload($type, $format);

        $ownsStream = false;
        if (is_resource($destination)) {
            $stream = $destination;
        } elseif (is_string($destination) && $destination !== "") {
            $stream = @fopen($destination, "wb");
            if ($stream === false) {
                throw new IPTrustException(
                    "Cannot open '" . $destination . "' for writing",
                    0,
                    ErrorCodes::REQUEST_FAILED
                );
            }
            $ownsStream = true;
        } else {
            throw new InvalidArgumentException("destination must be a file path or a stream resource.");
        }

        // The signed URL carries its own auth; do not send the API key to it.
        $req = new HttpRequest("GET", $download->url, ["User-Agent" => $this->config->userAgent]);
        $req->connectTimeout = $this->config->connectTimeout;
        $req->sink = $stream;

        try {
            $resp = $this->transport->send($req);
            if (!$resp->isSuccess()) {
                throw IPTrustException::fromResponse($resp->status, $resp->body);
            }
        } catch (IPTrustException $e) {
            if ($ownsStream) {
                fclose($stream);
                @unlink($destination);
            }
            throw $e;
        }

        if ($ownsStream) {
            fclose($stream);
        }
        return $download;
    }

    /**
     * Perform an API request and decode the JSON response.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws IPTrustException
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $headers = [
            "Accept" => "application/json",
            "User-Agent" => $this->config->userAgent,
        ];
        foreach ($this->config->headers as $name => $value) {
            if (strtolower($name) === "x-api-key") {
                continue;
            }
            $headers[$name] = $value;
        }
        $headers["X-API-Key"] = $this->config->apiKey;

        $encoded = null;
        if ($body !== null) {
            $encoded = json_encode($body);
            if ($encoded === false) {
                throw new IPTrustException(
                    "Failed to encode request body: " . json_last_error_msg(),
                    0,
                    ErrorCodes::REQUEST_FAILED
                );
            }
            $headers["Content-Type"] = "application/json";
        }

        $req = new HttpRequest($method, $this->config->baseUrl . $path, $headers, $encoded);
        $req->timeout = $this->config->timeout;
        $req->connectTimeout = $this->config->connectTimeout;

        $resp = $this->transport->send($req);
        if (!$resp->isSuccess()) {
            throw IPTrustException::fromResponse($resp->status, $resp->body);
        }

        $data = json_decode($resp->body, true);
        if (!is_array($data)) {
            throw new IPTrustException(
                "IP Trust API returned a response that was not valid JSON",
                $resp->status,
                ErrorCodes::INVALID_RESPONSE
            );
        }
        return $data;
    }
}
