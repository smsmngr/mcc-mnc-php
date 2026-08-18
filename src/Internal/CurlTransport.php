<?php

declare(strict_types=1);

namespace MccMnc\Internal;

use MccMnc\Exception\TransportException;

/** Default {@see HttpTransport} built on ext-curl. Zero external dependencies. */
final class CurlTransport implements HttpTransport
{
    public function request(string $url, array $headers, float $timeout): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Failed to initialize cURL');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headerLines,
            // Capture response headers (Retry-After in particular) as we go.
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (\count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return \strlen($line);
            },
        ]);

        if ($timeout > 0) {
            curl_setopt($handle, CURLOPT_TIMEOUT_MS, (int) round($timeout * 1000));
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            curl_close($handle);
            if ($error === '') {
                $error = curl_strerror($errno) ?? 'Unknown cURL error';
            }

            throw new TransportException(sprintf('cURL error %d: %s', $errno, $error), $errno);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new HttpResponse($status, $responseHeaders, (string) $body);
    }
}
