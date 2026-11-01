<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class PostalWeatherTest extends TestCase
{
    public function testRawPostalAndWeatherResponsesRemainUnchanged(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/postal-weather.json'), true, flags: JSON_THROW_ON_ERROR);
        $bodies = array_values($fixtures);
        $calls = [];
        $client = new Client('fixture', transport: static function (string $url, array $headers) use (&$calls, $bodies): array {
            $index = count($calls);
            $calls[] = [$url, $headers];
            return [200, [], json_encode($bodies[$index], JSON_THROW_ON_ERROR)];
        });
        foreach ($fixtures as $name => $expected) {
            $actual = str_starts_with($name, 'weather') ? $client->weather(0, 0, deep: true) : $client->postal('10001', country: 'US', deep: true);
            $this->assertSame($expected, $actual);
        }
        $this->assertCount(count($fixtures), $calls);
        foreach ($calls as [$url, $headers]) {
            $this->assertSame('parseapi-php/' . Client::VERSION, $headers['User-Agent']);
        }
        $this->assertSame(['annual_median' => 0], $fixtures['postal_compact']['deep']['property_tax']);
        $this->assertNull($fixtures['weather_current']['deep']['forecast']);
        $this->assertSame([], $fixtures['weather_current']['deep']['alerts']);
    }
}
