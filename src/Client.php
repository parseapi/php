<?php

declare(strict_types=1);

namespace ParseAPI;

/**
 * Official parseAPI client for PHP.
 *
 *   $parse = new \ParseAPI\Client('your-api-key');
 *   $country = $parse->country('US');
 */
class Client
{
	public const VERSION = '0.2.1';

	private const DEFAULT_BASE_URL = 'https://api.parseapi.com';
	private const RETRY_STATUS = [429, 500, 502, 503, 504];
	private const RETRY_AFTER_CAP = 5.0;

	private string $apiKey;
	private string $baseUrl;
	private float $timeout;
	private int $retries;
	/** @var ?callable fn(string $url, array $headers): array{0:int,1:array,2:string} */
	private $transport;
	private ?\CurlHandle $curl = null;

	public function __construct(
		?string $apiKey = null,
		?string $baseUrl = null,
		float $timeout = 10.0,
		int $retries = 2,
		?callable $transport = null,
	) {
		$key = $apiKey ?? (getenv('PARSEAPI_KEY') ?: null);
		if ($key === null || $key === '') {
			throw new \InvalidArgumentException('parseapi: missing API key. Pass one or set PARSEAPI_KEY.');
		}
		$this->apiKey = $key;
		$this->baseUrl = rtrim($baseUrl ?? (getenv('PARSEAPI_BASE_URL') ?: self::DEFAULT_BASE_URL), '/');
		$this->timeout = $timeout;
		$this->retries = $retries;
		$this->transport = $transport;
	}

	// --- Lookup methods (one per endpoint, named after the route) ---

	public function ip(string $ip, bool $deep = false): array
	{
		return $this->get('/ip/' . rawurlencode($ip), ['deep' => $deep]);
	}

	public function ipSelf(bool $deep = false): array
	{
		return $this->get('/ip', ['deep' => $deep]);
	}

	public function continent(string $code): array
	{
		return $this->get('/continent/' . rawurlencode($code));
	}

	public function continentCountries(string $code): array
	{
		return $this->get('/continent/' . rawurlencode($code) . '/countries');
	}

	public function bloc(string $code): array
	{
		return $this->get('/bloc/' . rawurlencode($code));
	}

	public function blocCountries(string $code): array
	{
		return $this->get('/bloc/' . rawurlencode($code) . '/countries');
	}

	public function country(string $code): array
	{
		return $this->get('/country/' . rawurlencode($code));
	}

	public function countryStates(string $code): array
	{
		return $this->get('/country/' . rawurlencode($code) . '/states');
	}

	public function state(string $code, ?string $country = null): array
	{
		return $this->get('/state/' . rawurlencode($code), ['country' => $country]);
	}

	public function stateDistricts(string $code, ?string $country = null): array
	{
		return $this->get('/state/' . rawurlencode($code) . '/districts', ['country' => $country]);
	}

	public function district(string $code, ?string $country = null, ?string $state = null): array
	{
		return $this->get('/district/' . rawurlencode($code), ['country' => $country, 'state' => $state]);
	}

	public function city(string $name, ?string $country = null, ?string $state = null): array
	{
		return $this->get('/city/' . rawurlencode($name), ['country' => $country, 'state' => $state]);
	}

	public function cityId(string $id): array
	{
		return $this->get('/city/id/' . rawurlencode($id));
	}

	public function citySearch(string $q, ?string $country = null, ?string $state = null, ?int $limit = null): array
	{
		return $this->get('/city', ['q' => $q, 'country' => $country, 'state' => $state, 'limit' => $limit]);
	}

	public function cityNearest(float $lat, float $lon): array
	{
		return $this->get('/city', ['lat' => $lat, 'lon' => $lon]);
	}

	public function cityNearby(string $name, ?float $radius = null, ?string $unit = null, ?string $country = null, ?string $state = null, ?int $limit = null): array
	{
		return $this->get('/city/' . rawurlencode($name) . '/nearby', [
			'radius' => $radius,
			'unit' => $unit,
			'country' => $country,
			'state' => $state,
			'limit' => $limit,
		]);
	}

	public function postal(string $code, ?string $country = null): array
	{
		return $this->get('/postal/' . rawurlencode($code), ['country' => $country]);
	}

	public function postalNearby(string $code, ?string $country = null, ?float $radius = null, ?string $unit = null): array
	{
		return $this->get('/postal/' . rawurlencode($code) . '/nearby', ['country' => $country, 'radius' => $radius, 'unit' => $unit]);
	}

	public function postalDistance(string $from, string $to, ?string $country = null): array
	{
		return $this->get('/postal/' . rawurlencode($from) . '/distance/' . rawurlencode($to), ['country' => $country]);
	}

	public function email(string $email, bool $deep = false): array
	{
		return $this->get('/email/' . rawurlencode($email), ['deep' => $deep]);
	}

	public function vat(string $number, ?string $country = null, bool $deep = false, ?string $from = null): array
	{
		return $this->get('/vat/' . rawurlencode($number), ['country' => $country, 'deep' => $deep, 'from' => $from]);
	}

	public function iban(string $iban, ?string $country = null): array
	{
		return $this->get('/iban/' . rawurlencode($iban), ['country' => $country]);
	}

	public function npi(string $npi, bool $deep = false): array
	{
		return $this->get('/npi/' . rawurlencode($npi), ['deep' => $deep]);
	}

	public function phone(string $number, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/phone/' . rawurlencode($number), ['country' => $country, 'deep' => $deep]);
	}

	public function carrier(string $number, ?string $country = null): array
	{
		return $this->get('/carrier/' . rawurlencode($number), ['country' => $country]);
	}

	public function caller(string $number, ?string $country = null): array
	{
		return $this->get('/caller/' . rawurlencode($number), ['country' => $country]);
	}

	public function hlr(string $number, ?string $country = null): array
	{
		return $this->get('/hlr/' . rawurlencode($number), ['country' => $country]);
	}

	public function domain(string $domain, bool $deep = false): array
	{
		return $this->get('/domain/' . rawurlencode($domain), ['deep' => $deep]);
	}

	public function mx(string $domain): array
	{
		return $this->get('/mx/' . rawurlencode($domain));
	}

	public function useragent(string $ua, bool $deep = false): array
	{
		return $this->get('/useragent', ['deep' => $deep], ['User-Agent' => $ua]);
	}

	public function vin(string $vin, bool $deep = false): array
	{
		return $this->get('/vin/' . rawurlencode($vin), ['deep' => $deep]);
	}

	public function tariff(string $code, bool $deep = false, ?string $origin = null): array
	{
		return $this->get('/tariff/' . rawurlencode($code), ['deep' => $deep, 'origin' => $origin]);
	}

	public function tariffSearch(string $q): array
	{
		return $this->get('/tariff', ['q' => $q]);
	}

	public function currency(string $code): array
	{
		return $this->get('/currency/' . rawurlencode($code));
	}

	public function currencyRate(string $base, string $quote, ?string $date = null, ?float $amount = null): array
	{
		return $this->get('/currency/' . rawurlencode($base) . '/' . rawurlencode($quote), [
			'date' => $date,
			'amount' => $amount,
		]);
	}

	public function language(string $code): array
	{
		return $this->get('/language/' . rawurlencode($code));
	}

	public function name(string $name): array
	{
		return $this->get('/name/' . rawurlencode($name));
	}

	public function timezone(string $id, ?string $at = null): array
	{
		return $this->get('/timezone/' . rawurlencode($id), ['at' => $at]);
	}

	public function holiday(string $country, ?int $year = null): array
	{
		return $this->get('/holiday/' . rawurlencode($country), ['year' => $year]);
	}

	public function holidayDate(string $country, string $date): array
	{
		return $this->get('/holiday/' . rawurlencode($country) . '/' . rawurlencode($date));
	}

	public function elevation(float $lat, float $lon): array
	{
		return $this->get('/elevation', ['lat' => $lat, 'lon' => $lon]);
	}

	public function point(float $lat, float $lon, bool $deep = false): array
	{
		return $this->get('/point', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep]);
	}

	public function weather(float $lat, float $lon, bool $deep = false): array
	{
		return $this->get('/weather', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep]);
	}

	public function emoji(string $emoji): array
	{
		return $this->get('/emoji/' . rawurlencode($emoji));
	}

	public function emojiSearch(string $q, ?int $limit = null): array
	{
		return $this->get('/emoji', ['q' => $q, 'limit' => $limit]);
	}

	// --- Transport ---

	private function get(string $path, array $query = [], array $headers = []): array
	{
		$clean = [];
		foreach ($query as $name => $value) {
			if ($value === null || $value === false) {
				continue;
			}
			$clean[$name] = $value === true ? 'true' : (string) $value;
		}
		$url = $this->baseUrl . $path;
		if ($clean !== []) {
			$url .= '?' . http_build_query($clean);
		}

		$requestHeaders = array_merge(
			['X-API-Key' => $this->apiKey, 'User-Agent' => 'parseapi-php/' . self::VERSION],
			$headers,
		);

		$attempt = 0;
		while (true) {
			try {
				[$status, $responseHeaders, $body] = $this->execute($url, $requestHeaders);
			} catch (\RuntimeException $e) {
				if ($attempt < $this->retries) {
					usleep((int) ($this->retryDelay($attempt, null) * 1_000_000));
					$attempt++;
					continue;
				}
				throw $e;
			}

			if ($status >= 200 && $status < 300) {
				$decoded = json_decode($body, true);
				return is_array($decoded) ? $decoded : [];
			}

			if (in_array($status, self::RETRY_STATUS, true) && $attempt < $this->retries) {
				usleep((int) ($this->retryDelay($attempt, $responseHeaders['retry-after'] ?? null) * 1_000_000));
				$attempt++;
				continue;
			}

			throw $this->buildError($status, $body);
		}
	}

	/** @return array{0:int,1:array,2:string} status, lowercased headers, body */
	private function execute(string $url, array $headers): array
	{
		if ($this->transport !== null) {
			return ($this->transport)($url, $headers);
		}

		if ($this->curl === null) {
			$this->curl = curl_init();
		}
		$headerLines = [];
		foreach ($headers as $name => $value) {
			$headerLines[] = $name . ': ' . $value;
		}
		$responseHeaders = [];
		curl_setopt_array($this->curl, [
			CURLOPT_URL => $url,
			CURLOPT_HTTPGET => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
			CURLOPT_HTTPHEADER => $headerLines,
			CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
				return strlen($line);
			},
		]);

		$body = curl_exec($this->curl);
		if ($body === false) {
			throw new \RuntimeException('parseapi: ' . curl_error($this->curl), curl_errno($this->curl));
		}
		$status = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);

		return [$status, $responseHeaders, (string) $body];
	}

	private function retryDelay(int $attempt, ?string $retryAfter): float
	{
		if ($retryAfter !== null && is_numeric($retryAfter) && (float) $retryAfter >= 0) {
			return min((float) $retryAfter, self::RETRY_AFTER_CAP);
		}
		return mt_rand() / mt_getrandmax() * 0.25 * (2 ** $attempt);
	}

	private function buildError(int $status, string $body): ParseAPIError
	{
		$parsed = json_decode($body, true);
		if (!is_array($parsed)) {
			$parsed = [];
		}
		return new ParseAPIError(
			status: $status,
			errorCode: is_string($parsed['code'] ?? null) ? $parsed['code'] : 'unknown_error',
			message: is_string($parsed['message'] ?? null) ? $parsed['message'] : "Request failed with status {$status}",
			docs: is_string($parsed['docs'] ?? null) ? $parsed['docs'] : null,
			requestId: is_string($parsed['request_id'] ?? null) ? $parsed['request_id'] : null,
		);
	}
}
