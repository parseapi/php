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

## Calls

One method per endpoint, named after the route.

```php
$parse->ip('8.8.8.8');
$parse->ipSelf();
$parse->email('hello@gmail.com');
$parse->vat('DE136695976');
$parse->iban('DE89370400440532013000');
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
$parse->name('Andrea', country: 'IT');
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

Each lookup returns an associative array. Related lookups are separate calls, such as `countryStates('US')`. Reading the result makes no further requests. New response fields and `null` values are preserved. JSON objects, including an empty `deep` object, decode as PHP arrays.

Use named arguments for optional settings, such as `country: 'US'` or `deep: true`.

DNS uses pooled requests on every plan. Omit `type` to check A, AAAA, CNAME, MX, NS, TXT, SOA, CAA, SRV and PTR. Records contain `name`, `type`, `ttl` in seconds and a DNS presentation `value`. TXT values retain quoting and chunk boundaries. A selected question can include its CNAME chain. Empty records mean no records. Lookup failures remain errors.

## Time

`time` returns local ISO `at` with its UTC offset and integer Unix seconds in `unix`. `offset_seconds` is the exact offset, while `offset_minutes` is whole minutes. Historical offsets and ISO times can include offset seconds. Omitted `at` means now. With `to`, an offsetless `at` is source wall time. Otherwise it is UTC. Include an offset for repeated local times around a clock change. Current time and conversion use pooled requests on every plan. Coordinate clock fields can be null when the timezone is unknown. Existing `timezone` methods remain supported.

## Measurements

```php
$result = $parse->measure('5 ft 11 in', to: 'cm');
$units = $parse->measureUnits(unit: 'm');
```

`amount` is a decimal string, such as `"180.34"`. Without `to`, the API returns the canonical unit for the measurement type. Pass `locale` for number formatting and `system` (`us` or `imperial`) when a customary unit needs context. Ambiguous input returns `valid: false`, a `reason`, and available `choices`. Invalid or incompatible target units use the normal API error.

Unit discovery accepts optional `query`, `type`, and `unit` filters. `unit` selects compatible targets. Omit the filters for the reviewed catalog. Both operations use pooled requests.

## Deep

Choose enrichment for the question you need answered.

| Operation | What `deep` requests |
|---|---|
| IP | Richer IP fields included with a paid plan. No separate check meter. |
| Email | A metered deliverability check, using included email checks or enabled on-demand usage. |
| VAT | A metered registry check where supported, using included VAT checks or enabled on-demand usage. |
| Phone | An empty object. Number parsing and formats are already in the core response. |

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
