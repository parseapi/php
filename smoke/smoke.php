<?php

/**
 * Live smoke against the edge. Canary-ready: env-driven, clean exit codes.
 *   PARSEAPI_KEY       required
 *   PARSEAPI_BASE_URL  optional override
 * Run: php smoke/smoke.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/ParseAPIError.php';
require __DIR__ . '/../src/Client.php';

use ParseAPI\Client;
use ParseAPI\ParseAPIError;

$failures = 0;
$total = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
	global $failures, $total;
	$total++;
	if (!$ok) {
		$failures++;
	}
	echo ($ok ? 'ok  ' : 'FAIL') . " {$name}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
}

function expectOk(string $name, callable $call, ?callable $assert = null): void
{
	try {
		$result = $call();
		$problem = $assert !== null ? $assert($result) : null;
		check($name, $problem === null, $problem ?? '');
	} catch (ParseAPIError $e) {
		check($name, false, "{$e->status} {$e->errorCode}");
	} catch (\Throwable $e) {
		check($name, false, $e->getMessage());
	}
}

function expectError(string $name, callable $call, string $code): void
{
	try {
		$call();
		check($name, false, 'expected error, got 200');
	} catch (ParseAPIError $e) {
		check($name, $e->errorCode === $code, "got {$e->errorCode}");
	} catch (\Throwable $e) {
		check($name, false, $e->getMessage());
	}
}

const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

$parse = new Client();

expectOk('ip', fn () => $parse->ip('8.8.8.8'), fn ($r) => $r['ip'] === '8.8.8.8' ? null : 'wrong ip');
expectOk('ipSelf', fn () => $parse->ipSelf(), fn ($r) => !empty($r['ip']) ? null : 'no ip');
expectOk('continent', fn () => $parse->continent('NA'), fn ($r) => $r['name'] === 'North America' ? null : 'wrong name');
expectOk('continentCountries', fn () => $parse->continentCountries('NA'), fn ($r) => !empty($r['countries']) ? null : 'empty');
expectOk('country', fn () => $parse->country('US'), fn ($r) => $r['iso3'] === 'USA' ? null : 'wrong iso3');
expectOk('countryStates', fn () => $parse->countryStates('US'), fn ($r) => count($r['states']) >= 50 ? null : 'too few');
expectOk('state', fn () => $parse->state('NC', 'US'), fn ($r) => $r['name'] === 'North Carolina' ? null : 'wrong');
expectOk('stateDistricts', fn () => $parse->stateDistricts('NC', 'US'), fn ($r) => !empty($r['districts']) ? null : 'empty');
expectOk('district', fn () => $parse->district('37081'), fn ($r) => str_contains($r['name'], 'Guilford') ? null : 'wrong district');
expectOk('city', fn () => $parse->city('charlotte', 'US'), fn ($r) => ($r['name'] === 'Charlotte' && str_starts_with((string) ($r['id'] ?? ''), 'city_')) ? null : 'wrong city');
expectOk('cityId', fn () => $parse->cityId($parse->city('charlotte', 'US')['id']), fn ($r) => $r['name'] === 'Charlotte' ? null : 'wrong city');
expectOk('citySearch', fn () => $parse->citySearch('char', 'US', limit: 5), fn ($r) => !empty($r['cities']) ? null : 'empty');
expectOk('cityNearest', fn () => $parse->cityNearest(35.2271, -80.8431), fn ($r) => array_key_exists('distance', $r) ? null : 'no distance');
expectOk('postal', fn () => $parse->postal('28202', 'US'), fn ($r) => $r['city'] === 'Charlotte' ? null : 'wrong city');
expectOk('postalNearby', fn () => $parse->postalNearby('28202', 'US', 40), fn ($r) => !empty($r['nearby']) ? null : 'empty');
expectOk('postalDistance', fn () => $parse->postalDistance('28202', '10001', 'US'), fn ($r) => $r['distance'] > 800 && $r['distance'] < 1000 ? null : "distance {$r['distance']}");
expectOk('email', fn () => $parse->email('hello@gmail.com'), fn ($r) => $r['valid'] === true ? null : 'not valid');
expectOk('phone', fn () => $parse->phone('+14155552671'), fn ($r) => $r['e164'] === '+14155552671' ? null : 'wrong e164');
expectOk('domain', fn () => $parse->domain('gmail.com'), fn ($r) => $r['available'] === false ? null : 'gmail available?');
expectOk('mx', fn () => $parse->mx('gmail.com'), fn ($r) => !empty($r['mx']) ? null : 'no mx');
expectOk('useragent', fn () => $parse->useragent(UA), fn ($r) => $r['browser'] === 'Chrome' ? null : "browser {$r['browser']}");
expectOk('currency', fn () => $parse->currency('USD'), fn ($r) => $r['symbol'] === '$' ? null : 'wrong symbol');
expectOk('currencyRate', fn () => $parse->currencyRate('USD', 'EUR'), fn ($r) => $r['rate'] > 0 && $r['rate'] < 10 ? null : 'bad rate');
expectOk('language', fn () => $parse->language('en'), fn ($r) => ($r['language'] === 'en' && $r['name'] === 'English') ? null : 'wrong language');
expectOk('timezone', fn () => $parse->timezone('America/New_York'), fn ($r) => in_array($r['offset_minutes'], [-240, -300], true) ? null : "offset {$r['offset_minutes']}");
expectOk('holiday', fn () => $parse->holiday('US'), fn ($r) => count($r['holidays']) > 5 ? null : 'too few');
expectOk('holidayDate', fn () => $parse->holidayDate('US', '2026-12-25'), fn ($r) => ($r['holiday']['name'] ?? null) === 'Christmas Day' ? null : 'not christmas');
expectOk('holiday null', fn () => $parse->holidayDate('US', '2026-08-12'), fn ($r) => $r['holiday'] === null ? null : 'expected null');
expectOk('elevation', fn () => $parse->elevation(35.2271, -80.8431), fn ($r) => is_numeric($r['elevation']) ? null : 'no elevation');
expectOk('point', fn () => $parse->point(36.0726, -79.792), fn ($r) => $r['country'] === 'US' ? null : "country {$r['country']}");
expectOk('weather', fn () => $parse->weather(40.7128, -74.006), fn ($r) => !empty($r['station']) ? null : 'no station');
expectOk('emoji', fn () => $parse->emoji('rocket'), fn ($r) => $r['emoji'] === "\u{1F680}" ? null : 'wrong emoji');
expectOk('emojiSearch', fn () => $parse->emojiSearch('fire', 5), fn ($r) => !empty($r['emojis']) ? null : 'empty');
expectOk('point deep triad', fn () => $parse->point(36.0726, -79.792, deep: true), fn ($r) => isset($r['deep']) && is_array($r['deep']) ? null : 'deep missing');

expectError('honest 404', fn () => $parse->city('notarealcityxyz'), 'not_found');
expectError('bogus key 401', fn () => (new Client('bogus_key_123', retries: 0))->country('US'), 'invalid_api_key');

echo "\n" . ($total - $failures) . "/{$total} passed\n";
exit($failures === 0 ? 0 : 1);
