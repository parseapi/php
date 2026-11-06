<?php
declare(strict_types=1);
namespace ParseAPI\Tests;
use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class NaturalContractTest extends TestCase
{
    public function testExplicitEmailVerificationUsesPostAndRootResult(): void
    {
        $calls = [];
        $result = ['email' => 'a+b@example.com', 'valid' => true, 'deliverable' => false, 'catchall' => null, 'future' => 0];
        $client = new Client('fixture', transport: static function (string $url, array $headers, string $method = 'GET', ?string $body = null) use (&$calls, $result): array {
            $calls[] = [$url, $headers, $method, $body];
            return [200, [], json_encode($result, JSON_THROW_ON_ERROR)];
        });
        $this->assertSame($result, $client->email($result['email']));
        $this->assertSame($result, $client->emailVerify($result['email']));
        $this->assertSame($result, $client->email($result['email'], deep: true));
        $this->assertSame('GET', $calls[0][2]);
        foreach (array_slice($calls, 1) as [$url, $headers, $method, $body]) {
            $this->assertSame('https://api.parseapi.com/email/verify', $url);
            $this->assertSame('2.1.0', $headers['X-API-Version']);
            $this->assertSame('POST', $method);
            $this->assertSame(['email' => $result['email']], json_decode($body, true, 512, JSON_THROW_ON_ERROR));
        }
        $this->assertArrayNotHasKey('deep', $result);
    }

    public function testVerificationHasNoAutomaticRetry(): void
    {
        $calls = 0;
        $client = new Client('fixture', transport: static function (...$args) use (&$calls): array {
            $calls++;
            return [503, ['retry-after' => '0'], '{"code":"unavailable","message":"Unavailable"}'];
        });
        try { $client->emailVerify('person@example.com'); $this->fail('Expected API error'); }
        catch (ParseAPIError $error) { $this->assertSame(503, $error->status); }
        $this->assertSame(1, $calls);
    }
}
