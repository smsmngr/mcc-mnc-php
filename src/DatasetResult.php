<?php

declare(strict_types=1);

namespace MccMnc;

/** Envelope returned by GET /dataset (format=json, the default). */
final class DatasetResult
{
    /**
     * @param int       $count       total rows in the dataset
     * @param string    $generatedAt ISO 8601 timestamp the dump was generated at
     * @param string    $license     dataset license (e.g. "CC BY-SA 4.0")
     * @param list<Row> $results
     */
    public function __construct(
        public readonly int $count = 0,
        public readonly string $generatedAt = '',
        public readonly string $license = '',
        public readonly array $results = [],
    ) {
    }

    /**
     * Build a DatasetResult from a decoded API payload. Tolerant of missing keys.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rows = [];
        if (isset($data['results']) && \is_array($data['results'])) {
            foreach ($data['results'] as $row) {
                if (\is_array($row)) {
                    $rows[] = Row::fromArray($row);
                }
            }
        }

        $count = $data['count'] ?? null;
        $generatedAt = $data['generated_at'] ?? null;
        $license = $data['license'] ?? null;

        return new self(
            count: is_numeric($count) ? (int) $count : \count($rows),
            generatedAt: \is_string($generatedAt) ? $generatedAt : '',
            license: \is_string($license) ? $license : '',
            results: $rows,
        );
    }
}
