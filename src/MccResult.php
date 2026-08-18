<?php

declare(strict_types=1);

namespace MccMnc;

/** Envelope returned by GET /mcc/{mcc}. */
final class MccResult
{
    /**
     * @param string    $mcc     the MCC as a string (leading zeros significant)
     * @param int       $count   number of networks for the MCC
     * @param list<Row> $results
     */
    public function __construct(
        public readonly string $mcc = '',
        public readonly int $count = 0,
        public readonly array $results = [],
    ) {
    }

    /**
     * Build an MccResult from a decoded API payload. Tolerant of missing keys.
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

        $mcc = $data['mcc'] ?? null;
        if (!\is_string($mcc)) {
            $mcc = \is_int($mcc) ? (string) $mcc : '';
        }

        $count = $data['count'] ?? null;

        return new self(
            mcc: $mcc,
            count: is_numeric($count) ? (int) $count : \count($rows),
            results: $rows,
        );
    }
}
