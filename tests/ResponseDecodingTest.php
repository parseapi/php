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
  foreach($calls as [$url,$headers]) {$this->assertArrayNotHasKey('Parse-Response',$headers);$this->assertSame('2.1.0',$headers['X-API-Version']);$this->assertNull(parse_url($url,PHP_URL_QUERY));}
 }

 public function testSevenNumericFieldsPassThrough(): void {
  $country='{"country":"US","deep":{"gdp_usd":30769700000000,"gdp_per_capita_usd":90026.5163005744,"population_growth_percent":-0.25,"urban_population_percent":0,"internet_users_percent":null}}';
  $weather='{"deep":{"minutes":[{"at":"2026-10-27T12:00:00Z","precipitation_rate":1.8,"precipitation_rate_in":0.071,"type":"rain"},{"at":"2026-10-27T12:01:00Z","precipitation_rate":0,"precipitation_rate_in":0},{"at":"2026-10-27T12:02:00Z","precipitation_rate":null,"precipitation_rate_in":null}],"history":{"precipitation":12.3,"precipitation_in":0.484}}}';
  $client=new Client('fixture',transport:static fn(string $url,array $headers):array => [200,[],str_contains($url,'/country/') ? $country : $weather]);
  $this->assertSame(json_decode($country,true),$client->country('US',deep:true));
  $this->assertSame(json_decode($weather,true),$client->weather(0,0,deep:true));
 }
}
