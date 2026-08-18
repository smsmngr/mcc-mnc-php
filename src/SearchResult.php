<?php

declare(strict_types=1);

namespace MccMnc;

/** Envelope returned by GET /search. */
final class SearchResult
{
    /**
     * @param string         $query   the query as the API echoed it back
     * @param PhoneInfo|null $phone   null for non-phone queries
     * @param int            $count   total matches before limit/offset pagination
     * @param list<Row>      $results
     */
    public function __construct(
        public readonly string $query = '',
        public readonly ?PhoneInfo $phone = null,
        public readonly int $count = 0,
        public readonly array $results = [],
    ) {
    }

    /**
     * Build a SearchResult from a decoded API payload. Tolerant of missing keys.
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

        $phone = isset($data['phone']) && \is_array($data['phone'])
            ? PhoneInfo::fromArray($data['phone'])
            : null;

        $query = $data['query'] ?? null;
        $count = $data['count'] ?? null;

        return new self(
            query: \is_string($query) ? $query : '',
            phone: $phone,
            count: is_numeric($count) ? (int) $count : \count($rows),
            results: $rows,
        );
    }
}
