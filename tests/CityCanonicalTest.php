<?php
declare(strict_types=1);
namespace ParseAPI\Tests;
use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class CityCanonicalTest extends TestCase
{
    public function testCanonicalSparsePagesFiltersAndReviewedRelations(): void
    {
        $calls = []; $id = 'city_7aj899nvwyku';
        $body = ['object' => 'list', 'data' => [['id' => $id, 'object' => 'city', 'name' => 'Vienna']], 'has_more' => true, 'url' => '/city', 'next_cursor' => 'opaque+/='];
        $client = new Client('fixture', retries: 0, transport: function ($url, $headers) use (&$calls, &$body) { $calls[] = $url; $this->assertSame('2.0.0', $headers['Parse-Version']); return [200, [], json_encode($body, JSON_THROW_ON_ERROR)]; });
        $this->assertSame($body, $client->cityList(countries: ['AT', 'CH'], excludeCountries: ['JP'], timezones: ['Europe/Vienna'], minElevation: -10, maxElevation: 0, minPopulation: 0, sort: '-population,+name', cursor: 'opaque+/='));
        parse_str(parse_url($calls[0], PHP_URL_QUERY), $query);
        foreach (['format'=>'list','countries'=>'AT,CH','exclude_countries'=>'JP','timezones'=>'Europe/Vienna','min_elevation'=>'-10','max_elevation'=>'0','min_population'=>'0','sort'=>'-population,+name','cursor'=>'opaque+/='] as $key=>$value) { $this->assertSame($value, $query[$key]); }
        foreach ([['cityChildren','children'], ['cityAncestors','ancestors'], ['citySiblings','siblings']] as [$method,$relationship]) {
            $body = ['object'=>'list','data'=>[],'has_more'=>false,'url'=>"/city/id/$id/$relationship",'next_cursor'=>null,'city_id'=>$id,'relationship'=>$relationship,'coverage'=>'partial'];
            $this->assertSame($body, $client->$method($id, ));
            $this->assertSame("/city/id/$id/$relationship", parse_url(end($calls), PHP_URL_PATH));
        }
        foreach ([0, null] as $value) {
            $body = ['object'=>'city_distance','from'=>$id,'to'=>$id,'distance'=>$value,'distance_mi'=>$value,'method'=>'great_circle'];
            $this->assertSame($body, $client->cityDistance($id,$id));
            $this->assertNull(parse_url(end($calls), PHP_URL_QUERY));
        }
        $body = ['id'=>$id,'object'=>'city','name'=>'Vienna'];
        $this->assertSame($body, $client->city('Vienna', format: 'resource'));
        $client->city('Vienna'); parse_str(parse_url(end($calls), PHP_URL_QUERY) ?? '', $query); $this->assertArrayNotHasKey('format',$query); $this->assertArrayNotHasKey('fields',$query);
    }

    public function testExplicitMatchingRetainsSuggestionEvidence(): void
    {
        $url='';$body=['object'=>'list','data'=>[['id'=>'city_7aj899nvwyku','object'=>'city','match'=>['type'=>'fuzzy','source'=>'name','value'=>'Vienna','language'=>null,'edit_distance'=>1]]],'has_more'=>false,'url'=>'/city','next_cursor'=>null];
        $client=new Client('fixture',transport:function($value)use(&$url,$body){$url=$value;return[200,[],json_encode($body,JSON_THROW_ON_ERROR)];});
        $this->assertSame($body,$client->citySearch('Viena',format:'list',match:'fuzzy',maxDistance:1));parse_str(parse_url($url,PHP_URL_QUERY),$query);$this->assertSame(['format'=>'list','q'=>'Viena','match'=>'fuzzy','max_distance'=>'1'],$query);$client->citySearch('Vi');parse_str(parse_url($url,PHP_URL_QUERY),$query);$this->assertSame(['q'=>'Vi'],$query);
    }

    public function testBoundedChoicesOnlyOnMatching409AndUnchangedOrdinaryErrors(): void
    {
        $safe = ['id'=>'city_7aj899nvwyku','object'=>'city','name'=>'Vienna','state'=>null];
        foreach ([[409,'ambiguous_city',true],[400,'ambiguous_city',false],[409,'invalid_request',false],[429,'rate_limited',false]] as [$status,$code,$present]) {
            $calls=0; $client = new Client('fixture', retries: 0, transport: function () use (&$calls,$safe,$status,$code) { $calls++; return [$status,['retry-after'=>'60'],json_encode(['code'=>$code,'message'=>'Choose','candidates'=>[$safe+['deep'=>['population'=>99]],$safe,$safe]],JSON_THROW_ON_ERROR)]; });
            try { $client->city('Vienna', ambiguity: 'error'); $this->fail('Expected an API error'); }
            catch (ParseAPIError $error) { $this->assertSame($status,$error->status); $this->assertSame($code,$error->errorCode); $this->assertSame('60',$error->retryAfter); $this->assertSame($present,isset($error->candidates)); if ($present) { $this->assertSame([$safe,$safe],$error->candidates); } }
            $this->assertSame(1,$calls);
        }
    }

    public function testReviewedRomanizedOptIn(): void
    {
        $url='';$payload=['id'=>'city_7aj899nvwyku','object'=>'city','name_romanized'=>'Kitakyūshū','romanization_language'=>'ja-Latn'];$c=new Client('fixture',transport:function($value)use(&$url,&$payload){$url=$value;return[200,[],json_encode($payload,JSON_THROW_ON_ERROR)];});
        $this->assertSame($payload,$c->cityId('city_7aj899nvwyku',format:'resource',romanized:true));parse_str(parse_url($url,PHP_URL_QUERY),$q);$this->assertSame(['romanized'=>'true','format'=>'resource'],$q);$payload['name_romanized']=null;$payload['romanization_language']=null;$this->assertSame($payload,$c->cityId('city_7aj899nvwyku',format:'resource',romanized:true));
    }

    public function testExplicitSpellingRouteAndClassicAdapter(): void {
        $calls=[];$row=['id'=>'city_7aj899nvwyku','object'=>'city','name'=>'Vienna'];
        $c=new Client('fixture',retries:0,transport:function($url)use(&$calls,$row){$path=parse_url($url,PHP_URL_PATH);$calls[]=$path;return [200,[],json_encode($path==='/city/search'?['object'=>'list','data'=>[$row],'q'=>'Viena','has_more'=>false,'url'=>'/city/search','next_cursor'=>null]:['q'=>'Vi','cities'=>[$row]])];});
        $this->assertSame([$row],$c->citySearch('Viena',match:'fuzzy',maxDistance:1)['cities']);
        $this->assertSame([$row],$c->citySearch('Viena',match:'fuzzy',format:'list')['data']);
        $this->assertSame([$row],$c->citySearch('Vi')['cities']);$this->assertSame(['/city/search','/city/search','/city'],$calls);
    }

}
