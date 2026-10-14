<?php
declare(strict_types=1);
namespace ParseAPI\Tests;

use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class MeasureResponseTest extends TestCase
{
    public function testCleanAndLegacyMeasureResponses(): void
    {
        $cases = json_decode(file_get_contents(__DIR__.'/fixtures/measure-response.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $body) {
            $client = new Client('fixture', transport: static fn() => [200, [], json_encode($body, JSON_THROW_ON_ERROR)]);
            $this->assertSame($body, $client->measure($body['measure']));
        }
    }
}
