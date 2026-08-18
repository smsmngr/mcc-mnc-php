<?php

declare(strict_types=1);

namespace MccMnc\Tests;

use MccMnc\Client;
use MccMnc\DatasetResult;
use MccMnc\Exception\ApiException;
use MccMnc\Exception\AuthException;
use MccMnc\Exception\NotFoundException;
use MccMnc\Exception\RateLimitException;
use MccMnc\MccResult;
use MccMnc\Row;
use MccMnc\SearchResult;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const ROW = [
        'plmn' => '23002',
        'mcc' => '230',
        'mnc' => '02',
        'country' => 'Czech Republic',
        'iso2' => 'CZ',
        'iso3' => 'CZE',
        'iso_numeric' => '203',
        'dial_prefix' => '+420',
        'brand' => 'O2',
        'operator' => 'O2 Czech Republic',
        'status' => 'Operational',
        'type' => 'National',
        'bands' => 'GSM 900 / GSM 1800 / LTE 800',
        'technology' => ['GSM-900/1800 MHz (GPRS, EDGE)'],
        'ownership' => 'PPF',
        'subscribers' => 5.987,
        'subscribers_as_of' => '2021-Q2',
        'market_reach' => 37.1,
        'market_reach_basis' => 'country_total',
        'rank' => 2,
        'notes' => 'Former Eurotel',
    ];

    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
    }

    private function client(): Client
    {
        return new Client('mcc_testkey', transport: $this->transport);
    }

    /** Path of the single recorded request URL. */
    private function requestedPath(): string
    {
        return (string) parse_url($this->transport->onlyRequest()['url'], PHP_URL_PATH);
    }

    /** Decoded query parameters of the single recorded request URL. */
    private function requestedQuery(): array
    {
        $query = parse_url($this->transport->onlyRequest()['url'], PHP_URL_QUERY);
        if ($query === null || $query === false) {
            return [];
        }
        parse_str($query, $params);

        return $params;
    }

    // ---- construction ----------------------------------------------------

    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('');
    }

    public function testVersionConstant(): void
    {
        self::assertSame('0.1.0', Client::VERSION);
    }

    public function testDefaultBaseUrlAndTimeout(): void
    {
        $client = $this->client();

        self::assertSame('https://mcc-mnc.dev/api/v1', $client->baseUrl);
        self::assertSame(10.0, $client->timeout);
    }

    // ---- happy paths -----------------------------------------------------

    public function testSearchReturnsEnvelopeAndEncodesQuery(): void
    {
        $this->transport->queueJson([
            'query' => '+420 601 123 456',
            'phone' => [
                'valid' => true,
                'possible' => true,
                'type' => 'MOBILE',
                'country' => 'CZ',
                'calling_code' => '420',
                'e164' => '+420601123456',
                'international' => '+420 601 123 456',
                'national' => '601 123 456',
            ],
            'count' => 1,
            'results' => [self::ROW],
        ]);

        $result = $this->client()->search('+420 601 123 456');

        self::assertInstanceOf(SearchResult::class, $result);
        self::assertSame('+420 601 123 456', $result->query);
        self::assertSame(1, $result->count);
        self::assertNotNull($result->phone);
        self::assertTrue($result->phone->valid);
        self::assertSame('CZ', $result->phone->country);
        self::assertSame('420', $result->phone->callingCode);
        self::assertCount(1, $result->results);
        self::assertSame('O2 Czech Republic', $result->results[0]->operator);

        self::assertSame('/api/v1/search', $this->requestedPath());
        self::assertSame(['q' => '+420 601 123 456'], $this->requestedQuery());
        // '+' must be percent-encoded on the wire, space as '+'
        self::assertStringContainsString('q=%2B420+601+123+456', $this->transport->onlyRequest()['url']);
    }

    public function testSearchPassesLimitAndOffset(): void
    {
        $this->transport->queueJson(['query' => 'O2', 'phone' => null, 'count' => 120, 'results' => []]);

        $result = $this->client()->search('O2', limit: 5, offset: 10);

        self::assertInstanceOf(SearchResult::class, $result);
        self::assertNull($result->phone);
        self::assertSame(120, $result->count);
        self::assertSame(['q' => 'O2', 'limit' => '5', 'offset' => '10'], $this->requestedQuery());
    }

    public function testSearchRejectsEmptyQueryWithoutRequesting(): void
    {
        try {
            $this->client()->search('');
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('q is required', $exception->getMessage());
        }

        self::assertSame([], $this->transport->requests);
    }

    public function testMccReturnsEnvelope(): void
    {
        $this->transport->queueJson(['mcc' => '230', 'count' => 1, 'results' => [self::ROW]]);

        $result = $this->client()->mcc('230');

        self::assertInstanceOf(MccResult::class, $result);
        self::assertSame('230', $result->mcc);
        self::assertSame(1, $result->count);
        self::assertCount(1, $result->results);
        self::assertSame('/api/v1/mcc/230', $this->requestedPath());
        self::assertSame([], $this->requestedQuery());
    }

    public function testLookupReturnsRowAndPreservesZeroPadding(): void
    {
        $this->transport->queueJson(self::ROW);

        $row = $this->client()->lookup('230', '02');

        self::assertInstanceOf(Row::class, $row);
        self::assertSame('02', $row->mnc); // string "02", not "2"
        self::assertSame('23002', $row->plmn);
        self::assertSame('O2', $row->brand);
        self::assertSame(['GSM-900/1800 MHz (GPRS, EDGE)'], $row->technology);
        self::assertSame(5.987, $row->subscribers);
        self::assertSame(37.1, $row->marketReach);
        self::assertSame(2, $row->rank);
        self::assertSame('/api/v1/mcc/230/02', $this->requestedPath());
    }

    public function testPathParametersAreUrlEncoded(): void
    {
        $this->transport->queueJson(self::ROW);

        $this->client()->lookup('2 30', '0/2');

        self::assertStringEndsWith('/api/v1/mcc/2%2030/0%2F2', $this->transport->onlyRequest()['url']);
    }

    public function testPlmnReturnsRow(): void
    {
        $this->transport->queueJson(self::ROW);

        $row = $this->client()->plmn('23002');

        self::assertInstanceOf(Row::class, $row);
        self::assertSame('23002', $row->plmn);
        self::assertSame('/api/v1/plmn/23002', $this->requestedPath());
    }

    public function testDatasetReturnsEnvelopeByDefault(): void
    {
        $this->transport->queueJson([
            'count' => 1,
            'generated_at' => '2026-08-18T00:00:00Z',
            'license' => 'CC BY-SA 4.0',
            'results' => [self::ROW],
        ]);

        $result = $this->client()->dataset();

        self::assertInstanceOf(DatasetResult::class, $result);
        self::assertSame(1, $result->count);
        self::assertSame('2026-08-18T00:00:00Z', $result->generatedAt);
        self::assertSame('CC BY-SA 4.0', $result->license);
        self::assertCount(1, $result->results);
        self::assertSame('/api/v1/dataset', $this->requestedPath());
        self::assertStringNotContainsString('?', $this->transport->onlyRequest()['url']);
    }

    // ---- fields and format on the wire -----------------------------------

    public function testFieldsAreCommaJoined(): void
    {
        $this->transport->queueJson(['query' => 'cz', 'phone' => null, 'count' => 0, 'results' => []]);

        $this->client()->search('cz', fields: ['mcc', 'mnc', 'brand']);

        self::assertSame(['q' => 'cz', 'fields' => 'mcc,mnc,brand'], $this->requestedQuery());
    }

    public function testDatasetCsvReturnsRawStringAndSendsFormat(): void
    {
        $csv = "plmn,mcc,mnc\r\n23002,230,02\r\n";
        $this->transport->queueText($csv, headers: ['content-type' => 'text/csv']);

        $result = $this->client()->dataset(fields: ['plmn', 'mcc', 'mnc'], format: 'csv');

        self::assertSame($csv, $result);
        self::assertSame(['fields' => 'plmn,mcc,mnc', 'format' => 'csv'], $this->requestedQuery());
    }

    public function testSearchTextFormatReturnsTsvString(): void
    {
        $tsv = "plmn\tmcc\tmnc\n23002\t230\t02\n";
        $this->transport->queueText($tsv);

        $result = $this->client()->search('23002', format: 'text');

        self::assertSame($tsv, $result);
        self::assertSame(['q' => '23002', 'format' => 'text'], $this->requestedQuery());
    }

    public function testLookupTextFormatReturnsRawString(): void
    {
        $tsv = "plmn\tmcc\tmnc\n23002\t230\t02\n";
        $this->transport->queueText($tsv);

        $result = $this->client()->lookup('230', '02', format: 'text');

        self::assertSame($tsv, $result);
    }

    // ---- error mapping ---------------------------------------------------

    public function testMaps401ToAuthException(): void
    {
        $this->transport->queueJson(
            ['error' => ['code' => 'invalid_api_key', 'message' => 'Invalid API key']],
            status: 401,
        );

        try {
            $this->client()->plmn('23002');
            self::fail('Expected AuthException');
        } catch (AuthException $exception) {
            self::assertInstanceOf(ApiException::class, $exception);
            self::assertSame(401, $exception->status);
            self::assertSame('invalid_api_key', $exception->errorCode);
            self::assertSame('Invalid API key', $exception->getMessage());
        }
    }

    public function testMaps404ToNotFoundException(): void
    {
        $this->transport->queueJson(
            ['error' => ['code' => 'not_found', 'message' => 'Unknown network']],
            status: 404,
        );

        $this->expectException(NotFoundException::class);

        $this->client()->lookup('999', '99');
    }

    public function testMaps429ToRateLimitExceptionWithRetryAfter(): void
    {
        $this->transport->queueJson(
            ['error' => ['code' => 'rate_limited', 'message' => 'Slow down']],
            status: 429,
            headers: ['Retry-After' => '10'],
        );

        try {
            $this->client()->search('cz');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $exception) {
            self::assertSame(429, $exception->status);
            self::assertSame('rate_limited', $exception->errorCode);
            self::assertSame(10.0, $exception->retryAfter);
        }
    }

    public function testRetryAfterIsNullWhenHeaderMissing(): void
    {
        $this->transport->queueJson(
            ['error' => ['code' => 'rate_limited', 'message' => 'Slow down']],
            status: 429,
        );

        try {
            $this->client()->search('cz');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $exception) {
            self::assertNull($exception->retryAfter);
        }
    }

    public function testMaps400ToBaseApiException(): void
    {
        $this->transport->queueJson(
            ['error' => ['code' => 'bad_request', 'message' => 'Unknown field: bogus']],
            status: 400,
        );

        try {
            $this->client()->dataset();
            self::fail('Expected ApiException');
        } catch (ApiException $exception) {
            self::assertNotInstanceOf(AuthException::class, $exception);
            self::assertNotInstanceOf(NotFoundException::class, $exception);
            self::assertNotInstanceOf(RateLimitException::class, $exception);
            self::assertSame(400, $exception->status);
            self::assertSame('bad_request', $exception->errorCode);
            self::assertSame('Unknown field: bogus', $exception->getMessage());
        }
    }

    public function testPlainTextErrorLineBecomesMessage(): void
    {
        $this->transport->queueText('not_found: unknown PLMN', status: 404);

        try {
            $this->client()->plmn('00000', format: 'text');
            self::fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            self::assertSame('not_found: unknown PLMN', $exception->getMessage());
            self::assertNull($exception->errorCode);
        }
    }

    public function testErrorWithEmptyBodyFallsBackToHttpStatusMessage(): void
    {
        $this->transport->queueText('', status: 500);

        try {
            $this->client()->dataset();
            self::fail('Expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(500, $exception->status);
            self::assertSame('HTTP 500', $exception->getMessage());
        }
    }

    // ---- headers, base URL, timeout --------------------------------------

    public function testSendsApiKeyAndVersionedUserAgent(): void
    {
        $this->transport->queueJson(self::ROW);

        $this->client()->plmn('23002');

        $headers = $this->transport->onlyRequest()['headers'];
        self::assertSame('mcc_testkey', $headers['X-API-Key']);
        self::assertSame('mcc-mnc-dev-php/' . Client::VERSION, $headers['User-Agent']);
        self::assertSame('mcc-mnc-dev-php/0.1.0', $headers['User-Agent']);
    }

    public function testCustomBaseUrlTrailingSlashesTrimmed(): void
    {
        $this->transport->queueJson(self::ROW);
        $client = new Client('mcc_testkey', baseUrl: 'http://localhost:8788/api/v1/', transport: $this->transport);

        $client->plmn('23002');

        self::assertSame('http://localhost:8788/api/v1/plmn/23002', $this->transport->onlyRequest()['url']);
    }

    public function testTimeoutIsPassedToTransport(): void
    {
        $this->transport->queueJson(self::ROW);
        $client = new Client('mcc_testkey', timeout: 2.5, transport: $this->transport);

        $client->plmn('23002');

        self::assertSame(2.5, $this->transport->onlyRequest()['timeout']);
    }

    public function testInvalidJsonOn2xxThrowsApiException(): void
    {
        $this->transport->queueText('this is not json');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        $this->client()->plmn('23002');
    }
}
