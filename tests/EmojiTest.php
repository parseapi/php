<?php
declare(strict_types=1);
namespace ParseAPI\Tests;
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class EmojiTest extends TestCase
{
	public function testFiltersAndPrivateTextBody(): void
	{
		$calls = [];
		$payload = ['total'=>2, 'emojis'=>[['emoji'=>'👍🏿', 'name'=>'thumbs up', 'shortcodes'=>[], 'category'=>null, 'occurrences'=>2, 'positions'=>[['start'=>2,'end'=>6],['start'=>7,'end'=>11]], 'deep'=>['base'=>'👍', 'variants'=>[['emoji'=>'👍🏿','tones'=>['dark']]]]]], 'future'=>true];
		$client = new Client('test', retries:0, transport:function($url, $headers, $method='GET', $body=null) use (&$calls, $payload) {
			$calls[] = compact('url','headers','method','body');
			return [200, [], json_encode($payload, JSON_THROW_ON_ERROR)];
		});
		$client->emoji(':cat:', shortcodeSource:'github');
		$client->emojiSearch('face', category:'Smileys & Emotion', version:'17.0', maxVersion:'18.0', includeSkinTones:false, offset:0);
		$client->emojiBrowse(includeSkinTones:false, offset:2);
		$client->emojiCategories();
		$this->assertSame($payload, $client->emojiParse('x 👍🏿 👍🏿', deep:true, lang:'fr'));
		parse_str(parse_url($calls[0]['url'],PHP_URL_QUERY), $lookup);
		$this->assertSame(['shortcode_source'=>'github'], $lookup);
		parse_str(parse_url($calls[1]['url'],PHP_URL_QUERY), $search);
		$this->assertSame(['q'=>'face','category'=>'Smileys & Emotion','version'=>'17.0','max_version'=>'18.0','include_skin_tones'=>'false','offset'=>'0'], $search);
		parse_str(parse_url($calls[2]['url'],PHP_URL_QUERY), $browse);
		$this->assertSame(['include_skin_tones'=>'false','offset'=>'2'], $browse);
		$this->assertSame('https://api.parseapi.com/emoji/categories', $calls[3]['url']);
		parse_str(parse_url($calls[4]['url'],PHP_URL_QUERY), $parse); $this->assertSame(['text'=>'x 👍🏿 👍🏿','deep'=>'true','lang'=>'fr'],$parse);
		$this->assertSame('GET', $calls[4]['method']);
		$this->assertNull($calls[4]['body']);
		$this->assertSame('2.2.0',$calls[4]['headers']['X-API-Version']);
		$this->assertCount(5, $calls);
	}
	public function testFullDownloadMetadataAndArtwork(): void
	{
		$bundle = json_decode(file_get_contents(__DIR__ . '/fixtures/emoji-catalog.json'), true, 512, JSON_THROW_ON_ERROR);
		$calls = [];
		$client = new Client('test', transport:function($url, $headers) use (&$calls, $bundle) {
			$calls[] = $url;
			return [200, [], json_encode(parse_url($url,PHP_URL_PATH) === '/emoji/metadata' ? $bundle['metadata'] : $bundle, JSON_THROW_ON_ERROR)];
		});
		$result = $client->emojiCatalog();
		$this->assertSame('https://api.parseapi.com/emoji/catalog',$calls[0]);
		$this->assertSame('❤️',$result['forms']['❤']);
		$this->assertSame([], $result['emojis'][0]['artwork']);
		$client->emojiCatalog( maxVersion:'17.0', includeSkinTones:false);
		$this->assertSame('https://api.parseapi.com/emoji/catalog?max_version=17.0&include_skin_tones=false',$calls[1]);
		$this->assertSame('Full source notice',$client->emojiCatalog()['metadata']['notices'][0]['notice']);
		$this->assertSame('https://api.parseapi.com/emoji/catalog',$calls[2]);
	}

 public function testPrivateConversion(): void
 {
  $calls=[];$client=new Client('test',transport:function($url,$headers,$method='GET',$body=null) use (&$calls){$calls[]=compact('url','method','body');return [200,[], '{"text":"🚀","unresolved":[]}'];});
  $this->assertSame('🚀',$client->emojiConvert(':rocket:',direction:'to_emoji',shortcodeSource:'github')['text']);
  $this->assertCount(1,$calls);$this->assertSame('GET',$calls[0]['method']);parse_str(parse_url($calls[0]['url'],PHP_URL_QUERY),$query);$this->assertSame(':rocket:',$query['text']);$this->assertNull($calls[0]['body']);
 }




 public function testKeyedArtwork(): void
 {
  $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/emoji-artwork.json'),true,512,JSON_THROW_ON_ERROR);
  $client=new Client('test',transport:fn($url,$headers)=>[200,[],json_encode($fixture['emoji'],JSON_THROW_ON_ERROR)]);
  $artwork=$client->emoji('rocket',deep:true)['artwork'];
  $this->assertSame(['noto','twemoji','openmoji'],array_keys($artwork));
  $this->assertSame('https://cdn.parseapi.com/emoji/openmoji/17.0.0/1f680.svg',$artwork['openmoji']['svg']);
  $this->assertSame('https://cdn.parseapi.com/emoji/openmoji/17.0.0/72/1f680.png',$artwork['openmoji']['png']['72']);
 }

    public function testLongTextUsesOnePostAndEmptyTextUsesGet(): void
    {
        foreach ([str_repeat('🚀', 8000), ''] as $text) {
            $calls=[];
            $client=new Client('fixture',transport:function($url,$headers,$method='GET',$body=null) use (&$calls) { $calls[]=compact('url','method','body'); return [200,[], '{"emojis":[]}']; });
            $client->emojiParse($text);
            $this->assertCount(1,$calls);parse_str(parse_url($calls[0]['url'],PHP_URL_QUERY)??'', $query);
            if ($text !== '') { $this->assertSame('POST',$calls[0]['method']);$this->assertSame(['text'=>$text],json_decode($calls[0]['body'],true));$this->assertArrayNotHasKey('text',$query); }
            else { $this->assertSame('GET',$calls[0]['method']);$this->assertSame('',$query['text']); }
        }
    }

}
