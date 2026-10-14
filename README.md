# iptrust-php

Official PHP SDK for the [IP Trust](https://iptrust.co) API. Look up geolocation, ASN, company, hosting, known bot, VPN, proxy, Tor and privacy relay data for any IPv4 or IPv6 address, and download the underlying databases. No dependencies beyond `ext-curl` and `ext-json`.

Get a free API key from the [IP Trust dashboard](https://dashboard.iptrust.co/). IP Trust is free for up to 10,000 requests a month.

## Install

Requires **PHP 7.2** or later.

```sh
composer require iptrust/sdk
```

## Quick start

```php
use IPTrust\SDK\Client;

$client = new Client("your-api-key"); // or new Client() to read IPTRUST_API_KEY from the environment

$result = $client->lookupIp("9.9.9.9");

echo $result->location->country;   // "United States"
echo $result->asn->number;         // "AS19281"
var_dump($result->vpn->detected);  // bool(false)
```

## IP lookup

`lookupIp($ip)` calls `GET /ip/{ip}` and returns an `IPResponse`. The response has the same structure on every plan. Each section (`asn`, `location`, `vpn`, and so on) is an object, or `null` when your plan does not include it. Property names match the JSON field names in the [API docs](https://iptrust.co/docs).

```php
use IPTrust\SDK\{LocationResolution, ProxyType, RiskLabel};

$r = $client->lookupIp("2001:4860:4860::8888");

if ($r->is_bogon) { /* reserved or unroutable */ }

if ($r->asn !== null) {
    $r->asn->number;                 // always present in a section
    if ($r->asn->risk !== null) {    // not every AS has been scored
        $r->asn->risk;               // 0.0 to 1.0
        $r->asn->risk_label;         // RiskLabel::VERY_LOW ... RiskLabel::VERY_HIGH
    }
}
if ($r->location !== null) {
    $r->location->resolution;        // LocationResolution::CITY, STATE or COUNTRY
    $r->location->area->radius_km;   // expected area, handy for impossible-travel checks
    $r->location->meta->country_iso2;
}
if ($r->proxy !== null && $r->proxy->detected) {
    $r->proxy->type;                 // ProxyType::RESIDENTIAL or ProxyType::OPEN, or null
    $r->proxy->last_detected;        // DateTimeImmutable in UTC, or null
}
```

Optional fields inside a section are `null` when the API did not return them. Timestamps are `DateTimeImmutable` objects in UTC. Enumerated values are plain strings; the `OrganizationType`, `RiskLabel`, `RiskType`, `LocationResolution`, `BotType` and `ProxyType` classes hold the known values as constants.

The decoded body is also available as an associative array in `$r->raw`, for fields newer than this SDK.

Full field reference: https://iptrust.co/docs

## Database downloads

For plans that include database downloads.

```php
use IPTrust\SDK\{DatabaseFormat, DatabaseType};

// What's available and what your plan covers
foreach ($client->listDatabases() as $db) {
    echo $db->type, " ", $db->entitled ? "entitled" : "not entitled", " ", count($db->formats), " formats\n";
}

// Get a signed URL (valid about an hour, see expires_at)
$dl = $client->createDatabaseDownload(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB);
echo $dl->url, " ", $dl->filename, " ", $dl->size_bytes;

// Or fetch the file in one step, to a path or an open stream
$dl = $client->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, "/var/db/" . "geolocation.mmdb");
$dl = $client->downloadDatabase(DatabaseType::ASN, DatabaseFormat::CSV, fopen("php://temp", "w+"));
```

The signed URL carries its own authorisation. Anyone holding it can download the file until it expires, so treat it like a credential. The client timeout is not applied to the file transfer itself, only the connect timeout. When a download to a path fails, the partial file is removed.

## Errors

Any non-2xx response throws an `IPTrustException`:

```php
use IPTrust\SDK\IPTrustException;

try {
    $client->lookupIp("not-an-ip");
} catch (IPTrustException $e) {
    $e->status;     // 400
    $e->errorCode;  // e.g. "bad_request", "token_invalid", "request_limit_exceeded" (see ErrorCodes)
    $e->detail;     // human-readable message from the API
    $e->errorId;    // set on 5xx responses, quote it to support
}
```

| Status | Meaning |
|--------|---------|
| 400 | Invalid IP, or unknown database type/format |
| 401 | Missing or invalid API key |
| 403 | Your plan does not include this resource |
| 404 | Database not published in that format |
| 429 | Quota or rate limit exceeded |
| 500 | Server error, retry or contact support |

Requests that never get a response also throw an `IPTrustException`, with `status` 0 and `errorCode` set to `ErrorCodes::TIMEOUT` or `ErrorCodes::REQUEST_FAILED`. Use `$e->isRequestError()` or `$e->isTimeout()` to tell these apart from API errors. A 2xx response that is not valid JSON throws with `ErrorCodes::INVALID_RESPONSE`.

Passing an empty API key or IP address throws an `InvalidArgumentException` before any request is made.

## Options

```php
use IPTrust\SDK\{Client, ClientConfig};

$config = (new ClientConfig())
    ->setApiKey("your-api-key")
    ->setBaseUrl("https://api.iptrust.co")      // override for testing
    ->setTimeout(10)                             // seconds per request, 0 to disable
    ->setConnectTimeout(10)                      // seconds to establish the connection
    ->setUserAgent("my-app/1.0")                 // default is "iptrust-php/<version>"
    ->setHeader("X-Request-Source", "web")       // extra header on every API request
    ->setTransport($myTransport);                // custom HTTP transport, see below

$client = new Client($config);
```

### Custom transport

Requests go through the `IPTrust\SDK\HttpTransport` interface. The default `CurlTransport` uses `ext-curl`. Implement the interface to route requests through another HTTP library, or to fake responses in tests:

```php
use IPTrust\SDK\{HttpRequest, HttpResponse, HttpTransport};

final class FakeTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        return new HttpResponse(200, '{"ip":"9.9.9.9","is_bogon":false}');
    }
}
```

A transport returns every response, whatever its status, and throws `IPTrustException` with status 0 only when no response was received. When `$request->sink` is set, a 2xx body goes to that stream instead of the response.

## Development

```sh
composer install
vendor/bin/phpunit
```

The test suite needs no network access. `CurlTransportTest` starts PHP's built-in web server on a free local port to exercise the real cURL transport, and skips itself if `proc_open` is unavailable.

To stay compatible with PHP 7.2, the source avoids typed properties, union types, arrow functions, `??=` and other newer syntax. CI runs the suite on PHP 7.2 through 8.4.

## License

MIT
