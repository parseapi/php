<?php

declare(strict_types=1);
namespace ParseAPI\Tests;
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class TimeGapsTest extends TestCase
{
 public function testCatalogKeepsFalseFiltersAndLocationUncertainty(): void
 {
  $calls=[];
  $payload=['timezone'=>null,'targets'=>null,'location'=>['input'=>['type'=>'city','value'=>'Springfield'],'status'=>'ambiguous','candidates'=>[['id'=>null,'latitude'=>0,'longitude'=>0]],'truncated'=>false,'source'=>'city_reference'],'deep'=>['standard_offset_seconds'=>3600,'dst_offset_seconds'=>-3600,'season'=>['start'=>['change_seconds'=>-3600,'before'=>['dst'=>false]],'end'=>null]]];
  $client=new Client('fixture', transport:function(string $url,array $headers) use (&$calls,$payload):array { parse_str(parse_url($url,PHP_URL_QUERY)??'', $query); $calls[]=$query; return [200,[],json_encode($payload,JSON_THROW_ON_ERROR)]; });
  $this->assertSame($payload,$client->timeZones(country:'US',area:'America',offset:'+00:00',abbreviation:'UTC',dst:false,observesDst:false,at:'1970-01-01T00:00:00Z',details:true,sort:'offset'));
  $this->assertSame(['country'=>'US','area'=>'America','offset'=>'+00:00','abbreviation'=>'UTC','dst'=>'false','observes_dst'=>'false','at'=>'1970-01-01T00:00:00Z','details'=>'true','sort'=>'offset'],$calls[0]);
  foreach ([['ip'=>'2001:db8::1'],['city'=>'Springfield','country'=>'US','state'=>'IL'],['country'=>'US'],['iata'=>'JFK'],['icao'=>'KJFK'],['unlocode'=>'US NYC'],['address'=>'1 Main Street','country'=>'US','state'=>'NY']] as $source) {
   $this->assertSame($payload,$client->time(...$source));
   $seen=$calls[array_key_last($calls)];
   $this->assertCount(count($source),$seen);
   foreach ($source as $key=>$value) $this->assertSame($value,$seen[$key]);
  }
  $count=count($calls);
  foreach ([['ip'=>'8.8.8.8','city'=>'Paris'],['ip'=>'8.8.8.8','country'=>'US'],['state'=>'NY'],['city'=>'Paris','state'=>'IDF'],['address'=>'a'],['ip'=>''],['timezone'=>'UTC','city'=>'Paris']] as $source) {
   try { $client->time(...$source); $this->fail('Invalid Time source dispatched'); } catch (\InvalidArgumentException $error) { $this->assertStringContainsString('one Time source',$error->getMessage()); }
  }
  $this->assertCount($count,$calls);
 }
}
