# UI and localization

[← Documentation index](README.md)

## Toggling the bundled UI

Enabled by default. Turn off to use the package purely as a NUKI Web API
client:

```dotenv
NUKI_UI_ENABLED=false
```

When enabled, [routes/web.php](../routes/web.php) is loaded and Livewire
components are registered under `nuki.*` aliases.

URL prefix: `nuki.ui.prefix` (default `nuki`). Layout: `nuki.ui.layout`
(default `nuki::layouts.app`). See
[Configuration → ui.\*](configuration.md#ui--bundled-livewire-ui).

## Bundled Livewire components

Auto-registered with `nuki.*` aliases by
[NukiServiceProvider](../src/NukiServiceProvider.php).

### Main UI

| Alias | Class | Route | Purpose |
|---|---|---|---|
| `nuki.dashboard` | [Dashboard](../src/Livewire/Dashboard.php) | `/dashboard` | KPI cards (total locks, locked, critical battery, open doors), recent activity feed, per-lock battery bars. |
| `nuki.smartlocks-index` | [SmartlocksIndex](../src/Livewire/SmartlocksIndex.php) | `/` | List of smartlocks; filtered for sub users. Quick lock/unlock actions. |
| `nuki.smartlock-show` | [SmartlockShow](../src/Livewire/SmartlockShow.php) | `/smartlocks/{id}` | Single-lock detail: state, recent logs, authorizations, rename + sync buttons. |
| `nuki.activity-timeline` | [ActivityTimeline](../src/Livewire/ActivityTimeline.php) | `/activity` | Visual timeline grouped per day; filter by lock or period. |
| `nuki.webhooks-index` | [WebhooksIndex](../src/Livewire/WebhooksIndex.php) | `/webhooks` | List + manage NUKI webhook subscriptions. |
| `nuki.oauth-connect` | [OAuthConnect](../src/Livewire/OAuthConnect.php) | `/oauth/connect` | UI entry point for the OAuth dance (only meaningful when `NUKI_AUTH=oauth`). |
| `nuki.accounts-index` | [AccountsIndex](../src/Livewire/AccountsIndex.php) | `/accounts` | CRUD for `nuki_accounts` (token mode, multi-account). |
| `nuki.account-switcher` | [AccountSwitcher](../src/Livewire/AccountSwitcher.php) | — | Dropdown used in the top navigation; dispatches the `nuki-account-changed` Livewire event. |

### Auth UI (only when `NUKI_AUTH_USERS_ENABLED=true`)

| Alias | Class | Route |
|---|---|---|
| `nuki.auth.login` | [LoginPage](../src/Livewire/Auth/LoginPage.php) | `/login` |
| `nuki.auth.otp` | [LoginOtpPage](../src/Livewire/Auth/LoginOtpPage.php) | `/login/otp` |
| `nuki.auth.register` | [RegisterPage](../src/Livewire/Auth/RegisterPage.php) | `/register` |
| `nuki.auth.forgot-password` | [ForgotPasswordPage](../src/Livewire/Auth/ForgotPasswordPage.php) | `/password/forgot` |
| `nuki.auth.reset-password` | [ResetPasswordPage](../src/Livewire/Auth/ResetPasswordPage.php) | `/password/reset/{token}` |
| `nuki.profile` | [ProfilePage](../src/Livewire/ProfilePage.php) | `/profile` |
| `nuki.sub-users-index` | [SubUsersIndex](../src/Livewire/SubUsersIndex.php) | `/sub-users` |
| `nuki.sub-user-show` | [SubUserShow](../src/Livewire/SubUserShow.php) | `/sub-users/{id}` |

You can embed any of these in your own Blade files:

```blade
<livewire:nuki.smartlocks-index />
```

## Account-aware components

Use the [UsesNukiAccount](../src/Concerns/UsesNukiAccount.php) trait:

```php
use Livewire\Attributes\On;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Livewire\Component;

class MyDashboard extends Component
{
    use UsesNukiAccount;

    #[On('nuki-account-changed')]
    public function refresh(): void
    {
        $this->locks = Nuki::as($this->accountKey)->smartlocks()->all();
    }
}
```

The trait:

- Mounts `$accountKey` from `session('nuki.current_account')` (or first
  accessible account for the authenticated user).
- Exposes `$availableAccounts` and `$currentAccountLabel` computed properties
  for use in Blade.
- `AccountSwitcher` writes the new value to the session and broadcasts
  `nuki-account-changed`; any component listening with the attribute above
  re-renders.

## Flux UI requirement

The bundled views use Flux components exclusively
(`<flux:card>`, `<flux:button>`, `<flux:badge>`, `<flux:callout>` and so on).
Flux 2.0+ must be available in the host application. If you publish the
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

The layout must yield `slot` (Livewire default) and call `@fluxScripts`
somewhere before `</body>`.

## Localization

Four locales ship: `en`, `nl`, `de`, `es`. Resolution happens per request
inside [SetLocale](../src/Http/Middleware/SetLocale.php), in this order:

1. The authenticated `NukiUser->locale` (when `NUKI_AUTH_USERS_ENABLED=true`).
2. `session('nuki.locale')` for guests and anonymous flows.
3. The host application's `app()->getLocale()`, if it appears in
   `nuki.ui.locales`.
4. `nuki.ui.default_locale` (default `en`).

`Carbon::setLocale()` is set alongside Laravel's locale, so `diffForHumans()`
and `isoFormat('L LT')` follow the active language automatically.

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

- `nuki::layouts.app` — the authenticated app shell (navigation, account
  switcher, locale switcher). Used by every protected page.
- `nuki::layouts.auth` — a Tailwind UI split-screen layout for the login /
  OTP / register / password-reset screens. Form column on the left, brand
  panel with gradient and feature bullets on the right (`lg+`). Collapses
  to single-column on mobile.

Both pull Flux scripts and the user's selected locale.

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
