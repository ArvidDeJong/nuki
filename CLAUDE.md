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
composer lint          # Pint, check only
composer format        # Pint, fixes the files
composer analyse       # Larastan level 8, with phpstan-baseline.neon

vendor/bin/pest tests/Feature/SmartLocksTest.php   # single test file
vendor/bin/pest --filter="locks a smartlock"       # single test by description
```

Tests use Pest 3/4 + Orchestra Testbench. Bootstrap lives in [tests/Pest.php](tests/Pest.php) and [tests/TestCase.php](tests/TestCase.php) — the latter registers `LivewireServiceProvider` and `NukiServiceProvider`, sets `nuki.auth=token`, `nuki.token_resolver=config`, and enables webhook routes with a fixed secret. `Http::fake()` is the standard fixture; no test should hit the real NUKI API.

CI is [.github/workflows/tests.yml](.github/workflows/tests.yml), which calls the shared workflow in `ArvidDeJong/.github` (see [../CLAUDE.md](../CLAUDE.md)): the PHP × Laravel × prefer-lowest/prefer-stable matrix plus one Pint and Larastan job. Keep it green before tagging a release.

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

The provider also: loads views (`nuki::` namespace), auto-loads migrations from [database/migrations/](database/migrations/), publishes the `nuki-config` / `nuki-migrations` / `nuki-views` / `nuki-seeders` tags, registers the two console commands, conditionally loads webhook routes (`NukiConfig::webhookEnabled()`), conditionally loads UI routes + registers Livewire components (`NukiConfig::uiEnabled()`), and — when `NukiConfig::demoEnabled()` — calls [DemoFixtures::register()](src/Support/DemoFixtures.php) to install an `Http::fake()` covering every NUKI endpoint.

## NUKI API authentication

This section is about authenticating **the package against the NUKI Web API**.
For the package's own end-user login system see "Package user authentication"
below.

**Token mode** (`NUKI_AUTH=token`): [TokenAuthenticator](src/Auth/TokenAuthenticator.php) calls `ApiTokenResolver->resolve($accountKey)` for every request.
- `config` resolver returns `NukiConfig::apiToken()` for all accounts.
- `database` resolver reads the encrypted `api_token` column from [src/Models/NukiAccount.php](src/Models/NukiAccount.php) (table `nuki_accounts`).

**OAuth mode** (`NUKI_AUTH=oauth`): [OAuthAuthenticator](src/Auth/OAuthAuthenticator.php) reads `NukiToken` records from `TokenStore` and refreshes them with a 30-second expiry leeway. Stored tokens live in `nuki_oauth_tokens` (DB driver) or cache. Authorization-code dance is handled by [src/Resources/OAuth.php](src/Resources/OAuth.php) (`authorizationUrl()`, `exchangeCode()`, `refresh()`).

`php artisan nuki:oauth-authorize` ([NukiOAuthAuthorizeCommand](src/Console/Commands/NukiOAuthAuthorizeCommand.php)) drives the CLI authorization flow; `php artisan nuki:webhook-register` ([NukiWebhookRegisterCommand](src/Console/Commands/NukiWebhookRegisterCommand.php)) registers a webhook subscription with NUKI.

## HTTP client

[src/Http/HttpClient.php](src/Http/HttpClient.php) is the choke point for every outbound NUKI request:

- Injects per-request auth headers via `Authenticator->apply($request, $accountKey)` (account-aware).
- Retries on connection failures, HTTP 429, and 5xx: `http.retries` is the **total** number of attempts and `http.retry_sleep` a fixed pause in milliseconds between them. There is no backoff, whatever the comment in `config/nuki.php` says. A POST (a lock action) is retried like any other call.
- Throws `Exceptions\ApiException::fromResponse($response, $endpoint)` on HTTP errors, with the raw body string in `->body`; auth failures throw `AuthenticationException`. Both extend `NukiException`. A connection failure after the last attempt surfaces as Laravel's `ConnectionException`, which does not.

When adding endpoints, route them through `HttpClient->get/put/post/delete()` so retries and error handling stay uniform — don't call `Http::` directly.

## Webhooks

Disabled by default. When `NUKI_WEBHOOK_ENABLED=true`:

- [routes/webhooks.php](routes/webhooks.php) registers `POST {nuki.webhook.route}` (default `/nuki/webhook`) under the `nuki.webhook.middleware` group (default `api`).
- [src/Http/Controllers/WebhookController.php](src/Http/Controllers/WebhookController.php) verifies the `X-Nuki-Signature` header (HMAC-SHA256 of the raw body using `NUKI_WEBHOOK_SECRET`, compared with `hash_equals`).
- Deduplication: cache key `nuki:webhook:{eventId}` with TTL `nuki.webhook.dedup_ttl` (default 600s). Duplicates return `{"status": "duplicate"}` without dispatching.
- On success, dispatches [NukiWebhookReceived](src/Events/NukiWebhookReceived.php) carrying `type`, `payload`, and `accountKey` (from query string). The package handles no business logic — consumers register their own listener.

## UI (Livewire + Flux)

When `nuki.ui.enabled=true`, [routes/web.php](routes/web.php) registers pages under the `nuki.ui.prefix` (default `nuki`) with middleware from `nuki.ui.middleware`, followed by [AuthorizeUi](src/Http/Middleware/AuthorizeUi.php). While `auth_users.enabled` is off that middleware aborts 403 unless the `viewNuki` gate allows the visitor; the provider defines the gate only when the host app has not, and the default allows the `local` environment only (the Horizon/Telescope model). With `auth_users.enabled` it steps aside, the `darvis-nuki` guard decides. It is also Livewire persistent middleware, because update requests do not run route middleware. Never add a UI route outside that group: the pages operate locks and hold API tokens, and a route without the middleware is public on every install. All pages are Livewire components in [src/Livewire/](src/Livewire/), auto-registered with `nuki.*` aliases (e.g. `nuki.smartlocks-index`).

Views in [resources/views/livewire/](resources/views/livewire/) use **Flux components exclusively** (`<flux:card>`, `<flux:button>`, `<flux:badge>`, `<flux:callout>`, etc.) — keep it that way; no hand-rolled Tailwind buttons or form controls when a Flux equivalent exists. The layout is [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), overridable via `nuki.ui.layout`.

Account-aware components use the [UsesNukiAccount](src/Concerns/UsesNukiAccount.php) trait, which reads `session('nuki.current_account', 'default')`. [AccountSwitcher](src/Livewire/AccountSwitcher.php) dispatches `nuki-account-changed`; other components listen with `#[On('nuki-account-changed')]` and reset their state.

Identifiers the permission checks hang on are `#[Locked]`: `accountKey` in the trait and `smartlockId` in `SmartlockShow`. Never leave such a property writable, because the browser can set any public property and the checks made in `mount()` then say nothing about what is loaded next. A handler for `nuki-account-changed` assigns through `authorizedAccountKey()`, never the raw argument: the browser can dispatch that event itself, so the key is only trusted after it is checked against `accessibleAccounts()`. (`AccountsIndex::$accountKey` is a form field, the key being edited, and stays writable.) An authorization check in a computed property goes before the `try`, because `abort()` throws and the `catch (\Throwable)` there would swallow it.

## Package user authentication

Optional, enabled with `NUKI_AUTH_USERS_ENABLED=true`. Completely separate from "NUKI API authentication" above — that one talks to NUKI; this one is the end-user login for the package's bundled UI.

- One table [nuki_users](database/migrations/2026_05_12_000000_create_nuki_users_table.php) with self-referencing `parent_id` (`null` = main user, otherwise sub). Both kinds log in via the `darvis-nuki` auth guard with email + password.
- Email OTP as 2FA: after a valid password, a 6-digit code is mailed via [NukiLoginOtpMail](src/Mail/NukiLoginOtpMail.php) and validated in [LoginOtpPage](src/Livewire/Auth/LoginOtpPage.php). Skipped only when `auth_users.otp.enabled` is false globally; `LoginPage` does not read the per user `two_factor_enabled` column (nor does `--no-2fa` change anything at login), and the docs say so.
- Account binding: pivot `nuki_user_account` (many-to-many to `NukiAccount` with `role` ∈ `{owner, member}`). Subs **inherit** account access from their parent (`NukiUser::accessibleAccounts()`).
- Smartlock binding: pivot `nuki_user_smartlock` with permissions (`can_lock`/`unlock`/`view_logs`/`manage_auths`), validity window (`allowed_from`/`until`) and a weekday bitmask (`allowed_weekdays`). Subs **never** inherit smartlock access — they always need an explicit pivot row.
- Main users: `accessibleSmartlockIds()` returns `null` (= wildcard, all locks). Subs: returns explicit ID list.
- The guard and provider are registered at runtime by [AuthConfigRegistrar](src/Auth/Users/AuthConfigRegistrar.php) — no consumer changes to `config/auth.php` required.
- Password reset uses [NukiPasswordResetService](src/Auth/Users/NukiPasswordResetService.php) with its own `nuki_password_resets` table — deliberately not Laravel's `PasswordBroker`, so we avoid `auth.passwords` config merging across Laravel versions.
- The trait [AuthorizesSmartlockAccess](src/Concerns/AuthorizesSmartlockAccess.php) (used in `SmartlocksIndex`, `SmartlockShow`, `Dashboard` and `ActivityTimeline`) gates list-filtering and `assertCan()` checks. The action handlers re-check permissions even if the UI hides the button, and `SmartlockShow` checks again where it loads data: plain access for the lock, `view_logs` for the logs, `manage_auths` for the authorizations (they contain keypad codes). For an account key without a `nuki_accounts` row a sub user gets `[]`, only a main user keeps the wildcard; never return `null` for a sub user, `null` means "every lock".
- Every component that lists locks or logs filters for a sub user: locks through `userAccessibleSmartlockIds()`, log entries through `userSmartlockIdsWithPermission($accountKey, 'view_logs')`. The account wide log endpoint takes one lock id at most, so the entries are filtered in PHP after one call; don't add a call per lock. A lock id that comes from the browser (`ActivityTimeline::$smartlockId` is a `#[Url]` filter) is checked with `assertCan()` and is a 403, never an empty list, so a guess tells nothing.
- `SubUserShow` takes `accountId` and `editingAccessId` from the browser: the account goes through `ownAccount()` (the main user's `accessibleAccounts()`), and a row is only ever reached through `$sub->smartlockAccess()`, never through the model class, or a main user rewrites the row of somebody else's sub user.
- [AuthorizesMainUser](src/Concerns/AuthorizesMainUser.php) (used in `AccountsIndex`, `WebhooksIndex`, `OAuthConnect`, `SubUsersIndex` and `SubUserShow`) aborts 403 in the Livewire `boot` hook unless the user is a main user. It is the boot hook because that runs on every request, so it covers `mount()` and every public action in one place; a check in `mount()` alone leaves the actions open.
- Self registration (`auth_users.register_enabled`) is off by default in the config and in `NukiConfig::registerEnabled()`. Never default it to on: `RegisterPage` creates a main user, and a main user may operate every lock.
- Weekday bitmask conventie (ma=64..zo=1) is shared via [Support/WeekdayBitmask](src/Support/WeekdayBitmask.php).
- CLI: `php artisan nuki:user-create` creates the first main user.
- Bundled UI routes (`/nuki/*`) get [AuthenticateNukiUser](src/Http/Middleware/AuthenticateNukiUser.php) appended in [routes/web.php](routes/web.php) when this feature is on, and the guest pages in [routes/auth.php](routes/auth.php) run [RedirectIfNukiUser](src/Http/Middleware/RedirectIfNukiUser.php). Never use the bare `auth:darvis-nuki` or `guest:darvis-nuki` on a package route: Laravel then redirects to the host app's `login`, `dashboard` or `home` route, which is another login or a 500 (`Route [login] not defined.`). The subclasses only override `redirectTo()`, so the host app's `redirectGuestsTo()` stays untouched. `AuthenticateNukiUser` is registered as Livewire persistent middleware, because Livewire only keeps Laravel's own `Authenticate` class on update requests, not a subclass.
- An account is reachable for a package user only through `nuki_user_account`. `AccountsIndex::save()` attaches the signed in main user to an account they create (`NukiAccount::ROLE_OWNER`), and `nuki:user-create --account=<key>` attaches a new user; never create an account in the UI without attaching its maker, or it is in nobody's switcher.
- [layouts/app.blade.php](resources/views/layouts/app.blade.php) is also the layout of `/profile` and `/sub-users`, which exist while `ui.enabled` is off. Never call `route('nuki.dashboard')` or another UI route there without `Route::has()`, and keep `<livewire:nuki.account-switcher />` inside the same check: the component is not registered then. `tests/UiOff/` runs with the UI off.

## Demo mode

Setting `NUKI_DEMO=true` triggers two things at boot:

1. The provider stubs the `nuki.token` config value to `demo-token` if it's null, so the bearer authenticator does not throw before the fake intercepts.
2. [DemoFixtures::register()](src/Support/DemoFixtures.php) installs `Http::fake(['api.nuki.io/*' => closure])` returning canned data for `/smartlock`, `/smartlock/{id}`, `/smartlock/{id}/log`, `/smartlock/{id}/auth`, `/smartlock/{id}/action`, the account-wide variants, `/account`, `/api/notification`, and `/oauth/token`.

Run [NukiDemoSeeder](src/Database/Seeders/NukiDemoSeeder.php) to populate `nuki_accounts` with four demo accounts so the `AccountSwitcher` has options to show:

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

When adding a new NUKI endpoint, also add a corresponding branch in `DemoFixtures::respondTo()` — otherwise the demo dashboard will silently return `[]` for that resource. Keep the fixture data realistic (Dutch names, plausible battery levels, a `batteryCritical: true` lock to show the warning badge).

## Conventions to preserve

- DTOs are `readonly` classes with static `fromArray()` factories — keep new ones in the same shape under [src/DTOs/](src/DTOs/).
- Resources are stateless; create via the manager, don't cache instances on long-lived objects.
- Config keys are sorted alphabetically at every level in [config/nuki.php](config/nuki.php).
- [NukiConfig](src/Support/NukiConfig.php) is the only place in the package that reads the config. Add an accessor there instead of calling `config('nuki.…')` anywhere else; a test in [tests/Feature/ConfigAccessorTest.php](tests/Feature/ConfigAccessorTest.php) walks `src/`, `resources/`, `routes/` and `database/` and fails the build on a direct read.
- [docs/](docs/) is the GitHub Pages site, served from `main` and `/docs`. Every page needs front matter with a unique `description` and `nav_order`, facts live once in [docs/_config.yml](docs/_config.yml) and [docs/_data/faq.yml](docs/_data/faq.yml), and a link to a source file is an absolute GitHub URL, because a relative `../` path leaves the site. [tests/DocsSiteTest.php](tests/DocsSiteTest.php) checks all of that.
- [README.md](README.md) and [CHANGELOG.md](CHANGELOG.md) are written in English. Update `CHANGELOG.md` for any behavioural change.

## Caveats

- The webhook receiver rejects every request while `webhook.secret` is empty. `webhook.verify_signature` set to `false` is the deliberate way out; anything other than `false` leaves the check on.

- The package must run on **Laravel 11, 12, and 13** plus **Livewire 3.5/4** and **Pest 3/4** simultaneously. Avoid framework features added after Laravel 11.0 unless guarded.
- Migrations are auto-loaded from the package path. Consumers can `vendor:publish --tag=nuki-migrations` if they want to customise; otherwise they apply in place.
- Adding a NUKI endpoint = a new `Resource` method calling `HttpClient`, plus a DTO if the response shape is new, plus a branch in [DemoFixtures::respondTo()](src/Support/DemoFixtures.php) so the demo dashboard stays functional. Don't add HTTP plumbing to the Livewire layer.
