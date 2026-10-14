<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

use IPTrust\SDK\DatabaseDownload;
use IPTrust\SDK\ErrorCodes;
use IPTrust\SDK\IPResponse;
use IPTrust\SDK\IPTrustException;
use IPTrust\SDK\LocationResolution;
use IPTrust\SDK\OrganizationType;
use IPTrust\SDK\ProxyType;
use IPTrust\SDK\RiskLabel;
use IPTrust\SDK\Util;
use IPTrust\SDK\VPN;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testParsesSampleResponse(): void
    {
        $res = IPResponse::fromJson(Fixtures::SAMPLE_IP);

        $this->assertSame("9.9.9.9", $res->ip);
        $this->assertFalse($res->is_bogon);

        $asn = $res->asn;
        $this->assertNotNull($asn);
        $this->assertSame("AS19281", $asn->number);
        $this->assertSame("QUAD9-AS-1", $asn->name);
        $this->assertSame("Quad9", $asn->company);
        $this->assertSame("US", $asn->country);
        $this->assertSame("9.9.9.0/24", $asn->route);
        $this->assertSame("ARIN", $asn->rir);
        $this->assertSame(OrganizationType::BUSINESS, $asn->type);
        $this->assertSame(0.1, $asn->risk);
        $this->assertSame(RiskLabel::VERY_LOW, $asn->risk_label);
        $this->assertNull($asn->domain);
        $this->assertNull($asn->description);
        $this->assertNull($asn->risk_types);

        $loc = $res->location;
        $this->assertNotNull($loc);
        $this->assertSame(LocationResolution::CITY, $loc->resolution);
        $this->assertSame("Berkeley", $loc->city);
        $this->assertSame(37.87, $loc->latitude);
        $this->assertSame(20.0, $loc->area->radius_km);
        $this->assertCount(1, $loc->area->territories);
        $this->assertSame(1000.0, $loc->area->territories[0]->radius_km);
        $this->assertSame("US", $loc->meta->country_iso2);
        $this->assertSame("🇺🇸", $loc->meta->country_emoji);

        $this->assertNotNull($res->vpn);
        $this->assertFalse($res->vpn->detected);
        $this->assertNull($res->vpn->provider);
        $this->assertNull($res->vpn->last_detected);

        $this->assertNotNull($res->proxy);
        $this->assertTrue($res->proxy->detected);
        $this->assertSame(ProxyType::OPEN, $res->proxy->type);
        $this->assertSame("2026-03-04T00:00:00+00:00", $res->proxy->last_detected->format("c"));

        $this->assertNull($res->company);
        $this->assertNull($res->known_bot);
        $this->assertNull($res->hosting);
        $this->assertNull($res->abuse_contact);
        $this->assertNull($res->tor_exit_node);
        $this->assertNull($res->privacy_relay);

        $this->assertSame("open", $res->raw["proxy"]["type"]);
    }

    public function testNullSectionsAndFieldsAreTreatedAsAbsent(): void
    {
        $res = IPResponse::fromArray([
            "ip" => "1.1.1.1",
            "is_bogon" => true,
            "asn" => null,
            "company" => ["name" => "Cloudflare", "type" => null, "domain" => "cloudflare.com"],
            "known_bot" => ["detected" => true, "bot_type" => "crawler", "name" => "google_bot"],
            "hosting" => ["detected" => false],
            "abuse_contact" => ["email" => "abuse@example.test"],
            "tor_exit_node" => ["detected" => true],
            "privacy_relay" => ["detected" => true, "provider" => "icloud_private_relay"],
            "vpn" => ["detected" => true, "provider" => "nord_vpn", "last_detected" => "2026-01-02T03:04:05.123456789Z"],
        ]);

        $this->assertTrue($res->is_bogon);
        $this->assertNull($res->asn);
        $this->assertSame("Cloudflare", $res->company->name);
        $this->assertNull($res->company->type);
        $this->assertSame("cloudflare.com", $res->company->domain);
        $this->assertSame("google_bot", $res->known_bot->name);
        $this->assertFalse($res->hosting->detected);
        $this->assertNull($res->hosting->name);
        $this->assertSame("abuse@example.test", $res->abuse_contact->email);
        $this->assertNull($res->abuse_contact->address);
        $this->assertTrue($res->tor_exit_node->detected);
        $this->assertSame("icloud_private_relay", $res->privacy_relay->provider);
        $this->assertSame("nord_vpn", $res->vpn->provider);
        $this->assertSame("2026-01-02 03:04:05.123456", $res->vpn->last_detected->format("Y-m-d H:i:s.u"));
        $this->assertNull($res->location);
    }

    public function testRiskTypesAreStrings(): void
    {
        $res = IPResponse::fromArray([
            "ip" => "1.1.1.1",
            "is_bogon" => false,
            "asn" => ["number" => "AS1", "risk_types" => ["confirmed_bot", "traffic_volume"]],
        ]);
        $this->assertSame(["confirmed_bot", "traffic_volume"], $res->asn->risk_types);
        $this->assertSame("", $res->asn->name);
    }

    public function testFromJsonRejectsNonObject(): void
    {
        foreach (["not json", "null", "42", '"str"'] as $bad) {
            try {
                IPResponse::fromJson($bad);
                $this->fail("expected exception for " . $bad);
            } catch (IPTrustException $e) {
                $this->assertSame(ErrorCodes::INVALID_RESPONSE, $e->errorCode);
            }
        }
    }

    public function testTimestampParsing(): void
    {
        $cases = [
            "2026-01-02T03:04:05Z" => "2026-01-02 03:04:05.000000",
            "2026-01-02T03:04:05.123456Z" => "2026-01-02 03:04:05.123456",
            "2026-01-02T03:04:05+01:00" => "2026-01-02 02:04:05.000000",
            "2026-01-02T03:04:05-0130" => "2026-01-02 04:34:05.000000",
            "2026-01-02T03:04:05" => "2026-01-02 03:04:05.000000",
            "2026-01-02 03:04:05" => "2026-01-02 03:04:05.000000",
            "2026-01-02" => "2026-01-02 00:00:00.000000",
        ];
        foreach ($cases as $in => $want) {
            $got = Util::parseTimestamp($in);
            $this->assertNotNull($got, $in);
            $this->assertSame("UTC", $got->getTimezone()->getName(), $in);
            $this->assertSame($want, $got->format("Y-m-d H:i:s.u"), $in);
        }

        $this->assertNull(Util::parseTimestamp(null));
        $this->assertNull(Util::parseTimestamp(""));

        foreach (["yesterday", "2026-13-45", "20260102", "not a date"] as $bad) {
            try {
                Util::parseTimestamp($bad);
                $this->fail("expected exception for " . $bad);
            } catch (IPTrustException $e) {
                $this->assertSame(ErrorCodes::INVALID_RESPONSE, $e->errorCode);
            }
        }

        try {
            VPN::fromArray(["detected" => true, "last_detected" => 12345]);
            $this->fail("expected exception for non-string timestamp");
        } catch (IPTrustException $e) {
            $this->assertSame(ErrorCodes::INVALID_RESPONSE, $e->errorCode);
        }
    }

    public function testDatabaseDownloadWithMissingTimestamps(): void
    {
        $dl = DatabaseDownload::fromArray(["type" => "asn", "format" => "csv", "filename" => "a", "url" => "u", "size_bytes" => 1]);
        $this->assertNull($dl->updated_at);
        $this->assertNull($dl->expires_at);
        $this->assertSame("a", $dl->filename);
        $this->assertSame(1, $dl->size_bytes);
    }

    public function testExceptionFromResponseTruncatesLongBodies(): void
    {
        $e = IPTrustException::fromResponse(503, str_repeat("x", 10000));
        $this->assertSame(503, $e->status);
        $this->assertSame(200, strlen($e->body));
        $this->assertNull($e->errorCode);
    }
}
