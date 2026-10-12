<?php
declare(strict_types=1);
namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
	private array $calls = [];
	private function client(array $queue = [[200, [], '{}']], int $retries = 0): Client
	{
		$this->calls = [];
		return new Client('test', retries: $retries, transport: function ($url, $headers) use (&$queue) {
			$this->calls[] = ['url' => $url, 'headers' => $headers];
			if (!$queue) throw new \LogicException('Unexpected extra request');
			return array_shift($queue);
		});
	}
	private function params(int $index): array
	{
		parse_str(parse_url($this->calls[$index]['url'], PHP_URL_QUERY) ?? '', $params);
		return $params;
	}

	public function testExistingRateAndOptionalSourceEvidencePreserveServerNumbers(): void
	{
		$body = '{"base":"USD","quote":"EUR","rate":1e-10,"date":"2024-01-05","amount":1000000000000000,"converted":1.01,"stale":false,"future":true}';
		$client = $this->client([[200, [], '{}'], [200, [], $body]]);
		$this->assertSame([], $client->currencyRate('USD', 'EUR'));
		$this->assertSame('https://api.parseapi.com/currency/USD/EUR', $this->calls[0]['url']);
		$this->assertSame(json_decode($body, true), $client->currencyRate('USD', 'EUR', '2024-01-05', 1e15, ));
		$this->assertSame(['date' => '2024-01-05', 'amount' => '1.0E+15'], $this->params(1));
		$this->assertSame('2.0.0', $this->calls[1]['headers']['Parse-Version']);
		$this->assertSame('test', $this->calls[1]['headers']['X-API-Key']);
	}

	public function testSourcesBatchAndBothHistoryShapesRemainPlainData(): void
	{
		$sources = ['data' => [['id' => 'nbp', 'available' => false, 'date' => null, 'currencies' => [], 'history' => ['from' => null, 'to' => null, 'currencies' => null], 'future' => true]]];
		$batch = ['base' => 'EUR', 'amount' => 1.005, 'rates' => ['USD' => ['rate' => 1.005, 'date' => '2024-01-05', 'converted' => 1.01]], 'missing' => [['quote' => 'XCG', 'reason' => 'no_rate']]];
		$pair = ['base' => 'EUR', 'quote' => 'USD', 'from' => '2024-01-01', 'to' => '2024-12-31', 'data' => [], 'missing' => ['2024-01-01']];
		$multi = ['base' => 'EUR', 'quotes' => ['USD', 'TRL'], 'data' => [['requested_date' => '2004-12-31', 'quote' => 'TRL', 'converted' => null]], 'missing' => [['requested_date' => '2005-02-01', 'quote' => 'TRL', 'reason' => 'no_rate']]];
		$client = $this->client(array_map(fn($value) => [200, [], json_encode($value)], [$batch, $pair, $multi]));
		$this->assertSame($batch, $client->currencyRates('EUR', ['USD', 'XCG'], amount: 1.005, ));
		$this->assertSame('USD,XCG', $this->params(0)['quotes']);
		$this->assertSame($pair, $client->currencyHistory('EUR', 'USD', '2024-01-01', '2024-12-31'));
		$this->assertSame($multi, $client->currencyRatesHistory('EUR', ['USD', 'TRL'], '2004-12-31', '2005-02-01', ));
		$this->assertSame('/currency/EUR/rates/history', parse_url($this->calls[2]['url'], PHP_URL_PATH));
		$this->assertCount(3, $this->calls);
	}

	public function testNewRequestBoundsRejectBeforeTransportAndExactMaximumWorks(): void
	{
		$client = $this->client();
		$quotes = array_map(fn($index) => 'Q' . chr(65 + intdiv($index, 26)) . chr(65 + $index % 26), range(0, 49));
		$invalid = [
			fn() => $client->currencyRates('EUR', []), fn() => $client->currencyRates('EUR', ['USD', 'usd']),
			fn() => $client->currencyRates('EUR', [...$quotes, 'ZZZ']), fn() => $client->currencyRates('EUR', ['USD'], amount: INF),
			fn() => $client->currencyRates('EUR', ['USD'], amount: 1e16),
			fn() => $client->currencyRates('EUR', ['USD'], date: '2999-01-01'),
			fn() => $client->currencyRates('EUR', ['USD'], date: "2024-01-\0"),
			fn() => $client->currencyHistory('EUR', 'USD', '1500-02-29', '1500-03-01'),
			fn() => $client->currencyHistory('EUR', 'USD', '0000-01-01', '0000-01-02'),
			fn() => $client->currencyHistory('EUR', 'USD', '2024-01-02', '2024-01-01'),
			fn() => $client->currencyHistory('EUR', 'USD', '2024-01-01', '2025-01-01'),
			fn() => $client->currencyRatesHistory('EUR', $quotes, '2024-01-01', '2024-04-10'),
		];
		foreach ($invalid as $call) { try { $call(); $this->fail('Invalid request accepted'); } catch (\InvalidArgumentException) {} }
		$this->assertCount(0, $this->calls);
		$client->currencyRatesHistory('EUR', $quotes, '2024-01-01', '2024-04-09');
		$this->assertCount(1, $this->calls);
	}



	public function testActualCandidateCollectionsAllQuotesCatalog(): void
	{
		$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/currency-reference.json'), true, 512, JSON_THROW_ON_ERROR);
		$values = [$fixture['all_rates'], $fixture['currencies'], $fixture['sources']];
		$client = $this->client(array_map(fn($value) => [200, [], json_encode($value)], $values));
		$all = $client->currencyRates('USD', );
		$this->assertCount(46, $all['rates']);
		$this->assertArrayNotHasKey('quotes', $this->params(0));
		$gold = $all['rates']['XAU'];
		$this->assertSame('troy_ounce', $gold['units']['quote']);
		$catalog = $client->currencyList(q: 'gold', deep: true, lang: 'fr');
		$this->assertSame($fixture['currencies'], $catalog); $this->assertNull($catalog['data'][0]['digits']);
		$this->assertSame('/currency', parse_url($this->calls[1]['url'], PHP_URL_PATH));
		$this->assertSame('fr', $this->params(1)['lang']);
	}

    public function testIndexedUnitsPreserveLiteralDelivery(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/currency-reference.json'), true, 512, JSON_THROW_ON_ERROR);
        $client = $this->client([[200, [], json_encode($fixture['indexed_pair'])]]);
        $pair = $client->currencyRate('USD', 'MXV', amount: 1);
        $this->assertSame('indexed_unit', $pair['units']['quote']); $this->assertSame($fixture['indexed_pair']['converted'], $pair['converted']);
        $this->assertCount(1, $this->calls);
    }

	public function testSampledArchiveAndCompletePublicationSummary(): void
	{
		$f = json_decode(file_get_contents(__DIR__ . '/fixtures/currency-summary.json'), true, 512, JSON_THROW_ON_ERROR);
		$client = $this->client([[200, [], json_encode($f['sampled_history'])], [200, [], json_encode($f['sampled_history'])], [200, [], json_encode($f['summary'])]]);
		$history = $client->currencyHistory('USD', 'JPY', '1999-01-01', '2026-10-09',  interval: 'month');
		$this->assertSame($f['sampled_history'], $history); $this->assertSame(['1999-01-01'], $history['missing']);
		$client->currencyRatesHistory('USD', ['JPY'], '1999-01-01', '2026-10-09',  interval: 'week');
		$report = $client->currencyRatesSummary('USD', ['EUR'], '1999-01-01', '1999-01-31', interval: 'month', );
		$this->assertSame($f['summary'], $report); $this->assertSame(20, $report['data'][0]['observation_count']); $this->assertSame(3, $report['data'][0]['missing_day_count']);
		$this->assertSame('/currency/USD/rates/summary', parse_url($this->calls[2]['url'], PHP_URL_PATH));
		$this->assertEquals(['quotes'=>'EUR','from'=>'1999-01-01','to'=>'1999-01-31','interval'=>'month'], $this->params(2));
		$this->assertSame(['month','week','month'], array_map(fn($i)=>$this->params($i)['interval'], range(0,2)));
		foreach ([fn()=>$client->currencyHistory('USD','EUR','1999-01-01','1999-01-31',interval:'year'), fn()=>$client->currencyRatesSummary('USD',['EUR'],'1999-01-01','1999-01-31',interval:'week')] as $invalid) {
			try { $invalid(); $this->fail('Unsupported interval accepted'); } catch (\InvalidArgumentException) {}
		}
		$this->assertCount(3, $this->calls);
	}

}
