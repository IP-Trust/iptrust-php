<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

use IPTrust\SDK\Client;
use IPTrust\SDK\ClientConfig;
use IPTrust\SDK\CurlTransport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new ClientConfig();
        $this->assertSame("https://api.iptrust.co", $config->baseUrl);
        $this->assertSame(10.0, $config->timeout);
        $this->assertSame(10.0, $config->connectTimeout);
        $this->assertSame("iptrust-php/" . Client::VERSION, $config->userAgent);
        $this->assertSame([], $config->headers);
        $this->assertNull($config->transport);
    }

    public function testSettersChain(): void
    {
        $t = new CurlTransport();
        $config = (new ClientConfig())
            ->setApiKey("k")
            ->setBaseUrl("http://localhost:8080/api/")
            ->setTimeout(3)
            ->setConnectTimeout(0)
            ->setUserAgent("ua")
            ->setHeader("A", "1")
            ->setHeader("A", "2")
            ->setTransport($t);

        $this->assertSame("k", $config->apiKey);
        $this->assertSame("http://localhost:8080/api", $config->baseUrl);
        $this->assertSame(3.0, $config->timeout);
        $this->assertSame(0.0, $config->connectTimeout);
        $this->assertSame("ua", $config->userAgent);
        $this->assertSame(["A" => "2"], $config->headers);
        $this->assertSame($t, $config->transport);
    }

    public function testInvalidBaseUrlThrows(): void
    {
        foreach (["something-invalid", "ftp://example.test", "//example.test", ""] as $bad) {
            try {
                (new ClientConfig())->setBaseUrl($bad);
                $this->fail("expected exception for '" . $bad . "'");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("setBaseUrl", $e->getMessage());
            }
        }
    }

    public function testNegativeTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ClientConfig())->setTimeout(-1);
    }

    public function testClientCopiesConfig(): void
    {
        $config = (new ClientConfig())->setApiKey("k");
        $client = new Client($config);
        $config->setApiKey("changed");
        $this->assertSame("k", $client->getConfig()->apiKey);
    }
}
