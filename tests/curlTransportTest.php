<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

use IPTrust\SDK\CurlTransport;
use IPTrust\SDK\ErrorCodes;
use IPTrust\SDK\HttpRequest;
use IPTrust\SDK\IPTrustException;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real cURL transport against PHP's built-in web server.
 */
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    /** @var string */
    private static $baseUrl = "";

    private static function startServer(): string
    {
        if (self::$baseUrl !== "") {
            return self::$baseUrl;
        }
        if (!function_exists("proc_open")) {
            self::markTestSkipped("proc_open is not available");
        }

        $probe = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr);
        if ($probe === false) {
            self::markTestSkipped("cannot bind a local port: " . $errstr);
        }
        $port = (int) explode(":", stream_socket_get_name($probe, false))[1];
        fclose($probe);

        $router = __DIR__ . "/server/router.php";
        // "exec" replaces the wrapping shell so proc_terminate() reaches php itself.
        $cmd = "exec " . escapeshellarg(PHP_BINARY) . " -S 127.0.0.1:" . $port . " " . escapeshellarg($router);
        $descriptors = [0 => ["pipe", "r"], 1 => ["file", "/dev/null", "w"], 2 => ["file", "/dev/null", "w"]];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            self::markTestSkipped("could not start php -S");
        }
        fclose($pipes[0]);
        self::$server = $proc;

        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $sock = @stream_socket_client("tcp://127.0.0.1:" . $port, $errno, $errstr, 0.2);
            if ($sock !== false) {
                fclose($sock);
                self::$baseUrl = "http://127.0.0.1:" . $port;
                return self::$baseUrl;
            }
            usleep(50000);
        }
        self::markTestSkipped("php -S did not start listening");
        return "";
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
        self::$baseUrl = "";
    }

    public function testGetSendsHeadersAndReturnsBody(): void
    {
        $base = self::startServer();
        $req = new HttpRequest("GET", $base . "/echo/ip/2001%3Adb8%3A%3A1", [
            "Accept" => "application/json",
            "User-Agent" => "iptrust-php/test",
            "X-API-Key" => "k",
        ]);
        $req->timeout = 5;
        $req->connectTimeout = 5;

        $resp = (new CurlTransport())->send($req);
        $this->assertSame(200, $resp->status);
        $echo = json_decode($resp->body, true);
        $this->assertSame("GET", $echo["method"]);
        $this->assertSame("/echo/ip/2001%3Adb8%3A%3A1", $echo["uri"]);
        $this->assertSame("k", $echo["headers"]["X-Api-Key"]);
        $this->assertSame("iptrust-php/test", $echo["headers"]["User-Agent"]);
        $this->assertSame("application/json", $echo["headers"]["Accept"]);
        $this->assertSame("", $echo["body"]);
    }

    public function testPostSendsBody(): void
    {
        $base = self::startServer();
        $req = new HttpRequest("POST", $base . "/echo", ["Content-Type" => "application/json"], '{"type":"asn"}');
        $resp = (new CurlTransport())->send($req);
        $this->assertSame(200, $resp->status);
        $echo = json_decode($resp->body, true);
        $this->assertSame("POST", $echo["method"]);
        $this->assertSame('{"type":"asn"}', $echo["body"]);
        $this->assertSame("application/json", $echo["headers"]["Content-Type"]);
    }

    public function testNonSuccessStatusIsReturnedNotThrown(): void
    {
        $base = self::startServer();
        $resp = (new CurlTransport())->send(new HttpRequest("GET", $base . "/status/429"));
        $this->assertSame(429, $resp->status);
        $this->assertSame("test_error", json_decode($resp->body, true)["error"]["error_code"]);
    }

    public function testTimeout(): void
    {
        $base = self::startServer();
        $req = new HttpRequest("GET", $base . "/slow");
        $req->timeout = 0.2;
        try {
            (new CurlTransport())->send($req);
            $this->fail("expected timeout");
        } catch (IPTrustException $e) {
            $this->assertSame(ErrorCodes::TIMEOUT, $e->errorCode);
            $this->assertTrue($e->isTimeout());
            $this->assertSame(0, $e->status);
        }
    }

    public function testConnectionRefused(): void
    {
        $probe = stream_socket_server("tcp://127.0.0.1:0");
        $port = (int) explode(":", stream_socket_get_name($probe, false))[1];
        fclose($probe);

        $req = new HttpRequest("GET", "http://127.0.0.1:" . $port . "/");
        $req->timeout = 2;
        try {
            (new CurlTransport())->send($req);
            $this->fail("expected failure");
        } catch (IPTrustException $e) {
            $this->assertSame(ErrorCodes::REQUEST_FAILED, $e->errorCode);
            $this->assertSame(0, $e->status);
        }
    }

    public function testSinkReceivesSuccessBody(): void
    {
        $base = self::startServer();
        $stream = fopen("php://memory", "w+");
        $req = new HttpRequest("GET", $base . "/file");
        $req->sink = $stream;
        $resp = (new CurlTransport())->send($req);
        $this->assertSame(200, $resp->status);
        $this->assertSame("", $resp->body);
        rewind($stream);
        $this->assertSame(str_repeat("abc", 1000), stream_get_contents($stream));
        fclose($stream);
    }

    public function testSinkIsUntouchedOnFailure(): void
    {
        $base = self::startServer();
        $stream = fopen("php://memory", "w+");
        $req = new HttpRequest("GET", $base . "/file-forbidden");
        $req->sink = $stream;
        $resp = (new CurlTransport())->send($req);
        $this->assertSame(403, $resp->status);
        $this->assertSame("expired", $resp->body);
        rewind($stream);
        $this->assertSame("", stream_get_contents($stream));
        fclose($stream);
    }
}
