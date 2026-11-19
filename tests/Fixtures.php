<?php

declare(strict_types=1);

namespace IPTrust\SDK\Test;

final class Fixtures
{
    const SAMPLE_IP = '{
  "ip": "9.9.9.9",
  "is_bogon": false,
  "asn": {
    "number": "AS19281", "name": "QUAD9-AS-1", "company": "Quad9", "country": "US",
    "route": "9.9.9.0/24", "rir": "ARIN", "type": "business", "risk": 0.1, "risk_label": "very_low"
  },
  "location": {
    "resolution": "city", "city": "Berkeley", "state": "California", "country": "United States",
    "latitude": 37.87, "longitude": -122.27,
    "area": {"latitude": 37.87, "longitude": -122.27, "radius_km": 20,
             "territories": [{"latitude": 64.2, "longitude": -149.5, "radius_km": 1000}]},
    "meta": {
      "country_iso2": "US", "country_iso3": "USA", "country_numeric_code": "840",
      "country_phone_code": "1", "country_capital": "Washington", "country_currency": "USD",
      "country_currency_name": "US Dollar", "country_native": "United States",
      "country_region": "Americas", "country_subregion": "Northern America",
      "country_emoji": "🇺🇸", "country_emoji_u": "U+1F1FA U+1F1F8"
    }
  },
  "vpn": {"detected": false},
  "proxy": {"detected": true, "type": "open", "last_detected": "2026-03-04"}
}';

    const SAMPLE_ASN = '{
  "asn": {
    "number": "AS13335", "name": "CLOUDFLARENET", "company": "Cloudflare, Inc.", "country": "US",
    "type": "hosting", "rir": "ARIN", "domain": "cloudflare.com", "risk": 0.3, "risk_label": "moderate"
  },
  "updated_at": "2026-11-16T21:01:02Z",
  "ipv4": {"total_ips": 1559552, "total_routes": 2736,
           "routes": [{"route": "152.114.0.0/17", "ips": 32768}]},
  "ipv6": {"total_ips_str": "1526285772224943937874993086464", "total_routes": 3094,
           "routes": [{"route": "2a0a:6c80::/29", "ips_str": "633825300114114700748351602688"}]},
  "countries": [
    {"country": "US", "country_name": "United States", "ipv4_ips": 1108047, "ipv4_routes": 1460,
     "ipv6_ips_str": "588213278578840409395446153213", "ipv6_routes": 2566}
  ],
  "anonymization": {"detected_vpn_ips": 111, "detected_proxy_ips": 20711}
}';

    const LIST_BODY = '{"databases":[
        {"type":"geolocation","entitled":true,"updated_at":"2026-01-02T03:04:05Z",
         "formats":[{"format":"mmdb","size_bytes":123,"updated_at":"2026-01-02T03:04:05Z"}]},
        {"type":"asn","entitled":false,"updated_at":"2026-01-01T00:00:00Z","formats":[]}
    ]}';

    const DOWNLOAD_BODY = '{"type":"geolocation","format":"mmdb","filename":"iptrust-geolocation.mmdb",
        "url":"https://files.example.test/iptrust-geolocation.mmdb?sig=abc","size_bytes":91234567,
        "updated_at":"2026-08-24T03:12:44Z","expires_at":"2026-08-24T04:12:44Z"}';
}
