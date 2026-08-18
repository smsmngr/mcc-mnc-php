# mcc-mnc/client

[![Packagist version](https://img.shields.io/packagist/v/mcc-mnc/client.svg)](https://packagist.org/packages/mcc-mnc/client)
[![license: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE)
[![php >= 8.1](https://img.shields.io/badge/php-%3E%3D8.1-777bb3.svg)](https://www.php.net)

Official PHP client for the [MCC-MNC.dev](https://mcc-mnc.dev) API — mobile network
code (MCC/MNC/PLMN) lookup, operator search, and phone-number network detection.
Zero runtime dependencies beyond `ext-curl` and `ext-json`; PHP 8.1+.

```sh
composer require mcc-mnc/client
```

Get a free API key at **https://mcc-mnc.dev**.

## Quickstart

```php
use MccMnc\Client;

$client = new Client('mcc_yourkey');

// Search by operator, country, MCC, PLMN — or a phone number
$result = $client->search('+420601123456');
echo $result->phone?->country, ' ', $result->results[0]->operator ?? '';
// "CZ" "O2 Czech Republic"

// Exact lookup — MCC/MNC are always strings, leading zeros matter ("02" !== "2")
$row = $client->lookup('230', '02');
echo $row->brand, ' ', $row->bands; // "O2" "GSM 900 / GSM 1800 / LTE 800 / ..."
```

## Error handling

```php
use MccMnc\Client;
use MccMnc\Exception\NotFoundException;
use MccMnc\Exception\RateLimitException;

$client = new Client('mcc_yourkey');

try {
    $row = $client->plmn('23002');
} catch (RateLimitException $e) {
    echo "Rate limited — retry in {$e->retryAfter}s"; // parsed from Retry-After
} catch (NotFoundException $e) {
    echo 'Unknown PLMN';
}
// AuthException (401) and the ApiException base ($status / $errorCode) also exist.
```

The client performs **no automatic retries** — `$retryAfter` tells you when it is
safe to try again.

## More

- `$client->mcc('230')` — every network for an MCC
- `$client->dataset()` — the full dataset (`$client->dataset(format: 'csv')` returns a CSV string)
- Every method accepts `fields: ['mcc', 'mnc', 'brand']` (wire column names) to slim responses,
  and `format: 'text'` for TSV output as a raw string
- Constructor options: `new Client('mcc_yourkey', baseUrl: ..., timeout: 5.0, transport: ...)`
  — inject your own `MccMnc\Internal\HttpTransport` for testing

Full API documentation: **https://mcc-mnc.dev/docs**
