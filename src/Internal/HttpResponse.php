<?php

declare(strict_types=1);

namespace MccMnc\Internal;

/** A raw HTTP response as returned by an {@see HttpTransport}. */
final class HttpResponse
{
    /**
     * @param int                   $status  HTTP status code
     * @param array<string, string> $headers response headers, keys lower-cased
     * @param string                $body    raw response body
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /** Case-insensitive header lookup; null when the header is absent. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
