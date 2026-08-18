<?php

declare(strict_types=1);

namespace MccMnc\Tests;

use MccMnc\DatasetResult;
use MccMnc\PhoneInfo;
use MccMnc\Row;
use MccMnc\SearchResult;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase
{
    public function testFromArrayMapsAllWireKeys(): void
    {
        $row = Row::fromArray([
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
            'bands' => 'GSM 900 / GSM 1800',
            'technology' => ['GSM', 'LTE'],
            'ownership' => 'PPF',
            'subscribers' => 5.987,
            'subscribers_as_of' => '2021-Q2',
            'market_reach' => 37.1,
            'market_reach_basis' => 'country_total',
            'rank' => 2,
            'notes' => 'Former Eurotel',
        ]);

        self::assertSame('23002', $row->plmn);
        self::assertSame('230', $row->mcc);
        self::assertSame('02', $row->mnc);
        self::assertSame('Czech Republic', $row->country);
        self::assertSame('CZ', $row->iso2);
        self::assertSame('CZE', $row->iso3);
        self::assertSame('203', $row->isoNumeric);
        self::assertSame('+420', $row->dialPrefix);
        self::assertSame('O2', $row->brand);
        self::assertSame('O2 Czech Republic', $row->operator);
        self::assertSame('Operational', $row->status);
        self::assertSame('National', $row->type);
        self::assertSame('GSM 900 / GSM 1800', $row->bands);
        self::assertSame(['GSM', 'LTE'], $row->technology);
        self::assertSame('PPF', $row->ownership);
        self::assertSame(5.987, $row->subscribers);
        self::assertSame('2021-Q2', $row->subscribersAsOf);
        self::assertSame(37.1, $row->marketReach);
        self::assertSame('country_total', $row->marketReachBasis);
        self::assertSame(2, $row->rank);
        self::assertSame('Former Eurotel', $row->notes);
    }

    public function testFromArrayToleratesMissingKeys(): void
    {
        // fields=brand projection: everything else is absent on the wire
        $row = Row::fromArray(['brand' => 'O2']);

        self::assertSame('O2', $row->brand);
        self::assertNull($row->plmn);
        self::assertNull($row->mcc);
        self::assertNull($row->mnc);
        self::assertNull($row->technology);
        self::assertNull($row->subscribers);
        self::assertNull($row->rank);
        self::assertNull($row->notes);
    }

    public function testFromArrayToleratesExplicitNulls(): void
    {
        $row = Row::fromArray([
            'plmn' => '90177',
            'mcc' => '901',
            'mnc' => '77',
            'status' => 'Operational',
            'type' => 'International',
            'country' => null,
            'technology' => null,
            'subscribers' => null,
            'market_reach' => null,
            'rank' => null,
        ]);

        self::assertSame('901', $row->mcc);
        self::assertNull($row->country);
        self::assertNull($row->technology);
        self::assertNull($row->subscribers);
        self::assertNull($row->marketReach);
        self::assertNull($row->rank);
    }

    public function testFromArrayKeepsZeroPaddedStringsVerbatim(): void
    {
        $row = Row::fromArray(['mcc' => '230', 'mnc' => '02']);

        self::assertSame('02', $row->mnc);
        self::assertNotSame('2', $row->mnc);
    }

    public function testSearchResultFromArrayToleratesMissingKeys(): void
    {
        $result = SearchResult::fromArray([]);

        self::assertSame('', $result->query);
        self::assertNull($result->phone);
        self::assertSame(0, $result->count);
        self::assertSame([], $result->results);
    }

    public function testDatasetResultFromArrayToleratesMissingKeys(): void
    {
        $result = DatasetResult::fromArray(['results' => [['plmn' => '23002']]]);

        self::assertSame(1, $result->count); // falls back to count(results)
        self::assertSame('', $result->generatedAt);
        self::assertSame('', $result->license);
        self::assertSame('23002', $result->results[0]->plmn);
    }

    public function testPhoneInfoFromArrayToleratesMissingKeys(): void
    {
        $phone = PhoneInfo::fromArray(['valid' => true]);

        self::assertTrue($phone->valid);
        self::assertFalse($phone->possible);
        self::assertNull($phone->type);
        self::assertNull($phone->country);
        self::assertNull($phone->callingCode);
        self::assertNull($phone->e164);
    }
}
