<?php

declare(strict_types=1);

namespace MccMnc\Exception;

/**
 * 429 — rate limited (100 requests/second per key).
 *
 * The client performs no automatic retries; `$retryAfter` tells you when it is
 * safe to try again.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param float|null $retryAfter seconds to wait before retrying, parsed from
     *                               the Retry-After header; null if absent/unparseable
     */
    public function __construct(
        string $message,
        ?string $errorCode = null,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($message, 429, $errorCode);
    }
}
