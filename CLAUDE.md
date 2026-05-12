# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package identity

`darvis/nuki` — a Laravel package that wraps the [NUKI Web API](https://api.nuki.io) with smartlock control, activity logs, keypad/user authorizations, a webhook receiver, and a Livewire/Flux UI.

- Author / maintainer: **Arvid de Jong** (<info@darvis.nl>) — sole developer of this package
- Namespace: `Darvis\Nuki\` ([src/](src/)), tests under `Darvis\Nuki\Tests\` ([tests/](tests/))
- Requires: PHP 8.2+, Laravel 11/12/13, Livewire 3.5+/4, Flux 2.0+
- Facade: `Darvis\Nuki\Facades\Nuki` (auto-aliased as `Nuki`)
- Service provider: [src/NukiServiceProvider.php](src/NukiServiceProvider.php) (auto-discovered via `extra.laravel.providers`)

The [README.md](README.md) is consumer-facing (install, env vars, facade examples). This file documents the internals.

## Commands

```bash
composer test          # run the Pest suite
composer lint          # apply Pint formatting
composer lint:check    # check formatting without writing

vendor/bin/pest tests/Feature/SmartLocksTest.php   # single test file
vendor/bin/pest --filter="locks a smartlock"       # single test by description
```

Tests use Pest 3/4 + Orchestra Testbench. Bootstrap lives in [tests/Pest.php](tests/Pest.php) and [tests/TestCase.php](tests/TestCase.php) — the latter registers `LivewireServiceProvider` and `NukiServiceProvider`, sets `nuki.auth=token`, `nuki.token_resolver=config`, and enables webhook routes with a fixed secret. `Http::fake()` is the standard fixture; no test should hit the real NUKI API.

CI runs the same suite plus Pint via [.github/workflows/tests.yml](.github/workflows/tests.yml): a matrix of PHP 8.2/8.3/8.4 × Laravel 11/12/13 (excluding PHP 8.2 + Laravel 13), and a single Pint check job. Keep both green before tagging a release.

## Architecture: manager + resources

[src/Nuki.php](src/Nuki.php) is the singleton manager. It holds an `$accountKey` (default `'default'`) and exposes factory methods returning resource objects:

- `smartlocks()` → [SmartLocks](src/Resources/SmartLocks.php) — list/find locks, `lock`/`unlock`/`lockAndGo` actions
- `logs()` → [SmartlockLogs](src/Resources/SmartlockLogs.php) — per-lock and account-wide activity logs
- `auths()` → [SmartlockAuths](src/Resources/SmartlockAuths.php) — keypad codes and app-user management
- `webhooks()` → [Webhooks](src/Resources/Webhooks.php) — webhook subscriptions
- `oauth()` → [OAuth](src/Resources/OAuth.php) — auth URL, code exchange, refresh
- `account()` → [Account](src/Resources/Account.php) — account info (cached 1h)

`Nuki::as(string $accountKey)` returns a clone scoped to a different account; resources receive the key and pass it through `HttpClient` so the authenticator resolves the right token. Account keys are **opaque strings** — the package never assumes a `User` model. Callers decide (`Nuki::as((string) $user->id)`).

Resources are stateless factories — instantiate via the manager rather than caching references. They map JSON responses to readonly DTOs in [src/DTOs/](src/DTOs/) via static `fromArray()` factories.

## Service provider wiring

[src/NukiServiceProvider.php](src/NukiServiceProvider.php) is the **only** place where strategies are selected. Three swappable contracts, each chosen by config:

| Contract | Config key | Drivers |
|---|---|---|
| `Contracts\TokenStore` | `nuki.oauth.token_store` | `cache` → [CacheTokenStore](src/Auth/CacheTokenStore.php), `database` → [DatabaseTokenStore](src/Auth/DatabaseTokenStore.php) |
| `Contracts\ApiTokenResolver` | `nuki.token_resolver` | `config` → [ConfigApiTokenResolver](src/Auth/ConfigApiTokenResolver.php), `database` → [DatabaseApiTokenResolver](src/Auth/DatabaseApiTokenResolver.php) |
| `Contracts\Authenticator` | `nuki.auth` | `token` → [TokenAuthenticator](src/Auth/TokenAuthenticator.php), `oauth` → [OAuthAuthenticator](src/Auth/OAuthAuthenticator.php) |

When adding a strategy, bind it in `register()` and document the config key — do **not** instantiate strategies elsewhere.

The provider also: loads views (`nuki::` namespace), auto-loads migrations from [database/migrations/](database/migrations/), publishes the `nuki-config` / `nuki-migrations` / `nuki-views` / `nuki-seeders` tags, registers the two console commands, conditionally loads webhook routes (`config('nuki.webhook.enabled') === true`), conditionally loads UI routes + registers Livewire components (`config('nuki.ui.enabled') === true`), and — when `config('nuki.demo.enabled') === true` — calls [DemoFixtures::register()](src/Support/DemoFixtures.php) to install an `Http::fake()` covering every NUKI endpoint.

## NUKI API authentication

This section is about authenticating **the package against the NUKI Web API**.
For the package's own end-user login system see "Package user authentication"
below.

**Token mode** (`NUKI_AUTH=token`): [TokenAuthenticator](src/Auth/TokenAuthenticator.php) calls `ApiTokenResolver->resolve($accountKey)` for every request.
- `config` resolver returns `config('nuki.token')` for all accounts.
- `database` resolver reads the encrypted `api_token` column from [src/Models/NukiAccount.php](src/Models/NukiAccount.php) (table `nuki_accounts`).

**OAuth mode** (`NUKI_AUTH=oauth`): [OAuthAuthenticator](src/Auth/OAuthAuthenticator.php) reads `NukiToken` records from `TokenStore` and refreshes them with a 30-second expiry leeway. Stored tokens live in `nuki_oauth_tokens` (DB driver) or cache. Authorization-code dance is handled by [src/Resources/OAuth.php](src/Resources/OAuth.php) (`authorizationUrl()`, `exchangeCode()`, `refresh()`).

`php artisan nuki:oauth-authorize` ([NukiOAuthAuthorizeCommand](src/Console/Commands/NukiOAuthAuthorizeCommand.php)) drives the CLI authorization flow; `php artisan nuki:webhook-register` ([NukiWebhookRegisterCommand](src/Console/Commands/NukiWebhookRegisterCommand.php)) registers a webhook subscription with NUKI.

## HTTP client

[src/Http/HttpClient.php](src/Http/HttpClient.php) is the choke point for every outbound NUKI request:

- Injects per-request auth headers via `Authenticator->authenticate()` (account-aware).
- Retries on connection failures, HTTP 429, and 5xx with exponential backoff (`http.retry_sleep` × 2^attempt ms).
- Throws `Exceptions\ApiException::fromResponse($response)` on HTTP errors; auth failures throw `AuthenticationException`. Both extend `NukiException`.

When adding endpoints, route them through `HttpClient->get/put/post/delete()` so retries and error handling stay uniform — don't call `Http::` directly.

## Webhooks

Disabled by default. When `NUKI_WEBHOOK_ENABLED=true`:

- [routes/webhooks.php](routes/webhooks.php) registers `POST {nuki.webhook.route}` (default `/nuki/webhook`) under the `nuki.webhook.middleware` group (default `api`).
- [src/Http/Controllers/WebhookController.php](src/Http/Controllers/WebhookController.php) verifies the `X-Nuki-Signature` header (HMAC-SHA256 of the raw body using `NUKI_WEBHOOK_SECRET`, compared with `hash_equals`).
- Deduplication: cache key `nuki:webhook:{eventId}` with TTL `nuki.webhook.dedup_ttl` (default 600s). Duplicates return `{"status": "duplicate"}` without dispatching.
- On success, dispatches [NukiWebhookReceived](src/Events/NukiWebhookReceived.php) carrying `type`, `payload`, and `accountKey` (from query string). The package handles no business logic — consumers register their own listener.

## UI (Livewire + Flux)

When `nuki.ui.enabled=true`, [routes/web.php](routes/web.php) registers pages under the `nuki.ui.prefix` (default `nuki`) with middleware from `nuki.ui.middleware`. All pages are Livewire components in [src/Livewire/](src/Livewire/), auto-registered with `nuki.*` aliases (e.g. `nuki.smartlocks-index`).

Views in [resources/views/livewire/](resources/views/livewire/) use **Flux components exclusively** (`<flux:card>`, `<flux:button>`, `<flux:badge>`, `<flux:callout>`, etc.) — keep it that way; no hand-rolled Tailwind buttons or form controls when a Flux equivalent exists. The layout is [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), overridable via `nuki.ui.layout`.

Account-aware components use the [UsesNukiAccount](src/Concerns/UsesNukiAccount.php) trait, which reads `session('nuki.current_account', 'default')`. [AccountSwitcher](src/Livewire/AccountSwitcher.php) dispatches `nuki-account-changed`; other components listen with `#[On('nuki-account-changed')]` and reset their state.

## Package user authentication

Optional, enabled with `NUKI_AUTH_USERS_ENABLED=true`. Completely separate from "NUKI API authentication" above — that one talks to NUKI; this one is the end-user login for the package's bundled UI.

- One table [nuki_users](database/migrations/2026_05_12_000000_create_nuki_users_table.php) with self-referencing `parent_id` (`null` = main user, otherwise sub). Both kinds log in via the `darvis-nuki` auth guard with email + password.
- Email OTP as 2FA: after a valid password, a 6-digit code is mailed via [NukiLoginOtpMail](src/Mail/NukiLoginOtpMail.php) and validated in [LoginOtpPage](src/Livewire/Auth/LoginOtpPage.php). Skipped if `two_factor_enabled` is false on the user or `auth_users.otp.enabled` is false globally.
- Account binding: pivot `nuki_user_account` (many-to-many to `NukiAccount` with `role` ∈ `{owner, member}`). Subs **inherit** account access from their parent (`NukiUser::accessibleAccounts()`).
- Smartlock binding: pivot `nuki_user_smartlock` with permissions (`can_lock`/`unlock`/`view_logs`/`manage_auths`), validity window (`allowed_from`/`until`) and a weekday bitmask (`allowed_weekdays`). Subs **never** inherit smartlock access — they always need an explicit pivot row.
- Main users: `accessibleSmartlockIds()` returns `null` (= wildcard, all locks). Subs: returns explicit ID list.
- The guard and provider are registered at runtime by [AuthConfigRegistrar](src/Auth/Users/AuthConfigRegistrar.php) — no consumer changes to `config/auth.php` required.
- Password reset uses [NukiPasswordResetService](src/Auth/Users/NukiPasswordResetService.php) with its own `nuki_password_resets` table — deliberately not Laravel's `PasswordBroker`, so we avoid `auth.passwords` config merging across Laravel versions.
- The trait [AuthorizesSmartlockAccess](src/Concerns/AuthorizesSmartlockAccess.php) (used in `SmartlocksIndex` / `SmartlockShow`) gates list-filtering and `assertCan()` checks. The action handlers re-check permissions even if the UI hides the button.
- Weekday bitmask conventie (ma=64..zo=1) is shared via [Support/WeekdayBitmask](src/Support/WeekdayBitmask.php).
- CLI: `php artisan nuki:user-create` creates the first main user.
- Bundled UI routes (`/nuki/*`) get `auth:darvis-nuki` middleware appended in [routes/web.php](routes/web.php) when this feature is on.

## Demo mode

Setting `NUKI_DEMO=true` triggers two things at boot:

1. The provider stubs `config('nuki.token')` to `demo-token` if it's null, so the bearer authenticator does not throw before the fake intercepts.
2. [DemoFixtures::register()](src/Support/DemoFixtures.php) installs `Http::fake(['api.nuki.io/*' => closure])` returning canned data for `/smartlock`, `/smartlock/{id}`, `/smartlock/{id}/log`, `/smartlock/{id}/auth`, `/smartlock/{id}/action`, the account-wide variants, `/account`, `/api/notification`, and `/oauth/token`.

Run [NukiDemoSeeder](src/Database/Seeders/NukiDemoSeeder.php) to populate `nuki_accounts` with four demo accounts so the `AccountSwitcher` has options to show:

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

When adding a new NUKI endpoint, also add a corresponding branch in `DemoFixtures::respondTo()` — otherwise the demo dashboard will silently return `[]` for that resource. Keep the fixture data realistic (Dutch names, plausible battery levels, a `batteryCritical: true` lock to show the warning badge).

## Conventions to preserve

- DTOs are `readonly` classes with static `fromArray()` factories — keep new ones in the same shape under [src/DTOs/](src/DTOs/).
- Resources are stateless; create via the manager, don't cache instances on long-lived objects.
- Config keys are sorted alphabetically inside each section in [config/nuki.php](config/nuki.php).
- [README.md](README.md) and [CHANGELOG.md](CHANGELOG.md) are written in English. Update `CHANGELOG.md` for any behavioural change.

## Caveats

- The package must run on **Laravel 11, 12, and 13** plus **Livewire 3.5/4** and **Pest 3/4** simultaneously. Avoid framework features added after Laravel 11.0 unless guarded.
- Migrations are auto-loaded from the package path. Consumers can `vendor:publish --tag=nuki-migrations` if they want to customise; otherwise they apply in place.
- Adding a NUKI endpoint = a new `Resource` method calling `HttpClient`, plus a DTO if the response shape is new, plus a branch in [DemoFixtures::respondTo()](src/Support/DemoFixtures.php) so the demo dashboard stays functional. Don't add HTTP plumbing to the Livewire layer.
