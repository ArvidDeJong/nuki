---
title: "API reference"
nav_order: 6
description: "Every public method of the darvis/nuki facade: smartlocks, logs, authorizations, webhooks, OAuth and account, the objects they return, commands and exceptions."
---

# API reference

Every interaction with the NUKI Web API runs through the
[Nuki facade](https://github.com/ArvidDeJong/nuki/blob/main/src/Facades/Nuki.php), which resolves a singleton
[Nuki manager](https://github.com/ArvidDeJong/nuki/blob/main/src/Nuki.php). The manager exposes six resource factories.
A resource is a small object that holds the account key it was made for. Ask the manager for one
each time instead of keeping a reference, or a later `Nuki::as()` does not reach it.

```php
use Darvis\Nuki\Facades\Nuki;

Nuki::smartlocks();   // SmartLocks
Nuki::logs();         // SmartlockLogs
Nuki::auths();        // SmartlockAuths
Nuki::webhooks();     // Webhooks
Nuki::oauth();        // OAuth
Nuki::account();      // Account
```

## Manager (`Darvis\Nuki\Nuki`)

| Method | Returns | Description |
|---|---|---|
| `as(string $accountKey)` | `self` | Clone scoped to the given account key. See [NUKI API authentication](nuki-api-authentication.md#multi-account-scoping-with-nukias). |
| `currentAccount()` | `string` | The key this instance is scoped to (`'default'` unless `as()` was called). |
| `smartlocks()` | [`SmartLocks`](#smartlocks) | |
| `logs()` | [`SmartlockLogs`](#smartlocklogs) | |
| `auths()` | [`SmartlockAuths`](#smartlockauths) | |
| `webhooks()` | [`Webhooks`](#webhooks) | |
| `oauth()` | [`OAuth`](#oauth) | |
| `account()` | [`Account`](#account) | |

## `SmartLocks`

Source: [src/Resources/SmartLocks.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/SmartLocks.php).

| Method | Returns | Description |
|---|---|---|
| `all(array $query = [])` | `Collection<int, SmartLock>` | List every smartlock visible to the account. Pass NUKI query params (e.g. `accountUserId`) in `$query`. |
| `find(int $smartlockId)` | `SmartLock` | Fetch a single smartlock. |
| `lock(int $smartlockId)` | `void` | Convenience wrapper around `action($id, 2)`. |
| `unlock(int $smartlockId)` | `void` | `action($id, 1)`. |
| `unlatch(int $smartlockId)` | `void` | `action($id, 3)`. |
| `lockAndGo(int $smartlockId)` | `void` | `action($id, 4)`. |
| `lockAndGoWithUnlatch(int $smartlockId)` | `void` | `action($id, 5)`. |
| `action(int $smartlockId, int $action, ?int $option = null)` | `void` | Low-level call to `POST /smartlock/{id}/action` with arbitrary action code + optional option flag. |
| `update(int $smartlockId, array $attributes)` | `void` | Update user-controllable fields (`name`, `favourite`, `defaultName`, advanced flags). Pass only keys you want to change — NUKI merges server-side. |
| `sync(int $smartlockId)` | `void` | `POST /smartlock/{id}/sync`: asks NUKI to refresh the state it has for the lock. |

The actions return nothing. A call that did not throw means NUKI accepted the command, not that
the bolt moved: call `sync()` and `find()` again, or listen for the `DEVICE_STATUS`
[webhook](webhooks.md). An action is a `POST` and is repeated on a 5xx like every call; see
[`http.retries`](configuration.md#http--outbound-http-tuning).

Action constants on the class:

```php
SmartLocks::ACTION_UNLOCK                  // 1
SmartLocks::ACTION_LOCK                    // 2
SmartLocks::ACTION_UNLATCH                 // 3
SmartLocks::ACTION_LOCK_AND_GO             // 4
SmartLocks::ACTION_LOCK_AND_GO_WITH_UNLATCH // 5
```

Example:

```php
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Support\Facades\Log;

$locks = Nuki::smartlocks()->all();

foreach ($locks->where('batteryCritical', true) as $lock) {
    Log::warning("Battery critical on {$lock->name}");
}

Nuki::smartlocks()->update($lockId, ['name' => 'Front door']);
Nuki::smartlocks()->sync($lockId);
```

## `SmartlockLogs`

Source: [src/Resources/SmartlockLogs.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/SmartlockLogs.php).

| Method | Returns | Description |
|---|---|---|
| `forSmartlock(int $smartlockId, array $filters = [])` | `Collection<int, LogEntry>` | `GET /smartlock/{id}/log`. NUKI filters such as `limit`, `fromDate`, `toDate` go in `$filters`. |
| `all(array $filters = [])` | `Collection<int, LogEntry>` | `GET /smartlock/log` — account-wide feed across all locks. |

```php
$recent = Nuki::logs()->forSmartlock($id, [
    'limit' => 50,
    'fromDate' => '2026-01-01T00:00:00Z',
]);
```

## `SmartlockAuths`

Source: [src/Resources/SmartlockAuths.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/SmartlockAuths.php).

| Method | Returns | Description |
|---|---|---|
| `forSmartlock(int $smartlockId, array $filters = [])` | `Collection<int, Authorization>` | All authorizations for one lock. |
| `all(array $filters = [])` | `Collection<int, Authorization>` | All authorizations across the account. |
| `create(int $smartlockId, array $attributes)` | `void` | `PUT /smartlock/{id}/auth`. |
| `update(int $smartlockId, string $authId, array $attributes)` | `void` | `POST /smartlock/{id}/auth/{authId}`. |
| `delete(int $smartlockId, string $authId)` | `void` | `DELETE /smartlock/{id}/auth/{authId}`. |

The filter and attribute arrays go to NUKI as they are; the package does not validate them.
`$authId` is the `id` property of an `Authorization`, a string.

Authorization type constants on the class:

```php
SmartlockAuths::TYPE_APP          // 0
SmartlockAuths::TYPE_BRIDGE       // 1
SmartlockAuths::TYPE_FOB          // 2
SmartlockAuths::TYPE_KEYPAD       // 3
SmartlockAuths::TYPE_KEYPAD_CODE  // 13
SmartlockAuths::TYPE_Z_KEY        // 14
```

A keypad code that only works on Monday, Wednesday and Friday within a period:

```php
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Resources\SmartlockAuths;
use Darvis\Nuki\Support\WeekdayBitmask;

Nuki::auths()->create($lockId, [
    'name' => 'Cleaning crew',
    'type' => SmartlockAuths::TYPE_KEYPAD_CODE,
    'code' => 246810,
    'allowedFromDate' => '2026-05-01T00:00:00Z',
    'allowedUntilDate' => '2026-12-31T23:59:59Z',
    'allowedWeekDays' => WeekdayBitmask::fromDays(['ma', 'wo', 'vr']),
]);
```

## `Webhooks`

Source: [src/Resources/Webhooks.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/Webhooks.php). For the
inbound side (receiving callbacks from NUKI) see [Webhooks](webhooks.md).

| Method | Returns | Description |
|---|---|---|
| `all()` | `Collection<int, WebhookSubscription>` | List your registered subscriptions on `/api/notification`. |
| `subscribe(string $callbackUrl, array $events)` | `WebhookSubscription` | `PUT /api/notification` with `notificationType: webhook` and the given `webhookFeatures`. Returns the new subscription. |
| `unsubscribe(string $id)` | `void` | `DELETE /api/notification/{id}`. |

`$events` is passed to NUKI as it is. The `nuki:webhook-register` command uses `DEVICE_STATUS`,
`DEVICE_CONFIG`, `DEVICE_LOGS` and `ACCOUNT_USER` by default; the NUKI Web API documentation has
the list. `subscribe()` fills `WebhookSubscription::$id` from the key `id` of the answer; when it
is empty, look in `->raw`.

## `OAuth`

Source: [src/Resources/OAuth.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/OAuth.php). Only relevant
when `NUKI_AUTH=oauth`. `Nuki::oauth()` ignores `Nuki::as()`: every method takes the account key
as an argument. The package has no callback route; see
[The callback route is yours to build](nuki-api-authentication.md#the-callback-route-is-yours-to-build).

| Method | Returns | Description |
|---|---|---|
| `authorizationUrl(?string $state = null, ?array $scopes = null)` | `string` | Builds the URL to redirect the user to for consent. Pass a random `state` and compare it yourself in your callback route; the package never checks it. `scopes` overrides the configured defaults. |
| `exchangeCode(string $code, string $accountKey = 'default')` | `NukiToken` | Exchanges an authorization code for a token, stores it under `$accountKey`. |
| `refresh(string $accountKey = 'default')` | `NukiToken` | Force a refresh using the stored refresh token. The authenticator does this automatically when needed — call this only when you want to refresh proactively. |
| `token(string $accountKey = 'default')` | `?NukiToken` | Returns the stored token (or null). |
| `revoke(string $accountKey = 'default')` | `void` | Removes the stored token. It does not call NUKI. |

## `Account`

Source: [src/Resources/Account.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Resources/Account.php).

| Method | Returns | Description |
|---|---|---|
| `info(bool $fresh = false)` | `?AccountInfo` | `GET /account`. Result is cached for one hour under `nuki:account-info:{accountKey}`. Pass `fresh: true` to skip the cache. Returns `null` on any failure, also a missing token, and on an empty answer; it never throws. |

## DTOs

All response shapes map onto readonly classes under
[src/DTOs/](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/). Every DTO has a static `fromArray(array $data)`
factory and exposes the raw NUKI payload under `$raw` for fields the typed
properties don't cover yet.

### `SmartLock`

[src/DTOs/SmartLock.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/SmartLock.php)

Main fields: `smartlockId`, `accountId`, `type`, `authId`, `name`,
`favourite`, `state`, `stateName`, `batteryCharge`, `batteryCritical`,
`batteryCharging`, `keypadBatteryCritical`, `doorsensorBatteryCritical`,
`firmwareVersion`, `hardwareVersion`, `doorState`, `serverState`,
`creationDate`, `updateDate`, `raw`.

Helpers: `isLocked()`, `isUnlocked()`, `doorStateLabel()` (localised).

Device-type constants: `TYPE_SMARTLOCK` (0), `TYPE_OPENER` (2),
`TYPE_SMARTDOOR` (3), `TYPE_SMARTLOCK_3` (4).

### `LogEntry`

[src/DTOs/LogEntry.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/LogEntry.php)

Main fields: `id`, `smartlockId`, `accountUserId`, `authId`, `authType`,
`name`, `action`, `trigger`, `state`, `autoUnlock`, `date`, `source`, `raw`.

### `Authorization`

[src/DTOs/Authorization.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/Authorization.php)

Main fields: `id`, `smartlockId`, `authId`, `code`, `type`, `name`,
`enabled`, `remoteAllowed`, `allowedFromDate`, `allowedUntilDate`,
`allowedWeekDays` (NUKI weekday bitmask), `lastActiveDate`, `creationDate`,
`updateDate`, `raw`.

### `WebhookSubscription`

[src/DTOs/WebhookSubscription.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/WebhookSubscription.php)

Fields: `id`, `callbackUrl`, `events`, `creationDate`, `raw`.

### `NukiToken`

[src/DTOs/NukiToken.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/NukiToken.php)

Fields: `accessToken`, `refreshToken`, `expiresAt` (CarbonImmutable),
`tokenType`, `scope`.

Helpers: `isExpired(int $leewaySeconds = 30)`, `toArray()`.

### `AccountInfo`

[src/DTOs/AccountInfo.php](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/AccountInfo.php)

Fields: `accountId`, `email`, `name`, `language`, `creationDate`, `raw`.

Helper: `displayName()` — falls back through `name → email → '#{id}'`.

## Console commands

Three commands ship with the package:

| Command | Description |
|---|---|
| `nuki:oauth-authorize` | Interactive OAuth authorization-code dance from the terminal. Options: `--account=<key>` (default `default`), `--code=<code>` (skip prompt). See [NukiOAuthAuthorizeCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiOAuthAuthorizeCommand.php). |
| `nuki:user-create` | Create a main `NukiUser` for the package's own auth guard. Options: `--email`, `--name`, `--password` (asked for when left out) and `--no-2fa`, which stores `two_factor_enabled = false`; that column is not read at login, so the user still gets a code while `auth_users.otp.enabled` is `true`. See [NukiUserCreateCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiUserCreateCommand.php) and [Users and permissions](users-and-permissions.md). |
| `nuki:webhook-register` | Register a callback URL with NUKI. Argument: optional `url` (defaults to `APP_URL` + `nuki.webhook.route`). Options: `--account=<key>` (default `default`), `--events=<name>`, repeatable (default `DEVICE_STATUS`, `DEVICE_CONFIG`, `DEVICE_LOGS`, `ACCOUNT_USER`). See [NukiWebhookRegisterCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiWebhookRegisterCommand.php). |

## Errors

| Exception | When | What it carries |
|---|---|---|
| [AuthenticationException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/AuthenticationException.php) | No token for the account key, a failed OAuth exchange or refresh, incomplete OAuth settings. Nothing was sent to the lock API. | The message. |
| [ApiException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/ApiException.php) | NUKI answered with a 4xx or 5xx. | `->status` (int), `->body` (the raw answer as a string; decode it yourself), `->endpoint` (for example `GET /smartlock/17`). The message is `NUKI API GET /smartlock/17 returned HTTP 404: <first 300 characters of the body>`. |
| [NukiException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/NukiException.php) | The base class of the two above, and thrown itself for an unknown `auth`, `token_resolver` or `oauth.token_store` value. | The message. |
| `Illuminate\Http\Client\ConnectionException` | The server could not be reached, also after the retries. | Laravel's exception. It is **not** a `NukiException`. |

```php
use Darvis\Nuki\Exceptions\ApiException;
use Darvis\Nuki\Exceptions\NukiException;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Http\Client\ConnectionException;

try {
    Nuki::smartlocks()->unlock($smartlockId);
} catch (ApiException $e) {
    report($e);                       // $e->status, $e->body, $e->endpoint
} catch (NukiException|ConnectionException $e) {
    report($e);
}
```

A connection error, an HTTP 429 and a 5xx are tried again before you see an exception:
`http.retries` attempts in total (default 3, the first one included), with a fixed pause of
`http.retry_sleep` milliseconds (default 200) in between. Any other 4xx is not repeated. The
package logs nothing itself.
