<?php
declare(strict_types=1);
namespace ParseAPI\Tests;
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;
final class CountryCorrectedTest extends TestCase {
 public function testCorrectedCountryIsPreservedWithoutExtraLookup(): void {
  $full=json_decode(file_get_contents(__DIR__.'/fixtures/country-corrected.json'),true,flags:JSON_THROW_ON_ERROR);
  $calls=0;
  $client=new Client('fixture',transport:static function(string $url,array $headers)use($full,&$calls):array{$calls++;return [200,[],json_encode($full,JSON_THROW_ON_ERROR)];});
  $this->assertSame($full,$client->country('US',deep:true));
  $this->assertSame(1,$calls);
  $this->assertSame('United States of America',$full['name_full']);
  $this->assertArrayNotHasKey('full_name',$full);
  $this->assertArrayNotHasKey('population',$full);
  $this->assertArrayNotHasKey('area',$full);
  $this->assertSame('American',$full['demonym']);
  $this->assertSame('🇺🇸',$full['flag']);
  $this->assertArrayNotHasKey('emoji',$full);
  $this->assertSame(['name'=>'Washington, D.C.','lat'=>38.89,'lon'=>-77.05],$full['capital']);
  $this->assertSame(['name'=>null,'rate'=>null],$full['deep']['tax']);
  $this->assertSame([],array_intersect(array_keys($full),['capital_lat','capital_lon']));
  $this->assertSame([],array_intersect(array_keys($full['deep']),['tax_rate','tax_id_format','tax_id_regex']));
  $this->assertSame('Americas',$full['region']);
  $this->assertSame('Northern America',$full['subregion']);
  $this->assertSame(760,$full['deep']['elevation_average']);
  $this->assertSame([],array_intersect(array_keys($full['deep']),['iso3','numeric','name_full','full_name','demonym','region','subregion','capital','capital_lat','capital_lon','borders','blocs']));
 }
 public function testActualAntarcticaNullLanguages(): void {
  $aq=json_decode(file_get_contents(__DIR__.'/fixtures/country-aq.json'),true,flags:JSON_THROW_ON_ERROR);
  $client=new Client('fixture',transport:static fn(string $url,array $headers):array => [200,[],json_encode($aq,JSON_THROW_ON_ERROR)]);
  $value=$client->country('AQ',deep:true);
  $this->assertSame($aq,$value);
  $this->assertNull($value['languages']);
  foreach (['currency','currency_name','currency_symbol'] as $key) $this->assertNull($value[$key]);
  $this->assertSame(0,$value['deep']['population']);
 }

 public function testTaxReferenceZeroesAndDeepModes(): void {
  $de=json_decode(file_get_contents(__DIR__.'/fixtures/country-de.json'),true,flags:JSON_THROW_ON_ERROR);
  $this->assertSame(['name'=>'VAT','rate'=>19],$de['deep']['tax']);
  $zero=$de;$zero['capital']=['name'=>null,'lat'=>0,'lon'=>0];$zero['deep']['tax']=['name'=>null,'rate'=>0];
  $core=$de;unset($core['deep']);$locked=[...$core,'deep'=>new \stdClass()];$bodies=[$de,$zero,$core,$locked];$calls=0;
  $client=new Client('fixture',transport:static function(string $url,array $headers)use(&$bodies,&$calls):array{$calls++;return [200,[],json_encode(array_shift($bodies),JSON_THROW_ON_ERROR)];});
  $this->assertSame($de,$client->country('DE',deep:true));$this->assertSame($zero,$client->country('DE',deep:true));$this->assertSame($core,$client->country('DE'));
  $lockedValue=$client->country('DE',deep:true);$this->assertSame([],$lockedValue['deep']);$this->assertSame(4,$calls);
 }

}
