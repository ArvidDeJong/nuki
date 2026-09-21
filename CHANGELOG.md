# Changelog

All notable changes to `darvis/nuki` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
Documentation only; nothing in the package changes. The corrections that matter when you relied
on the old text:

- **Retries.** The docs described exponential backoff (`http.retry_sleep` × 2^n) and called
  `http.retries` the number of retries. `http.retries` is the total number of attempts, the first
  one included, and the pause between them is fixed. A lock action is a POST and is retried on a
  5xx like any other call; set `http.retries` to `1` when a second command is worse than an error.
- **Exceptions.** "All exceptions inherit from `NukiException`" was wrong: a server that cannot be
  reached surfaces as Laravel's `ConnectionException`, which does not. Catch
  `NukiException|ConnectionException`. `ApiException::$body` is the raw answer as a string, not a
  parsed body.
- **The login code per user.** The docs said `two_factor_enabled = false` and the `--no-2fa`
  option skip the emailed code for one user. They do not: while `auth_users.otp.enabled` is `true`
  every user gets a code, and the column is not read at login.
- **Email verification.** `email_verified_at` was documented as "reserved". It is enforced: with
  the default settings a user, also one made with `nuki:user-create`, first has to open the
  confirmation link.
- **Where a guest goes.** The docs said a visitor who is not signed in is redirected to
  `/nuki/login`. Laravel's `auth` middleware sends them to the `login` route of your application,
  and without one the answer is `Route [login] not defined.` The auth routes page has the
  `redirectGuestsTo()` snippet that points guests of the package pages at the package login.
- **A signed in user on the login page** was said to go to `auth_users.redirect_after_login`.
  Laravel's `guest` middleware decides: the `dashboard` or `home` route of your application, or `/`.
- **OAuth.** The docs showed `NUKI_OAUTH_REDIRECT_URL=.../nuki/oauth/callback` as if that route
  existed. The package has no callback route and never checks `state`; your application builds the
  route and the check. The API authentication page has a complete example.
- **The `config` token resolver** was said to return the token for every account key. It only
  knows `default`; any other key throws an `AuthenticationException`. Token mode with the
  `database` resolver is multi account, which the Boost guideline denied.
- **Accounts and users.** Nothing attaches a main user to an account, not the accounts page and
  not `nuki:user-create`. The users page now says so and shows the
  `$user->accounts()->syncWithoutDetaching([...])` call an owner runs today.
- **The webhook test example** posted an array and signed `json_encode($payload)`, which answers
  401. The example now sends the raw body with `call()`.
- **Troubleshooting.** "A 401 from NUKI clears the token" (only a refused refresh does),
  "duplicate for everything with the array cache store" (the array store forgets everything, so
  nothing is a duplicate), the advice for "table already exists" and the headings that were not
  the literal messages are corrected.
- Smaller ones: Livewire and Flux are Composer requirements, not optional; demo mode has five
  locks, not four; there is no locale switcher in the layout, a user picks a language on the
  profile page; with `NUKI_UI_ENABLED=false` the profile and sub user pages need your own
  `ui.layout`; the migrations create every table whichever features are on.

### Added
- Documentation pages: [Quick start](https://arviddejong.github.io/nuki/quickstart.html), a
  complete page with routes, controller and view, and
  [Testing](https://arviddejong.github.io/nuki/testing.html), how to test your own code without
  calling NUKI. `getting-started.md` became `installation.md`, with numbered steps and a "Check
  that it works" section; the old address `/getting-started.html` no longer exists.
- The README follows the order of the other darvis packages, with a Features and a Requirements
  section.

## [1.2.0] - 2026-09-21

### Security
- **The bundled UI was open to everyone after `composer require`.** With the defaults
  (`ui.enabled` on, `ui.middleware` `['web']`, package users off) anyone who knew the URL could
  open `/nuki`, lock and unlock doors and manage API tokens. The UI routes now run an
  `AuthorizeUi` middleware that asks a `viewNuki` gate, the way Horizon, Telescope and Pulse do.
  The package defines that gate only when your application has not, and its default allows the
  `local` environment and nothing else. **What you have to do:** to keep the UI reachable outside
  `local`, define the gate in your application, for example in `AppServiceProvider::boot()`:

  ```php
  use App\Models\User;
  use Illuminate\Support\Facades\Gate;

  Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
  ```

  Keep the parameter nullable (`?User $user`), otherwise Laravel never calls the gate for a guest.
  Nothing to do when `NUKI_AUTH_USERS_ENABLED=true`: the package's own login already protects the
  pages and the gate is not asked. `ui.enabled` and `ui.middleware` work as before.
- **Self registration created a main user, who may operate every lock.** With package users on,
  `/nuki/register` was open by default, so any visitor with a mailbox could make an account with
  full access. `auth_users.register_enabled` is now `false` by default: the route answers 404, the
  login page has no link to it, and a registration posted from a page that was still open is
  refused. **What you have to do:** nothing, unless you relied on self registration. Create users
  with `php artisan nuki:user-create`, or switch it back on in `.env`:

  ```dotenv
  NUKI_AUTH_USERS_REGISTER_ENABLED=true
  ```

  A published `config/nuki.php` keeps the value it has; set `'register_enabled' => false` there,
  or replace it with `env('NUKI_AUTH_USERS_REGISTER_ENABLED', false)`.
- **A sub user could manage accounts, API tokens, webhooks and the OAuth connection.** The
  accounts, webhooks and connection pages did not look at who was signed in, so a sub user could
  also disconnect the account's OAuth token. With package users on they are now for a main user
  only: a sub user gets a 403 on the page and on every action, and no longer sees the three
  links. Nothing to do. Without package users nothing changes here; the `viewNuki` gate guards the pages.
- **A sub user could read the activity log and the keypad codes of a lock that was not theirs.**
  The smartlock page trusted a lock id and an account key that the browser could change, and loaded
  logs and authorizations without looking at the permissions. Both are locked now, the lock itself
  needs an active permission row, the activity tab needs `view_logs` and the authorizations tab
  needs `manage_auths`; anything else is a 403 and the tab is not offered. The account switcher
  lists only the accounts a package user has access to, and switching to any other account, from
  the menu or with a hand made event, is a 403. Nothing to do, but check that every main user is
  attached to the accounts they work in (`$user->accounts()->syncWithoutDetaching([...])`): an
  account nobody is attached to no longer shows up in their switcher.
- **A sub user saw every lock on an account without a row in `nuki_accounts`**, for example the
  `default` account in single account mode. Such a sub user now sees no locks there; a main user
  still sees them all. Nothing to do.

### Changed
- The bundled UI answers 403 outside the `local` environment until the application defines the
  `viewNuki` gate (only while `auth_users.enabled` is `false`). See Security above for the snippet.
- `auth_users.register_enabled` defaults to `false` and reads `NUKI_AUTH_USERS_REGISTER_ENABLED`.
- With package users on, a sub user no longer sees the accounts, webhooks and connection links, the account
  switcher only lists accessible accounts, and the smartlock page only offers the tabs the user
  has the permission for.
- If you published the views (`nuki-views`), compare `layouts/app.blade.php`,
  `livewire/account-switcher.blade.php` and `livewire/smartlock-show.blade.php` with the package
  versions. An older `smartlock-show` view still asks for the logs of a sub user without
  `view_logs`, which now ends in a 403 for the whole page.

## [1.1.2] - 2026-09-21

### Security
- **Editing an account in the bundled UI stored a new API token as plain text.** The accounts
  screen saved the change with a query builder update, which skips the `encrypted` cast on
  `api_token`. The token then sat readable in `nuki_accounts`, and the next read of that account
  threw a `DecryptException`, which took the accounts screen down. The change is saved through the
  model now. An account that was created and never edited with a new token is not affected.
  After upgrading, open every account you edited a token for and enter the token again, or rotate
  it in NUKI Web. To find them: a value in `nuki_accounts.api_token` that does not start with
  `eyJ` is plain text.

## [1.1.1] - 2026-09-21

### Added
- A Laravel Boost skill, `nuki-development`, in `resources/boost/skills/`. It covers how a call
  runs and what every failure gives you (exception class and message), smartlock actions, logs and
  keypad codes, multi account with `Nuki::as()`, the OAuth callback the host application has to
  build, the webhook receiver and its pitfalls, closing off the bundled UI and its users, reading
  settings through `NukiConfig`, and testing without calling the NUKI Web API.
- A social preview image for the documentation site (`docs/assets/images/social-preview.png`),
  set as the default Open Graph and Twitter card image for every page.

## [1.1.0] - 2026-09-21

### Added
- The package now carries the same tooling as the other darvis packages: Pint, Larastan level 8
  with a baseline for the existing code, the `test`, `lint`, `format` and `analyse` composer
  scripts, a `.gitattributes` that keeps `docs/`, `tests/` and `.github/` out of the dist
  archive, issue and pull request templates, a `CODE_OF_CONDUCT.md`, a `CONTRIBUTING.md`, and a
  Laravel Boost guideline in `resources/boost/`.
- `Darvis\Nuki\Support\NukiConfig`, the one place that reads the package config. It holds every
  default exactly once and is used everywhere in the package, so a renamed key now breaks in a
  single place instead of silently falling back. A test walks `src/`, `resources/`, `routes/` and
  `database/` and fails the build on a direct `config('nuki.…')` read.
- `nuki.webhook.verify_signature` (default `true`). Set it to `false` to accept unsigned webhook
  requests, for example behind a gateway that already authenticates the caller. Only an explicit
  `false` switches the check off, so a typo leaves it on.
- `docs/` is a GitHub Pages site (Just the Docs) instead of ten Markdown files with a hand written
  index. The pages carry front matter with a title, a description and a navigation order, there is
  an FAQ and an `llms.txt` generated from one `_data/faq.yml`, and links to the source now point at
  GitHub so they keep working off the repository. A test guards the front matter, the YAML and the
  single source facts, because an invalid value makes Jekyll abort the build while GitHub keeps
  serving the last version that did build.

### Fixed
- **The webhook receiver accepted every request while no secret was configured.** With
  `NUKI_WEBHOOK_ENABLED=true` and no `NUKI_WEBHOOK_SECRET`, the signature check returned early and
  anyone who knew the URL could dispatch `NukiWebhookReceived` events into the application. The
  receiver now rejects every request with `401` until a secret is set.
- Registering, resetting a password and changing a password did nothing on Laravel 11.0 through
  11.31: they used the `confirmed:otherField` validation rule, which only accepts a custom field
  name from a later 11.x release. On an older 11.x the rule never matched, validation failed and
  no account was created or changed, while `composer.json` promised `^11.0`. The three rules use
  `same:` now, which every supported Laravel has.

### Changed
- CI calls the shared reusable workflow in `ArvidDeJong/.github` instead of its own copy. That
  adds the `prefer-lowest` column, which tests whether the version constraints in
  `composer.json` are actually true, and it keeps the PHP and Laravel matrix in one place for
  every package.
- `phpunit/phpunit` is a dev dependency instead of an implied one, and `SECURITY.md` moved from
  `.github/` to the repository root, where the other packages keep it.
- `config/nuki.php` has its keys sorted alphabetically at every level, guarded by a test. No key
  was renamed or removed, so a published config file keeps working.

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
