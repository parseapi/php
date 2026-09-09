<?php

declare(strict_types=1);

namespace ParseAPI;

/**
 * Official ParseAPI client for PHP.
 *
 *   $parse = new \ParseAPI\Client('your-api-key');
 *   $country = $parse->country('US');
 */
final class Client
{
	public const VERSION = '0.3.2';

	private const DEFAULT_BASE_URL = 'https://api.parseapi.com';
	private const RETRY_STATUS = [429, 500, 502, 503, 504];
	private const RETRY_AFTER_CAP = 5.0;
	private const METERED_CORE = ['carrier', 'caller', 'hlr', 'litigator', 'reassigned'];

	private string $apiKey;
	private string $baseUrl;
	private float $timeout;
	private ?int $retries;
	/** @var ?callable fn(string $url, array $headers): array{0:int,1:array,2:string} */
	private $transport;
	private ?\CurlHandle $curl = null;

	public function __construct(
		?string $apiKey = null,
		?string $baseUrl = null,
		float $timeout = 10.0,
		?int $retries = null,
		?callable $transport = null,
	) {
		// You found Dev. https://parseapi.com/dev
		$key = $apiKey ?? (getenv('PARSEAPI_KEY') ?: null);
		if ($key === null || $key === '') {
			throw new \InvalidArgumentException('parseapi: missing API key. Pass one or set PARSEAPI_KEY.');
		}
		if (!is_finite($timeout) || $timeout <= 0) {
			throw new \InvalidArgumentException('parseapi: timeout must be a finite positive number.');
		}
		if ($retries !== null && $retries < 0) {
			throw new \InvalidArgumentException('parseapi: retries must be a non-negative integer.');
		}
		$this->apiKey = $key;
		$this->baseUrl = rtrim($baseUrl ?? (getenv('PARSEAPI_BASE_URL') ?: self::DEFAULT_BASE_URL), '/');
		$this->timeout = $timeout;
		$this->retries = $retries;
		$this->transport = $transport;
	}

	/** Release the connection. A later lookup opens a new one. */
	public function close(): void
	{
		$this->curl = null;
	}

	/** Keep credentials out of var_dump and print_r output. */
	public function __debugInfo(): array
	{
		return ['apiKey' => '[REDACTED]', 'timeout' => $this->timeout, 'retries' => $this->retries ?? 'auto'];
	}

	// --- Lookup methods (one per endpoint, named after the route) ---

	/**
	 * Look up an IP. Deep enrichment is included with a paid plan, without a separate check meter.
	 */
	public function ip(string $ip, bool $deep = false): array
	{
		return $this->get('/ip/' . rawurlencode($ip), ['deep' => $deep]);
	}

	/**
	 * Look up the public IP making this request. On a server, this is the server's IP.
	 */
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

	public function country(string $code, bool $deep = false): array
	{
		return $this->get('/country/' . rawurlencode($code), ['deep' => $deep]);
	}

	public function countryStates(string $code): array
	{
		return $this->get('/country/' . rawurlencode($code) . '/states');
	}

	public function state(string $code, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/state/' . rawurlencode($code), ['country' => $country, 'deep' => $deep]);
	}

	public function stateDistricts(string $code, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/state/' . rawurlencode($code) . '/districts', ['country' => $country, 'deep' => $deep]);
	}

	public function district(string $code, ?string $country = null, ?string $state = null, bool $deep = false): array
	{
		return $this->get('/district/' . rawurlencode($code), ['country' => $country, 'state' => $state, 'deep' => $deep]);
	}

	public function city(string $name, ?string $country = null, ?string $state = null, bool $deep = false): array
	{
		return $this->get('/city/' . rawurlencode($name), ['country' => $country, 'state' => $state, 'deep' => $deep]);
	}

	public function cityId(string $id, bool $deep = false): array
	{
		return $this->get('/city/id/' . rawurlencode($id), ['deep' => $deep]);
	}

	public function citySearch(string $query, ?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false): array
	{
		return $this->get('/city', ['q' => $query, 'country' => $country, 'state' => $state, 'limit' => $limit, 'deep' => $deep]);
	}

	public function cityNearest(float $lat, float $lon, bool $deep = false): array
	{
		return $this->get('/city', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep]);
	}

	public function cityNearby(string $name, ?float $radius = null, ?string $unit = null, ?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false): array
	{
		return $this->get('/city/' . rawurlencode($name) . '/nearby', [
			'radius' => $radius,
			'unit' => $unit,
			'country' => $country,
			'state' => $state,
			'limit' => $limit,
		'deep' => $deep,
		]);
	}

	/**
	 * Look up a postal area. Pass country when known. Check nullable coordinates before another
	 * location lookup.
	 */
	public function postal(string $code, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/postal/' . rawurlencode($code), ['country' => $country, 'deep' => $deep]);
	}

	public function postalNearby(string $code, ?string $country = null, ?float $radius = null, ?string $unit = null, bool $deep = false): array
	{
		return $this->get('/postal/' . rawurlencode($code) . '/nearby', ['country' => $country, 'radius' => $radius, 'unit' => $unit, 'deep' => $deep]);
	}

	public function postalDistance(string $from, string $to, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/postal/' . rawurlencode($from) . '/distance/' . rawurlencode($to), ['country' => $country, 'deep' => $deep]);
	}

	public function address(string $address, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/address/' . rawurlencode($address), ['country' => $country, 'deep' => $deep]);
	}

	/**
	 * Find address suggestions using the context supplied. Prefer postal, or city and state, from the
	 * form; ip is an optional end-user locality hint for server-side calls. An empty result has reason
	 * more_input, missing_context or no_matches. Suggestions have reason null. Operational failures
	 * are errors.
	 */
	public function addressSearch(string $query, ?string $country = null, ?string $postal = null, ?string $city = null, ?string $state = null, ?string $ip = null): array
	{
		return $this->get('/address', ['q' => $query, 'country' => $country, 'postal' => $postal, 'city' => $city, 'state' => $state, 'ip' => $ip]);
	}

	public function company(string $number, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/company/' . rawurlencode($number), ['country' => $country, 'deep' => $deep]);
	}

	/**
	 * Parse an email and check its format and domain. Deep explicitly requests a metered
	 * deliverability check. Deep checks use one attempt by default. An explicit retry count can
	 * repeat paid usage.
	 */
	public function email(string $email, bool $deep = false): array
	{
		return $this->get('/email/' . rawurlencode($email), ['deep' => $deep]);
	}

	/**
	 * Check VAT format and checksum. Deep requests a metered registry check where supported. Deep
	 * checks use one attempt by default. Supply your own VAT number for a consultation reference
	 * when supported.
	 */
	public function vat(string $number, ?string $country = null, bool $deep = false, ?string $from = null): array
	{
		return $this->get('/vat/' . rawurlencode($number), ['country' => $country, 'deep' => $deep, 'from' => $from]);
	}

	public function iban(string $iban, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/iban/' . rawurlencode($iban), ['country' => $country, 'deep' => $deep]);
	}

	/** Look up a 6-11 digit card prefix, preserving leading zeros. */
	public function bin(string $bin, bool $deep = false): array
	{
		return $this->get('/bin/' . rawurlencode($bin), ['deep' => $deep]);
	}


	public function npi(string $npi, bool $deep = false): array
	{
		return $this->get('/npi/' . rawurlencode($npi), ['deep' => $deep]);
	}

	/**
	 * Parse a phone number and its formats. Pass country for national numbers when needed. Deep
	 * adds numbering-plan geography on every plan. Carrier, caller, and HLR are separate metered lookups.
	 */
	public function phone(string $number, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/phone/' . rawurlencode($number), ['country' => $country, 'deep' => $deep]);
	}

	/**
	 * Request a metered carrier lookup. No automatic retries by default.
	 */
	public function carrier(string $number, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/carrier/' . rawurlencode($number), ['country' => $country, 'deep' => $deep]);
	}

	/**
	 * Request a metered caller-name lookup for a NANP number. No automatic retries by default.
	 */
	public function caller(string $number, ?string $country = null): array
	{
		return $this->get('/caller/' . rawurlencode($number), ['country' => $country]);
	}

	/**
	 * Look up phone status at the last check. Live means assigned and connected means reachable at
	 * that check. Cached results may be returned. Null means unconfirmed. Deep adds network
	 * diagnostics within the same metered lookup. No automatic retries by default.
	 */
	public function hlr(string $number, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/hlr/' . rawurlencode($number), ['country' => $country, 'deep' => $deep]);
	}

	/** Check whether a domain is registered. Deep adds registration dates, registrar, status and DNSSEC on paid plans. */
	public function domain(string $domain, bool $deep = false): array
	{
		return $this->get('/domain/' . rawurlencode($domain), ['deep' => $deep]);
	}

	public function asn(string $asn): array
	{
		return $this->get('/asn/' . rawurlencode($asn));
	}

	public function mac(string $mac): array
	{
		return $this->get('/mac/' . rawurlencode($mac));
	}

	/** Published DNS records with TTLs. Omit type to check all supported types.
	 * Type selects the question, including its CNAME chain. Pooled on every plan.
	 */
	public function dns(string $domain, ?string $type = null): array
	{
		return $this->get('/dns/' . rawurlencode($domain), ['type' => $type]);
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

	/** US NAICS 2022 definition and hierarchy. */
	public function naics(string $code, bool $deep = false): array
	{
		return $this->get('/naics/' . rawurlencode($code), ['deep' => $deep]);
	}

	/** Keyword search. Limit defaults to 10 and accepts 1-50. */
	public function naicsSearch(string $query, ?int $limit = null, bool $deep = false): array
	{
		return $this->get('/naics', ['q' => $query, 'limit' => $limit, 'deep' => $deep]);
	}

	/**
	 * Look up the general US duty schedule line. Paid deep adds units and the special and other
	 * schedule columns. Add origin with deep to resolve country-specific measures. Without origin,
	 * schedule detail remains available and origin-dependent fields are null. A null effective rate is
	 * not a zero rate.
	 */
	public function tariff(string $code, bool $deep = false, ?string $origin = null): array
	{
		return $this->get('/tariff/' . rawurlencode($code), ['deep' => $deep, 'origin' => $origin]);
	}

	public function tariffSearch(string $query): array
	{
		return $this->get('/tariff', ['q' => $query]);
	}

	public function currency(string $code, bool $deep = false): array
	{
		return $this->get('/currency/' . rawurlencode($code), ['deep' => $deep]);
	}

	public function currencyRate(string $base, string $quote, ?string $date = null, ?float $amount = null): array
	{
		return $this->get('/currency/' . rawurlencode($base) . '/' . rawurlencode($quote), [
			'date' => $date,
			'amount' => $amount,
		]);
	}

	public function language(string $code, bool $deep = false): array
	{
		return $this->get('/language/' . rawurlencode($code), ['deep' => $deep]);
	}

	public function name(string $name, ?string $country = null, bool $deep = false): array
	{
		return $this->get('/name/' . rawurlencode($name), ['country' => $country, 'deep' => $deep]);
	}

	/** Current local time, UTC by default. With to, offsetless at is source wall time. */
	public function time(?string $timezone = null, ?string $at = null, ?string $to = null, bool $deep = false): array
	{
		return $this->get($timezone === null ? '/time' : '/time/' . rawurlencode($timezone), ['at' => $at, 'to' => $to, 'deep' => $deep]);
	}

	public function timeAt(float $lat, float $lon, ?string $at = null, ?string $to = null, bool $deep = false): array
	{
		return $this->get('/time', ['lat' => $lat, 'lon' => $lon, 'at' => $at, 'to' => $to, 'deep' => $deep]);
	}

	public function timezone(string $id, ?string $at = null, ?string $to = null, bool $deep = false): array
	{
		return $this->get('/timezone/' . rawurlencode($id), ['at' => $at, 'to' => $to, 'deep' => $deep]);
	}

	public function timezoneAt(float $lat, float $lon, ?string $at = null, bool $deep = false): array
	{
		return $this->get('/timezone', ['lat' => $lat, 'lon' => $lon, 'at' => $at, 'deep' => $deep]);
	}

	public function date(string $date, ?string $format = null, ?string $to = null, bool $deep = false): array
	{
		return $this->get('/date/' . rawurlencode($date), ['format' => $format, 'to' => $to, 'deep' => $deep]);
	}

	public function dateToday(?string $to = null, bool $deep = false): array
	{
		return $this->get('/date', ['to' => $to, 'deep' => $deep]);
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

	/**
	 * Resolve the country, state, district and timezone at coordinates. Deep adds terrain and compact
	 * nearest-city context on every plan. The timezone ID stays in core. The nearest city is null when
	 * none is within 200 km.
	 */
	public function point(float $lat, float $lon, bool $deep = false): array
	{
		return $this->get('/point', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep]);
	}

	/**
	 * Get current conditions in metric and imperial units. Paid deep adds specialist current
	 * measurements, forecasts and related detail. With deep, date selects a past UTC day (YYYY-MM-DD)
	 * in deep.history alongside current conditions. Date alone does not request history.
	 */
	public function weather(float $lat, float $lon, bool $deep = false, ?string $date = null): array
	{
		return $this->get('/weather', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep, 'date' => $date]);
	}

	public function emoji(string $emoji, bool $deep = false): array
	{
		return $this->get('/emoji/' . rawurlencode($emoji), ['deep' => $deep]);
	}

	public function emojiSearch(string $query, ?int $limit = null, bool $deep = false): array
	{
		return $this->get('/emoji', ['q' => $query, 'limit' => $limit, 'deep' => $deep]);
	}

	// --- Transport ---

	/** Parse or convert a measurement. Amount is a decimal string. Without to, use the
	 * type's canonical unit. Locale and system (us or imperial) resolve explicit ambiguity. */
	public function measure(string $measure, ?string $to = null, ?string $locale = null, ?string $system = null): array
	{
		return $this->get('/measure/' . rawurlencode($measure), ['to' => $to, 'locale' => $locale, 'system' => $system]);
	}

	/** Discover reviewed units. unit filters compatible conversion targets. */
	public function measureUnits(?string $query = null, ?string $type = null, ?string $unit = null): array
	{
		return $this->get('/measure/units', ['q' => $query, 'type' => $type, 'unit' => $unit]);
	}

	private function get(string $path, array $query = [], array $headers = []): array
	{
		$retries = $this->retriesFor($path, $query);
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
				if ($attempt < $retries) {
					usleep((int) ($this->retryDelay($attempt, null) * 1_000_000));
					$attempt++;
					continue;
				}
				throw $e;
			}

			if ($status >= 200 && $status < 300) {
				$decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($decoded)) {
					throw new \UnexpectedValueException('parseapi: expected a JSON object or array response.');
				}
				return $decoded;
			}

			if (in_array($status, self::RETRY_STATUS, true) && $attempt < $retries) {
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
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
			CURLOPT_HTTPHEADER => $headerLines,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
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
		if ($retryAfter !== null) {
			$date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \\G\\M\\T', $retryAfter, new \DateTimeZone('GMT'));
			if ($date !== false) {
				return min(max($date->getTimestamp() - time(), 0), self::RETRY_AFTER_CAP);
			}
		}
		return mt_rand() / mt_getrandmax() * 0.25 * (2 ** $attempt);
	}

	private function retriesFor(string $path, array $query): int
	{
		if ($this->retries !== null) {
			return $this->retries;
		}
		$product = explode('/', ltrim($path, '/'), 2)[0];
		$metered = in_array($product, self::METERED_CORE, true)
			|| (in_array($product, ['email', 'vat', 'address'], true) && ($query['deep'] ?? false) === true);
		return $metered ? 0 : 2;
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
