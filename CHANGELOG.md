# Changelog

All notable changes to `darvis/nuki` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-05-15

### Added
- **Mandatory email verification.** New main users registered via
  [RegisterPage](src/Livewire/Auth/RegisterPage.php) are no longer logged in
  automatically: a signed verification link is mailed
  ([NukiVerifyEmailMail](src/Mail/NukiVerifyEmailMail.php)) and the user lands
  on a new notice page ([VerifyEmailNoticePage](src/Livewire/Auth/VerifyEmailNoticePage.php),
  route `nuki.auth.verify.notice`) with a throttled resend button. Clicking the
  link hits [NukiVerifyEmailController](src/Http/Controllers/NukiVerifyEmailController.php)
  (route `nuki.auth.verify`, `signed` middleware) which marks the account
  verified. Login is blocked for unverified accounts. `NukiUser` now implements
  `MustVerifyEmail`. Configurable via
  `nuki.auth_users.email_verification.{enabled,link_lifetime_minutes}` (default
  on, 60 min). New translation keys `nuki::nuki.auth.verify_*`,
  `nuki::nuki.auth.info.{verification_sent,email_verified}`,
  `nuki::nuki.auth.errors.verify_link_invalid` and a `nuki::mail.verify_email`
  block in EN, NL, DE and ES.

### Fixed
- `create_nuki_users_table` migration used `->after('two_factor_enabled')`
  inside `Schema::create()`. `AFTER` is only valid in `ALTER TABLE`; on MySQL
  this is a hard syntax error (SQLite silently ignored it, hiding the bug).
  Removed the `->after()` call — column order already matches definition order.

## [1.0.0] - 2026-05-12

### Added
- Split-screen auth layout (`nuki::layouts.auth`): a form column on the left
  and a brand panel with gradient and feature bullets on the right (`lg+`).
  The right-hand panel is toggleable via `NUKI_UI_AUTH_PANEL=false` (default
  `true`), and collapses to single-column on mobile. New translation keys
  `nuki::nuki.auth.panel.{heading,subheading,features}` in EN, NL, DE and ES.
- Configurable brand logo via `NUKI_UI_LOGO_LIGHT` and `NUKI_UI_LOGO_DARK`
  (path/URL to SVG/PNG). When unset, falls back to a neutral lock icon plus
  the configured `NUKI_UI_BRAND` name. New config: `nuki.ui.logo.{light,dark}`.
- Optional tagline above the auth form via `NUKI_UI_TAGLINE`. Falls back to
  the localised `auth.panel.subheading` string. New config: `nuki.ui.tagline`.
- Footer on auth pages: `© {year} {brand}` plus an optional list of links
  via `nuki.ui.footer.links` (array of `['label' => …, 'url' => …]`).
  Default is empty so existing installs see only the copyright line.
- Informational `flux:callout` on every main page (Dashboard, Smartlocks index/show,
  Activity, Accounts, Sub-users index/show, Webhooks, OAuth, Profile) explaining
  the page purpose to first-time users. New `nuki::nuki.<page>.info.{heading,text}`
  keys in all four locales (EN, NL, DE, ES).
- Multi-language support for the bundled UI, console commands and emails.
  Ships with **English, Dutch, German and Spanish** translations under the
  `nuki::` namespace (`lang/en/nuki.php`, `validation.php`, `mail.php`).
  Locale is resolved per request by `Http\Middleware\SetLocale` from
  (1) the authenticated `NukiUser->locale`, (2) `session('nuki.locale')`,
  (3) `app()->getLocale()`, (4) `nuki.ui.default_locale`. `Carbon::setLocale()`
  is set in lockstep so `diffForHumans()` and `isoFormat('L LT')` follow the
  active language. New config: `nuki.ui.locales` and `nuki.ui.default_locale`
  (env: `NUKI_DEFAULT_LOCALE`). New migration adds `locale` to `nuki_users`
  and the profile screen exposes a language picker. Publish overrides with
  `php artisan vendor:publish --tag=nuki-lang`.
- Optional package user authentication (`NUKI_AUTH_USERS_ENABLED=true`) with a
  dedicated `darvis-nuki` Laravel auth guard. Includes Livewire pages for
  login, e-mail OTP (2FA), registration, password reset and a profile screen,
  all rendered with Flux components.
- `NukiUser` model with self-referencing `parent_id` so a main user can manage
  sub-users; pivots `nuki_user_account` (many-to-many to `NukiAccount` with
  `owner`/`member` role) and `nuki_user_smartlock` (per-smartlock permissions
  `lock`/`unlock`/`view_logs`/`manage_auths` plus `allowed_from`/`until`
  validity and weekday bitmask).
- `SubUsersIndex` / `SubUserShow` Livewire screens for main users to create
  sub-users and assign smartlock access. No data is synced to NUKI — the
  permissions stay local to your app.
- Console command `nuki:user-create` to bootstrap the first main user.
- Existing UI routes are automatically wrapped with `auth:darvis-nuki`
  middleware when user auth is enabled; `AccountSwitcher` and smartlock
  screens filter to the user's accessible accounts and locks.
- `Support\WeekdayBitmask` helper extracted from `SmartlockShow` so UI and
  access checks share the same NUKI-style ma=64..zo=1 mapping.
- Dashboard page at `/nuki/dashboard` with KPI cards (total locks, locked,
  critical battery, doors open), recent activity feed and per-lock battery
  bars — ideal as a first-glance overview and as a hero screenshot.
- Account-wide activity timeline at `/nuki/activity`: visual timeline grouped
  by day with smartlock and period filters.
- `SmartLocks::update()` for renaming a lock (and other writable fields) and
  `SmartLocks::sync()` to trigger a state refresh from the device.
- Rename and Synchronise buttons on the smartlock detail page.
- Visual weekday grid (with Ma–Vr / weekend presets) replacing the checkbox
  row in the authorization modal.
- `Support/LogPresenter` helper that maps NUKI log actions to a label / icon
  / colour triple so the dashboard and timeline render consistently.
- Initial package scaffold for the NUKI Web API.
- Bearer token (API token) and OAuth 2.0 Authorization Code authentication.
- Resources: SmartLocks, SmartlockLogs, SmartlockAuths, Webhooks, OAuth.
- Webhook controller with HMAC signature verification and idempotent dispatch
  of a generic `NukiWebhookReceived` event.
- `Nuki` facade and account-aware manager (`Nuki::as($key)`).
- Console commands: `nuki:oauth-authorize`, `nuki:webhook-register`.
- Publishable config and OAuth tokens migration.
- Demo mode (`NUKI_DEMO=true`) that fakes every `api.nuki.io` response with
  realistic data via `Darvis\Nuki\Support\DemoFixtures`, for screenshots and
  local exploration without a real NUKI account.
- `Darvis\Nuki\Database\Seeders\NukiDemoSeeder` to populate `nuki_accounts`
  with demo accounts; publishable via the `nuki-seeders` tag.
- GitHub Actions CI workflow (PHP 8.2/8.3/8.4 × Laravel 11/12/13 matrix
  plus a Pint lint job).
- Dependabot config for weekly Composer and GitHub Actions updates.
- Security policy ([.github/SECURITY.md](.github/SECURITY.md)).
- `support` block in `composer.json` (issues, source, docs, e-mail) so
  Packagist surfaces clickable links.

### Changed
- **OTP is now mandatory for every user** as long as
  `nuki.auth_users.otp.enabled` is `true`. The per-user `two_factor_enabled`
  column is no longer consulted by the login gate (kept in the schema for
  potential future use).
- New main users are no longer auto-logged-in after registration; they must
  confirm their email address first (see *Mandatory email verification*).
