<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class CityQueriesTest extends TestCase
{
    public function testFiltersEncodeZeroBoundsDatelineAndOpaqueCursor(): void
    {
        $calls = [];
        $client = new Client('fixture', retries: 0, transport: function ($url) use (&$calls) { $calls[] = $url; return [200, [], '{}']; });
        $filters = ['minPopulation' => 0, 'maxPopulation' => 5000000, 'timezone' => 'Asia/Tokyo', 'bbox' => '170,-20,-170,20', 'sort' => 'population', 'order' => 'desc', 'cursor' => 'opaque+/=cursor?&'];
        $client->cityList(...array_merge(['country' => 'JP', 'limit' => 5, 'hierarchy' => true], $filters));
        $client->cityNearbyCoords(0, 0, ...array_merge(['radius' => 20, 'unit' => 'mi', 'country' => 'JP', 'state' => '27'], $filters));
        $client->citySearch('Sak', ...array_merge(['page' => true], $filters));
        $client->cityNearby('Sakai', ...array_merge(['page' => true], $filters));
        foreach ($calls as $index => $url) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $this->assertSame('/city' . ($index === 3 ? '/Sakai/nearby' : ''), parse_url($url, PHP_URL_PATH));
            foreach (['min_population' => '0', 'max_population' => '5000000', 'timezone' => 'Asia/Tokyo', 'bbox' => '170,-20,-170,20', 'sort' => 'population', 'order' => 'desc', 'cursor' => 'opaque+/=cursor?&'] as $name => $value) { $this->assertSame($value, $query[$name]); }
            if ($index < 2) { $this->assertArrayNotHasKey('page', $query); } else { $this->assertSame('true', $query['page']); }
        }
        parse_str(parse_url($calls[0], PHP_URL_QUERY), $browse); $this->assertSame('true', $browse['browse']);
        parse_str(parse_url($calls[1], PHP_URL_QUERY), $coords); $this->assertSame('true', $coords['nearby']); $this->assertSame('0', $coords['lat']); $this->assertSame('0', $coords['lon']);
    }

    public function testNewMethodDefaultsAndCursorOnlyOmitOtherOptions(): void
    {
        $calls = [];
        $client = new Client('fixture', retries: 0, transport: function ($url) use (&$calls) { $calls[] = $url; return [200, [], '{}']; });
        $client->cityList(); $client->cityNearbyCoords(0, 0); $client->citySearch('Sak', cursor: 'opaque');
        foreach (['browse=true&format=list', 'lat=0&lon=0&nearby=true&format=list', 'q=Sak&cursor=opaque'] as $index => $expected) { parse_str($expected, $want); parse_str(parse_url($calls[$index], PHP_URL_QUERY), $got); $this->assertEquals($want, $got); }
    }

    public function testPaginationPassesThroughAndInvalidCursorUsesExistingError(): void
    {
        $body = ['cities' => [['id' => 'city_one', 'name' => 'One']], 'next_cursor' => null, 'future' => true];
        $client = new Client('fixture', retries: 0, transport: fn() => [200, [], json_encode($body, JSON_THROW_ON_ERROR)]);
        $this->assertSame($body, $client->cityList());
        $error = ['code' => 'invalid_request', 'message' => 'Cursor does not match this query.', 'docs' => 'https://parseapi.com/docs/city', 'request_id' => 'req_fixture'];
        $client = new Client('fixture', retries: 0, transport: fn() => [400, [], json_encode($error, JSON_THROW_ON_ERROR)]);
        try { $client->citySearch('Sak', cursor: 'bad'); $this->fail('Invalid cursor swallowed'); } catch (ParseAPIError $caught) { $this->assertSame(400, $caught->status); $this->assertSame('invalid_request', $caught->errorCode); }
    }
}
