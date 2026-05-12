# Troubleshooting

[← Documentation index](README.md)

Common errors with the root cause and the fix.

## `AuthenticationException: No NUKI API token configured for account [default]`

Thrown by [TokenAuthenticator](../src/Auth/TokenAuthenticator.php) when the
resolver returns an empty string.

- **`token_resolver=config`** — set `NUKI_API_TOKEN` in `.env`.
- **`token_resolver=database`** — make sure a row exists in `nuki_accounts`
  with the `account_key` you are using. The literal `default` key falls
  back to `NUKI_API_TOKEN` as a convenience.
- **OAuth mode but you wrote `NUKI_AUTH=token`** — see next item.

## `AuthenticationException: No NUKI OAuth token stored for account [...]`

Either the OAuth flow never completed, or the token was cleared.

- Run `php artisan nuki:oauth-authorize --account=<key>` (or the in-app
  flow — see [NUKI API authentication](nuki-api-authentication.md#in-app-flow)).
- Check the `nuki_oauth_tokens` table (`token_store=database`) or the
  configured cache store (`token_store=cache`). Both are accessible via
  `Nuki::oauth()->token($accountKey)`.
- A 401 from NUKI clears the token in the authenticator; you'll need to
  re-authorize.

## `AuthenticationException: NUKI OAuth token … expired and could not be refreshed`

The refresh attempt itself failed (revoked credentials, NUKI-side error,
no `refresh_token` issued). Run `nuki:oauth-authorize` again. If this keeps
happening, double-check `NUKI_OAUTH_CLIENT_SECRET` — a typo here looks
identical to expiration from the outside.

## `ApiException: HTTP 401 Unauthorized`

NUKI rejected the credentials.

- Token mode: the token was deleted on `web.nuki.io` or never had the right
  permissions. Regenerate it.
- OAuth mode: scopes mismatch. Check `nuki.oauth.scopes` against what NUKI
  granted. By default the package requests
  `account notification smartlock smartlock.readOnly smartlock.action smartlock.auth`.

## `ApiException: HTTP 429 Too Many Requests` (after retries)

`HttpClient` already retries on 429 with exponential backoff
(`http.retries`, `http.retry_sleep`). If you still see this, either:

- A real rate-limit cap was hit. Slow the caller down or batch operations
  (e.g. use `Nuki::logs()->all()` once instead of `forSmartlock()` N times).
- Multiple processes are hammering NUKI in parallel — coordinate via a queue.

## `Route [nuki.auth.login] not defined`

You're trying to redirect to the auth UI but haven't enabled the feature.
Set `NUKI_AUTH_USERS_ENABLED=true` and run `php artisan migrate`. The
service provider only loads [routes/auth.php](../routes/auth.php) when this
flag is on.

## `Auth guard [darvis-nuki] is not defined`

Two possible causes.

- **The provider didn't run** — usually because the package's service
  provider isn't discovered. Verify `vendor/composer/installed.json` lists
  `darvis/nuki` with `extra.laravel.providers` containing
  `Darvis\Nuki\NukiServiceProvider`. If not, register it manually in
  `bootstrap/providers.php` (Laravel 11+) or `config/app.php`.
- **Another provider read `auth.guards` before `NukiServiceProvider::register()`**.
  The runtime merge happens in `register()`, but some packages cache the
  config earlier. Pin the values in your own `config/auth.php`:

  ```php
  'guards' => [
      'darvis-nuki' => ['driver' => 'session', 'provider' => 'darvis-nuki-users'],
  ],
  'providers' => [
      'darvis-nuki-users' => ['driver' => 'eloquent', 'model' => \Darvis\Nuki\Models\NukiUser::class],
  ],
  ```

## OTP mail doesn't arrive

- `NUKI_AUTH_USERS_MAIL_FROM_ADDRESS` is required if your host
  `mail.from.address` is empty.
- Test your Laravel mail driver in isolation: `Mail::raw('test', fn ($m) => $m->to(...));`.
- Check the `nuki_user_otp_codes` table — if a row exists with `expires_at`
  in the past five minutes but the mail never arrived, the queue or driver
  is the culprit, not this package.
- Globally: `nuki.auth_users.otp.enabled = false` skips OTP entirely. Per
  user: `two_factor_enabled = false` on `nuki_users` does the same. Either
  takes precedence over the other.

## Password-reset link says "invalid token"

- The token expired —
  `auth_users.password_reset.token_lifetime_minutes` (default 60).
- The user account was deactivated (`is_active = false`).
- The email parameter on the URL is URL-encoded incorrectly. The package
  generates the link with `route()`, so this only happens if you typed the
  URL by hand.

## Webhook receiver returns 401

Signature mismatch.

- Check that `NUKI_WEBHOOK_SECRET` matches exactly what NUKI was told.
  Leading/trailing whitespace breaks `hash_equals`.
- The signature is `hash_hmac('sha256', $rawBody, $secret)`. If a proxy or
  middleware rewrites the body (CORS, body parsers that normalise JSON),
  the recomputed HMAC won't match. Keep the route on the `api` middleware
  group only.
- Header name mismatch — `NUKI_WEBHOOK_SIGNATURE_HEADER` defaults to
  `X-Nuki-Signature`. If NUKI uses a different header in your tier, set the
  env var.

## Webhook receiver returns "duplicate" for everything

Your cache driver is `array` — every request is a fresh process so the
`Cache::add` dedup never fires (it always succeeds the second time too, but
sometimes a different worker processes the same event). Use `redis`,
`database`, `memcached` or `file`. See
[Webhooks → Cache driver matters for dedup](webhooks.md#5-cache-driver-matters-for-dedup).

## Sub user sees no smartlocks

Expected — sub users start with **zero** smartlock access. Add rows in
`nuki_user_smartlock` (use the bundled `/nuki/sub-users/{id}` UI or insert
directly; see
[Users and permissions → Sub-users — programmatic](users-and-permissions.md#sub-users--programmatic)).

If the rows exist but the lock is still hidden, check:

- `is_active = true`.
- `allowed_from` is null or in the past.
- `allowed_until` is null or in the future.
- `allowed_weekdays` is null/0, **or** today's bit is set
  (e.g. Wednesday = bit 16; check
  [WeekdayBitmask](../src/Support/WeekdayBitmask.php)).

## Demo mode UI is empty

You didn't run the seeder. The Livewire pages query `nuki_accounts` for the
`AccountSwitcher`. Run:

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

If you only want to see the smartlocks index (no switcher), you can skip
this — `Nuki::smartlocks()->all()` still returns the four canned locks.

## Migrations fail with "table … already exists"

Auto-loaded migrations and published migrations are running side by side.
Either:

- You published with `--tag=nuki-migrations` and the copies in
  `database/migrations/` are picked up *before* the package versions
  (Laravel runs in alphabetical order). Delete the package versions from
  your app, or rename the published copies to come first.
- Two installs of the package are active (rare, but possible during a
  monorepo migration). Check `composer show -i | grep nuki`.

## Pint complains in CI

Run `composer lint` locally to apply the standard. Don't add suppressions
for package-shipped files — the CI matrix runs Pint on every PHP/Laravel
combo, and divergence will cause flakes.

## Still stuck

- Check [CHANGELOG.md](../CHANGELOG.md) — the symptom may be a known
  regression with a fix in a newer minor.
- Read the file mentioned in the exception's trace — the source is the
  source of truth, this documentation can lag.
- Open an issue with the exact exception, your `nuki.auth` / `token_resolver`
  / `oauth.token_store` config and a minimal reproduction.
