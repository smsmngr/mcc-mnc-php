<?php

declare(strict_types=1);

namespace MccMnc\Internal;

use MccMnc\Exception\TransportException;

/**
 * Minimal HTTP transport abstraction so the {@see \MccMnc\Client} can be tested
 * (or embedded) without real network access. The API is GET-only.
 */
interface HttpTransport
{
    /**
     * Perform a GET request and return the raw response, whatever its status.
     *
     * @param string                $url     absolute URL, query string included
     * @param array<string, string> $headers request headers (name => value)
     * @param float                 $timeout total timeout in seconds; <= 0 disables the timeout
     *
     * @throws TransportException on network-level failure (DNS, connect, TLS, timeout)
     */
    public function request(string $url, array $headers, float $timeout): HttpResponse;
}
