<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use DateTimeImmutable;

/*
 * Types describing the ASN lookup response.
 *
 * Property names match the JSON field names. IPv6 address counts do not fit
 * in a PHP integer, so the API sends them as decimal strings in the fields
 * ending `_str`. Use the GMP or BCMath extensions to do arithmetic on them.
 */

/** One IPv4 route announced by an AS. */
class ASNIPv4Route
{
    /** @var string The prefix in CIDR notation. */
    public $route;
    /** @var int Number of addresses the route covers. */
    public $ips;

    public static function fromArray(array $data): ASNIPv4Route
    {
        $o = new self();
        $o->route = Util::str($data, "route");
        $o->ips = Util::int($data, "ips");
        return $o;
    }
}

/** The IPv4 address space announced by an AS. */
class ASNIPv4
{
    /** @var int Number of IPv4 addresses announced. */
    public $total_ips;
    /** @var int Number of IPv4 routes announced. */
    public $total_routes;
    /** @var ASNIPv4Route[] The 100 largest routes, largest first. */
    public $routes;

    public static function fromArray(array $data): ASNIPv4
    {
        $o = new self();
        $o->total_ips = Util::int($data, "total_ips");
        $o->total_routes = Util::int($data, "total_routes");
        $o->routes = [];
        foreach (Util::optArray($data, "routes") ?? [] as $route) {
            if (is_array($route)) {
                $o->routes[] = ASNIPv4Route::fromArray($route);
            }
        }
        return $o;
    }
}

/** One IPv6 route announced by an AS. */
class ASNIPv6Route
{
    /** @var string The prefix in CIDR notation. */
    public $route;
    /** @var string Number of addresses the route covers, as a decimal string. */
    public $ips_str;

    public static function fromArray(array $data): ASNIPv6Route
    {
        $o = new self();
        $o->route = Util::str($data, "route");
        $o->ips_str = Util::str($data, "ips_str");
        return $o;
    }
}

/** The IPv6 address space announced by an AS. */
class ASNIPv6
{
    /** @var string Number of IPv6 addresses announced, as a decimal string. */
    public $total_ips_str;
    /** @var int Number of IPv6 routes announced. */
    public $total_routes;
    /** @var ASNIPv6Route[] The 100 largest routes, largest first. */
    public $routes;

    public static function fromArray(array $data): ASNIPv6
    {
        $o = new self();
        $o->total_ips_str = Util::str($data, "total_ips_str");
        $o->total_routes = Util::int($data, "total_routes");
        $o->routes = [];
        foreach (Util::optArray($data, "routes") ?? [] as $route) {
            if (is_array($route)) {
                $o->routes[] = ASNIPv6Route::fromArray($route);
            }
        }
        return $o;
    }
}

/** The share of an AS's address space located in one country. */
class ASNCountry
{
    /** @var string ISO 3166-1 alpha-2 country code. */
    public $country;
    /** @var string Full country name. */
    public $country_name;
    /** @var int Number of the AS's IPv4 addresses in this country. */
    public $ipv4_ips;
    /** @var int Number of IPv4 routes with addresses in this country. A route spanning several countries is counted in each. */
    public $ipv4_routes;
    /** @var string Number of the AS's IPv6 addresses in this country, as a decimal string. */
    public $ipv6_ips_str;
    /** @var int Number of IPv6 routes with addresses in this country. A route spanning several countries is counted in each. */
    public $ipv6_routes;

    public static function fromArray(array $data): ASNCountry
    {
        $o = new self();
        $o->country = Util::str($data, "country");
        $o->country_name = Util::str($data, "country_name");
        $o->ipv4_ips = Util::int($data, "ipv4_ips");
        $o->ipv4_routes = Util::int($data, "ipv4_routes");
        $o->ipv6_ips_str = Util::str($data, "ipv6_ips_str");
        $o->ipv6_routes = Util::int($data, "ipv6_routes");
        return $o;
    }
}

/** Counts of the addresses in an AS detected as anonymizers. */
class ASNAnonymization
{
    /** @var int Number of addresses detected as VPN exit nodes. */
    public $detected_vpn_ips;
    /** @var int Number of addresses detected as proxies. */
    public $detected_proxy_ips;

    public static function fromArray(array $data): ASNAnonymization
    {
        $o = new self();
        $o->detected_vpn_ips = Util::int($data, "detected_vpn_ips");
        $o->detected_proxy_ips = Util::int($data, "detected_proxy_ips");
        return $o;
    }
}

/** Response from `GET /asn/{asn}`. */
class ASNResponse
{
    /**
     * @var ASN The autonomous system. `route` is empty and `risk_types` is
     * null, because they describe a single IP.
     */
    public $asn;
    /** @var DateTimeImmutable|null When the details of this AS were last updated (UTC). Null if the API omitted it. */
    public $updated_at;
    /** @var ASNIPv4 The IPv4 address space announced by the AS. */
    public $ipv4;
    /** @var ASNIPv6 The IPv6 address space announced by the AS. */
    public $ipv6;
    /** @var ASNCountry[] Where the AS's addresses are located, ordered by IPv4 address count, largest first. */
    public $countries;
    /** @var ASNAnonymization Counts of the addresses detected as VPNs or proxies. */
    public $anonymization;
    /**
     * @var array<string, mixed> The decoded response body as an associative
     * array, for access to fields this SDK version does not model.
     */
    public $raw;

    public static function fromArray(array $data): ASNResponse
    {
        $o = new self();
        $o->raw = $data;
        $o->asn = ASN::fromArray(Util::optArray($data, "asn") ?? []);
        $o->updated_at = Util::parseTimestamp($data["updated_at"] ?? null);
        $o->ipv4 = ASNIPv4::fromArray(Util::optArray($data, "ipv4") ?? []);
        $o->ipv6 = ASNIPv6::fromArray(Util::optArray($data, "ipv6") ?? []);
        $o->countries = [];
        foreach (Util::optArray($data, "countries") ?? [] as $country) {
            if (is_array($country)) {
                $o->countries[] = ASNCountry::fromArray($country);
            }
        }
        $o->anonymization = ASNAnonymization::fromArray(Util::optArray($data, "anonymization") ?? []);
        return $o;
    }
}
