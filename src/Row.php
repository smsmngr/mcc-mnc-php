<?php

declare(strict_types=1);

namespace MccMnc;

/**
 * A single mobile network row (wire shape: flat, snake_case — see
 * https://mcc-mnc.dev/docs). Property names are camelCased for PHP; the
 * `$fields` request parameter still takes the wire (snake_case) column names.
 *
 * `$mcc` and `$mnc` are ALWAYS strings — leading zeros are significant
 * ("02" is not the same network as "2").
 *
 * On the wire, `plmn`, `mcc`, `mnc`, `status` and `type` are never null; any
 * property here is null only when the API returned null for it or when it was
 * excluded via the `$fields` option.
 */
final class Row
{
    /**
     * @param list<string>|null $technology non-null on Europe-enriched rows only
     * @param float|null        $subscribers millions of subscribers
     * @param float|null        $marketReach percentage 0-100 (null outside Europe)
     */
    public function __construct(
        public readonly ?string $plmn = null,
        public readonly ?string $mcc = null,
        public readonly ?string $mnc = null,
        public readonly ?string $country = null,
        public readonly ?string $iso2 = null,
        public readonly ?string $iso3 = null,
        public readonly ?string $isoNumeric = null,
        public readonly ?string $dialPrefix = null,
        public readonly ?string $brand = null,
        public readonly ?string $operator = null,
        public readonly ?string $status = null,
        public readonly ?string $type = null,
        public readonly ?string $bands = null,
        public readonly ?array $technology = null,
        public readonly ?string $ownership = null,
        public readonly ?float $subscribers = null,
        public readonly ?string $subscribersAsOf = null,
        public readonly ?float $marketReach = null,
        public readonly ?string $marketReachBasis = null,
        public readonly ?int $rank = null,
        public readonly ?string $notes = null,
    ) {
    }

    /**
     * Build a Row from a decoded API payload. Tolerant of missing keys —
     * anything absent (e.g. excluded via `$fields`) becomes null.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            plmn: self::stringOrNull($data, 'plmn'),
            mcc: self::stringOrNull($data, 'mcc'),
            mnc: self::stringOrNull($data, 'mnc'),
            country: self::stringOrNull($data, 'country'),
            iso2: self::stringOrNull($data, 'iso2'),
            iso3: self::stringOrNull($data, 'iso3'),
            isoNumeric: self::stringOrNull($data, 'iso_numeric'),
            dialPrefix: self::stringOrNull($data, 'dial_prefix'),
            brand: self::stringOrNull($data, 'brand'),
            operator: self::stringOrNull($data, 'operator'),
            status: self::stringOrNull($data, 'status'),
            type: self::stringOrNull($data, 'type'),
            bands: self::stringOrNull($data, 'bands'),
            technology: self::stringListOrNull($data, 'technology'),
            ownership: self::stringOrNull($data, 'ownership'),
            subscribers: self::floatOrNull($data, 'subscribers'),
            subscribersAsOf: self::stringOrNull($data, 'subscribers_as_of'),
            marketReach: self::floatOrNull($data, 'market_reach'),
            marketReachBasis: self::stringOrNull($data, 'market_reach_basis'),
            rank: self::intOrNull($data, 'rank'),
            notes: self::stringOrNull($data, 'notes'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function stringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (\is_string($value)) {
            return $value;
        }

        return \is_int($value) || \is_float($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $data */
    private static function floatOrNull(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }

        return \is_string($value) && is_numeric($value) ? (float) $value : null;
    }

    /** @param array<string, mixed> $data */
    private static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (\is_int($value)) {
            return $value;
        }

        return \is_float($value) || (\is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>|null
     */
    private static function stringListOrNull(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return null;
        }

        $list = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
