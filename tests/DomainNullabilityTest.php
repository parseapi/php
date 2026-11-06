<?php
use ParseAPI\Client;
use PHPUnit\Framework\TestCase;

final class DomainNullabilityTest extends TestCase {
    public function testOrdinaryDomainPreservesUnknownAndKnownStates(): void {
        foreach ([null, false, true] as $available) {
            foreach ([[], ['dnssec' => null], ['dnssec' => false], ['dnssec' => true]] as $signing) {
                $body = ['domain' => 'example.com', 'available' => $available, 'deep' => $available === null ? new stdClass() : ['registration' => $available ? ['registered' => false] : ['registered' => true] + $signing]];
                $calls = [];
                $client = new Client('fixture', transport: static function(string $url, array $headers) use (&$calls, $body): array {
                    $calls[] = [$url, $headers]; return [200, [], json_encode($body, JSON_THROW_ON_ERROR)];
                });
                $this->assertSame(json_decode(json_encode($body), true), $client->domain('example.com', deep: true));
                $this->assertCount(1, $calls);
                $this->assertSame('https://api.parseapi.com/domain/example.com?deep=true', $calls[0][0]);
                $this->assertSame('2.1.0', $calls[0][1]['X-API-Version']);
            }
        }
    }
}
