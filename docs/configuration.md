# Configuration reference

[← Documentation index](README.md)

Every setting lives in [config/nuki.php](../config/nuki.php). Publish it once
with `php artisan vendor:publish --tag=nuki-config` and edit the resulting
`config/nuki.php` in your application — the package merges in defaults for
anything you leave out.

Each section below lists the env var, the config key, the default and what it
does. Environment variables only override the config when the published file
uses `env('…')`, so unpublished customisations stick.

## Top-level keys

### `base_url`

- Env: `NUKI_BASE_URL`
- Default: `https://api.nuki.io`

The base URL of the NUKI Web API. You normally never change this; it exists so
tests can point at a local mock.

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

- `config` — single-account mode. Returns `nuki.token` for every account key.
- `database` — multi-account mode. Looks up the (encrypted) `api_token`
  column on [nuki_accounts](#nuki_accounts) by `account_key`. Falls back to
  `nuki.token` for the literal `default` key when no row matches.

### `token`

- Env: `NUKI_API_TOKEN`
- Default: `null`

The personal API token used by the `config` resolver and as the `default`
fallback for the `database` resolver. Generate one per customer in their
NUKI Web account under *API*.

## `auth_users.*` — Package user authentication

Optional self-contained login system for the bundled `/nuki/*` pages. Enabling
this registers a `darvis-nuki` Laravel auth guard at runtime, gates all UI
routes behind it and publishes login / OTP / register / password-reset Livewire
screens. See [Users and permissions](users-and-permissions.md) for the data
model and [Auth routes](auth-routes.md) for the registered URLs.

| Key | Env | Default | Effect |
|---|---|---|---|
| `auth_users.enabled` | `NUKI_AUTH_USERS_ENABLED` | `false` | Master switch. When `true`, [AuthConfigRegistrar](../src/Auth/Users/AuthConfigRegistrar.php) registers the guard and provider; [routes/auth.php](../routes/auth.php) is loaded; UI routes get `auth:darvis-nuki` appended. |
| `auth_users.mail.from.address` | `NUKI_AUTH_USERS_MAIL_FROM_ADDRESS` | `null` | From-address for OTP, password-reset and email-verification mails. Falls back to Laravel's `mail.from.address`. |
| `auth_users.mail.from.name` | `NUKI_AUTH_USERS_MAIL_FROM_NAME` | `null` | From-name for the same mails. |
| `auth_users.email_verification.enabled` | – | `true` | When `true`, new registrations are not auto-logged-in: a signed link is mailed and login is blocked until the address is confirmed. Set to `false` to disable verification entirely (registration logs in directly). |
| `auth_users.email_verification.link_lifetime_minutes` | – | `60` | Lifetime of the signed verification link. |
| `auth_users.otp.enabled` | – | `true` | Global toggle for email OTP. When `true`, OTP is mandatory for **every** user on login; the per-user `two_factor_enabled` column is no longer consulted. Set to `false` to skip OTP for everyone. |
| `auth_users.otp.expiry_minutes` | – | `5` | OTP code lifetime. |
| `auth_users.otp.length` | – | `6` | Number of digits in the OTP code. |
| `auth_users.otp.rate_limit.max_per_window` | – | `5` | Maximum OTP sends/attempts per window. |
| `auth_users.otp.rate_limit.window_minutes` | – | `15` | Length of the rate-limit window. |
| `auth_users.password_reset.enabled` | – | `true` | When `false`, the forgot-password and reset routes return 404. |
| `auth_users.password_reset.token_lifetime_minutes` | – | `60` | Reset-link lifetime. |
| `auth_users.redirect_after_login` | – | `/nuki` | Where the login flow sends the user after success. |
| `auth_users.redirect_after_logout` | – | `/nuki/login` | Where logout sends the user. |
| `auth_users.register_enabled` | – | `true` | When `false`, the `/nuki/register` route returns 404. |
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
| `oauth.redirect_url` | `NUKI_OAUTH_REDIRECT_URL` | – | The exact URL NUKI redirects to after consent. Must match the value registered on the developer portal. |
| `oauth.scopes` | – | `['account', 'notification', 'smartlock', 'smartlock.readOnly', 'smartlock.action', 'smartlock.auth']` | Scopes requested at authorization time. |
| `oauth.token_store` | `NUKI_TOKEN_STORE` | `cache` | Where issued tokens are persisted: `cache` (Laravel cache) or `database` (dedicated [nuki_oauth_tokens](#nuki_oauth_tokens) table). |
| `oauth.cache_store` | `NUKI_TOKEN_CACHE_STORE` | `null` | Name of the cache store when using the cache driver. `null` = default. |
| `oauth.cache_prefix` | – | `nuki:oauth:` | Key prefix for the cache driver. |

## `webhook.*` — Webhook receiver

Disabled by default. When enabled, the package registers a single POST route
that accepts NUKI callbacks, verifies the HMAC signature and dispatches the
[NukiWebhookReceived](../src/Events/NukiWebhookReceived.php) event. See
[Webhooks](webhooks.md) for the full flow.

| Key | Env | Default | Effect |
|---|---|---|---|
| `webhook.enabled` | `NUKI_WEBHOOK_ENABLED` | `false` | Master switch. When `true`, [routes/webhooks.php](../routes/webhooks.php) is loaded. |
| `webhook.route` | `NUKI_WEBHOOK_ROUTE` | `/nuki/webhook` | URL path of the callback. |
| `webhook.middleware` | – | `['api']` | Middleware group. Skip CSRF and session — webhooks are external POSTs. |
| `webhook.secret` | `NUKI_WEBHOOK_SECRET` | `null` | HMAC-SHA256 shared secret. When empty, signature verification is skipped — only acceptable for local development. |
| `webhook.signature_header` | `NUKI_WEBHOOK_SIGNATURE_HEADER` | `X-Nuki-Signature` | Header containing the signature. |
| `webhook.dedup_ttl` | – | `600` | Seconds the dedup cache key (`nuki:webhook:{eventId}`) lives. |

## `ui.*` — Bundled Livewire UI

| Key | Env | Default | Effect |
|---|---|---|---|
| `ui.enabled` | `NUKI_UI_ENABLED` | `true` | Master switch. When `false`, none of `/nuki/*` routes are registered. |
| `ui.brand` | `NUKI_UI_BRAND` | `NUKI` | Displayed in the top navigation and on the auth pages. |
| `ui.auth_panel.enabled` | `NUKI_UI_AUTH_PANEL` | `true` | Show the right-hand brand panel on the auth layout (`lg+`). When `false`, the form fills the full width. |
| `ui.default_locale` | `NUKI_DEFAULT_LOCALE` | `en` | Fallback locale; see [UI and localization](ui-and-localization.md). |
| `ui.footer.links` | – | `[]` | Array of `['label' => …, 'url' => …]` entries shown next to the copyright on the auth pages. Empty = no links. |
| `ui.layout` | – | `nuki::layouts.app` | Blade layout the pages extend. Override to wrap the UI in your own chrome. |
| `ui.locales` | – | `['en' => 'English', 'nl' => 'Nederlands', 'de' => 'Deutsch', 'es' => 'Español']` | Languages shown in the locale switcher. |
| `ui.logo.light` | `NUKI_UI_LOGO_LIGHT` | `null` | Path/URL to an SVG/PNG shown above the auth form in light mode. When both light/dark are empty, a neutral lock icon plus `ui.brand` is rendered. |
| `ui.logo.dark` | `NUKI_UI_LOGO_DARK` | `null` | Dark-mode variant of the logo. Swapped via `dark:hidden` / `hidden dark:block`. |
| `ui.middleware` | – | `['web']` | Middleware group for UI routes. `SetLocale` and (when `auth_users.enabled`) `auth:darvis-nuki` are appended automatically. |
| `ui.prefix` | `NUKI_UI_PREFIX` | `nuki` | URL prefix for all UI routes. |
| `ui.tagline` | `NUKI_UI_TAGLINE` | `null` | Optional one-line tagline shown on the auth brand panel. Falls back to the localised `nuki::nuki.auth.panel.subheading` string. |

## `http.*` — Outbound HTTP tuning

Applies to every call made through [HttpClient](../src/Http/HttpClient.php).

| Key | Default | Effect |
|---|---|---|
| `http.timeout` | `10` | Seconds before a single request times out. |
| `http.retries` | `3` | Number of retries on connection errors, HTTP 429 and 5xx responses. |
| `http.retry_sleep` | `200` | Base sleep in milliseconds; doubled per attempt (exponential backoff). |

## `demo.*` — Demo mode

| Key | Env | Default | Effect |
|---|---|---|---|
| `demo.enabled` | `NUKI_DEMO` | `false` | When `true`, every call to `api.nuki.io` is intercepted by [DemoFixtures](../src/Support/DemoFixtures.php) and answered with canned data. The package also stubs `nuki.token` to `demo-token` so the bearer authenticator stops complaining. **Never enable in production.** See [Demo mode](demo-mode.md). |

## Swappable contracts

Three strategies are selected by config and bound in
[NukiServiceProvider::register()](../src/NukiServiceProvider.php). They are the
**only** place strategies are picked — do not instantiate alternatives anywhere
else.

| Contract | Config key | Drivers |
|---|---|---|
| [Contracts\TokenStore](../src/Contracts/TokenStore.php) | `oauth.token_store` | `cache` → [CacheTokenStore](../src/Auth/CacheTokenStore.php), `database` → [DatabaseTokenStore](../src/Auth/DatabaseTokenStore.php) |
| [Contracts\ApiTokenResolver](../src/Contracts/ApiTokenResolver.php) | `token_resolver` | `config` → [ConfigApiTokenResolver](../src/Auth/ConfigApiTokenResolver.php), `database` → [DatabaseApiTokenResolver](../src/Auth/DatabaseApiTokenResolver.php) |
| [Contracts\Authenticator](../src/Contracts/Authenticator.php) | `auth` | `token` → [TokenAuthenticator](../src/Auth/TokenAuthenticator.php), `oauth` → [OAuthAuthenticator](../src/Auth/OAuthAuthenticator.php) |

To add your own driver, bind it in a service provider that runs **after**
`NukiServiceProvider`, e.g.:

```php
$this->app->singleton(\Darvis\Nuki\Contracts\TokenStore::class, MyTokenStore::class);
```

## Database tables

All migrations are auto-loaded from the package path. Customers who need to
edit them can `php artisan vendor:publish --tag=nuki-migrations` once, after
which the package picks up the copies in `database/migrations/`.

| Table | Created by | Used when |
|---|---|---|
| <a id="nuki_accounts"></a>`nuki_accounts` | [2026_05_11_000100_create_nuki_accounts_table](../database/migrations/2026_05_11_000100_create_nuki_accounts_table.php) | `token_resolver = database`. Columns: `account_key` (unique), `name`, `api_token` (text, encrypted), `description`, `is_active`. |
| <a id="nuki_oauth_tokens"></a>`nuki_oauth_tokens` | [2026_05_11_000000_create_nuki_oauth_tokens_table](../database/migrations/2026_05_11_000000_create_nuki_oauth_tokens_table.php) | `oauth.token_store = database`. Columns: `account_key` (unique), `access_token` (text), `refresh_token` (text, nullable), `expires_at`, `token_type`, `scope`. |
| `nuki_users` | [2026_05_12_000000_create_nuki_users_table](../database/migrations/2026_05_12_000000_create_nuki_users_table.php) | `auth_users.enabled = true`. Columns: `parent_id` (self-FK, nullable), `name`, `email` (unique), `email_verified_at` (nullable; set by the email-verification flow), `password`, `two_factor_enabled`, `is_active`, `last_login_at`, `locale`. |
| `nuki_user_otp_codes` | [2026_05_12_000100_create_nuki_user_otp_codes_table](../database/migrations/2026_05_12_000100_create_nuki_user_otp_codes_table.php) | `auth_users.enabled = true`. Columns: `nuki_user_id`, `code_hash`, `purpose`, `expires_at`, `consumed_at`, `ip`, `user_agent`. |
| `nuki_password_resets` | [2026_05_12_000200_create_nuki_password_resets_table](../database/migrations/2026_05_12_000200_create_nuki_password_resets_table.php) | `auth_users.enabled = true`. Columns: `email` (primary key), `token_hash`, `created_at`. |
| `nuki_user_account` | [2026_05_12_000300_create_nuki_user_account_table](../database/migrations/2026_05_12_000300_create_nuki_user_account_table.php) | `auth_users.enabled = true`. Pivot. Columns: `nuki_user_id`, `nuki_account_id`, `role` (default `member`). Unique on the pair. |
| `nuki_user_smartlock` | [2026_05_12_000400_create_nuki_user_smartlock_table](../database/migrations/2026_05_12_000400_create_nuki_user_smartlock_table.php) | `auth_users.enabled = true`. Pivot with permissions. Columns: `nuki_user_id`, `nuki_account_id`, `smartlock_id`, `can_lock`, `can_unlock`, `can_view_logs`, `can_manage_auths`, `allowed_from`, `allowed_until`, `allowed_weekdays` (tinyint, NUKI bitmask), `is_active`. |

## Publish tags

| Tag | What it copies |
|---|---|
| `nuki-config` | `config/nuki.php` → `config/nuki.php` |
| `nuki-migrations` | `database/migrations/*` → `database/migrations/` |
| `nuki-views` | `resources/views/*` → `resources/views/vendor/nuki/` |
| `nuki-lang` | `lang/*` → `lang/vendor/nuki/` |
| `nuki-seeders` | `src/Database/Seeders/NukiDemoSeeder.php` → `database/seeders/NukiDemoSeeder.php` |
