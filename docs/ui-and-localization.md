---
title: "UI and localization"
nav_order: 10
description: "The bundled Livewire pages of darvis/nuki: who may open them, the components and their routes, your own layout, and the four languages and how one is chosen."
---

# UI and localization

## Switch the bundled UI on or off

The pages are registered by default, and closed to everyone outside the `local` environment until
you open them (next section). Switch them off to use the package as a NUKI Web API client only:

```dotenv
NUKI_UI_ENABLED=false
```

When enabled, [routes/web.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/web.php) is loaded and Livewire
components are registered under `nuki.*` aliases.

URL prefix: `nuki.ui.prefix` (default `nuki`). Layout: `nuki.ui.layout`
(default `nuki::layouts.app`). See
[Configuration → ui.\*](configuration.md#ui--bundled-livewire-ui).

## Who may open the UI

The pages lock and unlock doors and hold API tokens, so they are closed unless you open them,
the same way Horizon, Telescope and Pulse are. Which rule applies depends on
`auth_users.enabled`:

- **`auth_users.enabled = false` (default).** Every UI route runs the
  [AuthorizeUi](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Middleware/AuthorizeUi.php) middleware, which
  asks the `viewNuki` gate and answers `403` when it says no. The package defines that gate only
  when your application has not, and its default allows the `local` environment and nothing
  else. To reach the UI anywhere else, define the gate yourself, for example in
  `AppServiceProvider::boot()`:

  ```php
  use App\Models\User;
  use Illuminate\Support\Facades\Gate;
  
  public function boot(): void
  {
      Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
  }
  ```

  The gate receives the user of your application's default guard, or `null` for a guest. Keep
  the parameter nullable (`?User $user`): Laravel does not call a gate for a guest otherwise, and
  the answer is then always no. Put your own `auth` middleware in `ui.middleware` when a guest
  should be sent to your login page instead of getting a `403`.
- **`auth_users.enabled = true`.** The package's own `darvis-nuki` guard protects the pages and
  the `viewNuki` gate is not asked. See [Users and permissions](users-and-permissions.md).

The middleware is also registered as Livewire persistent middleware, so the requests a page sends
after it was loaded are checked the same way. `ui.enabled` and `ui.middleware` work as before;
`AuthorizeUi` runs after the middleware you list there.

## Bundled Livewire components

Auto-registered with `nuki.*` aliases by
[NukiServiceProvider](https://github.com/ArvidDeJong/nuki/blob/main/src/NukiServiceProvider.php).

### Main UI

| Alias | Class | Route | Purpose |
|---|---|---|---|
| `nuki.dashboard` | [Dashboard](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Dashboard.php) | `/dashboard` | KPI cards (total locks, locked, critical battery, open doors), recent activity feed, per-lock battery bars. |
| `nuki.smartlocks-index` | [SmartlocksIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SmartlocksIndex.php) | `/` | List of smartlocks; filtered for sub users. Quick lock/unlock actions. |
| `nuki.smartlock-show` | [SmartlockShow](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SmartlockShow.php) | `/smartlocks/{smartlockId}` | Single-lock detail: state, recent logs, authorizations, rename + sync buttons. |
| `nuki.activity-timeline` | [ActivityTimeline](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/ActivityTimeline.php) | `/activity` | Visual timeline grouped per day; filter by lock or period. |
| `nuki.webhooks-index` | [WebhooksIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/WebhooksIndex.php) | `/webhooks` | List + manage NUKI webhook subscriptions. |
| `nuki.oauth-connect` | [OAuthConnect](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/OAuthConnect.php) | `/oauth/connect` | Shows the stored OAuth token of the current account, generates an authorization URL and disconnects. It does not receive the redirect from NUKI; [that route is yours](nuki-api-authentication.md#the-callback-route-is-yours-to-build). Only meaningful when `NUKI_AUTH=oauth`. |
| `nuki.accounts-index` | [AccountsIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/AccountsIndex.php) | `/accounts` | CRUD for `nuki_accounts` (token mode, multi-account). |
| `nuki.account-switcher` | [AccountSwitcher](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/AccountSwitcher.php) | — | Dropdown used in the top navigation; dispatches the `nuki-account-changed` Livewire event. |

### Auth UI (only when `NUKI_AUTH_USERS_ENABLED=true`)

| Alias | Class | Route |
|---|---|---|
| `nuki.auth.login` | [LoginPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginPage.php) | `/login` |
| `nuki.auth.otp` | [LoginOtpPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginOtpPage.php) | `/login/otp` |
| `nuki.auth.register` | [RegisterPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/RegisterPage.php) | `/register` |
| `nuki.auth.forgot-password` | [ForgotPasswordPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/ForgotPasswordPage.php) | `/password/forgot` |
| `nuki.auth.reset-password` | [ResetPasswordPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/ResetPasswordPage.php) | `/password/reset/{token}` |
| `nuki.profile` | [ProfilePage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/ProfilePage.php) | `/profile` |
| `nuki.sub-users-index` | [SubUsersIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUsersIndex.php) | `/sub-users` |
| `nuki.sub-user-show` | [SubUserShow](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUserShow.php) | `/sub-users/{id}` |

The main components are registered while `ui.enabled` is `true`, the auth components while
`auth_users.enabled` is `true`. `nuki.auth.verify-email`
([VerifyEmailNoticePage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/VerifyEmailNoticePage.php),
`/email/verify`) is registered as well.

You can render one in a Blade file of your own:

```blade
<livewire:nuki.smartlocks-index />
```

The `AuthorizeUi` middleware is on the package's routes, not on the component. A component you
place on your own page is as open as that page, so put the page behind your own middleware.

## Account-aware components

Use the [UsesNukiAccount](https://github.com/ArvidDeJong/nuki/blob/main/src/Concerns/UsesNukiAccount.php) trait:

```php
use Livewire\Attributes\On;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Livewire\Component;

class MyDashboard extends Component
{
    use UsesNukiAccount;

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        // Never assign the raw argument: the browser can send this event too.
        $this->accountKey = $this->authorizedAccountKey($accountKey);
    }

    public function render()
    {
        return view('livewire.my-dashboard', [
            'locks' => Nuki::as($this->accountKey)->smartlocks()->all(),
        ]);
    }
}
```

This class belongs in `app/Livewire/MyDashboard.php`, with a view of your own in
`resources/views/livewire/my-dashboard.blade.php`.

The trait:

- Sets `$accountKey` when the component mounts, from `session('nuki.current_account')`. For a
  package user it falls back to the user's first accessible account, or to `default` when there is
  none. The property is `#[Locked]`: the browser can read it and cannot change it.
- Has `authorizedAccountKey(string $accountKey)`, which returns the key when the current user may
  use it (`default`, or one of the user's accessible accounts; any key without package users) and
  aborts with `403` otherwise.
- Exposes `$availableAccounts` and `$currentAccountLabel` computed properties
  for use in Blade.
- `AccountSwitcher` writes the new value to the session (for a package user only when the account
  is `default` or one of the user's accessible accounts, otherwise `403`) and broadcasts
  `nuki-account-changed`; any component listening with the attribute above
  re-renders.

## Flux UI requirement

The bundled views use Flux components exclusively
(`<flux:card>`, `<flux:button>`, `<flux:badge>`, `<flux:callout>` and so on).
Flux 2 is a Composer requirement of the package, and the free edition has every component the
views use. Your layout loads the Flux and Tailwind assets. If you publish the
views (`--tag=nuki-views`) and customise them, keep the Flux components in
place — don't drop in hand-rolled Tailwind buttons or form controls.

## Custom layout

Override `nuki.ui.layout` to wrap the pages in your own chrome:

```php
// config/nuki.php
'ui' => [
    'layout' => 'layouts.app',  // your own layout
],
```

The layout is a Blade component layout: it echoes the `$slot` variable where the page goes, and
calls `@fluxAppearance` in the `<head>` and `@fluxScripts` before `</body>`. The package's own
[layouts/app.blade.php](https://github.com/ArvidDeJong/nuki/blob/main/resources/views/layouts/app.blade.php)
is the example to copy; it loads your application's `resources/css/app.css` and
`resources/js/app.js` through Vite, so Tailwind in your build has to cover the package views.

## Localization

Four locales ship: `en`, `nl`, `de`, `es`. Resolution happens per request
inside [SetLocale](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Middleware/SetLocale.php), in this order:

1. The `locale` of the signed in `NukiUser` (when `NUKI_AUTH_USERS_ENABLED=true`). The user sets
   it on the `/nuki/profile` page.
2. `session('nuki.locale')`. The package never writes this value; set it from your own code, for
   example from a language menu of your application.
3. The host application's `app()->getLocale()`, if it appears in
   `nuki.ui.locales`.
4. `nuki.ui.default_locale` (default `en`).

A value that is not a key of `nuki.ui.locales` is skipped. `Carbon::setLocale()` is set alongside
Laravel's locale, so dates such as `diffForHumans()` follow the language.

Set the package default:

```dotenv
NUKI_DEFAULT_LOCALE=nl
```

Add or remove locales by editing `nuki.ui.locales` in your published config.

### Overriding individual strings

Publish the language files into your app:

```bash
php artisan vendor:publish --tag=nuki-lang
```

This copies into `lang/vendor/nuki/{en,nl,de,es}/*.php`. Laravel resolves
`__('nuki::...')` lookups from this path before falling back to the package
defaults, so you can change a single phrase without forking the whole file.

## Layouts shipped

Two Blade layouts are bundled under `nuki::layouts.*`:

- `nuki::layouts.app` — the shell of the pages: navigation, account switcher and a menu with the
  profile and logout links. There is no language menu; see [Localization](#localization).
- `nuki::layouts.auth` — a split-screen layout for the login, OTP, register, password reset and
  email verification pages. Form column on the left, brand
  panel with gradient and feature bullets on the right (`lg+`). Collapses
  to single-column on mobile.


### Branding the auth pages

The auth layout reads four optional `ui.*` keys to drop in your own brand
without forking the views. All have sensible defaults so a fresh install
renders cleanly without any of them set.

```dotenv
# Logo above the form. Both light/dark optional; if unset, a neutral lock
# icon plus NUKI_UI_BRAND is rendered.
NUKI_UI_LOGO_LIGHT=/img/brand-light.svg
NUKI_UI_LOGO_DARK=/img/brand-dark.svg

# One-line tagline on the right-hand brand panel. Falls back to the
# localised nuki::nuki.auth.panel.subheading string.
NUKI_UI_TAGLINE="Smartlock management for Acme B.V."

# Toggle the right-hand brand panel. False = form fills the full width.
NUKI_UI_AUTH_PANEL=true
```

Footer links (privacy, terms, support) live next to the copyright. Set
them in your published config:

```php
// config/nuki.php
'ui' => [
    'footer' => [
        'links' => [
            ['label' => 'Privacy', 'url' => '/privacy'],
            ['label' => 'Terms',   'url' => '/terms'],
        ],
    ],
],
```

The right-hand panel's heading, subheading and three feature bullets come
from `nuki::nuki.auth.panel.*` in each of the four shipped locales. Override
them like any other string by publishing the language files
(`php artisan vendor:publish --tag=nuki-lang`) and editing
`lang/vendor/nuki/{en,nl,de,es}/nuki.php`.
