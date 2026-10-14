<?php

/*
 * List the downloadable databases and fetch one to disk.
 *
 *   IPTRUST_API_KEY=your-key php examples/download.php geolocation mmdb
 */

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use IPTrust\SDK\Client;
use IPTrust\SDK\DatabaseFormat;
use IPTrust\SDK\DatabaseType;
use IPTrust\SDK\IPTrustException;

$type = $argv[1] ?? DatabaseType::GEOLOCATION;
$format = $argv[2] ?? DatabaseFormat::MMDB;

$client = new Client(); // reads IPTRUST_API_KEY

try {
    foreach ($client->listDatabases() as $db) {
        $formats = array_map(function ($f) {
            return $f->format;
        }, $db->formats);
        printf(
            "%-14s entitled=%s updated=%s formats=%s\n",
            $db->type,
            $db->entitled ? "yes" : "no",
            $db->updated_at !== null ? $db->updated_at->format("Y-m-d") : "-",
            implode(",", $formats)
        );
    }

    $dl = $client->downloadDatabase($type, $format, $type . "." . ($format === "csv" ? "csv.gz" : $format));
    printf("Downloaded %s (%d bytes), published %s\n", $dl->filename, $dl->size_bytes,
        $dl->updated_at !== null ? $dl->updated_at->format("c") : "-");
} catch (IPTrustException $e) {
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}
