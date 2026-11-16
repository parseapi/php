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
            $this->assertSame('/email/verify', parse_url($url, PHP_URL_PATH));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame(['email' => $result['email']], $query);
            $this->assertSame('2.2.0', $headers['X-API-Version']);
            $this->assertSame('GET', $method);
            $this->assertNull($body);
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
    public function testEmailOutcomesKeepUnknownDistinctWithStatus(): void
    {
        foreach ([[true, 'accepted'], [false, 'mailbox_not_found'], [null, 'catchall']] as [$deliverable, $reason]) {
            $status = $reason === 'accepted' ? 'deliverable' : ($reason === 'mailbox_not_found' ? 'undeliverable' : 'risky');
            $result = ['status' => $status, 'email' => 'a@example.com', 'valid' => true, 'domain' => 'example.com', 'deliverable' => $deliverable, 'catchall' => $deliverable === null, 'reason' => $reason];
            $client = new Client('fixture', transport: static fn (string $url, array $headers, string $method = 'GET', ?string $body = null): array => [200, [], json_encode($result, JSON_THROW_ON_ERROR)]);
            $this->assertSame($result, $client->emailVerify($result['email']));
            $this->assertSame($status, $result['status']);
        }
    }

}
