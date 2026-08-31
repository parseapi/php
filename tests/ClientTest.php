<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
	/** @var array<array{url: string, headers: array}> */
	private array $calls = [];

	private function stubClient(?array $responses = null, int $retries = 0): Client
	{
		$this->calls = [];
		$queue = $responses;
		return new Client(
			apiKey: 'test_key_123',
			retries: $retries,
			transport: function (string $url, array $headers) use (&$queue): array {
				$this->calls[] = ['url' => $url, 'headers' => $headers];
				if ($queue === null) {
					return [200, [], '{}'];
				}
				if ($queue === []) {
					throw new \LogicException('stub exhausted');
				}
				return array_shift($queue);
			},
		);
	}

	public static function urlTable(): array
	{
		return [
			'ip' => [fn (Client $p) => $p->ip('8.8.8.8'), 'https://api.parseapi.com/ip/8.8.8.8'],
			'ipSelf' => [fn (Client $p) => $p->ipSelf(), 'https://api.parseapi.com/ip'],
			'ip deep' => [fn (Client $p) => $p->ip('8.8.8.8', deep: true), 'https://api.parseapi.com/ip/8.8.8.8?deep=true'],
			'continent' => [fn (Client $p) => $p->continent('NA'), 'https://api.parseapi.com/continent/NA'],
			'continentCountries' => [fn (Client $p) => $p->continentCountries('NA'), 'https://api.parseapi.com/continent/NA/countries'],
			'bloc' => [fn (Client $p) => $p->bloc('EU'), 'https://api.parseapi.com/bloc/EU'],
			'blocCountries' => [fn (Client $p) => $p->blocCountries('SCHENGEN'), 'https://api.parseapi.com/bloc/SCHENGEN/countries'],
			'country' => [fn (Client $p) => $p->country('US'), 'https://api.parseapi.com/country/US'],
			'countryStates' => [fn (Client $p) => $p->countryStates('US'), 'https://api.parseapi.com/country/US/states'],
			'state' => [fn (Client $p) => $p->state('NC', 'US'), 'https://api.parseapi.com/state/NC?country=US'],
			'stateDistricts' => [fn (Client $p) => $p->stateDistricts('NC', 'US'), 'https://api.parseapi.com/state/NC/districts?country=US'],
			'district' => [fn (Client $p) => $p->district('37081'), 'https://api.parseapi.com/district/37081'],
			'city' => [fn (Client $p) => $p->city('charlotte', state: 'NC'), 'https://api.parseapi.com/city/charlotte?state=NC'],
			'cityId' => [fn (Client $p) => $p->cityId('city_mb8mbqrkz8zb'), 'https://api.parseapi.com/city/id/city_mb8mbqrkz8zb'],
			'citySearch' => [fn (Client $p) => $p->citySearch('char', country: 'US', limit: 10), 'https://api.parseapi.com/city?q=char&country=US&limit=10'],
			'cityNearest' => [fn (Client $p) => $p->cityNearest(35.2271, -80.8431), 'https://api.parseapi.com/city?lat=35.2271&lon=-80.8431'],
			'cityNearby' => [fn (Client $p) => $p->cityNearby('denver', radius: 8, unit: 'mi', limit: 3), 'https://api.parseapi.com/city/denver/nearby?radius=8&unit=mi&limit=3'],
			'stateName' => [fn (Client $p) => $p->state('colorado'), 'https://api.parseapi.com/state/colorado'],
			'postal' => [fn (Client $p) => $p->postal('28202', 'US'), 'https://api.parseapi.com/postal/28202?country=US'],
			'postalBare' => [fn (Client $p) => $p->postal('SW1A 1AA'), 'https://api.parseapi.com/postal/SW1A%201AA'],
			'postalNearby' => [fn (Client $p) => $p->postalNearby('28202', 'US', radius: 40, unit: 'km'), 'https://api.parseapi.com/postal/28202/nearby?country=US&radius=40&unit=km'],
			'postalDistance' => [fn (Client $p) => $p->postalDistance('28202', '10001', 'US'), 'https://api.parseapi.com/postal/28202/distance/10001?country=US'],
			'email' => [fn (Client $p) => $p->email('a@b.com'), 'https://api.parseapi.com/email/a%40b.com'],
			'vat' => [fn (Client $p) => $p->vat('DE136695976'), 'https://api.parseapi.com/vat/DE136695976'],
			'iban' => [fn (Client $p) => $p->iban('DE89370400440532013000'), 'https://api.parseapi.com/iban/DE89370400440532013000'],
			'iban country' => [fn (Client $p) => $p->iban('89370400440532013000', 'DE'), 'https://api.parseapi.com/iban/89370400440532013000?country=DE'],
			'npi' => [fn (Client $p) => $p->npi('1881018208'), 'https://api.parseapi.com/npi/1881018208'],
			'vat from deep' => [fn (Client $p) => $p->vat('DE136695976', from: 'IE6388047V', deep: true), 'https://api.parseapi.com/vat/DE136695976?deep=true&from=IE6388047V'],
			'phone encodes plus' => [fn (Client $p) => $p->phone('+14155552671', deep: true), 'https://api.parseapi.com/phone/%2B14155552671?deep=true'],
			'carrier encodes plus' => [fn (Client $p) => $p->carrier('+14155552671'), 'https://api.parseapi.com/carrier/%2B14155552671'],
			'caller with country' => [fn (Client $p) => $p->caller('4155552671', country: 'US'), 'https://api.parseapi.com/caller/4155552671?country=US'],
			'hlr' => [fn (Client $p) => $p->hlr('+447712345678'), 'https://api.parseapi.com/hlr/%2B447712345678'],
			'domain' => [fn (Client $p) => $p->domain('example.com'), 'https://api.parseapi.com/domain/example.com'],
			'mx' => [fn (Client $p) => $p->mx('example.com'), 'https://api.parseapi.com/mx/example.com'],
			'useragent' => [fn (Client $p) => $p->useragent('TestUA/1.0'), 'https://api.parseapi.com/useragent'],
			'vin' => [fn (Client $p) => $p->vin('1HGCM82633A004352'), 'https://api.parseapi.com/vin/1HGCM82633A004352'],
			'vin deep' => [fn (Client $p) => $p->vin('1HGCM82633A004352', true), 'https://api.parseapi.com/vin/1HGCM82633A004352?deep=true'],
			'currency' => [fn (Client $p) => $p->currency('USD'), 'https://api.parseapi.com/currency/USD'],
			'currencyRate' => [fn (Client $p) => $p->currencyRate('USD', 'EUR'), 'https://api.parseapi.com/currency/USD/EUR'],
			'currencyRate date amount' => [fn (Client $p) => $p->currencyRate('USD', 'JPY', date: '2026-08-28', amount: 100), 'https://api.parseapi.com/currency/USD/JPY?date=2026-08-28&amount=100'],
			'language' => [fn (Client $p) => $p->language('en'), 'https://api.parseapi.com/language/en'],
			'name encodes spaces' => [fn (Client $p) => $p->name('Smith, John'), 'https://api.parseapi.com/name/Smith%2C%20John'],
			'timezone encodes slash' => [fn (Client $p) => $p->timezone('America/New_York'), 'https://api.parseapi.com/timezone/America%2FNew_York'],
			'holiday' => [fn (Client $p) => $p->holiday('US', 1955), 'https://api.parseapi.com/holiday/US?year=1955'],
			'holidayDate' => [fn (Client $p) => $p->holidayDate('US', '2026-12-25'), 'https://api.parseapi.com/holiday/US/2026-12-25'],
			'elevation' => [fn (Client $p) => $p->elevation(35.2, -80.8), 'https://api.parseapi.com/elevation?lat=35.2&lon=-80.8'],
			'point deep' => [fn (Client $p) => $p->point(36.0726, -79.792, deep: true), 'https://api.parseapi.com/point?lat=36.0726&lon=-79.792&deep=true'],
			'weather' => [fn (Client $p) => $p->weather(40.7128, -74.006, deep: true), 'https://api.parseapi.com/weather?lat=40.7128&lon=-74.006&deep=true'],
			'emoji' => [fn (Client $p) => $p->emoji('rocket'), 'https://api.parseapi.com/emoji/rocket'],
			'emojiSearch' => [fn (Client $p) => $p->emojiSearch('fire', 20), 'https://api.parseapi.com/emoji?q=fire&limit=20'],
		];
	}

	#[DataProvider('urlTable')]
	public function testUrlMapping(callable $invoke, string $expected): void
	{
		$client = $this->stubClient();
		$invoke($client);
		$this->assertSame($expected, $this->calls[0]['url']);
	}

	public function testHeaders(): void
	{
		$client = $this->stubClient();
		$client->country('US');
		$this->assertSame('test_key_123', $this->calls[0]['headers']['X-API-Key']);
		$this->assertMatchesRegularExpression('/^parseapi-php\/\d+\.\d+\.\d+$/', $this->calls[0]['headers']['User-Agent']);
	}

	public function testUseragentHeaderOverride(): void
	{
		$client = $this->stubClient();
		$client->useragent('Mozilla/5.0 (Test)');
		$this->assertSame('Mozilla/5.0 (Test)', $this->calls[0]['headers']['User-Agent']);
	}

	public function testMissingKey(): void
	{
		$saved = getenv('PARSEAPI_KEY');
		putenv('PARSEAPI_KEY');
		try {
			$this->expectException(\InvalidArgumentException::class);
			new Client();
		} finally {
			if ($saved !== false) {
				putenv('PARSEAPI_KEY=' . $saved);
			}
		}
	}

	public function testEnvKey(): void
	{
		$saved = getenv('PARSEAPI_KEY');
		putenv('PARSEAPI_KEY=env_key_456');
		try {
			$calls = &$this->calls;
			$client = new Client(transport: function (string $url, array $headers) use (&$calls): array {
				$calls[] = ['url' => $url, 'headers' => $headers];
				return [200, [], '{}'];
			});
			$client->country('US');
			$this->assertSame('env_key_456', $this->calls[0]['headers']['X-API-Key']);
		} finally {
			if ($saved !== false) {
				putenv('PARSEAPI_KEY=' . $saved);
			} else {
				putenv('PARSEAPI_KEY');
			}
		}
	}

	public function testErrorShape(): void
	{
		$body = json_encode([
			'code' => 'not_found',
			'message' => 'City not found',
			'docs' => 'https://parseapi.com/docs#not_found',
			'request_id' => 'req_abc',
		]);
		$client = $this->stubClient([[404, [], $body]]);
		try {
			$client->city('notarealcityxyz');
			$this->fail('expected ParseAPIError');
		} catch (ParseAPIError $e) {
			$this->assertSame(404, $e->status);
			$this->assertSame('not_found', $e->errorCode);
			$this->assertSame('City not found', $e->getMessage());
			$this->assertSame('https://parseapi.com/docs#not_found', $e->docs);
			$this->assertSame('req_abc', $e->requestId);
		}
	}

	public function testNonJsonErrorBody(): void
	{
		$client = $this->stubClient([[400, [], 'gateway timeout']]);
		try {
			$client->country('US');
			$this->fail('expected ParseAPIError');
		} catch (ParseAPIError $e) {
			$this->assertSame('unknown_error', $e->errorCode);
		}
	}

	public function testRetryThenSuccess(): void
	{
		$client = $this->stubClient([
			[500, [], '{"code":"server_error","message":"boom"}'],
			[200, [], '{"country":"us"}'],
		], retries: 2);
		$result = $client->country('US');
		$this->assertSame('us', $result['country']);
		$this->assertCount(2, $this->calls);
	}

	public function testNoRetryOn404(): void
	{
		$client = $this->stubClient([[404, [], '{"code":"not_found","message":"nope"}']], retries: 2);
		$this->expectException(ParseAPIError::class);
		try {
			$client->country('XX');
		} finally {
			$this->assertCount(1, $this->calls);
		}
	}

	public function testGivesUpAfterRetries(): void
	{
		$rateLimited = [429, [], '{"code":"rate_limited","message":"slow down"}'];
		$client = $this->stubClient([$rateLimited, $rateLimited, $rateLimited], retries: 2);
		try {
			$client->country('US');
			$this->fail('expected ParseAPIError');
		} catch (ParseAPIError $e) {
			$this->assertSame('rate_limited', $e->errorCode);
			$this->assertCount(3, $this->calls);
		}
	}
}
