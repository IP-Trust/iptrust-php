<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use DateTimeImmutable;

/*
 * Types describing the IP lookup response.
 *
 * Field documentation mirrors https://iptrust.co/docs. The lookup response has
 * one shape for every plan; sections your plan does not include are simply
 * absent, so every top-level section of IPResponse is nullable.
 *
 * Property names match the JSON field names, so `$response->asn->risk_label`
 * is the `asn.risk_label` field of the API response.
 */

// ---------------------------------------------------------------------------
// Enumerations (PHP 7.2 has no enums, so these are constant holders)
// ---------------------------------------------------------------------------

/** AS or company classification. */
final class OrganizationType
{
    const ISP = "isp";
    const HOSTING = "hosting";
    const BUSINESS = "business";
    const GOVERNMENT = "government";
    const EDUCATION = "education";
}

/** Risk level derived from the numeric `asn.risk` score. */
final class RiskLabel
{
    const VERY_LOW = "very_low";
    const LOW = "low";
    const MODERATE = "moderate";
    const HIGH = "high";
    const VERY_HIGH = "very_high";
}

/** Risk signal categories attached to an AS. */
final class RiskType
{
    const CONFIRMED_BOT = "confirmed_bot";
    const SUSPECTED_BOT = "suspected_bot";
    const TRAFFIC_VOLUME = "traffic_volume";
    const REPEATED_ABUSE = "repeated_abuse";
    const TARGETED_ABUSE = "targeted_abuse";
}

/** Precision of the geolocation data. */
final class LocationResolution
{
    const CITY = "city";
    const STATE = "state";
    const COUNTRY = "country";
}

/** Category of a detected known bot. */
final class BotType
{
    const CRAWLER = "crawler";
    const AI = "ai";
    const OTHER = "other";
}

/** Proxy classification. */
final class ProxyType
{
    const RESIDENTIAL = "residential";
    const OPEN = "open";
}

// ---------------------------------------------------------------------------
// Lookup response sections
// ---------------------------------------------------------------------------

/** Autonomous System information (Identity package). */
class ASN
{
    /** @var string The Autonomous System Number, e.g. "AS8075". */
    public $number;
    /** @var string Registered name of the AS as listed by the RIR. */
    public $name;
    /** @var string Legal organization name that owns the AS. */
    public $company;
    /** @var string ISO 3166-1 alpha-2 country code where the AS is registered. */
    public $country;
    /** @var string Most-specific BGP route prefix containing this IP. */
    public $route;
    /** @var string Regional Internet Registry, e.g. "ARIN", "RIPE", "APNIC". */
    public $rir;
    /** @var string|null Primary domain associated with the AS owner. */
    public $domain;
    /** @var string|null AS classification, one of the `OrganizationType` constants. */
    public $type;
    /** @var string|null Human-readable description of the AS owner organization. */
    public $description;
    /** @var float|null Risk score from 0.0 (very low) to 1.0 (very high). Null when the AS has not been scored. */
    public $risk;
    /** @var string|null Risk level label, one of the `RiskLabel` constants. Present when `risk` is. */
    public $risk_label;
    /** @var string[]|null Risk signal categories, see `RiskType`. Null when the API reported none. */
    public $risk_types;

    public static function fromArray(array $data): ASN
    {
        $o = new self();
        $o->number = Util::str($data, "number");
        $o->name = Util::str($data, "name");
        $o->company = Util::str($data, "company");
        $o->country = Util::str($data, "country");
        $o->route = Util::str($data, "route");
        $o->rir = Util::str($data, "rir");
        $o->domain = Util::optString($data, "domain");
        $o->type = Util::optString($data, "type");
        $o->description = Util::optString($data, "description");
        $o->risk = Util::optFloat($data, "risk");
        $o->risk_label = Util::optString($data, "risk_label");
        $types = Util::optArray($data, "risk_types");
        $o->risk_types = $types === null ? null : array_values(array_map("strval", $types));
        return $o;
    }
}

/** A circular area described by its centre and radius. */
class Area
{
    /** @var float Latitude of the centre of the area (WGS84). */
    public $latitude;
    /** @var float Longitude of the centre of the area (WGS84). */
    public $longitude;
    /** @var float Radius of the area in kilometres. */
    public $radius_km;

    public static function fromArray(array $data): Area
    {
        $o = new self();
        $o->latitude = Util::float($data, "latitude");
        $o->longitude = Util::float($data, "longitude");
        $o->radius_km = Util::float($data, "radius_km");
        return $o;
    }
}

/**
 * The area the IP is expected to be within. Matches `Location::$resolution`
 * and is intended for checks such as impossible travel detection.
 */
class LocationArea extends Area
{
    /**
     * @var Area[]|null Only present when the resolution is `country` and the
     * country has territories outside the main area, such as Alaska and
     * Hawaii for the United States.
     */
    public $territories;

    public static function fromArray(array $data): Area
    {
        $o = new self();
        $o->latitude = Util::float($data, "latitude");
        $o->longitude = Util::float($data, "longitude");
        $o->radius_km = Util::float($data, "radius_km");
        $territories = Util::optArray($data, "territories");
        if ($territories === null) {
            $o->territories = null;
        } else {
            $o->territories = [];
            foreach ($territories as $t) {
                if (is_array($t)) {
                    $o->territories[] = Area::fromArray($t);
                }
            }
        }
        return $o;
    }
}

/** Country metadata attached to a location. */
class LocationMeta
{
    /** @var string ISO 3166-1 alpha-2 code. */
    public $country_iso2;
    /** @var string ISO 3166-1 alpha-3 code. */
    public $country_iso3;
    /** @var string ISO 3166-1 numeric code. */
    public $country_numeric_code;
    /** @var string International dialling code. */
    public $country_phone_code;
    /** @var string Capital city. */
    public $country_capital;
    /** @var string ISO 4217 currency code. */
    public $country_currency;
    /** @var string Full currency name. */
    public $country_currency_name;
    /** @var string Country name in local language. */
    public $country_native;
    /** @var string UN geographic macro-region. */
    public $country_region;
    /** @var string UN geographic sub-region. */
    public $country_subregion;
    /** @var string Country flag emoji. */
    public $country_emoji;
    /** @var string Country flag as Unicode code points. */
    public $country_emoji_u;

    public static function fromArray(array $data): LocationMeta
    {
        $o = new self();
        $o->country_iso2 = Util::str($data, "country_iso2");
        $o->country_iso3 = Util::str($data, "country_iso3");
        $o->country_numeric_code = Util::str($data, "country_numeric_code");
        $o->country_phone_code = Util::str($data, "country_phone_code");
        $o->country_capital = Util::str($data, "country_capital");
        $o->country_currency = Util::str($data, "country_currency");
        $o->country_currency_name = Util::str($data, "country_currency_name");
        $o->country_native = Util::str($data, "country_native");
        $o->country_region = Util::str($data, "country_region");
        $o->country_subregion = Util::str($data, "country_subregion");
        $o->country_emoji = Util::str($data, "country_emoji");
        $o->country_emoji_u = Util::str($data, "country_emoji_u");
        return $o;
    }
}

/** Geolocation information (Geolocation package). */
class Location
{
    /** @var string Precision of the location data, one of the `LocationResolution` constants. */
    public $resolution;
    /**
     * @var string City-level location. When resolution is `state` or
     * `country`, this is a suitable default city for that state or country.
     */
    public $city;
    /** @var string State, province, or top-level administrative region. */
    public $state;
    /** @var string Full country name. */
    public $country;
    /** @var float Approximate latitude of the provided city (WGS84). */
    public $latitude;
    /** @var float Approximate longitude of the provided city (WGS84). */
    public $longitude;
    /** @var LocationArea The area the IP is expected to be within. */
    public $area;
    /** @var LocationMeta Country metadata. */
    public $meta;

    public static function fromArray(array $data): Location
    {
        $o = new self();
        $o->resolution = Util::str($data, "resolution");
        $o->city = Util::str($data, "city");
        $o->state = Util::str($data, "state");
        $o->country = Util::str($data, "country");
        $o->latitude = Util::float($data, "latitude");
        $o->longitude = Util::float($data, "longitude");
        $o->area = LocationArea::fromArray(Util::optArray($data, "area") ?? []);
        $o->meta = LocationMeta::fromArray(Util::optArray($data, "meta") ?? []);
        return $o;
    }
}

/** Company operating at this IP (Identity package). */
class Company
{
    /** @var string Legal name of the company. */
    public $name;
    /** @var string|null Company classification, one of the `OrganizationType` constants. */
    public $type;
    /** @var string|null Primary website domain. */
    public $domain;
    /** @var string|null Brief description of the company. */
    public $description;

    public static function fromArray(array $data): Company
    {
        $o = new self();
        $o->name = Util::str($data, "name");
        $o->type = Util::optString($data, "type");
        $o->domain = Util::optString($data, "domain");
        $o->description = Util::optString($data, "description");
        return $o;
    }
}

/** Known bot / crawler detection (Identity package). */
class KnownBot
{
    /** @var bool Whether a known bot or crawler was detected. */
    public $detected;
    /** @var string|null Bot category, one of the `BotType` constants. */
    public $bot_type;
    /**
     * @var string|null Bot identifier, e.g. "google_bot", "gpt_bot",
     * "anthropic". The list grows over time; see https://iptrust.co/docs.
     */
    public $name;

    public static function fromArray(array $data): KnownBot
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        $o->bot_type = Util::optString($data, "bot_type");
        $o->name = Util::optString($data, "name");
        return $o;
    }
}

/** Hosting / cloud provider detection (Identity package). */
class Hosting
{
    /** @var bool Whether the IP belongs to a known hosting or cloud provider. */
    public $detected;
    /** @var string|null Website domain of the hosting provider. */
    public $domain;
    /** @var string|null Name of the hosting or cloud provider. */
    public $name;

    public static function fromArray(array $data): Hosting
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        $o->domain = Util::optString($data, "domain");
        $o->name = Util::optString($data, "name");
        return $o;
    }
}

/** Abuse contact for the network (Identity package). */
class AbuseContact
{
    /** @var string|null Physical mailing address. */
    public $address;
    /** @var string|null Email for reporting abuse. */
    public $email;
    /** @var string|null Name of the abuse contact person or team. */
    public $name;

    public static function fromArray(array $data): AbuseContact
    {
        $o = new self();
        $o->address = Util::optString($data, "address");
        $o->email = Util::optString($data, "email");
        $o->name = Util::optString($data, "name");
        return $o;
    }
}

/** Tor exit node detection (Anonymization package). */
class TorExitNode
{
    /** @var bool Whether this IP is a known Tor exit node. */
    public $detected;

    public static function fromArray(array $data): TorExitNode
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        return $o;
    }
}

/** Commercial privacy relay detection (Anonymization package). */
class PrivacyRelay
{
    /** @var bool Whether the IP is part of a commercial privacy relay service. */
    public $detected;
    /** @var string|null Provider identifier, e.g. "icloud_private_relay". */
    public $provider;

    public static function fromArray(array $data): PrivacyRelay
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        $o->provider = Util::optString($data, "provider");
        return $o;
    }
}

/** Proxy detection (Anonymization package). */
class Proxy
{
    /** @var bool Whether this IP is a detected proxy server. */
    public $detected;
    /** @var string|null Proxy type, one of the `ProxyType` constants. */
    public $type;
    /** @var DateTimeImmutable|null When proxy activity was last observed (UTC). */
    public $last_detected;

    public static function fromArray(array $data): Proxy
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        $o->type = Util::optString($data, "type");
        $o->last_detected = Util::parseTimestamp($data["last_detected"] ?? null);
        return $o;
    }
}

/** VPN detection (Anonymization package). */
class VPN
{
    /** @var bool Whether this IP is part of a VPN service. */
    public $detected;
    /** @var string|null VPN provider identifier, e.g. "nord_vpn". */
    public $provider;
    /** @var DateTimeImmutable|null When VPN activity was last observed (UTC). */
    public $last_detected;

    public static function fromArray(array $data): VPN
    {
        $o = new self();
        $o->detected = Util::bool($data, "detected");
        $o->provider = Util::optString($data, "provider");
        $o->last_detected = Util::parseTimestamp($data["last_detected"] ?? null);
        return $o;
    }
}

/**
 * Response from `GET /ip/{ip}`.
 *
 * The shape is the same for every plan. Sections not included in your plan
 * are null.
 */
class IPResponse
{
    /** @var string The queried IP address, echoed back. */
    public $ip;
    /** @var bool Whether the IP belongs to a bogon (unroutable or reserved) range. */
    public $is_bogon;
    /** @var ASN|null */
    public $asn;
    /** @var Location|null */
    public $location;
    /** @var Company|null */
    public $company;
    /** @var KnownBot|null */
    public $known_bot;
    /** @var Hosting|null */
    public $hosting;
    /** @var AbuseContact|null */
    public $abuse_contact;
    /** @var TorExitNode|null */
    public $tor_exit_node;
    /** @var PrivacyRelay|null */
    public $privacy_relay;
    /** @var Proxy|null */
    public $proxy;
    /** @var VPN|null */
    public $vpn;
    /**
     * @var array<string, mixed> The decoded response body as an associative
     * array, for access to fields this SDK version does not model.
     */
    public $raw;

    /**
     * Build a response from decoded JSON.
     *
     * @param string $json
     * @throws IPTrustException if the JSON cannot be decoded into an object.
     */
    public static function fromJson(string $json): IPResponse
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new IPTrustException(
                "IP Trust API returned a response that was not valid JSON",
                0,
                ErrorCodes::INVALID_RESPONSE
            );
        }
        return self::fromArray($data);
    }

    public static function fromArray(array $data): IPResponse
    {
        $o = new self();
        $o->raw = $data;
        $o->ip = Util::str($data, "ip");
        $o->is_bogon = Util::bool($data, "is_bogon");

        $asn = Util::optArray($data, "asn");
        $o->asn = $asn === null ? null : ASN::fromArray($asn);
        $location = Util::optArray($data, "location");
        $o->location = $location === null ? null : Location::fromArray($location);
        $company = Util::optArray($data, "company");
        $o->company = $company === null ? null : Company::fromArray($company);
        $knownBot = Util::optArray($data, "known_bot");
        $o->known_bot = $knownBot === null ? null : KnownBot::fromArray($knownBot);
        $hosting = Util::optArray($data, "hosting");
        $o->hosting = $hosting === null ? null : Hosting::fromArray($hosting);
        $abuse = Util::optArray($data, "abuse_contact");
        $o->abuse_contact = $abuse === null ? null : AbuseContact::fromArray($abuse);
        $tor = Util::optArray($data, "tor_exit_node");
        $o->tor_exit_node = $tor === null ? null : TorExitNode::fromArray($tor);
        $relay = Util::optArray($data, "privacy_relay");
        $o->privacy_relay = $relay === null ? null : PrivacyRelay::fromArray($relay);
        $proxy = Util::optArray($data, "proxy");
        $o->proxy = $proxy === null ? null : Proxy::fromArray($proxy);
        $vpn = Util::optArray($data, "vpn");
        $o->vpn = $vpn === null ? null : VPN::fromArray($vpn);
        return $o;
    }
}
