---
title: "Configuration"
nav_order: 4
description: "Every config/nuki.php key and NUKI_* environment variable of darvis/nuki with its default and effect, the database tables and the publish tags."
---

# Configuration reference

Every setting lives in [config/nuki.php](https://github.com/ArvidDeJong/nuki/blob/main/config/nuki.php). Publish it once
with `php artisan vendor:publish --tag=nuki-config` and edit the resulting
`config/nuki.php` in your application. Laravel fills in a top level key you remove from the file
with the package default; inside the `oauth` group keep every key, because that group is used as
a whole.

Each section below lists the environment variable, the config key, the default and what it
does. A key with a `–` in the Env column has no environment variable: change it in your published
`config/nuki.php`. A published file keeps the content it had when you published it, so after an
update of the package compare it with the package's version. Run `php artisan config:clear` after
a change when your config is cached.

The switches `auth_users.enabled`, `auth_users.register_enabled`, `demo.enabled`, `ui.enabled` and
`webhook.enabled` only count when they are the boolean `true`. In `.env` write `true`; `1` or
`yes` leaves the feature off.

The package itself never calls `config('nuki.…')`. Every read goes through
[NukiConfig](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/NukiConfig.php), which holds each default exactly
once. Use it in your own code too, so a renamed key breaks in one place
instead of silently falling back:

```php
use Darvis\Nuki\Support\NukiConfig;

NukiConfig::uiPrefix();        // 'nuki'
NukiConfig::webhookRoute();    // '/nuki/webhook'
NukiConfig::apiToken();        // null when the token is empty or unset
```

## Top-level keys

### `base_url`

- Env: `NUKI_BASE_URL`
- Default: `https://api.nuki.io`

The base URL of the NUKI Web API. You normally never change this.

### `web_url`

- Env: `NUKI_WEB_URL`
- Default: `https://web.nuki.io`

The customer-facing NUKI Web portal. The bundled UI deep-links to this URL
when guiding a user to generate a personal API token.

### `auth`

- Env: `NUKI_AUTH`
- Default: `token`
- Allowed: `token` | `oauth`

Selects the API authentication strategy. See
[NUKI API authentication](nuki-api-authentication.md) for the trade-offs and
flow.

### `token_resolver`

- Env: `NUKI_TOKEN_RESOLVER`
- Default: `database`
- Allowed: `config` | `database`

When `auth = token`, controls how the package looks up the bearer token per
account key.

- `config` — single-account mode. Returns `nuki.token` for the account key `default` and
  nothing for any other key, so `Nuki::as('something-else')` throws an
  `AuthenticationException`. Never touches the database.
- `database` — multi-account mode. Looks up the (encrypted) `api_token`
  column on [nuki_accounts](#nuki_accounts) by `account_key`, among the rows with `is_active`
  true. Falls back to `nuki.token` for the literal `default` key when no row matches. Needs
  `php artisan migrate`, because every call runs this query.

### `token`

- Env: `NUKI_API_TOKEN`
- Default: `null`

The personal API token used by the `config` resolver and as the `default`
fallback for the `database` resolver. Generate it in the NUKI Web account that owns the locks,
under *API*.

## `auth_users.*` — Package user authentication

Optional self-contained login system for the bundled `/nuki/*` pages. Enabling
this registers a `darvis-nuki` Laravel auth guard at runtime, gates all UI
routes behind it and adds login, OTP, password reset, email verification and (off by default)
registration pages. See [Users and permissions](users-and-permissions.md) for the data
model and [Auth routes](auth-routes.md) for the registered URLs.

| Key | Env | Default | Effect |
|---|---|---|---|
| `auth_users.enabled` | `NUKI_AUTH_USERS_ENABLED` | `false` | Master switch. When `true`, [AuthConfigRegistrar](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/Users/AuthConfigRegistrar.php) registers the guard and provider; [routes/auth.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/auth.php) is loaded; UI routes get `auth:darvis-nuki` appended. |
| `auth_users.mail.from.address` | `NUKI_AUTH_USERS_MAIL_FROM_ADDRESS` | `null` | From-address for OTP, password-reset and email-verification mails. When empty, your application's `mail.from` is used. |
| `auth_users.mail.from.name` | `NUKI_AUTH_USERS_MAIL_FROM_NAME` | `null` | From-name for the same mails. |
| `auth_users.email_verification.enabled` | – | `true` | When `true`, a user without `email_verified_at` cannot sign in: a signed link is mailed and login is blocked until the address is confirmed. That includes a user made with `nuki:user-create`. Set to `false` to switch verification off (a registration then signs in directly). |
| `auth_users.email_verification.link_lifetime_minutes` | – | `60` | Lifetime of the signed verification link. |
| `auth_users.otp.enabled` | – | `true` | Global switch for the emailed login code. When `true`, **every** user gets a code at login; the per user `two_factor_enabled` column and the `--no-2fa` option are stored and not read. Set to `false` to skip the code for everyone. |
| `auth_users.otp.expiry_minutes` | – | `5` | OTP code lifetime. |
| `auth_users.otp.length` | – | `6` | Number of digits in the OTP code. |
| `auth_users.otp.rate_limit.max_per_window` | – | `5` | Maximum number of codes sent per email address and IP, and of code attempts per user, per window. |
| `auth_users.otp.rate_limit.window_minutes` | – | `15` | Length of the rate-limit window. |
| `auth_users.password_reset.enabled` | – | `true` | When `false`, the forgot-password and reset routes return 404. |
| `auth_users.password_reset.token_lifetime_minutes` | – | `60` | Reset-link lifetime. |
| `auth_users.redirect_after_login` | – | `/nuki` | Where the login flow sends the user after success. |
| `auth_users.redirect_after_logout` | – | `/nuki/login` | Where logout sends the user. |
| `auth_users.register_enabled` | `NUKI_AUTH_USERS_REGISTER_ENABLED` | `false` | Self registration. Off by default, because whoever registers becomes a main user, and a main user may operate every lock. While it is off `/nuki/register` answers 404 and the login page has no link to it. Create users with `php artisan nuki:user-create` instead. |
| `auth_users.routes.middleware` | – | `['web']` | Middleware group for the auth routes. `SetLocale` is always appended automatically. |
| `auth_users.routes.prefix` | – | `nuki` | URL prefix; shared with the rest of the UI. |

## `oauth.*` — OAuth 2.0

Used when `auth = oauth`. Register your application on the
[NUKI Developer Portal](https://developer.nuki.io/) to obtain credentials and
a redirect URL.

| Key | Env | Default | Effect |
|---|---|---|---|
| `oauth.authorize_url` | `NUKI_OAUTH_AUTHORIZE_URL` | `https://api.nuki.io/oauth/authorize` | Authorization endpoint. |
| `oauth.token_url` | `NUKI_OAUTH_TOKEN_URL` | `https://api.nuki.io/oauth/token` | Token endpoint. |
| `oauth.client_id` | `NUKI_OAUTH_CLIENT_ID` | – | Your client id. |
| `oauth.client_secret` | `NUKI_OAUTH_CLIENT_SECRET` | – | Your client secret. |
| `oauth.redirect_url` | `NUKI_OAUTH_REDIRECT_URL` | – | The URL NUKI redirects to after consent, the same one you registered with NUKI. The package has no route for it; [you build it](nuki-api-authentication.md#the-callback-route-is-yours-to-build). |
| `oauth.scopes` | – | `['account', 'notification', 'smartlock', 'smartlock.action', 'smartlock.auth', 'smartlock.readOnly']` | Scopes requested at authorization time. |
| `oauth.token_store` | `NUKI_TOKEN_STORE` | `cache` | Where issued tokens are kept: `cache` (Laravel cache; gone after `php artisan cache:clear`, and an entry expires one day after the access token does) or `database` (the [nuki_oauth_tokens](#nuki_oauth_tokens) table, encrypted). |
| `oauth.cache_store` | `NUKI_TOKEN_CACHE_STORE` | `null` | Name of the cache store when using the cache driver. `null` = default. |
| `oauth.cache_prefix` | – | `nuki:oauth:` | Key prefix for the cache driver. |

## `webhook.*` — Webhook receiver

Disabled by default. When enabled, the package registers a single POST route
that accepts NUKI callbacks, verifies the HMAC signature and dispatches the
[NukiWebhookReceived](https://github.com/ArvidDeJong/nuki/blob/main/src/Events/NukiWebhookReceived.php) event. See
[Webhooks](webhooks.md) for the full flow.

| Key | Env | Default | Effect |
|---|---|---|---|
| `webhook.enabled` | `NUKI_WEBHOOK_ENABLED` | `false` | Master switch. When `true`, [routes/webhooks.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/webhooks.php) is loaded. |
| `webhook.route` | `NUKI_WEBHOOK_ROUTE` | `/nuki/webhook` | URL path of the callback. |
| `webhook.middleware` | – | `['api']` | Middleware of the route. The `api` group has no session and no CSRF check, which a request from another server needs. |
| `webhook.secret` | `NUKI_WEBHOOK_SECRET` | `null` | HMAC-SHA256 shared secret. Without it every request is rejected with `401`. |
| `webhook.signature_header` | `NUKI_WEBHOOK_SIGNATURE_HEADER` | `X-Nuki-Signature` | Header containing the signature. |
| `webhook.verify_signature` | `NUKI_WEBHOOK_VERIFY_SIGNATURE` | `true` | Set to `false` to accept unsigned requests, for example behind a gateway that already authenticates the caller. Only an explicit `false` switches the check off. |
| `webhook.dedup_ttl` | – | `600` | Seconds the dedup cache key (`nuki:webhook:{eventId}`) lives. |

## `ui.*` — Bundled Livewire UI

| Key | Env | Default | Effect |
|---|---|---|---|
| `ui.enabled` | `NUKI_UI_ENABLED` | `true` | Master switch. When `false`, none of `/nuki/*` routes are registered. |
| `ui.brand` | `NUKI_UI_BRAND` | `NUKI` | Displayed in the top navigation and on the auth pages, and used as the sender name of the package's mail when `auth_users.mail.from.name` is empty. |
| `ui.auth_panel.enabled` | `NUKI_UI_AUTH_PANEL` | `true` | Show the right-hand brand panel on the auth layout (`lg+`). When `false`, the form fills the full width. |
| `ui.default_locale` | `NUKI_DEFAULT_LOCALE` | `en` | Fallback locale; see [UI and localization](ui-and-localization.md). |
| `ui.footer.links` | – | `[]` | Array of `['label' => …, 'url' => …]` entries shown next to the copyright on the auth pages. Empty = no links. |
| `ui.layout` | – | `nuki::layouts.app` | Blade layout the pages extend. Override to wrap the UI in your own chrome. |
| `ui.locales` | – | `['de' => 'Deutsch', 'en' => 'English', 'es' => 'Español', 'nl' => 'Nederlands']` | The languages the UI accepts, as code and label. A package user picks one on the profile page. |
| `ui.logo.light` | `NUKI_UI_LOGO_LIGHT` | `null` | Path/URL to an SVG/PNG shown above the auth form in light mode. When both light/dark are empty, a neutral lock icon plus `ui.brand` is rendered. |
| `ui.logo.dark` | `NUKI_UI_LOGO_DARK` | `null` | Dark-mode variant of the logo. Swapped via `dark:hidden` / `hidden dark:block`. |
| `ui.middleware` | – | `['web']` | Middleware group for UI routes. `AuthorizeUi`, `SetLocale` and (when `auth_users.enabled`) `auth:darvis-nuki` are appended automatically. While `auth_users.enabled` is `false`, `AuthorizeUi` answers `403` unless the `viewNuki` gate allows the visitor; the default gate only allows the `local` environment. See [Who may open the UI](ui-and-localization.md#who-may-open-the-ui). |
| `ui.prefix` | `NUKI_UI_PREFIX` | `nuki` | URL prefix for all UI routes. |
| `ui.tagline` | `NUKI_UI_TAGLINE` | `null` | Optional one-line tagline shown on the auth brand panel. Falls back to the localised `nuki::nuki.auth.panel.subheading` string. |

## `http.*` — Outbound HTTP tuning

Applies to every call made through [HttpClient](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/HttpClient.php).

| Key | Default | Effect |
|---|---|---|
| `http.timeout` | `10` | Seconds before a single request times out. |
| `http.retries` | `3` | The **total** number of attempts for one call, the first one included. A connection error, an HTTP 429 and a 5xx are tried again; any other 4xx is not. `1` means no retry. |
| `http.retry_sleep` | `200` | Pause in milliseconds between two attempts. The pause is fixed; it does not grow. |

A lock action is a `POST` and is repeated on a 5xx like any other call. Set `http.retries` to `1`
when a second command is worse than an error. These keys have no environment variable.

## `demo.*` — Demo mode

| Key | Env | Default | Effect |
|---|---|---|---|
| `demo.enabled` | `NUKI_DEMO` | `false` | When `true`, every call to `api.nuki.io` is intercepted by [DemoFixtures](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/DemoFixtures.php) and answered with canned data. The package also stubs `nuki.token` to `demo-token` so the bearer authenticator stops complaining. **Never enable in production.** See [Demo mode](demo-mode.md). |

## Swappable contracts

Three strategies are selected by config and bound in
[NukiServiceProvider::register()](https://github.com/ArvidDeJong/nuki/blob/main/src/NukiServiceProvider.php). They are the
**only** place strategies are picked — do not instantiate alternatives anywhere
else.

| Contract | Config key | Drivers |
|---|---|---|
| [Contracts\TokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/TokenStore.php) | `oauth.token_store` | `cache` → [CacheTokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/CacheTokenStore.php), `database` → [DatabaseTokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/DatabaseTokenStore.php) |
| [Contracts\ApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/ApiTokenResolver.php) | `token_resolver` | `config` → [ConfigApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/ConfigApiTokenResolver.php), `database` → [DatabaseApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/DatabaseApiTokenResolver.php) |
| [Contracts\Authenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/Authenticator.php) | `auth` | `token` → [TokenAuthenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/TokenAuthenticator.php), `oauth` → [OAuthAuthenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/OAuthAuthenticator.php) |

To use your own implementation, bind it in the `register()` method of
`app/Providers/AppServiceProvider.php`. Application providers run after package providers, so
your binding replaces the package's:

```php
$this->app->singleton(\Darvis\Nuki\Contracts\TokenStore::class, \App\Nuki\MyTokenStore::class);
```

## Database tables

`php artisan migrate` creates all seven tables, whichever features you use; the package loads its
migrations from its own folder. To change one, publish them with
`php artisan vendor:publish --tag=nuki-migrations` and keep the file names: Laravel then runs your
copy instead of the package's file.

| Table | Created by | Read and written when |
|---|---|---|
| <a id="nuki_accounts"></a>`nuki_accounts` | [2026_05_11_000100_create_nuki_accounts_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_11_000100_create_nuki_accounts_table.php) | `token_resolver = database`. Columns: `account_key` (unique), `name`, `api_token` (text, encrypted), `description`, `is_active`. |
| <a id="nuki_oauth_tokens"></a>`nuki_oauth_tokens` | [2026_05_11_000000_create_nuki_oauth_tokens_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_11_000000_create_nuki_oauth_tokens_table.php) | `oauth.token_store = database`. Columns: `account_key` (unique), `access_token` (text, encrypted), `refresh_token` (text, encrypted, nullable), `expires_at`, `token_type`, `scope`. |
| `nuki_users` | [2026_05_12_000000_create_nuki_users_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000000_create_nuki_users_table.php) | `auth_users.enabled = true`. Columns: `parent_id` (self-FK, nullable), `name`, `email` (unique), `email_verified_at` (nullable; set by the email-verification flow), `password`, `two_factor_enabled`, `is_active`, `last_login_at`, `locale`. |
| `nuki_user_otp_codes` | [2026_05_12_000100_create_nuki_user_otp_codes_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000100_create_nuki_user_otp_codes_table.php) | `auth_users.enabled = true`. Columns: `nuki_user_id`, `code_hash`, `purpose`, `expires_at`, `consumed_at`, `ip`, `user_agent`. |
| `nuki_password_resets` | [2026_05_12_000200_create_nuki_password_resets_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000200_create_nuki_password_resets_table.php) | `auth_users.enabled = true`. Columns: `email` (primary key), `token_hash`, `created_at`. |
| `nuki_user_account` | [2026_05_12_000300_create_nuki_user_account_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000300_create_nuki_user_account_table.php) | `auth_users.enabled = true`. Pivot. Columns: `nuki_user_id`, `nuki_account_id`, `role` (default `member`). Unique on the pair. |
| `nuki_user_smartlock` | [2026_05_12_000400_create_nuki_user_smartlock_table](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000400_create_nuki_user_smartlock_table.php) | `auth_users.enabled = true`. Pivot with permissions. Columns: `nuki_user_id`, `nuki_account_id`, `smartlock_id`, `can_lock`, `can_unlock`, `can_view_logs`, `can_manage_auths`, `allowed_from`, `allowed_until`, `allowed_weekdays` (tinyint, [weekday bitmask](users-and-permissions.md#weekday-bitmask)), `is_active`. |

## Publish tags

| Tag | What it copies |
|---|---|
| `nuki-config` | `config/nuki.php` → `config/nuki.php` |
| `nuki-migrations` | `database/migrations/*` → `database/migrations/` |
| `nuki-views` | `resources/views/*` → `resources/views/vendor/nuki/` |
| `nuki-lang` | `lang/*` → `lang/vendor/nuki/` |
| `nuki-seeders` | `src/Database/Seeders/NukiDemoSeeder.php` → `database/seeders/NukiDemoSeeder.php` |
