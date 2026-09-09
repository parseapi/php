<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
	public function testNAICSExclusionsAndMatchPassThrough(): void
	{
		$records = json_decode('[{"naics":"541511","name":"Custom Computer Programming Services","description":null,"level":6,"parent":"54151","parent_name":"Computer Systems Design and Related Services","children":[],"year":2022,"country":"US"},{"naics":"541511","name":"Custom Computer Programming Services","description":null,"level":6,"parent":"54151","parent_name":"Computer Systems Design and Related Services","children":[],"year":2022,"country":"US","exclusions":null,"match":null},{"naics":"541511","name":"Custom Computer Programming Services","description":null,"level":6,"parent":"54151","parent_name":"Computer Systems Design and Related Services","children":[],"year":2022,"country":"US","exclusions":[],"match":{"field":"future-field","text":"Future matching evidence","corrections":[],"future":true}},{"naics":"541511","name":"Custom Computer Programming Services","description":null,"level":6,"parent":"54151","parent_name":"Computer Systems Design and Related Services","children":[],"year":2022,"country":"US","exclusions":[{"description":"Designing integrated computer systems","codes":[{"naics":"541512","name":"Computer Systems Design Services"}]},{"description":"Activities classified elsewhere","codes":[]}],"match":{"field":"term","text":"Computer software programming services","corrections":[{"from":"sofware","to":"software"}]},"future":true}]', true, 512, JSON_THROW_ON_ERROR);
		foreach ($records as $record) {
			$body = ['q' => 'sofware', 'year' => 2022, 'country' => 'US', 'results' => [$record]];
			$client = $this->stubClient([[200, [], json_encode($body, JSON_THROW_ON_ERROR)]]);
			$this->assertSame($body, $client->naicsSearch('sofware'));
			$this->assertSame('https://api.parseapi.com/naics?q=sofware', $this->calls[0]['url']);
		}
	}

	public function testBinPreservesNullFalseAndPrefix(): void
	{
		$body = ['bin' => '00123456', 'prefix' => '001234', 'country' => null, 'issuer' => 'Fixture Bank', 'brand' => 'future-brand', 'type' => null, 'prepaid' => false, 'deep' => [], 'future' => true];
		$client = $this->stubClient([[200, [], '{"bin":"00123456","prefix":"001234","country":null,"issuer":"Fixture Bank","brand":"future-brand","type":null,"prepaid":false,"deep":{},"future":true}']]);
		$this->assertSame($body, $client->bin('00 1234-56', deep: true));
	}

	public function testPublicApiMatchesTheReviewedManifest(): void
	{
		$expected = json_decode(file_get_contents(__DIR__ . '/public_api.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame($expected, PublicApiSnapshot::capture());
	}

	/** @var array<array{url: string, headers: array}> */
	private array $calls = [];

	private function stubClient(?array $responses = null, ?int $retries = 0): Client
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
				$response = array_shift($queue);
				if ($response instanceof \Throwable) {
					throw $response;
				}
				return $response;
			},
		);
	}

	public function testMeasureDecimalAndAmbiguity(): void
	{
		foreach ([
			['measure' => '0 m', 'valid' => true, 'type' => 'future-type', 'amount' => '0', 'unit' => 'm', 'reason' => null, 'choices' => [], 'future' => null],
			['measure' => '1 gallon', 'valid' => false, 'type' => null, 'amount' => null, 'unit' => null, 'reason' => 'ambiguous_unit', 'choices' => [['unit' => 'us_gal', 'name' => 'US liquid gallon']]],
		] as $body) {
			$client = $this->stubClient([[200, [], json_encode($body, JSON_THROW_ON_ERROR)]]);
			$this->assertSame($body, $client->measure($body['measure']));
		}
	}

	public function testDnsPreservesPresentationAndEmptyRecords(): void
	{
		foreach ([[], [['name' => 'example.com.', 'type' => 'TXT', 'ttl' => 0, 'value' => '"one" "two"', 'future' => null]]] as $records) {
			$body = ['domain' => 'example.com', 'records' => $records, 'future' => true];
			$client = $this->stubClient([[200, [], json_encode($body, JSON_THROW_ON_ERROR)]]);
			$this->assertSame($body, $client->dns('example.com', type: 'TXT'));
		}
	}

	public function testNameCountryAndKnown(): void
	{
		$body = ['name' => '王', 'valid' => true, 'known' => true, 'countries' => ['CN', 'TW'], 'gender' => null, 'future' => true];
		$client = $this->stubClient([[200, [], json_encode($body)], [200, [], json_encode($body)]]);
		$this->assertSame($body, $client->name('王', country: 'CN'));
		$this->assertSame('https://api.parseapi.com/name/%E7%8E%8B?country=CN', $this->calls[0]['url']);
		$client->name('Andrea');
		$this->assertSame('https://api.parseapi.com/name/Andrea', $this->calls[1]['url']);
	}

	public function testMeasureBadTargetUsesApiError(): void
	{
		$client = $this->stubClient([[400, [], '{"code":"bad_request","message":"Incompatible units","request_id":"req_measure"}']], null);
		try {
			$client->measure('1 m', to: 'kg');
			$this->fail('Expected an API error');
		} catch (ParseAPIError $error) {
			$this->assertSame('bad_request', $error->errorCode);
			$this->assertCount(1, $this->calls);
		}
	}

	public function testAdpOptionalDepth(): void
	{
		$rows = json_decode('[["country", ["US"], {}], ["state", ["NC"], {"country": "US"}], ["state.districts", ["NC"], {"country": "US"}], ["district", ["37081"], {"country": "US", "state": "NC"}], ["city", ["Charlotte"], {"country": "US", "state": "NC"}], ["city.id", ["city_test"], {}], ["city.search", ["Charlotte"], {"country": "US", "state": "NC", "limit": 2}], ["city.nearest", [0, 0], {}], ["city.nearby", ["Charlotte"], {"radius": 0, "unit": "km", "country": "US", "state": "NC", "limit": 2}], ["postal", ["28202"], {"country": "US"}], ["postal.nearby", ["28202"], {"country": "US", "radius": 0, "unit": "km"}], ["postal.distance", ["28202", "10001"], {"country": "US"}], ["iban", ["DE89370400440532013000"], {"country": "DE"}], ["carrier", ["+14155552671"], {"country": "US"}], ["hlr", ["+447712345678"], {"country": "GB"}], ["naics", ["31-33"], {}], ["naics.search", ["coffee"], {"limit": 2}], ["currency", ["USD"], {}], ["language", ["ar"], {}], ["name", ["Andrea"], {"country": "IT"}], ["time", [], {"at": "2026-09-08", "to": "UTC"}], ["time.at", [0, 0], {"at": "2026-09-08", "to": "UTC"}], ["timezone", ["UTC"], {"at": "2026-09-08", "to": "UTC"}], ["timezone.at", [0, 0], {"at": "2026-09-08"}], ["date", ["03/04/2026"], {"format": "dmy", "to": "2026-09-08"}], ["date.today", [], {"to": "2026-09-08"}], ["emoji", ["fire"], {}], ["emoji.search", ["fire"], {"limit": 2}]]', true, 512, JSON_THROW_ON_ERROR);
		foreach ($rows as [$method, $args, $options]) {
			$native = preg_replace_callback('/\.([a-z])/', fn ($m) => strtoupper($m[1]), $method);
			$client = $this->stubClient();
			$client->$native(...[...$args, ...$options]);
			$client->$native(...[...$args, ...$options, 'deep' => true]);
			$last = array_slice($this->calls, -2);
			$firstUrl = parse_url($last[0]['url']);
			$deepUrl = parse_url($last[1]['url']);
			parse_str($firstUrl['query'] ?? '', $firstQuery);
			parse_str($deepUrl['query'] ?? '', $deepQuery);
			$this->assertSame($firstUrl['path'], $deepUrl['path'], $method);
			$this->assertSame([...$firstQuery, 'deep' => 'true'], $deepQuery, $method);
		}
	}

	public static function urlTable(): array
	{
		return [
			'bin' => [fn (Client $p) => $p->bin('001234'), 'https://api.parseapi.com/bin/001234'],
			'bin deep' => [fn (Client $p) => $p->bin('00 1234-56', deep: true), 'https://api.parseapi.com/bin/00%201234-56?deep=true'],
			'dns' => [fn (Client $p) => $p->dns('example.com'), 'https://api.parseapi.com/dns/example.com'],
			'dns type' => [fn (Client $p) => $p->dns('_dmarc.bücher.example.', type: 'txt'), 'https://api.parseapi.com/dns/_dmarc.b%C3%BCcher.example.?type=txt'],
			'naics' => [fn (Client $p) => $p->naics('31-33'), 'https://api.parseapi.com/naics/31-33'],
			'naics encoded' => [fn (Client $p) => $p->naics('54/11'), 'https://api.parseapi.com/naics/54%2F11'],
			'naicsSearch' => [fn (Client $p) => $p->naicsSearch('coffee & tea', limit: 5), 'https://api.parseapi.com/naics?q=coffee+%26+tea&limit=5'],
			'naicsSearch default' => [fn (Client $p) => $p->naicsSearch('plumbing'), 'https://api.parseapi.com/naics?q=plumbing'],
			'measure' => [fn (Client $p) => $p->measure('5 ft 11 in', to: 'cm', locale: 'en-US', system: 'us'), 'https://api.parseapi.com/measure/5%20ft%2011%20in?to=cm&locale=en-US&system=us'],
			'measure compound' => [fn (Client $p) => $p->measure('1 kg/m^3', to: 'g/L'), 'https://api.parseapi.com/measure/1%20kg%2Fm%5E3?to=g%2FL'],
			'measureUnits' => [fn (Client $p) => $p->measureUnits(query: 'US gallon', type: 'volume', unit: 'L'), 'https://api.parseapi.com/measure/units?q=US+gallon&type=volume&unit=L'],
			'measureUnits all' => [fn (Client $p) => $p->measureUnits(), 'https://api.parseapi.com/measure/units'],
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
			'address' => [fn (Client $p) => $p->address('1600 Pennsylvania Ave NW, Washington, DC 20500', country: 'US'), 'https://api.parseapi.com/address/1600%20Pennsylvania%20Ave%20NW%2C%20Washington%2C%20DC%2020500?country=US'],
			'addressSearch' => [fn (Client $p) => $p->addressSearch('123 main', country: 'US', postal: '27401', city: 'Greensboro', state: 'NC', ip: '8.8.8.8'), 'https://api.parseapi.com/address?q=123+main&country=US&postal=27401&city=Greensboro&state=NC&ip=8.8.8.8'],
			'company' => [fn (Client $p) => $p->company('732829320', country: 'FR', deep: true), 'https://api.parseapi.com/company/732829320?country=FR&deep=true'],
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
			'asn' => [fn (Client $p) => $p->asn('AS13335'), 'https://api.parseapi.com/asn/AS13335'],
			'mac' => [fn (Client $p) => $p->mac('00:1B:63:84:45:E6'), 'https://api.parseapi.com/mac/00%3A1B%3A63%3A84%3A45%3AE6'],
			'mx' => [fn (Client $p) => $p->mx('example.com'), 'https://api.parseapi.com/mx/example.com'],
			'useragent' => [fn (Client $p) => $p->useragent('TestUA/1.0'), 'https://api.parseapi.com/useragent'],
			'vin' => [fn (Client $p) => $p->vin('1HGCM82633A004352'), 'https://api.parseapi.com/vin/1HGCM82633A004352'],
			'vin deep' => [fn (Client $p) => $p->vin('1HGCM82633A004352', true), 'https://api.parseapi.com/vin/1HGCM82633A004352?deep=true'],
			'tariff' => [fn (Client $p) => $p->tariff('8471.30.01.00', deep: true, origin: 'CN'), 'https://api.parseapi.com/tariff/8471.30.01.00?deep=true&origin=CN'],
			'tariffSearch' => [fn (Client $p) => $p->tariffSearch('sunglasses'), 'https://api.parseapi.com/tariff?q=sunglasses'],
			'currency' => [fn (Client $p) => $p->currency('USD'), 'https://api.parseapi.com/currency/USD'],
			'currencyRate' => [fn (Client $p) => $p->currencyRate('USD', 'EUR'), 'https://api.parseapi.com/currency/USD/EUR'],
			'currencyRate date amount' => [fn (Client $p) => $p->currencyRate('USD', 'JPY', date: '2026-08-28', amount: 100), 'https://api.parseapi.com/currency/USD/JPY?date=2026-08-28&amount=100'],
			'language' => [fn (Client $p) => $p->language('en'), 'https://api.parseapi.com/language/en'],
			'name encodes spaces' => [fn (Client $p) => $p->name('Smith, John'), 'https://api.parseapi.com/name/Smith%2C%20John'],
			'time UTC' => [fn (Client $p) => $p->time(), 'https://api.parseapi.com/time'],
			'time conversion' => [fn (Client $p) => $p->time('America/New_York', at: '2026-09-05T15:00', to: 'Europe/London'), 'https://api.parseapi.com/time/America%2FNew_York?at=2026-09-05T15%3A00&to=Europe%2FLondon'],
			'time coordinates' => [fn (Client $p) => $p->timeAt(0, 0, at: '1970-01-01T00:00:00Z', to: 'UTC'), 'https://api.parseapi.com/time?lat=0&lon=0&at=1970-01-01T00%3A00%3A00Z&to=UTC'],
			'timezone encodes slash' => [fn (Client $p) => $p->timezone('America/New_York'), 'https://api.parseapi.com/timezone/America%2FNew_York'],
			'timezone conversion' => [fn (Client $p) => $p->timezone('America/New_York', at: '2026-09-05T15:00', to: 'Europe/London'), 'https://api.parseapi.com/timezone/America%2FNew_York?at=2026-09-05T15%3A00&to=Europe%2FLondon'],
			'timezone coordinates' => [fn (Client $p) => $p->timezoneAt(0, 0, at: '2026-09-05T12:00Z'), 'https://api.parseapi.com/timezone?lat=0&lon=0&at=2026-09-05T12%3A00Z'],
			'date' => [fn (Client $p) => $p->date('03/04/2026', format: 'mdy', to: '2026-04-01'), 'https://api.parseapi.com/date/03%2F04%2F2026?format=mdy&to=2026-04-01'],
			'date today' => [fn (Client $p) => $p->dateToday(), 'https://api.parseapi.com/date'],
			'date today distance' => [fn (Client $p) => $p->dateToday(to: '2026-12-25'), 'https://api.parseapi.com/date?to=2026-12-25'],
			'holiday' => [fn (Client $p) => $p->holiday('US', 1955), 'https://api.parseapi.com/holiday/US?year=1955'],
			'holidayDate' => [fn (Client $p) => $p->holidayDate('US', '2026-12-25'), 'https://api.parseapi.com/holiday/US/2026-12-25'],
			'elevation' => [fn (Client $p) => $p->elevation(35.2, -80.8), 'https://api.parseapi.com/elevation?lat=35.2&lon=-80.8'],
			'point deep' => [fn (Client $p) => $p->point(36.0726, -79.792, deep: true), 'https://api.parseapi.com/point?lat=36.0726&lon=-79.792&deep=true'],
			'weather' => [fn (Client $p) => $p->weather(40.7128, -74.006, deep: true), 'https://api.parseapi.com/weather?lat=40.7128&lon=-74.006&deep=true'],
			'weather history' => [fn (Client $p) => $p->weather(40.7128, -74.006, true, '2026-09-01'), 'https://api.parseapi.com/weather?lat=40.7128&lon=-74.006&deep=true&date=2026-09-01'],
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

	public function testNativeResponsePreservesUnknownFieldsAndNulls(): void
	{
		$body = '{"country":"zz","future":{"items":[null,false,0]},"deep":{},"unknown":null}';
		$client = $this->stubClient([[200, [], $body]]);
		$this->assertSame(json_decode($body, true), $client->country('ZZ'));
		$this->assertCount(1, $this->calls);
	}

	public function testMalformedSuccessThrowsWithoutRetrying(): void
	{
		$client = $this->stubClient([[200, [], '{"country":']], retries: 2);
		$this->expectException(\JsonException::class);
		try {
			$client->country('US');
		} finally {
			$this->assertCount(1, $this->calls);
		}
	}

	public function testScalarSuccessIsNotAnEmptyResult(): void
	{
		$client = $this->stubClient([[200, [], 'null']]);
		$this->expectException(\UnexpectedValueException::class);
		$client->country('US');
	}

	public static function invalidTimeouts(): array
	{
		return [[0.0], [-1.0], [NAN], [INF]];
	}

	#[DataProvider('invalidTimeouts')]
	public function testInvalidTimeoutFailsAtConstruction(float $timeout): void
	{
		$this->expectException(\InvalidArgumentException::class);
		new Client('k', timeout: $timeout);
	}

	public function testNegativeRetriesFailAtConstruction(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		new Client('k', retries: -1);
	}

	public function testZeroRetriesMakesOneAttempt(): void
	{
		$client = $this->stubClient([[503, [], '{}']], retries: 0);
		$this->expectException(ParseAPIError::class);
		try {
			$client->country('US');
		} finally {
			$this->assertCount(1, $this->calls);
		}
	}

	public function testRetryAfterSupportsHttpDatesAndCapsDelays(): void
	{
		$client = new Client('k');
		$delay = new \ReflectionMethod(Client::class, 'retryDelay');
		$this->assertSame(0.0, $delay->invoke($client, 0, 'Sun, 06 Nov 1994 08:49:37 GMT'));
		$this->assertSame(5.0, $delay->invoke($client, 0, gmdate('D, d M Y H:i:s \\G\\M\\T', time() + 60)));
		$this->assertSame(5.0, $delay->invoke($client, 0, '100'));
	}

	public function testRedirectIsAnErrorWithoutForwardingTheKey(): void
	{
		$client = $this->stubClient([[302, ['location' => 'https://other.example/steal'], '']], retries: 2);
		try {
			$client->country('US');
			$this->fail('expected ParseAPIError');
		} catch (ParseAPIError $e) {
			$this->assertSame(302, $e->status);
			$this->assertCount(1, $this->calls);
			$this->assertSame('https://api.parseapi.com/country/US', $this->calls[0]['url']);
		}
	}

	public function testCloseIsIdempotentAndReleasesTheSession(): void
	{
		$client = new Client('k');
		$curl = new \ReflectionProperty(Client::class, 'curl');
		$curl->setValue($client, curl_init());
		$client->close();
		$client->close();
		$this->assertNull($curl->getValue($client));
	}

	public function testDebugOutputDoesNotExposeCredentials(): void
	{
		$client = new Client('do_not_log_this_key', baseUrl: 'https://private:password@example.test');
		ob_start();
		var_dump($client);
		$dump = ob_get_clean();
		foreach ([$dump, print_r($client, true)] as $output) {
			$this->assertStringNotContainsString('do_not_log_this_key', $output);
			$this->assertStringNotContainsString('password', $output);
			$this->assertStringContainsString('[REDACTED]', $output);
		}
	}

	public static function retryPolicyCases(): array
	{
		return [
			'country' => [fn (Client $p) => $p->country('US'), 3],
			'email core' => [fn (Client $p) => $p->email('hello@example.com'), 3],
			'email deep' => [fn (Client $p) => $p->email('hello@example.com', deep: true), 1],
			'vat core' => [fn (Client $p) => $p->vat('DE136695976'), 3],
			'vat deep' => [fn (Client $p) => $p->vat('DE136695976', deep: true), 1],
			'carrier' => [fn (Client $p) => $p->carrier('+14155552671'), 1],
			'caller' => [fn (Client $p) => $p->caller('+14155552671'), 1],
			'hlr' => [fn (Client $p) => $p->hlr('+447712345678'), 1],
			'address reserved deep' => [fn (Client $p) => $p->address('1600 Pennsylvania Ave NW, Washington, DC 20500', deep: true), 1],
			'ip plan deep' => [fn (Client $p) => $p->ip('8.8.8.8', deep: true), 3],
		];
	}

	#[DataProvider('retryPolicyCases')]
	public function testDefaultRetryPolicy(callable $invoke, int $expected): void
	{
		$client = $this->stubClient(array_fill(0, 3, [503, ['retry-after' => '0'], '{}']), retries: null);
		try {
			$invoke($client);
			$this->fail('expected ParseAPIError');
		} catch (ParseAPIError $e) {
			$this->assertSame(503, $e->status);
			$this->assertCount($expected, $this->calls);
		}
	}

	public function testMeteredNetworkFailureIsNotRetriedByDefault(): void
	{
		$client = $this->stubClient([new \RuntimeException('response lost')], retries: null);
		$this->expectException(\RuntimeException::class);
		try {
			$client->email('hello@example.com', deep: true);
		} finally {
			$this->assertCount(1, $this->calls);
		}
	}

	public function testExplicitRetriesOverrideTheMeteredPolicy(): void
	{
		$client = $this->stubClient([[503, ['retry-after' => '0'], '{}'], [200, [], '{}']], retries: 1);
		$this->assertSame([], $client->carrier('+14155552671'));
		$this->assertCount(2, $this->calls);
	}

 public function testNameLocalPreservedInDirectAndNestedResponses(): void
 {
  foreach (['München', null] as $nameLocal) {
   $record = ['name' => 'Munich', 'name_local' => $nameLocal];
   $cases = [
    [fn (Client $p) => $p->country('DE'), $record],
    [fn (Client $p) => $p->state('BY'), $record],
    [fn (Client $p) => $p->city('Munich'), $record],
    [fn (Client $p) => $p->language('de'), $record],
    [fn (Client $p) => $p->holiday('DE'), ['holidays' => [$record]]],
    [fn (Client $p) => $p->point(48, 11, deep: true), ['deep' => ['city' => $record]]],
   ];
   foreach ($cases as [$invoke, $body]) {
    $client = $this->stubClient([[200, [], json_encode($body, JSON_THROW_ON_ERROR)]]);
    $this->assertSame($body, $invoke($client));
   }
  }
 }

}
