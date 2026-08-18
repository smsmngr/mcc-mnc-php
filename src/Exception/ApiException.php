<?php

declare(strict_types=1);

namespace MccMnc\Exception;

/**
 * Base exception for any non-2xx MCC-MNC.dev API response.
 *
 * Hierarchy:
 *   ApiException (base — carries HTTP $status + machine-readable $errorCode)
 *   ├── AuthException       (401 — missing_api_key / invalid_api_key)
 *   ├── NotFoundException   (404 — not_found)
 *   └── RateLimitException  (429 — rate_limited, carries $retryAfter seconds)
 *
 * `getCode()` also returns the HTTP status for convenience.
 */
class ApiException extends \RuntimeException
{
    /**
     * @param string      $message   human-readable error message from the API (or "HTTP <status>")
     * @param int         $status    HTTP status code of the response
     * @param string|null $errorCode machine-readable code from the response body
     *                               (e.g. "bad_request"), null if the body had none
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
