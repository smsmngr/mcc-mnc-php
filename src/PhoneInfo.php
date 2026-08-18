<?php

declare(strict_types=1);

namespace MccMnc;

/**
 * Parsed phone-number details, present on {@see SearchResult} when the /search
 * query was a phone number (national formats auto-detected), null otherwise.
 */
final class PhoneInfo
{
    public function __construct(
        public readonly bool $valid = false,
        public readonly bool $possible = false,
        public readonly ?string $type = null,
        public readonly ?string $country = null,
        public readonly ?string $callingCode = null,
        public readonly ?string $e164 = null,
        public readonly ?string $international = null,
        public readonly ?string $national = null,
    ) {
    }

    /**
     * Build a PhoneInfo from a decoded API payload. Tolerant of missing keys.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valid: (bool) ($data['valid'] ?? false),
            possible: (bool) ($data['possible'] ?? false),
            type: self::stringOrNull($data, 'type'),
            country: self::stringOrNull($data, 'country'),
            callingCode: self::stringOrNull($data, 'calling_code'),
            e164: self::stringOrNull($data, 'e164'),
            international: self::stringOrNull($data, 'international'),
            national: self::stringOrNull($data, 'national'),
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
}
