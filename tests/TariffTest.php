<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class TariffTest extends TestCase
{
	public function testSearchPreservesParentContextAndOlderResults(): void
	{
		foreach ([[], ['lineage' => null], ['lineage' => []], ['lineage' => ['Live horses', 'Other horses']]] as $extra) {
			$body = ['q' => 'horses & ponies', 'revision' => 'fixture', 'lines' => [['hts' => '0101.29.00.90', 'description' => 'Other', 'general' => null, 'future' => true] + $extra]];
			$client = new Client('test', transport: function (string $url, array $headers) use ($body): array {
				$this->assertSame('/tariff', parse_url($url, PHP_URL_PATH));
				parse_str(parse_url($url, PHP_URL_QUERY), $query);
				$this->assertSame(['q' => 'horses & ponies'], $query);
				return [200, [], json_encode($body, JSON_THROW_ON_ERROR)];
			});
			$this->assertSame($body, $client->tariffSearch('horses & ponies'));
		}
	}

	public function testEditionDateRoundtrip(): void
	{
		$edition = str_repeat('a', 64);
		$body = ['hts' => '0101', 'edition' => $edition, 'date' => '2026-09-15', 'deep' => ['effective_rate' => null, 'reason' => 'future_reason', 'measures' => []]];
		$seen = [];
		$client = new Client('test', transport: function (string $url, array $headers) use ($body, &$seen): array {
			parse_str(parse_url($url, PHP_URL_QUERY), $query);
			$seen[] = $query;
			$body['date'] = $query['date'] ?? null;
			return [200, [], json_encode($body, JSON_THROW_ON_ERROR)];
		});
		$this->assertSame($body, $client->tariff('0101', deep: true, origin: 'CA', edition: $edition, date: '2026-09-15'));
		$this->assertSame($body, $client->tariffSearch('horses', edition: $edition, date: '2026-09-15'));
		$client->tariff('0101', edition: $edition);
		$this->assertSame([['deep' => 'true', 'origin' => 'CA', 'edition' => $edition, 'date' => '2026-09-15'], ['q' => 'horses', 'edition' => $edition, 'date' => '2026-09-15'], ['edition' => $edition]], $seen);
	}

	public function testIgnoredSelectionIsRejected(): void
	{
		$client = new Client('test', transport: fn() => [200, [], '{"revision":"old"}']);
		foreach ([fn() => $client->tariff('0101', edition: str_repeat('a', 64)), fn() => $client->tariffSearch('horses', date: '2026-09-15')] as $call) {
			try { $call(); $this->fail('Ignored selection accepted'); }
			catch (\ParseAPI\ParseAPIError $error) { $this->assertSame('tariff_selection_mismatch', $error->errorCode); $this->assertSame(0, $error->status); }
		}
	}

	public function testDateSelectionRejectsInvalidReturnedEdition(): void
	{
		foreach (['legacy', '', str_repeat('A', 64), str_repeat('a', 64) . "\n"] as $edition) {
			$client = new Client('test', transport: fn() => [200, [], json_encode(['edition' => $edition, 'date' => '2026-09-15'], JSON_THROW_ON_ERROR)]);
			foreach ([fn() => $client->tariff('0101', date: '2026-09-15'), fn() => $client->tariffSearch('horses', date: '2026-09-15')] as $call) {
				try { $call(); $this->fail('Invalid returned edition accepted'); }
				catch (\ParseAPI\ParseAPIError $error) { $this->assertSame('tariff_selection_mismatch', $error->errorCode); $this->assertSame(0, $error->status); }
			}
		}
	}
}
