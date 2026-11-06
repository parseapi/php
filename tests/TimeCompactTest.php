<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class TimeCompactTest extends TestCase {
 public function testApprovedCompactTimeBodiesPassThrough(): void {
  $rows=json_decode(file_get_contents(__DIR__.'/fixtures/time-compact.json'),true,512,JSON_THROW_ON_ERROR);
  $pending=$rows;
  $client=new Client('fixture',retries:0,transport:static function(string $url,array $headers) use (&$pending):array {
   self::assertSame('2.1.0',$headers['X-API-Version']);
   return [200,[],json_encode(array_shift($pending)['body'],JSON_THROW_ON_ERROR)];
  });
  foreach($rows as $row) $this->assertSame($row['body'],$client->time(),$row['id']);
  $this->assertSame([],$pending);
 }
}
