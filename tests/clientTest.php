<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

use IPTrust\SDK\Client;
use IPTrust\SDK\ClientConfig;
use IPTrust\SDK\DatabaseFormat;
use IPTrust\SDK\DatabaseType;
use IPTrust\SDK\ErrorCodes;
use IPTrust\SDK\HttpResponse;
use IPTrust\SDK\IPTrustException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function makeClient(FakeTransport $transport, ?ClientConfig $config = null): Client
    {
        $config = $config ?? new ClientConfig();
        if ($config->apiKey === "") {
            $config->setApiKey("test-key");
        }
        $config->setTransport($transport);
        return new Client($config);
    }

    // -- constructor ---------------------------------------------------------

    public function testRequiresApiKey(): void
    {
        putenv("IPTRUST_API_KEY");
        try {
            $this->expectException(InvalidArgumentException::class);
            new Client("   ");
        } finally {
            putenv("IPTRUST_API_KEY");
        }
    }

    public function testRequiresApiKeyWithEmptyConfig(): void
    {
        putenv("IPTRUST_API_KEY");
        $this->expectException(InvalidArgumentException::class);
        new Client(new ClientConfig());
    }

    public function testFallsBackToEnvironmentVariable(): void
    {
        putenv("IPTRUST_API_KEY=env-key");
        try {
            $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_IP));
            $client = new Client((new ClientConfig())->setTransport($t));
            $client->lookupIp("9.9.9.9");
            $this->assertSame("env-key", $t->last()->headers["X-API-Key"]);
        } finally {
            putenv("IPTRUST_API_KEY");
        }
    }

    public function testAcceptsApiKeyString(): void
    {
        $client = new Client("my-key");
        $this->assertSame("my-key", $client->getConfig()->apiKey);
    }

    public function testRejectsUnknownConfigType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client(42);
    }

    public function testStripsTrailingSlashesFromBaseUrl(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_IP));
        $this->makeClient($t, (new ClientConfig())->setBaseUrl("https://example.test///"))->lookupIp("9.9.9.9");
        $this->assertSame("https://example.test/ip/9.9.9.9", $t->last()->url);
    }

    // -- lookupIp ------------------------------------------------------------

    public function testLookupIpSendsHeadersAndParsesBody(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_IP));
        $res = $this->makeClient($t)->lookupIp("9.9.9.9");

        $req = $t->last();
        $this->assertSame("GET", $req->method);
        $this->assertSame("https://api.iptrust.co/ip/9.9.9.9", $req->url);
        $this->assertSame("test-key", $req->headers["X-API-Key"]);
        $this->assertSame("iptrust-php/" . Client::VERSION, $req->headers["User-Agent"]);
        $this->assertSame("application/json", $req->headers["Accept"]);
        $this->assertNull($req->body);
        $this->assertSame(10.0, $req->timeout);
        $this->assertSame(1, preg_match('/^\d+\.\d+\.\d+/', Client::VERSION));

        $this->assertSame("9.9.9.9", $res->ip);
        $this->assertFalse($res->is_bogon);
        $this->assertSame("AS19281", $res->asn->number);
        $this->assertSame("US", $res->location->meta->country_iso2);
        $this->assertNull($res->company);
        $this->assertSame("9.9.9.9", $res->raw["ip"]);
    }

    public function testLookupIpUrlEncodesIpv6AndTrims(): void
    {
        $t = (new FakeTransport())->replyJson(["ip" => "2001:db8::1", "is_bogon" => false]);
        $res = $this->makeClient($t)->lookupIp(" 2001:db8::1 ");
        $this->assertSame("https://api.iptrust.co/ip/2001%3Adb8%3A%3A1", $t->last()->url);
        $this->assertSame("2001:db8::1", $res->ip);
    }

    public function testLookupIpEncodesPathLikeInput(): void
    {
        $t = (new FakeTransport())->replyJson(["error" => ["error_code" => "bad_request"]], 400);
        try {
            $this->makeClient($t)->lookupIp("../database?x=1");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(400, $e->status);
        }
        $this->assertSame("https://api.iptrust.co/ip/..%2Fdatabase%3Fx%3D1", $t->last()->url);
    }

    public function testLookupIpRejectsEmptyWithoutRequest(): void
    {
        $t = new FakeTransport();
        try {
            $this->makeClient($t)->lookupIp("   ");
            $this->fail("expected exception");
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, $t->callCount());
        }
    }

    public function testApiErrorIsParsed(): void
    {
        $t = (new FakeTransport())->replyJson(
            ["error" => ["error_code" => "token_invalid", "detail" => "invalid api key"]],
            401
        );
        try {
            $this->makeClient($t)->lookupIp("9.9.9.9");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame(401, $e->getCode());
            $this->assertSame(ErrorCodes::TOKEN_INVALID, $e->errorCode);
            $this->assertSame("invalid api key", $e->detail);
            $this->assertNull($e->errorId);
            $this->assertFalse($e->isRequestError());
            $this->assertStringContainsString("401", $e->getMessage());
            $this->assertStringContainsString("invalid api key", $e->getMessage());
        }
    }

    public function testInternalErrorExposesErrorIdAndData(): void
    {
        $t = (new FakeTransport())->replyJson(
            ["error" => ["error_code" => "internal_server_error", "error_id" => "abc-123", "detail" => "boom", "data" => ["x" => 1]]],
            500
        );
        try {
            $this->makeClient($t)->listDatabases();
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(500, $e->status);
            $this->assertSame("abc-123", $e->errorId);
            $this->assertSame(["x" => 1], $e->data);
        }
    }

    public function testNonJsonErrorBody(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(502, "<html>Bad Gateway</html>"));
        try {
            $this->makeClient($t)->lookupIp("9.9.9.9");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(502, $e->status);
            $this->assertNull($e->errorCode);
            $this->assertSame("<html>Bad Gateway</html>", $e->body);
            $this->assertStringContainsString("Bad Gateway", $e->getMessage());
        }
    }

    public function testEmptyErrorBodyUsesStatusText(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(429, ""));
        try {
            $this->makeClient($t)->lookupIp("9.9.9.9");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame("IP Trust API request failed with status 429: Too Many Requests", $e->getMessage());
        }
    }

    public function testInvalidJsonSuccessBody(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, "not json"));
        try {
            $this->makeClient($t)->lookupIp("9.9.9.9");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(ErrorCodes::INVALID_RESPONSE, $e->errorCode);
            $this->assertSame(200, $e->status);
        }
    }

    public function testTransportErrorsPropagate(): void
    {
        $t = (new FakeTransport())->fail(new IPTrustException("timed out", 0, ErrorCodes::TIMEOUT));
        try {
            $this->makeClient($t)->lookupIp("9.9.9.9");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertTrue($e->isTimeout());
            $this->assertTrue($e->isRequestError());
            $this->assertSame(0, $e->status);
        }
    }

    public function testExtraHeadersNeverOverrideApiKey(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_IP));
        $config = (new ClientConfig())
            ->setUserAgent("my-app/1.0")
            ->setHeader("X-Extra", "yes")
            ->setHeader("x-api-key", "nope");
        $this->makeClient($t, $config)->lookupIp("9.9.9.9");

        $h = $t->last()->headers;
        $this->assertSame("my-app/1.0", $h["User-Agent"]);
        $this->assertSame("yes", $h["X-Extra"]);
        $this->assertSame("test-key", $h["X-API-Key"]);
        $this->assertArrayNotHasKey("x-api-key", $h);
    }

    public function testTimeoutsArePassedToTransport(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_IP));
        $config = (new ClientConfig())->setTimeout(2.5)->setConnectTimeout(1);
        $this->makeClient($t, $config)->lookupIp("9.9.9.9");
        $this->assertSame(2.5, $t->last()->timeout);
        $this->assertSame(1.0, $t->last()->connectTimeout);
    }

    // -- lookupIps -----------------------------------------------------------

    public function testLookupIpsPostsAddressesAndKeysResultsAsSent(): void
    {
        $body = '{"9.9.9.9": ' . Fixtures::SAMPLE_IP . ', "2001:DB8::1": {"ip": "2001:db8::1", "is_bogon": true}}';
        $t = (new FakeTransport())->reply(new HttpResponse(200, $body));
        $res = $this->makeClient($t)->lookupIps(["9.9.9.9", "2001:DB8::1"]);

        $req = $t->last();
        $this->assertSame("POST", $req->method);
        $this->assertSame("https://api.iptrust.co/ip/batch", $req->url);
        $this->assertSame("application/json", $req->headers["Content-Type"]);
        $this->assertSame("test-key", $req->headers["X-API-Key"]);
        $this->assertSame(["ips" => ["9.9.9.9", "2001:DB8::1"]], json_decode($req->body, true));

        $this->assertSame(["9.9.9.9", "2001:DB8::1"], array_keys($res));
        $this->assertSame("AS19281", $res["9.9.9.9"]->asn->number);
        $this->assertSame("2001:db8::1", $res["2001:DB8::1"]->ip);
        $this->assertTrue($res["2001:DB8::1"]->is_bogon);
        $this->assertNull($res["2001:DB8::1"]->asn);
    }

    public function testLookupIpsSendsAListWhateverTheInputKeys(): void
    {
        $t = (new FakeTransport())->replyJson(["9.9.9.9" => ["ip" => "9.9.9.9", "is_bogon" => false]]);
        $this->makeClient($t)->lookupIps(["first" => "9.9.9.9"]);
        $this->assertSame('{"ips":["9.9.9.9"]}', $t->last()->body);
    }

    public function testLookupIpsRejectsEmptyWithoutRequest(): void
    {
        $t = new FakeTransport();
        try {
            $this->makeClient($t)->lookupIps([]);
            $this->fail("expected exception");
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, $t->callCount());
        }
    }

    public function testLookupIpsApiError(): void
    {
        $t = (new FakeTransport())->replyJson(
            ["error" => ["error_code" => "bad_request", "detail" => 'invalid ip address: "nope"']],
            400
        );
        try {
            $this->makeClient($t)->lookupIps(["9.9.9.9", "nope"]);
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame(ErrorCodes::BAD_REQUEST, $e->errorCode);
        }
    }

    // -- lookupAsn -----------------------------------------------------------

    public function testLookupAsnParsesBody(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::SAMPLE_ASN));
        $res = $this->makeClient($t)->lookupAsn(" AS13335 ");

        $req = $t->last();
        $this->assertSame("GET", $req->method);
        $this->assertSame("https://api.iptrust.co/asn/AS13335", $req->url);
        $this->assertNull($req->body);

        $this->assertSame("AS13335", $res->asn->number);
        $this->assertSame("Cloudflare, Inc.", $res->asn->company);
        $this->assertSame("hosting", $res->asn->type);
        $this->assertSame(0.3, $res->asn->risk);
        $this->assertSame("", $res->asn->route);
        $this->assertNull($res->asn->risk_types);
        $this->assertSame("2026-11-16T21:01:02+00:00", $res->updated_at->format("c"));

        $this->assertSame(1559552, $res->ipv4->total_ips);
        $this->assertSame(2736, $res->ipv4->total_routes);
        $this->assertCount(1, $res->ipv4->routes);
        $this->assertSame("152.114.0.0/17", $res->ipv4->routes[0]->route);
        $this->assertSame(32768, $res->ipv4->routes[0]->ips);

        $this->assertSame("1526285772224943937874993086464", $res->ipv6->total_ips_str);
        $this->assertSame(3094, $res->ipv6->total_routes);
        $this->assertSame("2a0a:6c80::/29", $res->ipv6->routes[0]->route);
        $this->assertSame("633825300114114700748351602688", $res->ipv6->routes[0]->ips_str);

        $this->assertCount(1, $res->countries);
        $country = $res->countries[0];
        $this->assertSame("US", $country->country);
        $this->assertSame("United States", $country->country_name);
        $this->assertSame(1108047, $country->ipv4_ips);
        $this->assertSame(1460, $country->ipv4_routes);
        $this->assertSame("588213278578840409395446153213", $country->ipv6_ips_str);
        $this->assertSame(2566, $country->ipv6_routes);

        $this->assertSame(111, $res->anonymization->detected_vpn_ips);
        $this->assertSame(20711, $res->anonymization->detected_proxy_ips);
        $this->assertSame("AS13335", $res->raw["asn"]["number"]);
    }

    public function testLookupAsnWithoutOptionalFields(): void
    {
        $t = (new FakeTransport())->replyJson([
            "asn" => ["number" => "AS38803", "name" => "GTELECOM-AS-AP", "company" => "Gtelecom Pty Ltd", "country" => "AU", "rir" => "APNIC"],
            "ipv4" => ["total_ips" => 4864, "total_routes" => 16, "routes" => []],
            "ipv6" => ["total_ips_str" => "0", "total_routes" => 0, "routes" => []],
            "countries" => [],
            "anonymization" => ["detected_vpn_ips" => 0, "detected_proxy_ips" => 0],
        ]);
        $res = $this->makeClient($t)->lookupAsn("AS38803");

        $this->assertNull($res->updated_at);
        $this->assertNull($res->asn->risk);
        $this->assertNull($res->asn->risk_label);
        $this->assertNull($res->asn->type);
        $this->assertSame("0", $res->ipv6->total_ips_str);
        $this->assertSame([], $res->ipv6->routes);
        $this->assertSame([], $res->countries);
    }

    public function testLookupAsnEncodesPathLikeInput(): void
    {
        $t = (new FakeTransport())->replyJson(["error" => ["error_code" => "bad_request"]], 400);
        try {
            $this->makeClient($t)->lookupAsn("../database");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(400, $e->status);
        }
        $this->assertSame("https://api.iptrust.co/asn/..%2Fdatabase", $t->last()->url);
    }

    public function testLookupAsnRejectsEmptyWithoutRequest(): void
    {
        $t = new FakeTransport();
        try {
            $this->makeClient($t)->lookupAsn("   ");
            $this->fail("expected exception");
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, $t->callCount());
        }
    }

    public function testLookupAsnNotFound(): void
    {
        $t = (new FakeTransport())->replyJson(
            ["error" => ["error_code" => "not_found", "detail" => "asn not found"]],
            404
        );
        try {
            $this->makeClient($t)->lookupAsn("AS999999999");
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(404, $e->status);
            $this->assertSame(ErrorCodes::NOT_FOUND, $e->errorCode);
        }
    }

    // -- databases -----------------------------------------------------------

    public function testListDatabases(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::LIST_BODY));
        $dbs = $this->makeClient($t)->listDatabases();

        $this->assertSame("GET", $t->last()->method);
        $this->assertSame("https://api.iptrust.co/database", $t->last()->url);
        $this->assertCount(2, $dbs);
        $this->assertSame(DatabaseType::GEOLOCATION, $dbs[0]->type);
        $this->assertTrue($dbs[0]->entitled);
        $this->assertCount(1, $dbs[0]->formats);
        $this->assertSame(DatabaseFormat::MMDB, $dbs[0]->formats[0]->format);
        $this->assertSame(123, $dbs[0]->formats[0]->size_bytes);
        $this->assertSame("2026-01-02T03:04:05+00:00", $dbs[0]->updated_at->format("c"));
        $this->assertFalse($dbs[1]->entitled);
        $this->assertSame([], $dbs[1]->formats);
    }

    public function testCreateDatabaseDownloadPostsJson(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::DOWNLOAD_BODY));
        $dl = $this->makeClient($t)->createDatabaseDownload(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB);

        $req = $t->last();
        $this->assertSame("POST", $req->method);
        $this->assertSame("https://api.iptrust.co/database/download", $req->url);
        $this->assertSame("application/json", $req->headers["Content-Type"]);
        $this->assertSame(["type" => "geolocation", "format" => "mmdb"], json_decode($req->body, true));

        $this->assertSame("iptrust-geolocation.mmdb", $dl->filename);
        $this->assertSame(91234567, $dl->size_bytes);
        $this->assertSame("https://files.example.test/iptrust-geolocation.mmdb?sig=abc", $dl->url);
        $this->assertSame(3600, $dl->expires_at->getTimestamp() - $dl->updated_at->getTimestamp());
    }

    public function testCreateDatabaseDownloadForbidden(): void
    {
        $t = (new FakeTransport())->replyJson(
            ["error" => ["error_code" => "forbidden", "detail" => "plan excludes database"]],
            403
        );
        try {
            $this->makeClient($t)->createDatabaseDownload(DatabaseType::ANONYMIZATION, DatabaseFormat::CSV);
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame(ErrorCodes::FORBIDDEN, $e->errorCode);
        }
    }

    public function testDownloadDatabaseToPath(): void
    {
        $t = (new FakeTransport())
            ->reply(new HttpResponse(200, Fixtures::DOWNLOAD_BODY))
            ->reply(new HttpResponse(200, "file-bytes"));
        $path = tempnam(sys_get_temp_dir(), "iptrust");
        try {
            $dl = $this->makeClient($t)->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, $path);
            $this->assertSame("iptrust-geolocation.mmdb", $dl->filename);
            $this->assertSame("file-bytes", file_get_contents($path));

            $this->assertSame(2, $t->callCount());
            $fileReq = $t->last();
            $this->assertSame($dl->url, $fileReq->url);
            $this->assertSame(["User-Agent" => "iptrust-php/" . Client::VERSION], $fileReq->headers);
            $this->assertSame(0.0, $fileReq->timeout, "client timeout must not apply to the file transfer");
            $this->assertSame(10.0, $fileReq->connectTimeout);
        } finally {
            @unlink($path);
        }
    }

    public function testDownloadDatabaseToStream(): void
    {
        $t = (new FakeTransport())
            ->reply(new HttpResponse(200, Fixtures::DOWNLOAD_BODY))
            ->reply(new HttpResponse(200, "file-bytes"));
        $stream = fopen("php://memory", "w+");
        $this->makeClient($t)->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, $stream);
        rewind($stream);
        $this->assertSame("file-bytes", stream_get_contents($stream));
        fclose($stream);
    }

    public function testDownloadDatabaseFailedFetchRemovesFile(): void
    {
        $t = (new FakeTransport())
            ->reply(new HttpResponse(200, Fixtures::DOWNLOAD_BODY))
            ->reply(new HttpResponse(403, "expired"));
        $path = sys_get_temp_dir() . "/iptrust-test-" . uniqid() . ".mmdb";
        try {
            $this->makeClient($t)->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, $path);
            $this->fail("expected exception");
        } catch (IPTrustException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame("expired", $e->body);
            $this->assertFalse(file_exists($path));
        } finally {
            @unlink($path);
        }
    }

    public function testDownloadDatabaseRejectsBadDestination(): void
    {
        $t = (new FakeTransport())->reply(new HttpResponse(200, Fixtures::DOWNLOAD_BODY));
        $this->expectException(InvalidArgumentException::class);
        $this->makeClient($t)->downloadDatabase(DatabaseType::GEOLOCATION, DatabaseFormat::MMDB, 42);
    }
}
