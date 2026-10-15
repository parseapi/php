<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;
final class ResponseDecodingTest extends TestCase {
 public function testDefaultResponseAndRetry(): void {
  $calls=[]; $body=file_get_contents(__DIR__.'/fixtures/response-fixtures/clean-timezones.json');
  $client=new Client('fixture',retries:1,transport:static function(string $url,array $headers) use (&$calls,$body):array {
   $calls[]=[$url,$headers]; return count($calls)===1 ? [503,['Retry-After'=>'0'],'{"code":"unavailable"}'] : [200,[],$body];
  });
  $this->assertSame(json_decode($body,true),$client->timeZones()); $this->assertCount(2,$calls);
  foreach($calls as [$url,$headers]) {$this->assertArrayNotHasKey('Parse-Response',$headers);$this->assertSame('2.0.0',$headers['Parse-Version']);$this->assertNull(parse_url($url,PHP_URL_QUERY));}
 }
}
