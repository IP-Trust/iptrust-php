<?php

/*
 * Look up an IP address and print what came back.
 *
 *   IPTRUST_API_KEY=your-key php examples/lookup.php 9.9.9.9
 */

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use IPTrust\SDK\Client;
use IPTrust\SDK\IPTrustException;
use IPTrust\SDK\RiskLabel;

$ip = $argv[1] ?? "9.9.9.9";

$client = new Client(); // reads IPTRUST_API_KEY

try {
    $res = $client->lookupIp($ip);
} catch (IPTrustException $e) {
    fwrite(STDERR, "Lookup failed: " . $e->getMessage() . "\n");
    if ($e->status === 429) {
        fwrite(STDERR, "Quota exceeded, back off.\n");
    }
    exit(1);
}

echo "IP:       ", $res->ip, "\n";
echo "Bogon:    ", $res->is_bogon ? "yes" : "no", "\n";

if ($res->location !== null) {
    echo "Location: ", $res->location->city, ", ", $res->location->state, ", ", $res->location->country,
        " (", $res->location->resolution, ")\n";
    echo "Area:     ", $res->location->area->radius_km, " km around ",
        $res->location->area->latitude, ",", $res->location->area->longitude, "\n";
}

if ($res->asn !== null) {
    echo "ASN:      ", $res->asn->number, " ", $res->asn->name, " (", $res->asn->company, ")\n";
    if ($res->asn->risk !== null) {
        echo "Risk:     ", $res->asn->risk, " ", $res->asn->risk_label,
            $res->asn->risk_label === RiskLabel::HIGH || $res->asn->risk_label === RiskLabel::VERY_HIGH ? " !!" : "", "\n";
    }
}

if ($res->company !== null) {
    echo "Company:  ", $res->company->name, " ", $res->company->domain ?? "", "\n";
}

if ($res->vpn !== null && $res->vpn->detected) {
    echo "VPN:      ", $res->vpn->provider ?? "unknown provider";
    if ($res->vpn->last_detected !== null) {
        echo " (last seen ", $res->vpn->last_detected->format("Y-m-d"), ")";
    }
    echo "\n";
}
if ($res->proxy !== null && $res->proxy->detected) {
    echo "Proxy:    ", $res->proxy->type ?? "unknown type", "\n";
}
if ($res->tor_exit_node !== null && $res->tor_exit_node->detected) {
    echo "Tor:      exit node\n";
}
if ($res->known_bot !== null && $res->known_bot->detected) {
    echo "Bot:      ", $res->known_bot->name, " (", $res->known_bot->bot_type, ")\n";
}
