<?php

declare(strict_types=1);

namespace MccMnc;

use MccMnc\Exception\ApiException;
use MccMnc\Exception\AuthException;
use MccMnc\Exception\NotFoundException;
use MccMnc\Exception\RateLimitException;
use MccMnc\Internal\CurlTransport;
use MccMnc\Internal\HttpResponse;
use MccMnc\Internal\HttpTransport;

/**
 * Client for the MCC-MNC.dev API v1.
 *
 * ```php
 * $client = new \MccMnc\Client('mcc_yourkey');
 * $row = $client->lookup('230', '02'); // MCC/MNC are always strings
 * ```
 *
 * Get a free API key at https://mcc-mnc.dev — docs at https://mcc-mnc.dev/docs.
 * No automatic retries are performed.
 *
 * `$fields` options take wire (snake_case) column names:
 * plmn, mcc, mnc, country, iso2, iso3, iso_numeric, dial_prefix, brand,
 * operator, status, type, bands, technology, ownership, subscribers,
 * subscribers_as_of, market_reach, market_reach_basis, rank, notes.
 */
final class Client
{
    /** Library version — kept in sync with composer.json. */
    public const VERSION = '0.1.0';

    public const DEFAULT_BASE_URL = 'https://mcc-mnc.dev/api/v1';

    /** Default request timeout in seconds. */
    public const DEFAULT_TIMEOUT = 10.0;

    /** API base URL, trailing slashes trimmed. */
    public readonly string $baseUrl;

    private readonly HttpTransport $transport;

    /**
     * @param string             $apiKey    your API key — get a free one at https://mcc-mnc.dev
     * @param string             $baseUrl   API base URL (trailing slashes trimmed)
     * @param float              $timeout   request timeout in seconds; 0 disables
     * @param HttpTransport|null $transport custom transport (testing, PSR-adapters);
     *                                      defaults to the built-in cURL transport
     *
     * @throws \InvalidArgumentException on empty $apiKey
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly float $timeout = self::DEFAULT_TIMEOUT,
        ?HttpTransport $transport = null,
    ) {
        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required — get a free key at https://mcc-mnc.dev');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
    }

    /**
     * GET /search — search by MCC ("230"), PLMN ("23002"), country name/ISO
     * code ("CZ", "Czech"), operator/brand name ("O2"), or a phone number
     * ("+420601123456", national formats auto-detected).
     *
     * @param string            $q      the search query (required)
     * @param list<string>|null $fields subset of Row columns to return (wire names)
     * @param int|null          $limit  default 50, max 500
     * @param int|null          $offset default 0
     * @param string|null       $format "json" (default) or "text" (TSV) — "text" returns the raw string
     *
     * @return SearchResult|string SearchResult for JSON, the raw TSV string for format "text"
     *
     * @throws \InvalidArgumentException on empty $q
     * @throws ApiException and subclasses on non-2xx responses
     */
    public function search(
        string $q,
        ?array $fields = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $format = null,
    ): SearchResult|string {
        if ($q === '') {
            throw new \InvalidArgumentException('q is required');
        }

        $response = $this->request('/search', [
            'q' => $q,
            'fields' => self::joinFields($fields),
            'format' => $format,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return self::isTextual($format) ? $response->body : SearchResult::fromArray($this->decode($response));
    }

    /**
     * GET /mcc/{mcc} — all networks for an MCC. Throws NotFoundException if none.
     *
     * @param list<string>|null $fields subset of Row columns to return (wire names)
     * @param string|null       $format "json" (default) or "text" (TSV)
     *
     * @return MccResult|string MccResult for JSON, the raw TSV string for format "text"
     *
     * @throws NotFoundException|ApiException
     */
    public function mcc(string $mcc, ?array $fields = null, ?string $format = null): MccResult|string
    {
        $response = $this->request('/mcc/' . rawurlencode($mcc), [
            'fields' => self::joinFields($fields),
            'format' => $format,
        ]);

        return self::isTextual($format) ? $response->body : MccResult::fromArray($this->decode($response));
    }

    /**
     * GET /mcc/{mcc}/{mnc} — a single network. Throws NotFoundException if unknown.
     * MCC and MNC are strings — leading zeros are significant ("02" !== "2").
     *
     * @param list<string>|null $fields subset of Row columns to return (wire names)
     * @param string|null       $format "json" (default) or "text" (TSV)
     *
     * @return Row|string Row for JSON, the raw TSV string for format "text"
     *
     * @throws NotFoundException|ApiException
     */
    public function lookup(string $mcc, string $mnc, ?array $fields = null, ?string $format = null): Row|string
    {
        $response = $this->request('/mcc/' . rawurlencode($mcc) . '/' . rawurlencode($mnc), [
            'fields' => self::joinFields($fields),
            'format' => $format,
        ]);

        return self::isTextual($format) ? $response->body : Row::fromArray($this->decode($response));
    }

    /**
     * GET /plmn/{plmn} — exact PLMN lookup (e.g. "23002"). Throws
     * NotFoundException if unknown.
     *
     * @param list<string>|null $fields subset of Row columns to return (wire names)
     * @param string|null       $format "json" (default) or "text" (TSV)
     *
     * @return Row|string Row for JSON, the raw TSV string for format "text"
     *
     * @throws NotFoundException|ApiException
     */
    public function plmn(string $plmn, ?array $fields = null, ?string $format = null): Row|string
    {
        $response = $this->request('/plmn/' . rawurlencode($plmn), [
            'fields' => self::joinFields($fields),
            'format' => $format,
        ]);

        return self::isTextual($format) ? $response->body : Row::fromArray($this->decode($response));
    }

    /**
     * GET /dataset — the full dump (no phone logic).
     *
     * @param list<string>|null $fields subset of Row columns to return (wire names)
     * @param string|null       $format "json" (default), "csv" or "text" —
     *                                  "csv"/"text" return the raw string
     *
     * @return DatasetResult|string DatasetResult for JSON, the raw CSV/TSV string otherwise
     *
     * @throws ApiException and subclasses on non-2xx responses
     */
    public function dataset(?array $fields = null, ?string $format = null): DatasetResult|string
    {
        $response = $this->request('/dataset', [
            'fields' => self::joinFields($fields),
            'format' => $format,
        ]);

        return self::isTextual($format) ? $response->body : DatasetResult::fromArray($this->decode($response));
    }

    /**
     * @param array<string, string|int|null> $query null values are omitted
     *
     * @throws ApiException and subclasses on non-2xx responses
     */
    private function request(string $path, array $query): HttpResponse
    {
        $params = array_filter($query, static fn ($value) => $value !== null);
        $url = $this->baseUrl . $path;
        $queryString = http_build_query($params, '', '&');
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $response = $this->transport->request($url, [
            'X-API-Key' => $this->apiKey,
            'User-Agent' => 'mcc-mnc-dev-php/' . self::VERSION,
        ], $this->timeout);

        if ($response->status < 200 || $response->status >= 300) {
            throw $this->errorFromResponse($response);
        }

        return $response;
    }

    /** Map a non-2xx response to the matching typed exception. */
    private function errorFromResponse(HttpResponse $response): ApiException
    {
        $code = null;
        $message = 'HTTP ' . $response->status;

        if ($response->body !== '') {
            $decoded = json_decode($response->body, true);
            if (\is_array($decoded) && isset($decoded['error']) && \is_array($decoded['error'])) {
                $error = $decoded['error'];
                if (isset($error['code']) && \is_string($error['code'])) {
                    $code = $error['code'];
                }
                if (isset($error['message']) && \is_string($error['message'])) {
                    $message = $error['message'];
                }
            } elseif ($decoded === null) {
                // text-format errors are a single plain-text line
                $trimmed = trim($response->body);
                if ($trimmed !== '') {
                    $message = $trimmed;
                }
            }
        }

        return match ($response->status) {
            401 => new AuthException($message, $code),
            404 => new NotFoundException($message, $code),
            429 => new RateLimitException($message, $code, self::parseRetryAfter($response->header('Retry-After'))),
            default => new ApiException($message, $response->status, $code),
        };
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ApiException when a 2xx body is not a JSON object/array
     */
    private function decode(HttpResponse $response): array
    {
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ApiException(
                'Invalid JSON in API response: ' . $exception->getMessage(),
                $response->status,
                null,
                $exception,
            );
        }

        if (!\is_array($decoded)) {
            throw new ApiException('Unexpected non-object JSON in API response', $response->status);
        }

        return $decoded;
    }

    /** Parse a Retry-After header (delta-seconds or HTTP-date) into seconds. */
    private static function parseRetryAfter(?string $header): ?float
    {
        if ($header === null) {
            return null;
        }
        $header = trim($header);
        if ($header === '') {
            return null;
        }
        if (is_numeric($header)) {
            return max(0.0, (float) $header);
        }
        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            return max(0.0, (float) ($timestamp - time()));
        }

        return null;
    }

    /** @param list<string>|null $fields */
    private static function joinFields(?array $fields): ?string
    {
        if ($fields === null || $fields === []) {
            return null;
        }

        return implode(',', $fields);
    }

    /** Whether the requested format resolves to a raw string body. */
    private static function isTextual(?string $format): bool
    {
        return $format === 'text' || $format === 'csv';
    }
}
