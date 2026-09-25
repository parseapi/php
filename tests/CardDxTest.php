<?php

declare(strict_types=1);
namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/card-weak-caller.php';

final class CardDxTest extends TestCase
{
	public function testPrefixOnlyGuardAndOriginalInputFromWeakCaller(): void
	{
		$calls = [];
		$client = new Client('fixture', transport: static function($url) use (&$calls) { $calls[] = $url; return [200, [], '{}']; });
		foreach (['', '1', '123456789012', '4242424242424242', '００１２３４', "00\u{00a0}1234", "00\v1234", '00%201234', '00+1234', '00/1234', '00_1234', str_repeat(' ', 59).'001234', 123456, null, false, []] as $input) {
			try { \callCardFromWeakPhp($client, $input); $this->fail('expected argument error'); }
			catch (\InvalidArgumentException $error) { $this->assertSame('parseapi: Card requires a string containing 2 to 11 digits. Send a prefix only.', $error->getMessage()); }
		}
		$this->assertSame([], $calls);
		foreach (['001234', '00123456789', '00 1234-56', "00\t12\r34\n-56", str_repeat(' ', 58).'001234'] as $input) {
			\callCardFromWeakPhp($client, $input);
			$this->assertSame('https://api.parseapi.com/card/'.rawurlencode($input), end($calls));
		}
	}

	public function testOptionalCardDeep(): void
	{
		$calls = [];
		$body = ['bin'=>'001234', 'brand'=>null, 'brand_name'=>null, 'logo'=>'https://cdn.parseapi.com/card/generic.svg', 'deep'=>['prefix'=>'001234', 'issuer'=>null, 'country'=>null, 'type'=>null, 'prepaid'=>false]];
		$client = new Client('fixture', transport: static function($url) use (&$calls, $body) { $calls[]=$url; return [200, [], json_encode($body)]; });
		$this->assertSame($body, $client->card('00-1234', deep:true));
		$this->assertSame(['https://api.parseapi.com/card/00-1234?deep=true'], $calls);
	}

	public function testLongRetryAfterReturnsOriginalErrorWithoutSleeping(): void
	{
		foreach (['60', str_repeat('9', 400), gmdate('D, d M Y H:i:s \\G\\M\\T', time()+60)] as $header) {
			$calls = 0;
			$client = new Client('fixture', transport: static function() use (&$calls, $header) { $calls++; return [429, ['retry-after'=>$header], '{"code":"rate_limited","message":"Wait for reset","docs":"https://parseapi.com/docs","request_id":"req_fixture"}']; });
			$started = microtime(true);
			try { $client->card('001234'); $this->fail('expected API error'); }
			catch (ParseAPIError $error) {
				$this->assertSame(429, $error->status); $this->assertSame('rate_limited', $error->errorCode);
				$this->assertSame('Wait for reset', $error->getMessage()); $this->assertSame('https://parseapi.com/docs', $error->docs);
				$this->assertSame('req_fixture', $error->requestId); $this->assertSame($header, $error->retryAfter);
			}
			$this->assertSame(1, $calls); $this->assertLessThan(1.0, microtime(true)-$started);
		}
	}

	public function testShortMalformedAndExhaustedRetryHeaders(): void
	{
		$client = new Client('fixture'); $delay = new \ReflectionMethod(Client::class, 'retryDelay');
		$this->assertSame(2.0, $delay->invoke($client, 0, '2'));
		$this->assertSame(0.0, $delay->invoke($client, 0, '0'));
		$dateDelay = $delay->invoke($client, 0, gmdate('D, d M Y H:i:s \\G\\M\\T', time()+3));
		$this->assertGreaterThanOrEqual(1.0, $dateDelay); $this->assertLessThanOrEqual(3.0, $dateDelay);
		foreach ([null, 'nonsense', '-1', 'NaN', 'Infinity', '1e999'] as $header) {
			$value = $delay->invoke($client, 0, $header); $this->assertGreaterThanOrEqual(0.0, $value); $this->assertLessThanOrEqual(0.25, $value);
		}
		foreach ([0,1] as $retries) {
			$calls=0;
			$client = new Client('fixture', retries:$retries, transport:static function() use (&$calls) { $calls++;return [429,['retry-after'=>'0'],'{"code":"rate_limited"}']; });
			try { $client->card('001234'); $this->fail('expected API error'); }
			catch(ParseAPIError $error) { $this->assertSame('0',$error->retryAfter); }
			$this->assertSame($retries+1,$calls);
		}
		$this->assertNull((new ParseAPIError(400,'invalid_request','Input',null,null))->retryAfter);
	}
}
