<?php

declare(strict_types=1);

namespace IPTrust\SDK;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Internal helpers.
 *
 * @internal
 */
final class Util
{
    /**
     * Parse an ISO 8601 timestamp or bare date (YYYY-MM-DD) from the API into
     * a UTC DateTimeImmutable. Returns null for null, empty or non-string
     * input. Throws IPTrustException for a string that is not a timestamp.
     *
     * @param mixed $value
     */
    public static function parseTimestamp($value): ?DateTimeImmutable
    {
        if ($value === null || $value === "") {
            return null;
        }
        if (!is_string($value)) {
            throw new IPTrustException(
                "IP Trust API returned a timestamp that is not a string",
                0,
                ErrorCodes::INVALID_RESPONSE
            );
        }

        $ok = preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/i',
            $value
        );
        if ($ok === 1) {
            // PHP keeps at most microsecond precision; trim longer fractions.
            $normalised = preg_replace_callback('/\.(\d+)/', function ($m) {
                return "." . substr($m[1], 0, 6);
            }, $value);
            try {
                $utc = new DateTimeZone("UTC");
                return (new DateTimeImmutable($normalised, $utc))->setTimezone($utc);
            } catch (Exception $e) {
                // fall through
            }
        }

        throw new IPTrustException(
            "IP Trust API returned an unparseable timestamp: " . $value,
            0,
            ErrorCodes::INVALID_RESPONSE
        );
    }

    /** Standard reason phrase for an HTTP status, or "" if unknown. */
    public static function statusText(int $status): string
    {
        static $texts = [
            400 => "Bad Request",
            401 => "Unauthorized",
            403 => "Forbidden",
            404 => "Not Found",
            405 => "Method Not Allowed",
            408 => "Request Timeout",
            409 => "Conflict",
            422 => "Unprocessable Entity",
            429 => "Too Many Requests",
            500 => "Internal Server Error",
            502 => "Bad Gateway",
            503 => "Service Unavailable",
            504 => "Gateway Timeout",
        ];
        return $texts[$status] ?? "";
    }

    /** Return $data[$key] as a string, or null when absent or null. */
    public static function optString(array $data, string $key): ?string
    {
        if (!isset($data[$key])) {
            return null;
        }
        return (string) $data[$key];
    }

    /** Return $data[$key] as a float, or null when absent or null. */
    public static function optFloat(array $data, string $key): ?float
    {
        if (!isset($data[$key])) {
            return null;
        }
        return (float) $data[$key];
    }

    /** Return $data[$key] as a string, or "" when absent. */
    public static function str(array $data, string $key): string
    {
        return isset($data[$key]) ? (string) $data[$key] : "";
    }

    /** Return $data[$key] as a float, or 0.0 when absent. */
    public static function float(array $data, string $key): float
    {
        return isset($data[$key]) ? (float) $data[$key] : 0.0;
    }

    /** Return $data[$key] as an int, or 0 when absent. */
    public static function int(array $data, string $key): int
    {
        return isset($data[$key]) ? (int) $data[$key] : 0;
    }

    /** Return $data[$key] as a bool, or false when absent. */
    public static function bool(array $data, string $key): bool
    {
        return isset($data[$key]) ? (bool) $data[$key] : false;
    }

    /** Return $data[$key] as an array, or null when absent or not an array. */
    public static function optArray(array $data, string $key): ?array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : null;
    }
}
