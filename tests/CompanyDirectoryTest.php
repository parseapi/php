<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;
use PHPUnit\Framework\TestCase;

final class CompanyDirectoryTest extends TestCase
{
	private array $calls = [];

	private function client(array $responses, ?int $retries = 0): Client
	{
		$this->calls = [];
		return new Client('company_fixture', retries: $retries, transport: function (string $url, array $headers) use (&$responses): array {
			$this->calls[] = ['url' => $url, 'headers' => $headers];
			$this->assertNotEmpty($responses, 'Unexpected request');
			[$status, $body] = array_shift($responses);
			return [$status, ['retry-after' => '0'], json_encode($body, JSON_THROW_ON_ERROR)];
		});
	}

	private function params(int $index): array
	{
		parse_str(parse_url($this->calls[$index]['url'], PHP_URL_QUERY) ?? '', $params);
		return $params;
	}

	public function testSelectorsEncodingFiltersAndCoverage(): void
	{
		$cases = [
			[fn ($p) => $p->companyId('co_/é?+', deep: true), '/company/id/co_%2F%C3%A9%3F%2B', ['deep' => 'true']],
			[fn ($p) => $p->companyId('co_caczn6wf36hj'), '/company/id/co_caczn6wf36hj', []],
			[fn ($p) => $p->companySearch(query: 'Café & Co', country: 'us', limit: 2, cursor: 'a+/=?&', deep: true), '/company', ['q' => 'Café & Co', 'country' => 'us', 'limit' => '2', 'cursor' => 'a+/=?&', 'deep' => 'true']],
			[fn ($p) => $p->companySearch(domain: 'WWW.Example.COM.'), '/company', ['domain' => 'WWW.Example.COM.']],
			[fn ($p) => $p->companySearch(ticker: 'BRK/B', exchange: 'X/Y'), '/company', ['ticker' => 'BRK/B', 'exchange' => 'X/Y']],
			[fn ($p) => $p->companySearch(identifier: '0000/123', authority: 'US/SEC', country: 'US'), '/company', ['identifier' => '0000/123', 'country' => 'US', 'authority' => 'US/SEC']],
			[fn ($p) => $p->companySearch(query: '', limit: 0, cursor: ''), '/company', ['q' => '', 'limit' => '0', 'cursor' => '']],
			[fn ($p) => $p->companySearch(country: 'US'), '/company', ['country' => 'US']],
			[fn ($p) => $p->companySearch(industry: '0700', industryType: 'sic'), '/company', ['industry' => '0700', 'industry_type' => 'sic']],
			[fn ($p) => $p->companySearch(country: 'US', industry: '0700', industryType: 'sic', cursor: 'opaque+/=', limit: 2, deep: true), '/company', ['country' => 'US', 'limit' => '2', 'cursor' => 'opaque+/=', 'deep' => 'true', 'industry' => '0700', 'industry_type' => 'sic']],
			[fn ($p) => $p->companySearch(query: 'Example', industry: '0700', industryType: 'sic', deep: false), '/company', ['q' => 'Example', 'industry' => '0700', 'industry_type' => 'sic']],
			[fn ($p) => $p->companySearch('Example', null, null, null, 'US', null, null, 2, 'old+/=', true), '/company', ['q' => 'Example', 'country' => 'US', 'limit' => '2', 'cursor' => 'old+/=', 'deep' => 'true']],
            [fn ($p) => $p->companySearch(registrationAuthority: 'ra000599'), '/company', ['registration_authority' => 'ra000599']],
            [fn ($p) => $p->companySearch(country: 'US', industry: '0700', industryType: 'sic', registrationAuthority: 'RA000599', registrationForm: 'DPC', registrationStatus: ' Good Standing ', limit: 2, cursor: 'opaque+/=', deep: true), '/company', ['country' => 'US', 'limit' => '2', 'cursor' => 'opaque+/=', 'deep' => 'true', 'industry' => '0700', 'industry_type' => 'sic', 'registration_authority' => 'RA000599', 'registration_form' => 'DPC', 'registration_status' => ' Good Standing ']],
            [fn ($p) => $p->companySearch(identifier: '00001', authority: 'SEC', registrationAuthority: 'RA000599', registrationForm: 'future/Form', registrationStatus: 'future+& status', deep: false), '/company', ['identifier' => '00001', 'authority' => 'SEC', 'registration_authority' => 'RA000599', 'registration_form' => 'future/Form', 'registration_status' => 'future+& status']],
			[fn ($p) => $p->companyCoverage(), '/company/directory/coverage', []],
		];
		foreach ($cases as [$call, $path, $params]) {
			$body = ['companies' => [], 'next' => null, 'future' => null];
			$client = $this->client([[200, $body]]);
			$this->assertSame($body, $call($client));
			$this->assertCount(1, $this->calls);
			$this->assertSame($path, parse_url($this->calls[0]['url'], PHP_URL_PATH));
			$this->assertSame($params, $this->params(0));
			$this->assertSame('2.0.0', $this->calls[0]['headers']['Parse-Version']);
			$this->assertSame('company_fixture', $this->calls[0]['headers']['X-API-Key']);
		}
	}

	public function testDeepTriadUnknownNullFieldsPaginationAndCoverage(): void
	{
		$base = ['id' => 'co_caczn6wf36hj', 'name' => 'GitLab', 'country' => 'US', 'website' => null, 'listings' => [], 'address' => null, 'future' => ['unknown' => null]];
		$rich = ['description' => null, 'logo' => 'https://example.com/logo.svg', 'socials' => [], 'founded' => ['value' => '2011', 'precision' => 'future_precision'], 'sources' => [['type' => 'future_source', 'url' => 'https://example.com/', 'fields' => ['logo'], 'observed_at' => null, 'updated_at' => null, 'future' => true]], 'parent' => null];
		$profiles = [$base, $base + ['deep' => (object) []], $base + ['deep' => $rich], $base + ['deep' => ['description' => null, 'logo' => null, 'socials' => null, 'founded' => null, 'sources' => null]]];
		$page = ['companies' => array_map(fn ($p) => $p + ['match' => ['field' => 'future_field', 'value' => null]], $profiles), 'next' => 'opaque+/=', 'future' => null];
		$empty = ['companies' => [], 'next' => null];
		$coverage = ['scope' => 'future_scope', 'companies' => 0, 'countries' => [], 'future' => null];
		$client = $this->client(array_map(fn ($body) => [200, $body], [...$profiles, $page, $empty, $coverage]));
		foreach ($profiles as $i => $profile) {
			$this->assertSame(json_decode(json_encode($profile, JSON_THROW_ON_ERROR), true), $client->companyId($base['id'], deep: $i > 0));
		}
		$this->assertSame(json_decode(json_encode($page, JSON_THROW_ON_ERROR), true), $client->companySearch(query: 'GitLab', limit: 4, deep: true));
		$this->assertSame($empty, $client->companySearch(query: 'GitLab', limit: 4, cursor: $page['next'], deep: true));
		$this->assertSame($coverage, $client->companyCoverage());
		$this->assertCount(7, $this->calls);
		$this->assertSame(['q' => 'GitLab', 'limit' => '4', 'cursor' => 'opaque+/=', 'deep' => 'true'], $this->params(5));
	}

	public function testApiValidationAndNotFoundErrors(): void
	{
		foreach ([[400, fn ($p) => $p->companySearch(query: 'A', domain: 'B')], [400, fn ($p) => $p->companySearch()], [404, fn ($p) => $p->companyId('co_unknown')]] as [$status, $call]) {
			$code = $status === 400 ? 'invalid_request' : 'not_found';
			$client = $this->client([[$status, ['code' => $code, 'message' => 'source error', 'request_id' => 'r1', 'docs' => null]]]);
			try { $call($client); $this->fail('Expected API error'); }
			catch (ParseAPIError $e) { $this->assertSame($status, $e->status); $this->assertSame($code, $e->errorCode); $this->assertSame('r1', $e->requestId); }
			$this->assertCount(1, $this->calls);
		}
	}

	public function testNationalNumberAndRetryControlsRemainUnchanged(): void
	{
		$body = ['valid' => true, 'company' => '51824753556', 'deep' => ['activity' => null]];
		$client = $this->client([[200, $body]]);
		$this->assertSame($body, $client->company('51 824 753 556', country: 'AU', deep: true, lang: 'fr'));
		$this->assertSame('/company/51%20824%20753%20556', parse_url($this->calls[0]['url'], PHP_URL_PATH));
		$this->assertSame(['country' => 'AU', 'deep' => 'true', 'lang' => 'fr'], $this->params(0));
		$client = $this->client([[503, []], [503, []], [200, []]], retries: null);
		$this->assertSame([], $client->companyId('co_caczn6wf36hj', deep: true));
		$this->assertCount(3, $this->calls);
		$client = $this->client([[503, []]], retries: 0);
		try { $client->companyCoverage(); $this->fail('Expected 503'); } catch (ParseAPIError $e) { $this->assertSame(503, $e->status); }
		$this->assertCount(1, $this->calls);
	}

	public function testDirectoryDoesNotAcceptLang(): void
	{
		$client = $this->client([]);
		foreach ([fn () => $client->companyId('co_caczn6wf36hj', lang: 'fr'), fn () => $client->companySearch(query: 'A', lang: 'fr')] as $call) {
			try { $call(); $this->fail('Expected unknown named argument'); }
			catch (\Error $e) { $this->assertStringContainsString('lang', $e->getMessage()); }
		}
		$this->assertCount(0, $this->calls);
	}

	public function testReadmeRecipeUsesExplicitSelectionAndReturnedCursor(): void
	{
		$section = explode("## Company directory\n", file_get_contents(dirname(__DIR__) . '/README.md'), 2)[1];
		preg_match('/```php\n(.*?)```/s', $section, $match);
		$parse = $this->client([[200, ['companies' => [], 'next' => 'opaque+/=']], [200, ['deep' => []]], [200, ['companies' => [], 'next' => null]], [200, ['scope' => 'sample']]]);
		eval($match[1]);
		$this->assertCount(4, $this->calls);
		$this->assertSame('/company/id/co_caczn6wf36hj', parse_url($this->calls[1]['url'], PHP_URL_PATH));
		$this->assertSame('opaque+/=', $this->params(2)['cursor']);
	}
	public function testEmployeeObservationsPreserveZeroFalseDatesAndFutureCodes(): void
	{
		$values = [
			[], ['employees' => null],
			['employees' => ['count' => 0, 'as_of' => '2025-12-31', 'scope' => 'legal_entity', 'method' => 'reported', 'approximate' => false]],
			['employees' => ['count' => 12500, 'as_of' => '2026-06-30', 'scope' => 'consolidated_group', 'method' => 'reported', 'approximate' => true]],
			['employees' => ['count' => 7, 'as_of' => '2026-01-15', 'scope' => 'future_scope', 'method' => 'future_method', 'approximate' => false, 'future' => null]],
		];
		foreach ($values as $deep) {
			$profile = ['id' => 'co_caczn6wf36hj', 'name' => 'GitLab', 'deep' => $deep];
			$page = ['companies' => [$profile + ['match' => ['field' => 'name', 'value' => 'GitLab']]], 'next' => null];
			$client = $this->client([[200, $profile], [200, $page]]);
			$result = $client->companyId($profile['id'], deep: true);
			$this->assertSame($profile, $result);
			$this->assertSame(array_key_exists('employees', $deep), array_key_exists('employees', $result['deep']));
			$this->assertSame($page, $client->companySearch(query: 'GitLab', deep: true));
			$this->assertCount(2, $this->calls);
		}
	}

 public function testRegistrationsPreserveSourceRolesNullsAndLeadingZeros(): void
 {
  $registration = json_decode('{"authority":"RA000599","number":"0001234567","jurisdiction":{"country":"US","state":"CO"},"role":"domestic","legal_form":{"code":"DNC","name":"Domestic Non-profit Corporation"},"status":"Good Standing","formation_date":"2004-02-29","address":{"kind":"principal","line1":"12 Main St.","line2":"Suite 2","city":"Example","state":"CO","postal":"00123-0001","country_raw":"US"},"future":"retained"}', true, 512, JSON_THROW_ON_ERROR);
  foreach ([[], ['registrations' => null], ['registrations' => []], ['registrations' => [$registration]], ['registrations' => [array_replace($registration, ['role' => 'future_role', 'formation_date' => null, 'address' => null])]]] as $deep) {
   $profile = ['id' => 'co_222222222222', 'name' => 'Example', 'deep' => $deep];
   $page = ['companies' => [$profile + ['match' => ['field' => 'identifier', 'value' => $registration['number']]]], 'next' => null];
   $client = $this->client([[200, $profile], [200, $page]]);
   $this->assertSame($profile, $client->companyId($profile['id'], deep: true));
   $this->assertSame($page, $client->companySearch(identifier: $registration['number'], authority: $registration['authority'], deep: true));
   $this->assertCount(2, $this->calls);
  }
 }

}
