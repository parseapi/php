<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
	public function testContractPinSurvivesRetrySelfAndUseragent(): void
	{
		$calls = [];
		$client = new Client('same-production-key', transport: static function (string $url, array $headers) use (&$calls): array {
			$calls[] = [$url, $headers];
			return count($calls) === 1
				? [503, ['retry-after' => '0'], '{"code":"unavailable"}']
				: [200, [], '{}'];
		});
		$client->country('US');
		$client->ipSelf();
		$client->useragent('Custom browser');
		$this->assertSame(['/country/US', '/country/US', '/ip', '/useragent'], array_map(static fn(array $call): string => parse_url($call[0], PHP_URL_PATH), $calls));
		foreach ($calls as [$url, $headers]) {
			$this->assertSame('2.0.0', $headers['Parse-Version']);
			$this->assertSame('same-production-key', $headers['X-API-Key']);
			$this->assertNull(parse_url($url, PHP_URL_QUERY));
		}
		$this->assertSame('Custom browser', $calls[3][1]['User-Agent']);
	}
}
