<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class CityQualityTest extends TestCase
{
    public function testOptionsKeepPreviousPositionsAndDefaults(): void
    {
        $calls = [];
        $client = new Client('fixture', retries: 0, transport: function ($url) use (&$calls) { $calls[] = $url; return [200, [], '{}']; });
        $client->city('Wien', 'AT', '9', true, 'de');
        $client->cityId('city_pin', true, 'de');
        $client->citySearch('Wie', 'AT', '9', 5, true, 'de');
        $client->cityNearest(48, 16, true, 'de');
        $client->cityNearby('Wien', 5, 'km', 'AT', '9', 3, true, 'de');
        foreach ($calls as $url) { parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query); $this->assertArrayNotHasKey('hierarchy', $query); $this->assertArrayNotHasKey('type', $query); $this->assertSame('true', $query['deep']); $this->assertSame('de', $query['lang']); }
        $client->city('Wien', type: 'city', grain: 'municipality', hierarchy: true, aliases: true, ambiguity: 'error');
        $client->cityId('city_pin', hierarchy: true);
        $client->citySearch('Wie', type: 'city', grain: 'municipality', hierarchy: true, aliases: true);
        $client->cityNearest(48, 16, country: 'AT', state: '9', type: 'city', grain: 'municipality', hierarchy: true);
        $client->cityNearby('Wien', type: 'city', grain: 'municipality', hierarchy: true, aliases: true, ambiguity: 'error');
        foreach (array_slice($calls, 5) as $index => $url) { parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query); $this->assertSame('true', $query['hierarchy']); if ($index !== 1) { $this->assertSame('city', $query['type']); $this->assertSame('municipality', $query['grain']); } if (in_array($index, [0, 2, 4], true)) { $this->assertSame('true', $query['aliases']); } if (in_array($index, [0, 4], true)) { $this->assertSame('error', $query['ambiguity']); } }
        parse_str(parse_url($calls[8], PHP_URL_QUERY), $nearest); $this->assertSame('AT', $nearest['country']); $this->assertSame('9', $nearest['state']);
    }

    public function testNullableHierarchyAndAmbiguityErrorPassThrough(): void
    {
        $body = ['id' => 'city_child', 'name' => 'Ward', 'grain' => 'subdivision', 'parent' => ['id' => 'city_parent', 'name' => null, 'name_local' => '堺市', 'country' => 'JP', 'state' => 'JP-27', 'type' => 'city', 'grain' => 'municipality', 'future' => true]];
        $client = new Client('fixture', retries: 0, transport: fn() => [200, [], json_encode($body, JSON_THROW_ON_ERROR)]);
        $this->assertSame($body, $client->cityId('city_child', hierarchy: true));
        $error = ['code' => 'invalid_request', 'message' => 'City name matches multiple places.', 'docs' => 'https://parseapi.com/docs/city', 'request_id' => 'req_fixture'];
        $client = new Client('fixture', retries: 0, transport: fn() => [400, [], json_encode($error, JSON_THROW_ON_ERROR)]);
        try { $client->city('Sakai', ambiguity: 'error'); $this->fail('Ambiguity error swallowed'); } catch (ParseAPIError $caught) { $this->assertSame(400, $caught->status); $this->assertSame('invalid_request', $caught->errorCode); }
    }
}
