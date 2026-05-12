# API reference

[← Documentation index](README.md)

Every interaction with the NUKI Web API runs through the
[Nuki facade](../src/Facades/Nuki.php), which resolves a singleton
[Nuki manager](../src/Nuki.php). The manager exposes six resource factories.
Each resource is stateless — instantiate them via the manager, do not cache.

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

Source: [src/Resources/SmartLocks.php](../src/Resources/SmartLocks.php).

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
| `sync(int $smartlockId)` | `void` | Force a state refresh from the bridge / Wi-Fi-equipped device. |

Action constants on the class for clarity:

```php
SmartLocks::ACTION_UNLOCK                  // 1
SmartLocks::ACTION_LOCK                    // 2
SmartLocks::ACTION_UNLATCH                 // 3
SmartLocks::ACTION_LOCK_AND_GO             // 4
SmartLocks::ACTION_LOCK_AND_GO_WITH_UNLATCH // 5
```

Example:

```php
$locks = Nuki::smartlocks()->all();

foreach ($locks->where('batteryCritical', true) as $lock) {
    Log::warning("Battery critical on {$lock->name}");
}

Nuki::smartlocks()->update($lockId, ['name' => 'Front door']);
Nuki::smartlocks()->sync($lockId);
```

## `SmartlockLogs`

Source: [src/Resources/SmartlockLogs.php](../src/Resources/SmartlockLogs.php).

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

Source: [src/Resources/SmartlockAuths.php](../src/Resources/SmartlockAuths.php).

| Method | Returns | Description |
|---|---|---|
| `forSmartlock(int $smartlockId, array $filters = [])` | `Collection<int, Authorization>` | All authorizations for one lock. |
| `all(array $filters = [])` | `Collection<int, Authorization>` | All authorizations across the account. |
| `create(int $smartlockId, array $attributes)` | `void` | `PUT /smartlock/{id}/auth`. |
| `update(int $smartlockId, string $authId, array $attributes)` | `void` | `POST /smartlock/{id}/auth/{authId}`. |
| `delete(int $smartlockId, string $authId)` | `void` | |

Authorization type constants (per NUKI):

```php
SmartlockAuths::TYPE_APP          // 0
SmartlockAuths::TYPE_BRIDGE       // 1
SmartlockAuths::TYPE_FOB          // 2
SmartlockAuths::TYPE_KEYPAD       // 3
SmartlockAuths::TYPE_KEYPAD_CODE  // 13
SmartlockAuths::TYPE_Z_KEY        // 14
```

Restricting a keypad code to weekday + window:

```php
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

Source: [src/Resources/Webhooks.php](../src/Resources/Webhooks.php). For the
inbound side (receiving callbacks from NUKI) see [Webhooks](webhooks.md).

| Method | Returns | Description |
|---|---|---|
| `all()` | `Collection<int, WebhookSubscription>` | List your registered subscriptions on `/api/notification`. |
| `subscribe(string $callbackUrl, array $events)` | `WebhookSubscription` | `PUT /api/notification` with `notificationType: webhook` and the given `webhookFeatures`. Returns the new subscription. |
| `unsubscribe(string $id)` | `void` | `DELETE /api/notification/{id}`. |

NUKI's webhook events (use any subset in `$events`): `DEVICE_STATUS`,
`DEVICE_CONFIG`, `DEVICE_LOGS`, `ACCOUNT_USER`, etc. — see the NUKI Web API
docs for the authoritative list.

## `OAuth`

Source: [src/Resources/OAuth.php](../src/Resources/OAuth.php). Only relevant
when `NUKI_AUTH=oauth`.

| Method | Returns | Description |
|---|---|---|
| `authorizationUrl(?string $state = null, ?array $scopes = null)` | `string` | Builds the URL to redirect the user to for consent. `state` is recommended for CSRF protection; `scopes` overrides the configured defaults. |
| `exchangeCode(string $code, string $accountKey = 'default')` | `NukiToken` | Exchanges an authorization code for a token, stores it under `$accountKey`. |
| `refresh(string $accountKey = 'default')` | `NukiToken` | Force a refresh using the stored refresh token. The authenticator does this automatically when needed — call this only when you want to refresh proactively. |
| `token(string $accountKey = 'default')` | `?NukiToken` | Returns the stored token (or null). |
| `revoke(string $accountKey = 'default')` | `void` | Removes the stored token. Does **not** call a NUKI revocation endpoint (NUKI offers none). |

## `Account`

Source: [src/Resources/Account.php](../src/Resources/Account.php).

| Method | Returns | Description |
|---|---|---|
| `info(bool $fresh = false)` | `?AccountInfo` | `GET /account`. Result is cached for one hour under `nuki:account-info:{accountKey}`. Pass `$fresh = true` to force a refetch. Returns `null` on HTTP error or empty payload. |

## DTOs

All response shapes map onto readonly classes under
[src/DTOs/](../src/DTOs/). Every DTO has a static `fromArray(array $data)`
factory and exposes the raw NUKI payload under `$raw` for fields the typed
properties don't cover yet.

### `SmartLock`

[src/DTOs/SmartLock.php](../src/DTOs/SmartLock.php)

Main fields: `smartlockId`, `accountId`, `type`, `authId`, `name`,
`favourite`, `state`, `stateName`, `batteryCharge`, `batteryCritical`,
`batteryCharging`, `keypadBatteryCritical`, `doorsensorBatteryCritical`,
`firmwareVersion`, `hardwareVersion`, `doorState`, `serverState`,
`creationDate`, `updateDate`, `raw`.

Helpers: `isLocked()`, `isUnlocked()`, `doorStateLabel()` (localised).

Device-type constants: `TYPE_SMARTLOCK` (0), `TYPE_OPENER` (2),
`TYPE_SMARTDOOR` (3), `TYPE_SMARTLOCK_3` (4).

### `LogEntry`

[src/DTOs/LogEntry.php](../src/DTOs/LogEntry.php)

Main fields: `id`, `smartlockId`, `accountUserId`, `authId`, `authType`,
`name`, `action`, `trigger`, `state`, `autoUnlock`, `date`, `source`, `raw`.

### `Authorization`

[src/DTOs/Authorization.php](../src/DTOs/Authorization.php)

Main fields: `id`, `smartlockId`, `authId`, `code`, `type`, `name`,
`enabled`, `remoteAllowed`, `allowedFromDate`, `allowedUntilDate`,
`allowedWeekDays` (NUKI weekday bitmask), `lastActiveDate`, `creationDate`,
`updateDate`, `raw`.

### `WebhookSubscription`

[src/DTOs/WebhookSubscription.php](../src/DTOs/WebhookSubscription.php)

Fields: `id`, `callbackUrl`, `events`, `creationDate`, `raw`.

### `NukiToken`

[src/DTOs/NukiToken.php](../src/DTOs/NukiToken.php)

Fields: `accessToken`, `refreshToken`, `expiresAt` (CarbonImmutable),
`tokenType`, `scope`.

Helpers: `isExpired(int $leewaySeconds = 30)`, `toArray()`.

### `AccountInfo`

[src/DTOs/AccountInfo.php](../src/DTOs/AccountInfo.php)

Fields: `accountId`, `email`, `name`, `language`, `creationDate`, `raw`.

Helper: `displayName()` — falls back through `name → email → '#{id}'`.

## Console commands

Three commands ship with the package:

| Command | Description |
|---|---|
| `nuki:oauth-authorize` | Interactive OAuth authorization-code dance from the terminal. Options: `--account=<key>` (default `default`), `--code=<code>` (skip prompt). See [NukiOAuthAuthorizeCommand](../src/Console/Commands/NukiOAuthAuthorizeCommand.php). |
| `nuki:user-create` | Create the first main `NukiUser` for the package's own auth guard. Options: `--email`, `--name`, `--password`, `--no-2fa`. See [NukiUserCreateCommand](../src/Console/Commands/NukiUserCreateCommand.php) and [Users and permissions](users-and-permissions.md). |
| `nuki:webhook-register` | Register a callback URL with NUKI. Argument: optional `url` (defaults to `APP_URL` + `nuki.webhook.route`). Options: `--account=<key>`, `--events=<list>` (default `DEVICE_STATUS`, `DEVICE_CONFIG`, `DEVICE_LOGS`, `ACCOUNT_USER`). See [NukiWebhookRegisterCommand](../src/Console/Commands/NukiWebhookRegisterCommand.php). |

## Errors

Hierarchy under [src/Exceptions/](../src/Exceptions/):

- [NukiException](../src/Exceptions/NukiException.php) — base class.
- [AuthenticationException](../src/Exceptions/AuthenticationException.php) —
  missing/expired token, failed OAuth exchange, invalid config.
- [ApiException](../src/Exceptions/ApiException.php) — non-success HTTP from
  NUKI; carries status and parsed body. Constructed via
  `ApiException::fromResponse($response)` inside `HttpClient`.

`HttpClient` retries connection errors, HTTP 429 and 5xx with exponential
backoff (`http.retries` attempts, `http.retry_sleep` × 2^n milliseconds), so
you only see exceptions when retries are exhausted.
