<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class CityCompactTest extends TestCase {
 public function testCompactMatchesKeepCityFactsAndCursor(): void {
  $rows=array_values(array_filter(json_decode(file_get_contents(__DIR__.'/fixtures/noise-approved.json'),true,512,JSON_THROW_ON_ERROR),static fn($row)=>str_starts_with($row['id'],'city-')));
  $pending=$rows;
  $client=new Client('fixture',retries:0,transport:static function(string $url,array $headers) use (&$pending):array {
   self::assertSame('2.1.0',$headers['X-API-Version']);
   return [200,[],json_encode(array_shift($pending)['body'],JSON_THROW_ON_ERROR)];
  });
  foreach($rows as $row) {
   $body=$row['body']; $result=$client->citySearch($body['q'],match:$body['match']);
   $this->assertSame($body['data'],$result['cities']); $this->assertSame($body['next_cursor'],$result['next_cursor']);
  }
  $this->assertSame([],$pending);
 }
}
