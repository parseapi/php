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
	public const VERSION = '1.10.5';
	private const API_VERSION = '2.0.0';

	private const DEFAULT_BASE_URL = 'https://api.parseapi.com';
	private const RETRY_STATUS = [429, 500, 502, 503, 504];
	private const RETRY_AFTER_CAP = 5.0;
	private const METERED_CORE = ['carrier', 'caller', 'hlr', 'litigator', 'reassigned'];

	private string $apiKey;
	private string $baseUrl;
	private float $timeout;
	private bool $timeoutExplicit;
	private ?int $retries;
	/** @var ?callable fn(string $url, array $headers): array{0:int,1:array,2:string} */
	private $transport;
	private ?\CurlHandle $curl = null;

	/** Null timeout selects operation defaults; explicit numbers apply to every lookup. */
	public function __construct(
		?string $apiKey = null,
		?string $baseUrl = null,
		?float $timeout = null,
		?int $retries = null,
		?callable $transport = null,
	) {
		// You found Dev. https://parseapi.com/dev
		$key = $apiKey ?? (getenv('PARSEAPI_KEY') ?: null);
		if ($key === null || $key === '') {
			throw new \InvalidArgumentException('parseapi: missing API key. Pass one or set PARSEAPI_KEY.');
		}
		$this->timeoutExplicit = $timeout !== null;
		$timeout ??= 10.0;
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
	public function ip(string $ip, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/ip/' . rawurlencode($ip), ['deep' => $deep, 'lang' => $lang]);
	}

	/**
	 * Look up the public IP making this request. On a server, this is the server's IP.
	 */
	public function ipSelf(bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/ip', ['deep' => $deep, 'lang' => $lang]);
	}

	public function continent(string $code, ?string $lang = null): array
	{
		return $this->get('/continent/' . rawurlencode($code), ['lang' => $lang]);
	}

	public function continentCountries(string $code, ?string $lang = null): array
	{
		return $this->get('/continent/' . rawurlencode($code) . '/countries', ['lang' => $lang]);
	}





	public function country(string $code, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/country/' . rawurlencode($code), ['deep' => $deep, 'lang' => $lang]);
	}

	public function countryStates(string $code, ?string $lang = null): array
	{
		return $this->get('/country/' . rawurlencode($code) . '/states', ['lang' => $lang]);
	}

	public function state(string $code, ?string $country = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/state/' . rawurlencode($code), ['country' => $country, 'deep' => $deep, 'lang' => $lang]);
	}

	public function stateDistricts(string $code, ?string $country = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/state/' . rawurlencode($code) . '/districts', ['country' => $country, 'deep' => $deep, 'lang' => $lang]);
	}

	public function district(string $code, ?string $country = null, ?string $state = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/district/' . rawurlencode($code), ['country' => $country, 'state' => $state, 'deep' => $deep, 'lang' => $lang]);
	}

	public function city(string $name, ?string $country = null, ?string $state = null, bool $deep = false, ?string $lang = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, bool $aliases = false, ?string $ambiguity = null, ?string $format = null, bool $romanized = false): array
	{
		return $this->get('/city/' . rawurlencode($name), ['romanized' => $romanized, 'format' => $format, 'country' => $country, 'state' => $state, 'deep' => $deep, 'lang' => $lang, 'type' => $type, 'grain' => $grain, 'hierarchy' => $hierarchy, 'aliases' => $aliases, 'ambiguity' => $ambiguity]);
	}

	public function cityId(string $id, bool $deep = false, ?string $lang = null, bool $hierarchy = false, ?string $format = null, bool $romanized = false): array
	{
		return $this->get('/city/id/' . rawurlencode($id), ['romanized' => $romanized, 'format' => $format, 'deep' => $deep, 'lang' => $lang, 'hierarchy' => $hierarchy]);
	}

	public function citySearch(string $query, ?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false, ?string $lang = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, bool $aliases = false, ?int $minPopulation = null, ?int $maxPopulation = null, ?string $timezone = null, ?string $bbox = null, ?string $sort = null, ?string $order = null, bool $page = false, ?string $cursor = null, ?string $format = null, array|string|null $countries = null, array|string|null $excludeCountries = null, array|string|null $timezones = null, ?float $minElevation = null, ?float $maxElevation = null, ?string $match = null, ?int $maxDistance = null, bool $romanized = false): array
	{
		$spelling = $match !== null || $maxDistance !== null;
		$response = $this->get($spelling ? '/city/search' : '/city', ['romanized' => $romanized, 'format' => $format, 'countries' => $this->cityCsv($countries), 'exclude_countries' => $this->cityCsv($excludeCountries), 'timezones' => $this->cityCsv($timezones), 'min_elevation' => $minElevation, 'max_elevation' => $maxElevation, 'q' => $query, 'match' => $match, 'max_distance' => $maxDistance, 'country' => $country, 'state' => $state, 'limit' => $limit, 'deep' => $deep, 'lang' => $lang, 'type' => $type, 'grain' => $grain, 'hierarchy' => $hierarchy, 'aliases' => $aliases, 'min_population' => $minPopulation, 'max_population' => $maxPopulation, 'timezone' => $timezone, 'bbox' => $bbox, 'sort' => $sort, 'order' => $order, 'page' => $page, 'cursor' => $cursor]);
		if ($spelling && $format === null) return ['q'=>$query, 'cities'=>$response['data'], 'next_cursor'=>$response['next_cursor'] ?? null] + ($country !== null ? ['country'=>$country] : []) + ($state !== null ? ['state'=>$state] : []);
		return $response;
	}

	public function cityNearest(float $lat, float $lon, bool $deep = false, ?string $lang = null, ?string $country = null, ?string $state = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, ?string $format = null, bool $romanized = false): array
	{
		return $this->get('/city', ['romanized' => $romanized, 'format' => $format, 'lat' => $lat, 'lon' => $lon, 'deep' => $deep, 'lang' => $lang, 'country' => $country, 'state' => $state, 'type' => $type, 'grain' => $grain, 'hierarchy' => $hierarchy]);
	}

	public function cityNearby(string $name, ?float $radius = null, ?string $unit = null, ?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false, ?string $lang = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, bool $aliases = false, ?string $ambiguity = null, ?int $minPopulation = null, ?int $maxPopulation = null, ?string $timezone = null, ?string $bbox = null, ?string $sort = null, ?string $order = null, bool $page = false, ?string $cursor = null, ?string $format = null, array|string|null $countries = null, array|string|null $excludeCountries = null, array|string|null $timezones = null, ?float $minElevation = null, ?float $maxElevation = null, bool $romanized = false): array
	{
		return $this->get('/city/' . rawurlencode($name) . '/nearby', ['romanized' => $romanized, 'format' => $format, 'countries' => $this->cityCsv($countries), 'exclude_countries' => $this->cityCsv($excludeCountries), 'timezones' => $this->cityCsv($timezones), 'min_elevation' => $minElevation, 'max_elevation' => $maxElevation,
			'radius' => $radius,
			'unit' => $unit,
			'country' => $country,
			'state' => $state,
			'limit' => $limit,
			'deep' => $deep,
			'lang' => $lang,
			'type' => $type,
			'grain' => $grain,
			'hierarchy' => $hierarchy,
			'aliases' => $aliases,
			'ambiguity' => $ambiguity,
			'min_population' => $minPopulation, 'max_population' => $maxPopulation, 'timezone' => $timezone, 'bbox' => $bbox, 'sort' => $sort, 'order' => $order, 'page' => $page, 'cursor' => $cursor,
		]);
	}

	/**
	 * Look up a postal area. Pass country when known. Check nullable coordinates before another
	 * location lookup.
	 */
	public function cityAncestors(string $id, bool $hierarchy = false, bool $deep = false, ?string $lang = null, bool $romanized = false): array
	{ return $this->get('/city/id/' . rawurlencode($id) . '/ancestors', ['romanized' => $romanized, 'hierarchy' => $hierarchy, 'deep' => $deep, 'lang' => $lang]); }


	public function cityChildren(string $id, ?int $limit = null, ?string $cursor = null, bool $hierarchy = false, bool $deep = false, ?string $lang = null, bool $romanized = false): array
	{ return $this->get('/city/id/' . rawurlencode($id) . '/children', ['limit' => $limit, 'cursor' => $cursor, 'romanized' => $romanized, 'hierarchy' => $hierarchy, 'deep' => $deep, 'lang' => $lang]); }


	public function cityDistance(string $from, string $to): array
	{ return $this->get('/city/id/' . rawurlencode($from) . '/distance/' . rawurlencode($to)); }

	public function cityList(?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false, ?string $lang = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, ?int $minPopulation = null, ?int $maxPopulation = null, ?string $timezone = null, ?string $bbox = null, ?string $sort = null, ?string $order = null, ?string $cursor = null, array|string|null $countries = null, array|string|null $excludeCountries = null, array|string|null $timezones = null, ?float $minElevation = null, ?float $maxElevation = null, bool $romanized = false): array
	{
		return $this->get('/city', ['romanized' => $romanized, 'format' => 'list', 'countries' => $this->cityCsv($countries), 'exclude_countries' => $this->cityCsv($excludeCountries), 'timezones' => $this->cityCsv($timezones), 'min_elevation' => $minElevation, 'max_elevation' => $maxElevation, 'browse' => true, 'country' => $country, 'state' => $state, 'limit' => $limit, 'deep' => $deep, 'lang' => $lang, 'type' => $type, 'grain' => $grain, 'hierarchy' => $hierarchy, 'min_population' => $minPopulation, 'max_population' => $maxPopulation, 'timezone' => $timezone, 'bbox' => $bbox, 'sort' => $sort, 'order' => $order, 'cursor' => $cursor]);
	}

	public function cityNearbyCoords(float $lat, float $lon, ?float $radius = null, ?string $unit = null, ?string $country = null, ?string $state = null, ?int $limit = null, bool $deep = false, ?string $lang = null, ?string $type = null, ?string $grain = null, bool $hierarchy = false, ?int $minPopulation = null, ?int $maxPopulation = null, ?string $timezone = null, ?string $bbox = null, ?string $sort = null, ?string $order = null, ?string $cursor = null, array|string|null $countries = null, array|string|null $excludeCountries = null, array|string|null $timezones = null, ?float $minElevation = null, ?float $maxElevation = null, bool $romanized = false): array
	{
		return $this->get('/city', ['romanized' => $romanized, 'format' => 'list', 'countries' => $this->cityCsv($countries), 'exclude_countries' => $this->cityCsv($excludeCountries), 'timezones' => $this->cityCsv($timezones), 'min_elevation' => $minElevation, 'max_elevation' => $maxElevation, 'lat' => $lat, 'lon' => $lon, 'nearby' => true, 'radius' => $radius, 'unit' => $unit, 'country' => $country, 'state' => $state, 'limit' => $limit, 'deep' => $deep, 'lang' => $lang, 'type' => $type, 'grain' => $grain, 'hierarchy' => $hierarchy, 'min_population' => $minPopulation, 'max_population' => $maxPopulation, 'timezone' => $timezone, 'bbox' => $bbox, 'sort' => $sort, 'order' => $order, 'cursor' => $cursor]);
	}

	public function citySiblings(string $id, ?int $limit = null, ?string $cursor = null, bool $hierarchy = false, bool $deep = false, ?string $lang = null, bool $romanized = false): array
	{ return $this->get('/city/id/' . rawurlencode($id) . '/siblings', ['limit' => $limit, 'cursor' => $cursor, 'romanized' => $romanized, 'hierarchy' => $hierarchy, 'deep' => $deep, 'lang' => $lang]); }

	public function postal(string $code, ?string $country = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/postal/' . rawurlencode($code), ['country' => $country, 'deep' => $deep, 'lang' => $lang]);
	}

	public function postalNearby(string $code, ?string $country = null, ?float $radius = null, ?string $unit = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/postal/' . rawurlencode($code) . '/nearby', ['country' => $country, 'radius' => $radius, 'unit' => $unit, 'deep' => $deep, 'lang' => $lang]);
	}

	public function postalDistance(string $from, string $to, ?string $country = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/postal/' . rawurlencode($from) . '/distance/' . rawurlencode($to), ['country' => $country, 'deep' => $deep, 'lang' => $lang]);
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

	public function company(string $number, ?string $country = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/company/' . rawurlencode($number), ['country' => $country, 'deep' => $deep, 'lang' => $lang]);
	}

	/** Fetch a directory profile by its stable Company ID. */
	public function companyId(string $id, bool $deep = false): array
	{
		return $this->get('/company/id/' . rawurlencode($id), ['deep' => $deep]);
	}

	/**
	 * Use at most one selector, or discover by country, industry or selected registration.
	 * Pair a four-digit SIC industry string with industryType="sic".
	 * The API validates selectors and filters. Review candidates before choosing an ID.
	 * Pass a returned cursor with the same selector and filters. Deep belongs to each result.
	 */
	public function companySearch(?string $query = null, ?string $domain = null, ?string $ticker = null, ?string $identifier = null, ?string $country = null, ?string $exchange = null, ?string $authority = null, ?int $limit = null, ?string $cursor = null, bool $deep = false, ?string $industry = null, ?string $industryType = null): array
	{
		return $this->get('/company', ['q' => $query, 'domain' => $domain, 'ticker' => $ticker, 'identifier' => $identifier, 'country' => $country, 'exchange' => $exchange, 'authority' => $authority, 'limit' => $limit, 'cursor' => $cursor, 'deep' => $deep, 'industry' => $industry, 'industry_type' => $industryType]);
	}

	/** Read the directory edition and source coverage. */
	public function companyCoverage(): array
	{
		return $this->get('/company/directory/coverage');
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






	public function bin(string $bin, bool $deep = false): array
	{
		return $this->get('/bin/' . rawurlencode($bin), ['deep' => $deep]);
	}








	/** Look up a 2-11 digit card prefix, preserving leading zeros. @param string $bin */
	public function card(mixed $bin, bool $deep = false): array
	{
		if (!is_string($bin) || strlen($bin) > 64 || preg_match('/\A[0-9]{2,11}\z/', str_replace([" ", "\t", "\r", "\n", "-"], '', $bin)) !== 1) {
			throw new \InvalidArgumentException('parseapi: Card requires a string containing 2 to 11 digits. Send a prefix only.');
		}
		return $this->get('/card/' . rawurlencode($bin), ['deep' => $deep]);
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

	/** Identify website technologies and versions by category.
	 * All categories are lists. Scope, pages and partial describe bounded coverage.
	 * Lists are null when no page could be checked and empty for no matches.
	 */
	public function stack(string $domain, bool $deep = false, bool $pretty = false): array
	{
		return $this->get('/stack/' . rawurlencode($domain), ['deep' => $deep, 'pretty' => $pretty]);
	}

	/** Check whether a domain is registered. Deep adds registration dates, registrar, status and DNSSEC on paid plans. */
	public function domain(string $domain, bool $deep = false): array
	{
		return $this->get('/domain/' . rawurlencode($domain), ['deep' => $deep]);
	}

	public function asn(string $asn, ?string $lang = null): array
	{
		return $this->get('/asn/' . rawurlencode($asn), ['lang' => $lang]);
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



	/** US NAICS 2022 definition and hierarchy. */
	/** Compatibility name for industry. */
	public function naics(string $code, bool $deep = false): array
	{ return $this->industry($code, $deep); }

	/** Compatibility name for industrySearch. */
	public function naicsSearch(string $query, ?int $limit = null, bool $deep = false): array
	{ return $this->industrySearch($query, $limit, $deep); }

	public function industry(string $code, bool $deep = false): array
	{
		return $this->get('/industry/' . rawurlencode($code), ['deep' => $deep]);
	}

	/** Keyword search. Limit defaults to 10 and accepts 1-50. */
	public function industrySearch(string $query, ?int $limit = null, bool $deep = false): array
	{
		return $this->get('/industry', ['q' => $query, 'limit' => $limit, 'deep' => $deep]);
	}





	public function language(string $code, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/language/' . rawurlencode($code), ['deep' => $deep, 'lang' => $lang]);
	}

	/** Name locale selects CLDR formatting rules, default en. Parsing and country-scoped gender stay unchanged. */
	public function name(string $name, ?string $country = null, bool $deep = false, ?string $nameLocale = null): array
	{
		return $this->get('/name/' . rawurlencode($name), ['country' => $country, 'deep' => $deep, 'name_locale' => $nameLocale]);
	}

	/** Current local time, UTC by default. With to or targets, offsetless at is source wall time. */
	public function time(?string $timezone = null, ?string $at = null, ?string $to = null, bool $deep = false, ?string $lang = null, ?string $disambiguation = null, ?array $targets = null, ?string $ip = null, ?string $city = null, ?string $country = null, ?string $state = null, ?string $iata = null, ?string $icao = null, ?string $unlocode = null, ?string $address = null): array
	{
		if ($timezone !== null && in_array(strtolower(trim($timezone)), ['zones', 'help'], true)) throw new \InvalidArgumentException('Time source must be an IANA timezone ID. Use timezone discovery to list IDs.');
		$source = self::timeSource($timezone, ['ip' => $ip, 'city' => $city, 'country' => $country, 'state' => $state, 'iata' => $iata, 'icao' => $icao, 'unlocode' => $unlocode, 'address' => $address]);
		return $this->get($timezone === null ? '/time' : '/time/' . rawurlencode($timezone), $source + ['at' => $at, 'to' => $to, 'deep' => $deep, 'lang' => $lang, 'disambiguation' => $disambiguation, 'targets' => self::timeTargets($targets, $to)]);
	}

	public function timeAt(float $lat, float $lon, ?string $at = null, ?string $to = null, bool $deep = false, ?string $lang = null, ?string $disambiguation = null, ?array $targets = null): array
	{
		return $this->get('/time', ['lat' => $lat, 'lon' => $lon, 'at' => $at, 'to' => $to, 'deep' => $deep, 'lang' => $lang, 'disambiguation' => $disambiguation, 'targets' => self::timeTargets($targets, $to)]);
	}

	/** Search serving timezone IDs. Omit query to list all. */
	public function timeZones(?string $query = null, ?string $country = null, ?string $area = null, ?string $offset = null, ?string $abbreviation = null, ?bool $dst = null, ?bool $observesDst = null, ?string $at = null, bool $details = false, ?string $sort = null): array
	{
		return $this->get('/time/zones', ['q' => $query, 'country' => $country, 'area' => $area, 'offset' => $offset, 'abbreviation' => $abbreviation, 'dst' => $dst === null ? null : ($dst ? 'true' : 'false'), 'observes_dst' => $observesDst === null ? null : ($observesDst ? 'true' : 'false'), 'at' => $at, 'details' => $details, 'sort' => $sort]);
	}

	private static function timeSource(?string $timezone, array $values): array
	{
		$primary = array_filter(array_intersect_key($values, array_flip(['ip', 'city', 'iata', 'icao', 'unlocode', 'address'])), fn($value) => $value !== null);
		$present = array_filter($values, fn($value) => $value !== null);
		if (array_filter($present, fn($value) => trim($value) === '') || ($timezone !== null && $present) || count($primary) > 1 ||
			($values['country'] !== null && $primary && $values['city'] === null && $values['address'] === null) ||
			($values['state'] !== null && (($values['city'] === null && $values['address'] === null) || $values['country'] === null)) ||
			($values['address'] !== null && $values['country'] === null)) {
			throw new \InvalidArgumentException('Pass one Time source, using country only with city or address and state only with city or address and country.');
		}
		return $values;
	}

	private static function timeTargets(?array $targets, ?string $to): ?string
	{
		if ($targets === null) return null;
		if ($to !== null || count($targets) < 1 || count($targets) > 10) {
			throw new \InvalidArgumentException('Time targets requires 1 to 10 timezone IDs and cannot be combined with to.');
		}
		foreach ($targets as $zone) {
			if (!is_string($zone) || trim($zone) === '' || str_contains($zone, ',')) {
				throw new \InvalidArgumentException('Time targets requires 1 to 10 timezone IDs and cannot be combined with to.');
			}
		}
		return implode(',', $targets);
	}

	public function timezone(string $id, ?string $at = null, ?string $to = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/timezone/' . rawurlencode($id), ['at' => $at, 'to' => $to, 'deep' => $deep, 'lang' => $lang]);
	}

	public function timezoneAt(float $lat, float $lon, ?string $at = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/timezone', ['lat' => $lat, 'lon' => $lon, 'at' => $at, 'deep' => $deep, 'lang' => $lang]);
	}

	public function date(string $date, ?string $format = null, ?string $to = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/date/' . rawurlencode($date), ['format' => $format, 'to' => $to, 'deep' => $deep, 'lang' => $lang]);
	}

	public function dateToday(?string $to = null, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/date', ['to' => $to, 'deep' => $deep, 'lang' => $lang]);
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
	public function point(float $lat, float $lon, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/point', ['lat' => $lat, 'lon' => $lon, 'deep' => $deep, 'lang' => $lang]);
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

	public function emoji(string $emoji, bool $deep = false, ?string $lang = null, ?string $shortcodeSource = null): array
	{
		return $this->get('/emoji/' . rawurlencode($emoji), ['deep' => $deep, 'lang' => $lang, 'shortcode_source' => $shortcodeSource]);
	}

	public function emojiSearch(string $query, ?int $limit = null, bool $deep = false, ?string $lang = null, ?string $category = null, ?string $subcategory = null, ?string $version = null, ?string $maxVersion = null, ?bool $includeSkinTones = null, ?int $offset = null): array
	{
		return $this->get('/emoji', ['q' => $query] + ['limit' => $limit, 'deep' => $deep, 'lang' => $lang, 'category' => $category, 'subcategory' => $subcategory, 'version' => $version, 'max_version' => $maxVersion, 'include_skin_tones' => $includeSkinTones === null ? null : ($includeSkinTones ? 'true' : 'false'), 'offset' => $offset]);
	}

	public function emojiBrowse(?int $limit = null, bool $deep = false, ?string $lang = null, ?string $category = null, ?string $subcategory = null, ?string $version = null, ?string $maxVersion = null, ?bool $includeSkinTones = null, ?int $offset = null): array
	{
		return $this->get('/emoji', ['limit' => $limit, 'deep' => $deep, 'lang' => $lang, 'category' => $category, 'subcategory' => $subcategory, 'version' => $version, 'max_version' => $maxVersion, 'include_skin_tones' => $includeSkinTones === null ? null : ($includeSkinTones ? 'true' : 'false'), 'offset' => $offset]);
	}

	public function emojiConvert(string $text, string $direction, string $shortcodeSource): array
	{
		return $this->get('/emoji/convert', ['direction' => $direction, 'shortcode_source' => $shortcodeSource], [], ['text' => $text]);
	}

	public function emojiCatalog(bool $deep = true, ?string $lang = null, ?string $category = null, ?string $subcategory = null, ?string $version = null, ?string $maxVersion = null, ?bool $includeSkinTones = null, ?string $profile = null): array
	{
		return $this->get('/emoji/catalog', ['profile' => $profile, 'deep' => $deep ? 'true' : 'false', 'lang' => $lang, 'category' => $category, 'subcategory' => $subcategory, 'version' => $version, 'max_version' => $maxVersion, 'include_skin_tones' => $includeSkinTones === null ? null : ($includeSkinTones ? 'true' : 'false')]);
	}

	public function emojiMetadata(): array
	{
		return $this->get('/emoji/metadata');
	}

	public function emojiCategories(): array
	{
		return $this->get('/emoji/categories');
	}

	/** Positions are UTF-16 code units; end is exclusive. */
	public function emojiParse(string $text, bool $deep = false, ?string $lang = null): array
	{
		return $this->get('/emoji/parse', ['deep' => $deep, 'lang' => $lang], [], ['text' => $text]);
	}

	// --- Transport ---


	private function timeoutFor(string $path): float
	{
		return !$this->timeoutExplicit && str_starts_with($path, '/stack/') ? 35.0 : $this->timeout;
	}

	private function get(string $path, array $query = [], array $headers = [], ?array $json = null): array
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
			['Parse-Version' => self::API_VERSION],
		);

		$requestBody = $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR);
		if ($requestBody !== null) $requestHeaders['Content-Type'] = 'application/json';
		$attempt = 0;
		while (true) {
			try {
				[$status, $responseHeaders, $body] = $this->execute($url, $requestHeaders, $this->timeoutFor($path), $requestBody);
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
				$delay = $this->retryDelay($attempt, $responseHeaders['retry-after'] ?? null);
				if ($delay !== null) {
					usleep((int) ($delay * 1_000_000));
					$attempt++;
					continue;
				}
			}

			throw $this->buildError($status, $body, $responseHeaders['retry-after'] ?? null);
		}
	}

	/** @return array{0:int,1:array,2:string} status, lowercased headers, body */
	private function execute(string $url, array $headers, float $timeout, ?string $requestBody = null): array
	{
		if ($this->transport !== null) {
			return $requestBody === null ? ($this->transport)($url, $headers) : ($this->transport)($url, $headers, 'POST', $requestBody);
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
			CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
			CURLOPT_HTTPHEADER => $headerLines,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
				return strlen($line);
			},
		]);

		if ($requestBody !== null) {
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $requestBody);
		}
		$body = curl_exec($this->curl);
		if ($body === false) {
			throw new \RuntimeException('parseapi: ' . curl_error($this->curl), curl_errno($this->curl));
		}
		$status = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);

		return [$status, $responseHeaders, (string) $body];
	}

	private function retryDelay(int $attempt, ?string $retryAfter): ?float
	{
		if ($retryAfter !== null) {
			$retryAfter = trim($retryAfter);
			if (preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $retryAfter) === 1) {
				$seconds = (float) $retryAfter;
				return $seconds > self::RETRY_AFTER_CAP ? null : $seconds;
			}
			$date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \\G\\M\\T', $retryAfter, new \DateTimeZone('GMT'));
			if ($date !== false) {
				$delay = max($date->getTimestamp() - time(), 0);
				return $delay > self::RETRY_AFTER_CAP ? null : (float) $delay;
			}
		}
		return mt_rand() / mt_getrandmax() * min(0.25 * (2 ** min($attempt, 16)), self::RETRY_AFTER_CAP);
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

	private function cityCsv(array|string|null $value): ?string
	{ return is_array($value) ? implode(',', $value) : $value; }

	private function buildError(int $status, string $body, ?string $retryAfter = null): ParseAPIError
	{
		$parsed = json_decode($body, true);
		if (!is_array($parsed)) {
			$parsed = [];
		}
		$error = new ParseAPIError(
			status: $status,
			errorCode: is_string($parsed['code'] ?? null) ? $parsed['code'] : 'unknown_error',
			message: is_string($parsed['message'] ?? null) ? $parsed['message'] : "Request failed with status {$status}",
			docs: is_string($parsed['docs'] ?? null) ? $parsed['docs'] : null,
			requestId: is_string($parsed['request_id'] ?? null) ? $parsed['request_id'] : null,
			retryAfter: $retryAfter,
		);
		if ($status === 409 && $error->errorCode === 'ambiguous_city' && is_array($parsed['candidates'] ?? null) && array_is_list($parsed['candidates'])) {
			$candidates = [];
			foreach (array_slice($parsed['candidates'], 0, 2) as $row) {
				if (!is_array($row) || !is_string($row['id'] ?? null) || !preg_match('/^city_[a-z0-9]{12}$/D', $row['id']) || ($row['object'] ?? null) !== 'city') { continue; }
				$candidate = ['id' => $row['id'], 'object' => 'city'];
				foreach (['name', 'name_local', 'country', 'country_name', 'state', 'state_name', 'type'] as $field) {
					if (array_key_exists($field, $row) && ($row[$field] === null || (is_string($row[$field]) && preg_match_all('/./us', $row[$field]) <= 255))) { $candidate[$field] = $row[$field]; }
				}
				$candidates[] = $candidate;
			}
			$error->candidates = $candidates;
		}
		return $error;
	}
}
