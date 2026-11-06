<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;
final class CoreConnectorsTest extends TestCase {
 public function testDynamicReferencesAndDeepStayUnchanged(): void {
  $fixtures=json_decode(file_get_contents(__DIR__.'/fixtures/core-connectors.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($fixtures as $body){
   $calls=[];
   $client=new Client('fixture',retries:0,transport:static function(string $url,array $headers)use(&$calls,$body):array{$calls[]=$url;return [200,[],json_encode($body,JSON_THROW_ON_ERROR)];});
   $this->assertSame($body,$client->country('US'));$this->assertCount(1,$calls);$this->assertNull(parse_url($calls[0],PHP_URL_QUERY));
  }
  $this->assertSame($fixtures['point']['city_id'],$fixtures['point']['deep']['city']['id']);
  $this->assertSame('US',$fixtures['postal']['country']);
  $this->assertSame(['00544'],$fixtures['postal']['deep']['neighbors']);$this->assertArrayNotHasKey('neighbors',$fixtures['postal']);$this->assertArrayNotHasKey('currency',$fixtures['postal']);
  $this->assertSame('0000320193',$fixtures['company']['identifiers'][0]['value']);
  $this->assertSame(0,$fixtures['country']['capital_lat']);
  $this->assertSame(['👍🏽'],$fixtures['emoji']['variants']);
  $this->assertSame('👍🏽',$fixtures['emoji']['deep']['variants'][0]['emoji']);
 }
}
