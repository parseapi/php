```bash
composer require parseapi/sdk
```

```php
use ParseAPI\Client;

$parse = new Client('your-api-key');
$country = $parse->country('US');
```

Get a key at [parseapi.com](https://parseapi.com). The client also reads `PARSEAPI_KEY` from the environment.

## Weather from a postal code

Start with the postal code, then pass its coordinates to weather. Reuse the client from the example above.

```php
$place = $parse->postal('28202', country: 'US');
if ($place['latitude'] !== null && $place['longitude'] !== null) {
    $weather = $parse->weather($place['latitude'], $place['longitude']);
    print_r($weather);
}
```

The coordinates represent the postal area. Weather is for that point. Missing coordinates skip the weather lookup. This composition performs two ordinary lookups when coordinates are available, with the retry policy below.

## Supply the context you know

Pass `country` when a postal code or national phone number needs disambiguation. A complete international phone number already carries its country context. For a numeric date such as `03/04/2026`, supply the intended `format`. Defaults resolve what the input establishes. Ambiguous input needs your context.

Results are plain data. Pass a returned code or coordinate to another operation when the task needs it. Check nullable values before composing the next call.

Use `$parse->postal('28202', country: 'US', deep: true)` for US ZIP tax references. `deep.tax` names the levy and `deep.tax_rate` is a percentage, so `7.9` means 7.9%. The state, county, city and special components explain that combined rate. An exact address can differ. Country and state lookups provide their own geographic reference rates, which should not be added to the ZIP rate. `null` means unknown and `0` means known zero. Country `deep.tax_id_format` and `deep.tax_id_regex` describe registration-number format only. Use `vat` for a metered registration check with `deep` explicitly enabled.

## Calls

One method per endpoint, named after the route.

```php
$parse->ip('8.8.8.8');
$parse->ipSelf();
$parse->email('hello@gmail.com');
$parse->vat('DE136695976');
$parse->iban('DE89370400440532013000');
$parse->bin('424242');
$parse->npi('1881018208');
$parse->phone('+14155552671');
$parse->carrier('+14155552671');
$parse->caller('+14155552671');
$parse->hlr('+14155552671');
$parse->postal('SW1A 1AA');
$parse->postal('28202', country: 'US');
$parse->postalNearby('28202', country: 'US', radius: 40);
$parse->postalDistance('28202', '10001', country: 'US');
$parse->address('1600 Pennsylvania Ave NW, Washington, DC 20500', country: 'US');
$parse->addressSearch('123 main', country: 'US', postal: '27401');
$parse->company('732829320', country: 'FR');
$parse->city('charlotte', country: 'US');
$parse->cityId('city_mb8mbqrkz8zb');
$parse->citySearch('char', country: 'US', limit: 10);
$parse->cityNearest(35.2271, -80.8431);
$parse->cityNearby('denver', radius: 8, unit: 'mi');
$parse->country('US');
$parse->countryStates('US');
$parse->state('colorado');
$parse->state('NC', country: 'US');
$parse->stateDistricts('NC', country: 'US');
$parse->district('37081');
$parse->continent('NA');
$parse->continentCountries('NA');
$parse->bloc('EU');
$parse->blocCountries('SCHENGEN');
$parse->currency('USD');
$parse->currencyRate('USD', 'EUR');
$parse->language('en');
$parse->name('BILLY OSHALL');
$parse->name('Andrea', country: 'IT', deep: true);
$parse->time(); // UTC now
$parse->time('America/New_York');
$parse->time('America/New_York', at: '2026-09-05T15:00', to: 'Europe/London');
$parse->timeAt(35.2271, -80.8431);
$parse->date('03/04/2026', format: 'mdy');
$parse->dateToday(to: '2026-12-25');
$parse->holiday('US', year: 2026);
$parse->holidayDate('US', '2026-12-25');
$parse->elevation(35.2271, -80.8431);
$parse->point(36.0726, -79.792);
$parse->weather(40.7128, -74.006);
$parse->weather(40.7128, -74.006, deep: true, date: '2026-09-01');
$parse->domain('example.com');
$parse->asn('AS13335');
$parse->mac('00:1B:63:84:45:E6');
$parse->mx('example.com');
$parse->dns('example.com');
$parse->dns('_dmarc.example.com', type: 'TXT');
$parse->useragent($uaString);
$parse->vin('1HGCM82633A004352');
$parse->naics('541511');
$parse->naicsSearch('coffee shop', limit: 5);
$parse->tariff('8471.30.01.00', origin: 'CN', deep: true);
$parse->tariffSearch('sunglasses');
$parse->emoji('rocket');
$parse->emojiSearch('fire');
```

NAICS paid deep records include classification `deep.exclusions`, each with a description and linked codes. Generic exclusions can have no linked codes. Omitted or null exclusions in older responses remain unknown. Search results also include `match`: the matched `field` (`name`, `term` or `naics`) and `text`, plus `corrections` with `from` and `to` tokens for typo fallback. Corrections are empty for exact, plural and prefix matches. Direct code lookups omit `match`. Older responses may omit it.

Each lookup returns an associative array. Related lookups are separate calls, such as `countryStates('US')`. Reading the result makes no further requests. New response fields and `null` values are preserved. JSON objects, including an empty `deep` object, decode as PHP arrays.

Use named arguments for optional settings, such as `country: 'US'` or `deep: true`.

DNS uses pooled requests on every plan. Omit `type` to check A, AAAA, CNAME, MX, NS, TXT, SOA, CAA, SRV and PTR. Records contain `name`, `type`, `ttl` in seconds and a DNS presentation `value`. TXT values retain quoting and chunk boundaries. A selected question can include its CNAME chain. Empty records mean no records. Lookup failures remain errors.

## Time

`time` returns local ISO `at` with its UTC offset and integer Unix seconds in `unix`. The core `offset` preserves exact precision. Optional `deep.offset_seconds` gives the numeric offset, while `deep.offset_minutes` gives whole minutes. Historical offsets and ISO times can include offset seconds. Omitted `at` means now. With `to`, an offsetless `at` is source wall time. Otherwise it is UTC. Include an offset for repeated local times around a clock change. Current time and conversion use pooled requests on every plan. Coordinate clock fields can be null when the timezone is unknown. Existing `timezone` methods remain supported.

## Measurements

```php
$result = $parse->measure('5 ft 11 in', to: 'cm');
$units = $parse->measureUnits(unit: 'm');
```

`amount` is a decimal string, such as `"180.34"`. Without `to`, the API returns the canonical unit for the measurement type. Pass `locale` for number formatting and `system` (`us` or `imperial`) when a customary unit needs context. Ambiguous input returns `valid: false`, a `reason`, and available `choices`. Invalid or incompatible target units use the normal API error.

Unit discovery accepts optional `query`, `type`, and `unit` filters. `unit` selects compatible targets. Omit the filters for the reviewed catalog. Both operations use pooled requests.

## Place statistics and optional detail

Postal and District paid profiles include `deep.property_tax` where supported. It contains `annual_median`, `currency` and `period`: median annual property tax payable on owner-occupied homes in the statistical area. The amount is adjusted to the final year of the reporting period (`YYYY-YYYY`). This is an area statistic, not a rate or an individual property bill. Unsupported, missing and censored estimates are null.

```php
$place = $parse->postal('28202', country: 'US', deep: true);
$propertyTax = $place['deep']['property_tax'] ?? null;
```

Read `population_period` alongside `population`: a reporting year (`YYYY`) or period (`YYYY-YYYY`), null when unknown or unverifiable. Keep missing or null values unknown and preserve a known zero. These fields belong to full place profiles. State district lists include each district's population and period. Postal nearby and distance detail remains metropolitan associations only. Continent population and its period remain in core.

Point returns the timezone ID with the core location. Its optional deep detail adds terrain and compact nearest-city context on every plan. A nearest city is null when none is within 200 km.

Weather returns current conditions by default. Paid deep adds specialist current measurements, forecasts and related detail. A past `date` is a UTC day and requires deep: it adds `deep.history` alongside current conditions. Date alone does not request history.

```php
$parse->weather(40.7128, -74.006, deep: true, date: '2026-08-15');
```

Tariff starts with the general schedule line. Paid deep adds units and the special and other schedule columns. An optional origin then resolves country-specific measures. The three calls below show those successive choices. Without origin, schedule detail is still returned and origin-dependent fields are null. A null effective rate is not a zero rate.

```php
$parse->tariff('8471.30.01.00');
$parse->tariff('8471.30.01.00', deep: true);
$parse->tariff('8471.30.01.00', deep: true, origin: 'CN');
```

Address search uses context from the form: prefer postal, or city and state. An optional end-user `ip` is a locality hint for server-side calls. An empty result explains itself with `reason`: `more_input`, `missing_context` or `no_matches`. With suggestions, reason is null. Older responses may omit it, and future reasons remain strings. Catalog and lookup failures use the existing API errors.

HLR reports status at the last check. `live` means assigned and `connected` means reachable at that check. Cached results may be returned. Null means unconfirmed. Deep diagnostics stay within the same metered lookup.

## Deep

Choose enrichment for the question you need answered.

| Operation | What `deep` requests |
|---|---|
| IP | Richer IP fields included with a paid plan. No separate check meter. |
| Domain | Registration dates, registrar, status and DNSSEC, included with a paid plan. Use `dns` for DNS records and `mx` for mail routing. |
| Email | A metered deliverability check, using included email checks or enabled on-demand usage. |
| VAT | A metered registry check where supported, using included VAT checks or enabled on-demand usage. |
| Phone, Time, Date, Currency, Language, Emoji, IBAN, Point | Optional detail in the same pooled request on every plan. |
| Country, State, District, City, Postal | The place profile on paid plans, including demographic and tax facts where held. |
| Name, NAICS | Name evidence or the industry definition profile on paid plans. |
| VIN, NPI, Tariff, Company | The complete product detail bag on paid plans. |
| Weather | Specialist current measurements and the existing forecast, alert, air and history bag on paid plans. |
| Carrier, HLR | Optional diagnostic detail within the same metered core unit, including Free allowance units. No second gate or additional check. |

Carrier, caller, and HLR are separate metered operations. Choose them explicitly when you need their answers. Ordinary lookups retry twice by default. Metered checks use one attempt by default. Setting retries explicitly can repeat paid usage.

Without `deep`, the response omits that key. When requested, it is an empty object if access is locked or the operation has no deep fields. Otherwise it contains the available fields. A missing or null field means unknown.

```php
$ip = $parse->ip('52.94.76.10', deep: true);
$datacenter = $ip['deep']['datacenter'] ?? null;
```

## Errors

Every non-2xx response throws `ParseAPI\ParseAPIError` with `status`, `errorCode`, `docs`, and `requestId`. Branch on `errorCode`.

```php
use ParseAPI\ParseAPIError;

try {
    $parse->city('atlantis');
} catch (ParseAPIError $e) {
    if ($e->errorCode === 'not_found') {
        // no such city
    }
}
```

## Options

```php
$parse = new Client(
    apiKey: 'your-api-key',
    timeout: 10.0, // per-attempt timeout in seconds
);
```

Ordinary lookups retry network errors and HTTP 429, 500, 502, 503, and 504 up to twice. Carrier, caller, and HLR lookups, plus email and VAT with `deep: true`, make one attempt by default because repeating them can repeat paid usage. Address with `deep: true` also uses one attempt, reserving that behavior for future verification. This does not mean address deep currently has a separate charge.

Pass `retries: 0` to make every lookup a single attempt. An explicit count such as `retries: 2` applies to every lookup, including paid ones. A retried request can count toward usage even when the first response was lost. Omit `retries` or pass `null` to use the defaults above.

Reuse one client for successive lookups. Call `$parse->close()` to release its connection when finished. A later lookup opens a new connection.

Network failures throw `RuntimeException`. Invalid JSON throws `JsonException`. A response that decodes to a scalar instead of an object or array throws `UnexpectedValueException`.

`Client` is final. For testing or instrumentation, pass a callable as `transport:`. It receives the URL and request-header array and returns `[status, lowercase_response_headers, body]`. Custom transports should use the supplied headers and keep redirect following disabled.

Requires PHP 8.1 or later with ext-curl. No Composer dependencies.

## Docs

Full field reference for every endpoint: [parseapi.com/docs](https://parseapi.com/docs)

BIN lookup accepts 6-11 digits as a string, including leading zeros. Spaces and hyphens are accepted. `prefix` is the actual longest match and can be shorter than the input. Unknown reference fields are null. `deep` adds an empty object on every plan.


## Optional detail

The default response answers the common task. Ask for `deep` when you need more detail about that same result. Core fields stay equal. City, NAICS and Emoji searches put detail inside each result. Postal nearby and distance put metropolitan detail beside the entity it describes. Time conversion keeps target detail in `to.deep`; only the source has `deep.next_dst`.

```php
$basic = $parse->time('America/New_York');
$detail = $parse->time('America/New_York', deep: true);
$next = $detail['deep']['next_dst'] ?? null;
```
