---
name: nuki-development
description: Work with darvis/nuki. Use it to list, lock and unlock NUKI smartlocks, read activity logs, manage keypad codes, scope calls to one of several NUKI accounts, connect an account with OAuth, receive signed NUKI webhooks, close off the bundled UI, and test all of that without calling the NUKI Web API.
---

# darvis/nuki development

## When to use this skill

Use this skill when code talks to a NUKI smartlock in an application that has `darvis/nuki` installed, when a call ends in an `AuthenticationException` or an `ApiException`, when a NUKI webhook is rejected or never reaches your listener, when you decide who may open the bundled `/nuki` pages, or when you write tests around any of this.

## How a call runs

1. `Nuki::smartlocks()`, `logs()`, `auths()`, `webhooks()` and `account()` return a new resource object for the current account key. That key is `'default'` until you call `Nuki::as('some-key')`, which returns a clone of the manager and leaves the original alone.
2. The resource calls `Darvis\Nuki\Http\HttpClient`, which asks the bound `Authenticator` for a bearer token for that account key. `auth` set to `token` uses an `ApiTokenResolver` (`config` or `database`), `auth` set to `oauth` reads a `NukiToken` from the `TokenStore` and refreshes it when it expires within 30 seconds.
3. The request goes to `base_url` as JSON with a timeout of `http.timeout` seconds. A connection error, a 429 and a 5xx are tried again, `http.retries` attempts in total, with a fixed pause of `http.retry_sleep` milliseconds in between. Any other 4xx is not repeated.
4. A response that still failed becomes an `ApiException`. A good response is mapped to readonly DTOs in `Darvis\Nuki\DTOs`; the untouched API row is always in `->raw`.

| Situation | What you get | Message |
| --- | --- | --- |
| Token mode, no token for the account key | `AuthenticationException`, nothing is sent | `No NUKI API token configured for account [<key>]. Add it via the NUKI accounts page or set NUKI_API_TOKEN for the default account.` |
| OAuth mode, no stored token | `AuthenticationException`, nothing is sent | `No NUKI OAuth token stored for account [<key>]. Run nuki:oauth-authorize or complete the OAuth flow first.` |
| OAuth refresh refused | `AuthenticationException`, and the stored token is deleted | `Failed to refresh NUKI OAuth token: HTTP <status> <body>` |
| OAuth token expired without a refresh token | `AuthenticationException` | `NUKI OAuth token for account [<key>] is expired and could not be refreshed.` |
| OAuth settings incomplete | `AuthenticationException` from `oauth()->authorizationUrl()`, `exchangeCode()` and `refresh()` | `Missing NUKI OAuth config key: <key>. Check config/nuki.php and your environment variables.` |
| HTTP 4xx or 5xx | `ApiException` with `->status`, `->body` (raw string) and `->endpoint` | `NUKI API GET /smartlock/1 returned HTTP 404: <first 300 characters of the body>` |
| Connection error after the last attempt | `Illuminate\Http\Client\ConnectionException`, which is **not** a `NukiException` | Laravel's message |
| Unknown `auth`, `token_resolver` or `oauth.token_store` | `NukiException` on first use | `Unknown nuki.auth mode: <value>` and the like |
| Anything wrong inside `account()->info()` | `null`, no exception | none |

The package logs nothing. `AuthenticationException` and `ApiException` both extend `Darvis\Nuki\Exceptions\NukiException`; catch `ConnectionException` next to it when a timeout must not break the page.

## Smartlocks, logs and keypad codes

```php
use Darvis\Nuki\Exceptions\NukiException;
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Resources\SmartlockAuths;
use Illuminate\Http\Client\ConnectionException;

$locks = Nuki::smartlocks()->all();            // Collection<int, SmartLock>
$lock = Nuki::smartlocks()->find($smartlockId);

if ($lock->isLocked() && ! $lock->batteryCritical) {
    try {
        Nuki::smartlocks()->unlock($lock->smartlockId);
    } catch (NukiException|ConnectionException $e) {
        report($e);
    }
}

$entries = Nuki::logs()->forSmartlock($smartlockId, ['limit' => 50]);
$everything = Nuki::logs()->all(['limit' => 100]);

Nuki::auths()->create($smartlockId, [
    'name' => 'Cleaner',
    'type' => SmartlockAuths::TYPE_KEYPAD_CODE,   // 13
    'code' => 246813,
    'allowedWeekDays' => 64 | 16,                 // Monday and Wednesday
]);
```

- The actions are `lock()`, `unlock()`, `unlatch()`, `lockAndGo()`, `lockAndGoWithUnlatch()` and `action($id, $action, $option)`. They return `void`: a call that did not throw means NUKI accepted the command, not that the bolt has moved. Call `sync($id)` and read `find($id)` again, or listen for the `DEVICE_STATUS` webhook.
- An action is a POST and is repeated on a 5xx like any other request. Set `http.retries` to `1` when a second unlock command is worse than an error.
- `auths()->create()`, `update()` and `delete()` also return `void`, and the filter and attribute arrays go to NUKI as they are. The package does not validate them.
- `SmartLock::$state` and `$stateName` are `null` when the API sends no `state` block. `isUnlocked()` is true for state 3, 5 and 6.
- The weekday bitmask is Monday 64, Tuesday 32, Wednesday 16, Thursday 8, Friday 4, Saturday 2, Sunday 1. `Darvis\Nuki\Support\WeekdayBitmask::fromDays()` takes Dutch day codes (`ma`, `di`, `wo`, `do`, `vr`, `za`, `zo`).
- `account()->info()` is cached for an hour per account key under `nuki:account-info:<key>`; pass `fresh: true` to skip the cache.

## More than one NUKI account

```php
use Darvis\Nuki\Models\NukiAccount;

NukiAccount::create([
    'account_key' => 'tenant-42',
    'name' => 'Office Utrecht',
    'api_token' => $personalApiToken,   // stored encrypted
    'is_active' => true,
]);

Nuki::as('tenant-42')->smartlocks()->all();
```

- The account key is an opaque string; the package never looks at your `User` model.
- `token_resolver` is `database` by default, so every call runs a query on `nuki_accounts`. Without `php artisan migrate` that query fails. It only finds rows with `is_active` true, and falls back to `NUKI_API_TOKEN` for the key `default` alone.
- With `token_resolver` set to `config` only the key `default` has a token. `Nuki::as('anything-else')` throws the `AuthenticationException` from the table above.
- `api_token` uses the `encrypted` cast. Write it through the model (`create()`, `$account->save()`); a query builder `update()` skips the cast and stores the token in plain text, which then fails to decrypt.
- Resources are cheap and stateless. Ask the manager for one each time instead of keeping a reference, or a later `as()` does not reach it.

## OAuth

The package builds the authorization URL and exchanges the code, but it registers **no callback route**. The `nuki:oauth-authorize` command prints the URL and asks you to paste the code; in a web flow the host application owns the redirect target and the `state` check.

```php
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

Route::get('/nuki/connect', function () {
    session(['nuki_oauth_state' => $state = Str::random(40)]);

    return redirect()->away(Nuki::oauth()->authorizationUrl(state: $state));
})->middleware('auth');

// This URL is NUKI_OAUTH_REDIRECT_URL.
Route::get('/nuki/oauth/callback', function (Request $request) {
    abort_unless(hash_equals((string) session()->pull('nuki_oauth_state'), (string) $request->query('state')), 403);

    Nuki::oauth()->exchangeCode((string) $request->query('code'), (string) $request->user()->id);

    return redirect('/nuki');
})->middleware('auth');
```

- `exchangeCode()` throws `AuthenticationException` with `NUKI OAuth code exchange failed: HTTP <status> <body>`.
- `oauth.token_store` is `cache` by default. `php artisan cache:clear` then disconnects every account, and an entry expires one day after the access token does. Use `database` (table `nuki_oauth_tokens`, encrypted columns) for anything that has to last.
- `oauth()->token($key)` returns the stored `NukiToken` or `null`; `oauth()->revoke($key)` only forgets it locally.

## Webhooks

1. The route exists only when `NUKI_WEBHOOK_ENABLED=true`: `POST /nuki/webhook`, name `nuki.webhook`, middleware `api`.
2. The controller computes `hash_hmac('sha256', <raw body>, <secret>)` and compares it with the `X-Nuki-Signature` header. Without a secret, without the header or on a mismatch the answer is `401 {"error":"invalid signature"}`. Only `webhook.verify_signature` set to the boolean `false` switches the check off.
3. The event id is `id`, otherwise `eventId`, otherwise a SHA-1 of the whole payload. `Cache::add('nuki:webhook:<id>', …)` remembers it for `webhook.dedup_ttl` seconds (600). A repeat gets `200 {"status":"duplicate"}` and no event.
4. `Darvis\Nuki\Events\NukiWebhookReceived` is dispatched with `type` (`event`, otherwise `type`, otherwise `'unknown'`), `payload` (the whole body) and `accountKey` (the `?account=` query parameter, or `null`). The answer is `200 {"status":"ok"}`.

```php
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (NukiWebhookReceived $event): void {
    if ($event->type !== 'DEVICE_LOGS') {
        return;
    }

    ProcessNukiLog::dispatch($event->payload, $event->accountKey);
});
```

Register the callback with `php artisan nuki:webhook-register "https://example.com/nuki/webhook?account=tenant-42" --account=tenant-42 --events=DEVICE_LOGS`. Without arguments it uses `APP_URL` plus the webhook route and the events `DEVICE_STATUS`, `DEVICE_CONFIG`, `DEVICE_LOGS` and `ACCOUNT_USER`.

Pitfalls:

- The event id is stored **before** your listener runs. A listener that throws gives NUKI a 500, and the retry is then dropped as a duplicate. Keep the listener to dispatching a queued job.
- A payload without `id` or `eventId` is deduplicated on its content. Two identical bodies within ten minutes count as one event.
- The `?account=` value is not part of the signed body. Treat `accountKey` as a hint and check it against the `smartlockId` in the payload before you act on it.
- The signature covers the raw body only, there is no timestamp. After `dedup_ttl` a captured request is accepted again, so make the handling idempotent.
- The dedup uses the default cache store. With the `array` store every request is new.
- `WebhookSubscription` fills `id` from `id`, `callbackUrl` from `callbackUrl` or `url`, and `events` from `webhookFeatures` or `events`. Anything NUKI names differently is only in `->raw`; check `->id !== ''` before you pass it to `webhooks()->unsubscribe()`.

## The bundled UI and its users

- `ui.enabled` is `true` by default and `ui.middleware` is `['web']`. As long as `auth_users.enabled` is `false`, every UI route also runs `Darvis\Nuki\Http\Middleware\AuthorizeUi`, which asks the **`viewNuki` gate** and answers `403` when it says no. The package defines that gate only when the application has not, and its default allows the `local` environment and nothing else. So in production `/nuki` is a `403` until the application defines the gate, for example in `AppServiceProvider::boot()`:

  ```php
  Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
  ```

  The gate gets the user of the default guard, or `null` for a guest. **Keep the parameter nullable**: Laravel skips a gate whose first parameter cannot be null when nobody is signed in, and the answer is then always no. Add `auth` to `ui.middleware` when a guest should go to your login page instead of getting a `403`. Set `NUKI_UI_ENABLED=false` when you only use the facade. In a test, `Gate::define('viewNuki', fn (?User $user = null) => true)` opens the pages; the `testing` environment is not `local`.
- `NUKI_AUTH_USERS_ENABLED=true` registers the `darvis-nuki` guard (provider `darvis-nuki-users`, model `Darvis\Nuki\Models\NukiUser`), adds `auth:darvis-nuki` to the UI routes and loads the login, OTP, register, password reset and sub user pages. Create the first user with `php artisan nuki:user-create`; with `auth_users.email_verification.enabled` (default `true`) that user first has to open the emailed confirmation link, so mail has to work.
- A guest on a UI route is redirected by Laravel's `auth` middleware to the route named `login` of the **host application**, not to `/nuki/login`, and without such a route the answer is `Route [login] not defined.` Send guests of the package pages to `route('nuki.auth.login')` with `$middleware->redirectGuestsTo(fn (Request $request) => $request->is('nuki', 'nuki/*') ? route('nuki.auth.login') : route('login'))` in `bootstrap/app.php`.
- Nothing attaches a `NukiUser` to a `nuki_accounts` row, not the accounts page and not `nuki:user-create`. Until the host application runs `$user->accounts()->syncWithoutDetaching([$account->id => ['role' => 'owner']])`, a package user only has the `default` account and every other key is a `403`. Sub users inherit the accounts of their parent.
- `auth_users.register_enabled` is `false` by default (`NUKI_AUTH_USERS_REGISTER_ENABLED`), so `/nuki/register` answers 404 and the login page has no link to it. A self registered user is a main user (`parent_id` null), and `NukiUser::canAccessSmartlock()` answers `true` for a main user whatever the account or the permission. Leave it off unless every visitor with a mailbox may operate the locks; create users with `php artisan nuki:user-create`.
- With `auth_users.enabled` the `viewNuki` gate is not asked, the `darvis-nuki` guard decides. `nuki.accounts-index` (API tokens) and `nuki.webhooks-index` are then for a main user only: a sub user gets a `403` on the page and on every action.
- A sub user needs a `nuki_user_smartlock` row per lock with `can_lock`, `can_unlock`, `can_view_logs`, `can_manage_auths`, an optional `allowed_from` and `allowed_until` and an `allowed_weekdays` bitmask. The permission names you pass are `lock`, `unlock`, `view_logs` and `manage_auths`. A sub user opens a smartlock page only with an active row for that lock, sees its activity only with `view_logs` and its authorizations (keypad codes included) only with `manage_auths`. On an account key without a `nuki_accounts` row a sub user sees no locks. These rows live in your database only; nothing is written to the NUKI account.
- While `auth_users.otp.enabled` is `true` every login gets an emailed code. The `two_factor_enabled` column and the `--no-2fa` option do not change that.
- The flags `webhook.enabled`, `ui.enabled`, `auth_users.enabled` and `demo.enabled` only count when they are the boolean `true`. `NUKI_WEBHOOK_ENABLED=1` leaves the webhook route unregistered; write `true` in `.env`.
- The pages are Livewire components named `nuki.dashboard`, `nuki.activity-timeline`, `nuki.smartlocks-index`, `nuki.smartlock-show`, `nuki.webhooks-index`, `nuki.oauth-connect`, `nuki.accounts-index` and `nuki.account-switcher`. The account the UI works on is `session('nuki.current_account')`. The `accountKey` and `smartlockId` properties of the pages are `#[Locked]`; for a package user the switcher and every `nuki-account-changed` handler accept `default` or one of `NukiUser::accessibleAccounts()` and answer `403` for anything else.

## Settings

Read them through `Darvis\Nuki\Support\NukiConfig`, never from the config repository directly: `authMethod()`, `tokenResolver()`, `apiToken()` (null when empty), `baseUrl()`, `oauth()`, `oauthTokenStore()`, `http()`, `webhookEnabled()`, `webhookRoute()`, `webhookSecret()` (null when empty), `webhookVerifySignature()`, `webhookSignatureHeader()`, `webhookDedupTtl()`, `uiEnabled()`, `uiPrefix()`, `uiMiddleware()`, `uiLayout()`, `authUsersEnabled()`, `registerEnabled()`, `otpEnabled()` and `demoEnabled()`.

The manager, the HTTP client, the authenticator and the token resolver are singletons that read their settings when they are first resolved. A token, base URL or retry count that changes later in the same request is not picked up.

`NUKI_DEMO=true` installs an `Http::fake()` for `api.nuki.io/*` in the running application and sets the token to `demo-token` when there is none. Every lock, log and code on screen is then made up. Never set it in production.

## Testing

Never call NUKI from a test. Fake the HTTP layer and set the config before the first `Nuki::` call.

```php
use Darvis\Nuki\Exceptions\ApiException;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('nuki.auth', 'token');
    config()->set('nuki.token_resolver', 'config');
    config()->set('nuki.token', 'test-token');
    config()->set('nuki.http.retries', 1);
});

it('unlocks the front door', function () {
    Http::fake(['api.nuki.io/smartlock/17/action' => Http::response('', 204)]);

    Nuki::smartlocks()->unlock(17);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['action'] === 1
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('reports a lock that NUKI does not know', function () {
    Http::fake(['api.nuki.io/*' => Http::response(['detail' => 'not found'], 404)]);

    expect(fn () => Nuki::smartlocks()->find(99))
        ->toThrow(ApiException::class, 'returned HTTP 404');
});
```

- A list response is a JSON array of rows: `Http::response([['smartlockId' => 17, 'name' => 'Front door', 'type' => 4, 'state' => ['state' => 1, 'batteryCharge' => 80]]])`. `smartlockId` is the only required key.
- Leave `http.retries` at `1` in tests. With the default of `3` a faked 500 is sent three times with a pause in between, and `Http::assertSentCount(1)` fails.
- To test your webhook listener through the real route, sign the exact body you send. `post()` with an array sends form fields, so the signature never matches; send the raw JSON:

```php
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::fake([NukiWebhookReceived::class]);

$body = json_encode(['event' => 'DEVICE_LOGS', 'id' => 'evt-1', 'smartlockId' => 17]);

$this->call('POST', route('nuki.webhook', ['account' => 'tenant-42']), server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_NUKI_SIGNATURE' => hash_hmac('sha256', $body, 'test-secret'),
], content: $body)->assertOk()->assertJson(['status' => 'ok']);

Event::assertDispatched(fn (NukiWebhookReceived $event) => $event->accountKey === 'tenant-42');
```

  The route only exists when `nuki.webhook.enabled` was `true` while the application booted, so set `NUKI_WEBHOOK_ENABLED=true` and `NUKI_WEBHOOK_SECRET=test-secret` in `phpunit.xml`, not inside the test. Use a fresh event `id` for every request, or the cache answers `duplicate`.
- To test the listener alone, dispatch the event yourself: `NukiWebhookReceived::dispatch('DEVICE_LOGS', $payload, 'tenant-42')`.
