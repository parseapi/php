<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;
final class IsoContractTest extends TestCase {
 public function testBothIsoContractsUseExistingMethodsAndPreserveRawValues(): void {
  $samples=json_decode(file_get_contents(__DIR__.'/fixtures/iso-contract.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($samples as $sample){
   $calls=[];
   $client=new Client('fixture',retries:0,transport:static function(string $url,array $headers)use(&$calls,$sample):array{
    $calls[]=$url;return [200,[],json_encode($sample['body'],JSON_THROW_ON_ERROR)];
   });
   $options=['deep'=>$sample['deep']];
   if(isset($sample['country']))$options['country']=$sample['country'];
   $result=$client->{$sample['operation']}($sample['code'],...$options);
   $this->assertSame($sample['body'],$result);$this->assertCount(1,$calls);
   $this->assertSame('/'.$sample['operation'].'/'.$sample['code'],parse_url($calls[0],PHP_URL_PATH));
   parse_str(parse_url($calls[0],PHP_URL_QUERY)??'', $query);
   $this->assertSame($sample['deep']?'true':null,$query['deep']??null);
  }
 }
}
