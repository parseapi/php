<?php
use ParseAPI\Client;use PHPUnit\Framework\TestCase;
final class IPDeepExpansionTest extends TestCase {
 public function testAdditionalFieldsPassThroughOneRequest():void {
  $body=json_decode(file_get_contents(__DIR__.'/fixtures/ip-deep-expansion.json'),true,512,JSON_THROW_ON_ERROR);$calls=[];
  $client=new Client('fixture',retries:0,transport:static function(string $url,array $headers)use(&$calls,$body):array{$calls[]=$url;return [200,[],json_encode($body,JSON_THROW_ON_ERROR)];});
  $this->assertSame($body,$client->ip($body['ip'],deep:true));$this->assertCount(1,$calls);$this->assertStringContainsString('deep=true',$calls[0]);
  $this->assertSame(0,$body['deep']['latitude']);$this->assertFalse($body['deep']['proxy']);$this->assertSame('00123',$body['deep']['postal']);
 }
}
