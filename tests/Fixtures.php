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

    const LIST_BODY = '{"databases":[
        {"type":"geolocation","entitled":true,"updated_at":"2026-01-02T03:04:05Z",
         "formats":[{"format":"mmdb","size_bytes":123,"updated_at":"2026-01-02T03:04:05Z"}]},
        {"type":"asn","entitled":false,"updated_at":"2026-01-01T00:00:00Z","formats":[]}
    ]}';

    const DOWNLOAD_BODY = '{"type":"geolocation","format":"mmdb","filename":"iptrust-geolocation.mmdb",
        "url":"https://files.example.test/iptrust-geolocation.mmdb?sig=abc","size_bytes":91234567,
        "updated_at":"2026-08-24T03:12:44Z","expires_at":"2026-08-24T04:12:44Z"}';
}
