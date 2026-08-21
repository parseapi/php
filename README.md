# parseapi/sdk

Official parseAPI client for PHP.

```bash
composer require parseapi/sdk
```

```php
use ParseAPI\Client;

$parse = new Client('your-api-key');
$country = $parse->country('US');
```

Get a key at [parseapi.com](https://parseapi.com). The client also reads `PARSEAPI_KEY` from the environment.

## Calls

One method per endpoint, named after the route.

```php
$parse->ip('8.8.8.8');
$parse->ipSelf();
$parse->email('hello@gmail.com');
$parse->phone('+14155552671');
$parse->postal('28202', 'US');
$parse->postalNearby('28202', 'US', radius: 40);
$parse->postalDistance('28202', '10001', 'US');
$parse->city('charlotte', 'US');
$parse->cityId('city_mb8mbqrkz8zb');
$parse->citySearch('char', 'US', limit: 10);
$parse->cityNearest(35.2271, -80.8431);
$parse->country('US');
$parse->countryStates('US');
$parse->state('NC', 'US');
$parse->stateDistricts('NC', 'US');
$parse->district('37081');
$parse->continent('NA');
$parse->continentCountries('NA');
$parse->currency('USD');
$parse->currencyRate('USD', 'EUR');
$parse->language('en');
$parse->name('BILLY OSHALL');
$parse->timezone('America/New_York');
$parse->holiday('US', 2026);
$parse->holidayDate('US', '2026-12-25');
$parse->elevation(35.2271, -80.8431);
$parse->point(36.0726, -79.792);
$parse->weather(40.7128, -74.006);
$parse->domain('example.com');
$parse->mx('example.com');
$parse->useragent($uaString);
$parse->emoji('rocket');
$parse->emojiSearch('fire');
```

Responses are associative arrays, exactly the JSON the API returns.

## Deep

Pass `deep: true` to include the nested `deep` object with richer fields.

```php
$ip = $parse->ip('52.94.76.10', deep: true);
$ip['deep']['datacenter']; // true
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
    retries: 2,    // automatic retries on network errors, 429, and 5xx
);
```

Requires PHP 8.1 or later with ext-curl. No Composer dependencies.

## Docs

Full field reference for every endpoint: [parseapi.com/docs](https://parseapi.com/docs)
