<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use DateTimeImmutable;

/*
 * Types describing database downloads.
 */

/** Identifier of a downloadable database. */
final class DatabaseType
{
    const ASN = "asn";
    const COMPANY = "company";
    const GEOLOCATION = "geolocation";
    const ABUSE_CONTACT = "abusecontact";
    const KNOWN_BOTS = "knownbots";
    const HOSTING = "hosting";
    const ANONYMIZATION = "anonymization";
}

/**
 * File format of a downloadable database. `mmdb` is a MaxMind DB file;
 * `csv` is a gzipped CSV export.
 */
final class DatabaseFormat
{
    const MMDB = "mmdb";
    const CSV = "csv";
}

/** One published format of a database. */
class DatabaseFile
{
    /** @var string One of the `DatabaseFormat` constants. */
    public $format;
    /** @var int File size in bytes as published. For `csv` this is the compressed size. */
    public $size_bytes;
    /** @var DateTimeImmutable|null When this file was published (UTC). Null if the API omitted it. */
    public $updated_at;

    public static function fromArray(array $data): DatabaseFile
    {
        $o = new self();
        $o->format = Util::str($data, "format");
        $o->size_bytes = Util::int($data, "size_bytes");
        $o->updated_at = Util::parseTimestamp($data["updated_at"] ?? null);
        return $o;
    }
}

/** One entry from `GET /database`. */
class Database
{
    /** @var string One of the `DatabaseType` constants. */
    public $type;
    /** @var bool Whether your plan includes downloads of this database. */
    public $entitled;
    /** @var DateTimeImmutable|null When the most recently refreshed file was published (UTC). Null if the API omitted it. */
    public $updated_at;
    /** @var DatabaseFile[] Published formats. */
    public $formats;

    public static function fromArray(array $data): Database
    {
        $o = new self();
        $o->type = Util::str($data, "type");
        $o->entitled = Util::bool($data, "entitled");
        $o->updated_at = Util::parseTimestamp($data["updated_at"] ?? null);
        $o->formats = [];
        foreach (Util::optArray($data, "formats") ?? [] as $f) {
            if (is_array($f)) {
                $o->formats[] = DatabaseFile::fromArray($f);
            }
        }
        return $o;
    }
}

/** Response from `POST /database/download`. */
class DatabaseDownload
{
    /** @var string One of the `DatabaseType` constants. */
    public $type;
    /** @var string One of the `DatabaseFormat` constants. */
    public $format;
    /** @var string Suggested filename for the downloaded file. */
    public $filename;
    /**
     * @var string Signed download URL. Needs no API key, and anyone holding
     * it can download the file until it expires, so treat it as a credential.
     */
    public $url;
    /** @var int File size in bytes. */
    public $size_bytes;
    /** @var DateTimeImmutable|null When the file was published (UTC). Null if the API omitted it. */
    public $updated_at;
    /** @var DateTimeImmutable|null When the signed URL stops working (UTC). Null if the API omitted it. */
    public $expires_at;

    public static function fromArray(array $data): DatabaseDownload
    {
        $o = new self();
        $o->type = Util::str($data, "type");
        $o->format = Util::str($data, "format");
        $o->filename = Util::str($data, "filename");
        $o->url = Util::str($data, "url");
        $o->size_bytes = Util::int($data, "size_bytes");
        $o->updated_at = Util::parseTimestamp($data["updated_at"] ?? null);
        $o->expires_at = Util::parseTimestamp($data["expires_at"] ?? null);
        return $o;
    }
}
