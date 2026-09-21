---
title: "Troubleshooting"
nav_order: 13
description: "Fix darvis/nuki problems by symptom: the literal error messages for tokens, OAuth, 403 and 404 pages, login, webhooks and migrations, with cause and fix."
---

# Troubleshooting

Every entry is a symptom, the cause and the fix. Messages are quoted literally from the package, so
you can search this page for the text on your screen.

Two things to try first, because they explain many surprises:

- **Cached config.** After a change in `.env` or `config/nuki.php`, run `php artisan config:clear`.
- **A published file that is out of date.** A published `config/nuki.php` or a published view
  (`resources/views/vendor/nuki`) keeps its old content after an update of the package. Compare
  it with the package's version, or delete it and publish again.

## Calls to the NUKI Web API

### `No NUKI API token configured for account [default]. Add it via the NUKI accounts page or set NUKI_API_TOKEN for the default account.`

An `AuthenticationException`. Nothing was sent to NUKI, because there is no token for this account
key.

- The key is `default`: set `NUKI_API_TOKEN` in `.env` and run `php artisan config:clear`.
- The key is something else and `NUKI_TOKEN_RESOLVER=config`: that resolver only knows `default`.
  Use `NUKI_TOKEN_RESOLVER=database` for more than one account.
- `NUKI_TOKEN_RESOLVER=database` (the default): there is no row in `nuki_accounts` with that
  `account_key`, its `is_active` is `false`, or its `api_token` is empty.

### An SQL error that names the table `nuki_accounts`

The migrations did not run. With the default `NUKI_TOKEN_RESOLVER=database` every call reads that
table. Run `php artisan migrate`, or set `NUKI_TOKEN_RESOLVER=config` for a single account.

### `NUKI API GET /smartlock returned HTTP 401: …`

An `ApiException`: NUKI answered with an error. The message has the method, the path, the status
and the first 300 characters of the answer. The exception also carries `->status`, `->body` (the
raw answer as a string) and `->endpoint`.

- `401`: NUKI refused the token. Create a new one in NUKI Web, or with OAuth connect the account
  again.
- `404` on `/smartlock/{id}`: the account does not have that lock. Check the account key you
  passed to `Nuki::as()`.
- `429` or `5xx`: these were already tried again (`http.retries` attempts in total, a fixed pause
  of `http.retry_sleep` milliseconds in between) before the exception reached you. Spread your
  calls, for example one `Nuki::logs()->all()` instead of `forSmartlock()` per lock.

### `Illuminate\Http\Client\ConnectionException`

The server could not reach `api.nuki.io`, also after the retries. This is Laravel's exception and
**not** a `NukiException`, so `catch (NukiException $e)` does not catch it. Catch both:

```php
use Darvis\Nuki\Exceptions\NukiException;
use Illuminate\Http\Client\ConnectionException;

try {
    Nuki::smartlocks()->unlock($smartlockId);
} catch (NukiException|ConnectionException $e) {
    report($e);
}
```

### `unlock()` did not throw, but the door is still locked

`lock()`, `unlock()` and the other actions return nothing. No exception means NUKI accepted the
command, not that the bolt moved. Call `Nuki::smartlocks()->sync($id)` and read
`Nuki::smartlocks()->find($id)` again, or listen for the `DEVICE_STATUS` [webhook](webhooks.md).

### A door got the same command twice

An action is a `POST` and is repeated on a `5xx` answer like any other call. Set `http.retries`
to `1` in `config/nuki.php` when a second command is worse than an error.

### `Unknown nuki.auth mode: …`, `Unknown nuki.token_resolver driver: …`, `Unknown nuki.oauth.token_store driver: …`

A `NukiException` on the first call. The value is not one the package knows: `NUKI_AUTH` is
`token` or `oauth`, `NUKI_TOKEN_RESOLVER` is `config` or `database`, `NUKI_TOKEN_STORE` is `cache`
or `database`.

### A value read back from `nuki_accounts` throws a `DecryptException`

The `api_token` column is encrypted with your `APP_KEY`. Either the `APP_KEY` changed, or the row
was written with a query builder `update()` or `insert()`, which skips the encryption. Write
tokens through the model: `NukiAccount::create([...])` or `$account->update([...])`.

## OAuth

### `No NUKI OAuth token stored for account [...]. Run nuki:oauth-authorize or complete the OAuth flow first.`

Nothing is stored for that account key.

- The account was never connected. Run `php artisan nuki:oauth-authorize --account=<key>` or send
  the user through [your own callback route](nuki-api-authentication.md#the-callback-route-is-yours-to-build).
- `NUKI_TOKEN_STORE=cache` (the default) and the cache was cleared, or the entry expired: a token
  is kept until one day after the access token expires. Use `NUKI_TOKEN_STORE=database` for
  anything that has to last.
- An earlier refresh failed. The package deletes the stored token when NUKI refuses a refresh.

`Nuki::oauth()->token($accountKey)` returns the stored token, or `null`.

### `Failed to refresh NUKI OAuth token: HTTP <status> <body>`

NUKI refused the refresh token, and the stored token was deleted. Check
`NUKI_OAUTH_CLIENT_ID` and `NUKI_OAUTH_CLIENT_SECRET`, then connect the account again.

### `NUKI OAuth token for account [...] is expired and could not be refreshed.`

The token expired and there is no refresh token to renew it with. Connect the account again.

### `Missing NUKI OAuth config key: client_id. Check config/nuki.php and your environment variables.`

Thrown by `authorizationUrl()`, `exchangeCode()` and `refresh()`. Set `NUKI_OAUTH_CLIENT_ID`,
`NUKI_OAUTH_CLIENT_SECRET` and `NUKI_OAUTH_REDIRECT_URL`, and run `php artisan config:clear`.

### `NUKI OAuth code exchange failed: HTTP <status> <body>`

NUKI refused the authorization code. A code works once and for a short time, and
`NUKI_OAUTH_REDIRECT_URL` has to be the same URL you registered with NUKI.

### NUKI redirects to my callback URL and I get a 404

The package has no callback route. You build it; see
[The callback route is yours to build](nuki-api-authentication.md#the-callback-route-is-yours-to-build).

## The bundled pages

### `/nuki` answers 403

Expected in every environment except `local` while `auth_users.enabled` is `false`. The `viewNuki`
gate decides who may open the pages, and the package's default only allows `local`. Define the gate
in `app/Providers/AppServiceProvider.php`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
}
```

Still 403 for a visitor who is not signed in? The parameter has to be nullable (`?User $user`),
otherwise Laravel never asks the gate without a user. See
[Who may open the UI](ui-and-localization.md#who-may-open-the-ui).

With `auth_users.enabled`:

- 403 on `/nuki/accounts`, `/nuki/webhooks`, `/nuki/oauth/connect` or `/nuki/sub-users`: the user
  is a sub user, and those pages are for a main user.
- 403 on a smartlock page: the sub user has no active `nuki_user_smartlock` row for that lock in
  the current account.
- 403 after switching account: the user has no access to that account.

### `/nuki` answers 404

The routes are not registered. `NUKI_UI_ENABLED` has to be the boolean `true` (the default);
`1` or `yes` leaves the pages off. Also check `NUKI_UI_PREFIX`, and run
`php artisan config:clear`.

### `Route [login] not defined.`

With `auth_users.enabled`, a visitor who is not signed in is handled by Laravel's `auth`
middleware, which redirects to the route named `login` of **your** application, not to
`/nuki/login`. Without such a route you get this error. Tell Laravel where guests of the
`darvis-nuki` guard go, in `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn (Request $request) => $request->is('nuki', 'nuki/*')
        ? route('nuki.auth.login')
        : route('login'));
})
```

Use your own prefix in place of `nuki` when you changed `NUKI_UI_PREFIX`.

### `Route [nuki.auth.login] not defined.`

The login routes only exist with `NUKI_AUTH_USERS_ENABLED=true` (the boolean `true`). Set it and
run `php artisan config:clear`.

### `Auth guard [darvis-nuki] is not defined.`

The package adds the guard to the auth config while the application starts. Two causes:

- The service provider was not discovered. Run `php artisan package:discover`, or add
  `Darvis\Nuki\NukiServiceProvider::class` to `bootstrap/providers.php`.
- `NUKI_AUTH_USERS_ENABLED` is not `true`, while your own code uses the guard. Or define the guard
  yourself in `config/auth.php`; the package leaves an existing definition alone:

  ```php
  'guards' => [
      'darvis-nuki' => ['driver' => 'session', 'provider' => 'darvis-nuki-users'],
  ],
  'providers' => [
      'darvis-nuki-users' => ['driver' => 'eloquent', 'model' => \Darvis\Nuki\Models\NukiUser::class],
  ],
  ```

### `/nuki/register` answers 404

Self registration is off by default, because a registered account is a main user who may operate
every lock. Create users with `php artisan nuki:user-create`, or switch it on:

```dotenv
NUKI_AUTH_USERS_REGISTER_ENABLED=true
```

A published `config/nuki.php` from before version 1.2.0 has a hard coded value for
`auth_users.register_enabled`; the variable has no effect until you replace it with
`env('NUKI_AUTH_USERS_REGISTER_ENABLED', false)`.

### `Route [nuki.dashboard] not defined.`

`NUKI_UI_ENABLED` is off while `auth_users` is on. The profile and sub user pages render in
`ui.layout`, and the package's layout links to the pages you switched off. Set `ui.layout` to a
layout of your own, or leave the UI on.

### A page is empty or broken after an update

You published the views earlier (`--tag=nuki-views`) and the package's views changed. Compare
`resources/views/vendor/nuki` with the package, or delete your copy.

## Logging in (package users)

### "The combination of email address and password is incorrect."

The email address is unknown, the password is wrong, or `is_active` is `false` on the user. The
message is the same for all three on purpose.

### The login code never arrives

- Set `NUKI_AUTH_USERS_MAIL_FROM_ADDRESS` when your application has no `mail.from.address`.
- Check that your application can send mail at all, for example with `MAIL_MAILER=log` and a look
  at `storage/logs/laravel.log`.
- "Too many requests. Try again in a few minutes.": more than
  `auth_users.otp.rate_limit.max_per_window` codes (default 5) were requested within
  `window_minutes` (default 15) for this address and IP.

### A user with `two_factor_enabled = false` still gets a login code

That is how it works today. While `auth_users.otp.enabled` is `true`, **every** user gets a code.
The `two_factor_enabled` column and the `--no-2fa` option of `nuki:user-create` are stored and not
read at login. To switch the code off, set `'otp' => ['enabled' => false]` under `auth_users` in
`config/nuki.php`; that is for everyone.

### "The link is invalid or expired. Request a new link."

The password reset link is older than `auth_users.password_reset.token_lifetime_minutes`
(default 60), a newer link was requested (only the last one works), or the user is not active.

### A new user cannot sign in and lands on "Confirm your email address"

`auth_users.email_verification.enabled` is `true` by default. A user without `email_verified_at`
gets a confirmation link and cannot sign in before using it. A user made with
`php artisan nuki:user-create` starts unverified too.

### A main user sees no accounts in the switcher

Nothing attaches a user to an account by itself, not the accounts page and not
`nuki:user-create`. See
[Attach a main user to an account](users-and-permissions.md#attach-a-main-user-to-an-account).

### A sub user sees no smartlocks

Expected at first: a sub user starts with access to nothing. Add a row per lock on
`/nuki/sub-users/{id}` or [in code](users-and-permissions.md#sub-users-in-code).

On an account key without a `nuki_accounts` row, such as `default` with
`NUKI_TOKEN_RESOLVER=config`, a sub user never sees a lock: a permission row points at an account
row.

When the row exists and the lock is still hidden, check:

- `is_active` is `true`.
- `allowed_from` is empty or in the past, `allowed_until` is empty or in the future.
- `allowed_weekdays` is empty or `0`, or today's bit is set (Monday 64, Tuesday 32, Wednesday 16,
  Thursday 8, Friday 4, Saturday 2, Sunday 1).

## Webhooks

### The receiver answers `401 {"error":"invalid signature"}`

- `NUKI_WEBHOOK_SECRET` is empty. Without a secret every request is refused.
- The header is missing or has another name than `webhook.signature_header`
  (default `X-Nuki-Signature`).
- The signature was made over another body. It has to be
  `hash_hmac('sha256', <raw body>, <secret>)`; a proxy that rewrites the JSON breaks it.
- In a test: `post()` with an array sends form fields. See
  [Testing](testing.md#test-a-webhook-listener).

### The receiver answers 404

`NUKI_WEBHOOK_ENABLED` is not the boolean `true`. `1` leaves the route unregistered. Run
`php artisan config:clear` after the change.

### The receiver answers `{"status":"duplicate"}`

The same event `id` arrived within `webhook.dedup_ttl` seconds (default 600), or the body has no
`id` and is identical to an earlier one. No event is dispatched for it.

### My listener runs twice for one event

The default cache store is `array`, which forgets everything between requests. Use a store that
all your workers share. See [Webhooks](webhooks.md#things-that-go-wrong-in-production).

## Demo mode

### The account switcher is empty in demo mode

The locks, logs and codes come from the fixtures; the accounts come from your database. Run:

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

### Demo mode shows 403

Demo mode does not open the pages. Outside `local` you still define the `viewNuki` gate.

## Migrations

### `table "nuki_accounts" already exists`

You published the migrations (`--tag=nuki-migrations`) and renamed the copies. The package still
loads its own files, and a file with another name counts as another migration. Give the copies
their original names back; Laravel then runs your copy instead of the package's file.

## Still stuck

- Read [CHANGELOG.md](https://github.com/ArvidDeJong/nuki/blob/main/CHANGELOG.md); a changed
  default is listed there with what to do.
- Open an [issue](https://github.com/ArvidDeJong/nuki/issues) with the literal message, your
  `NUKI_AUTH`, `NUKI_TOKEN_RESOLVER` and `NUKI_TOKEN_STORE` values and the smallest example that
  shows it. Never paste a token.
