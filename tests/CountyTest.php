<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;
final class CountyTest extends TestCase {
 public function testCanonicalAndDeprecatedCallsUseCountyWithoutChangingFields(): void {
  $calls=[]; $county=['county'=>'E09000033','iso_code'=>'GB-WSM','type'=>'district','population'=>0,'county_name_local'=>null];
  $listing=['state'=>'ENG','country'=>'GB','counties'=>[$county]];
  $client=new Client('fixture',transport:static function(string $url,array $headers) use (&$calls,$county,$listing):array {
   $calls[]=parse_url($url,PHP_URL_PATH); self::assertSame('2.2.0',$headers['X-API-Version']);
   return [200,[],json_encode(str_ends_with(parse_url($url,PHP_URL_PATH),'/counties')?$listing:$county)];
  });
  self::assertSame($county,$client->county('Westminster',country:'GB'));
  self::assertSame($county,$client->district('Westminster',country:'GB'));
  self::assertSame($listing,$client->stateCounties('ENG',country:'GB'));
  self::assertSame($listing,$client->stateDistricts('ENG',country:'GB'));
  self::assertSame(['/county/Westminster','/county/Westminster','/state/ENG/counties','/state/ENG/counties'],$calls);
 }
}
