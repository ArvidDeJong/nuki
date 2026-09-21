---
title: "Auth routes"
nav_order: 8
description: "Every route darvis/nuki registers for login, OTP, registration, password reset and email verification, its middleware, and where a guest is redirected."
---

# Auth routes

When `NUKI_AUTH_USERS_ENABLED=true`, [routes/auth.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/auth.php) is
loaded by the service provider and the bundled UI routes from
[routes/web.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/web.php) are wrapped in `auth:darvis-nuki`. This
page is the canonical list.

## Common middleware

All routes registered under [routes/auth.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/auth.php) use:

- The middleware group from `nuki.auth_users.routes.middleware` (default `['web']`).
- [SetLocale](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Middleware/SetLocale.php), always appended.

URL prefix: `nuki.auth_users.routes.prefix` (default `nuki`).
Route name prefix: `nuki.` (declared by the route group).

UI routes from [routes/web.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/web.php) use
`nuki.ui.middleware` (default `['web']`), then — when `auth_users.enabled` is on
— `auth:darvis-nuki`, then [AuthorizeUi](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Middleware/AuthorizeUi.php)
and `SetLocale`. `AuthorizeUi` steps aside when `auth_users.enabled` is on; otherwise it asks the
`viewNuki` gate, see [Who may open the UI](ui-and-localization.md#who-may-open-the-ui).

## Guest routes (`guest:darvis-nuki`)

`guest:darvis-nuki` is Laravel's own `guest` middleware. A package user who is already signed in
and opens one of these pages is redirected by Laravel, to the route named `dashboard` or `home`
of your application when it has one, otherwise to `/`. That is not
`auth_users.redirect_after_login`; change it with `redirectUsersTo()` in `bootstrap/app.php`.

| Method | Path | Name | Component | Conditional on |
|---|---|---|---|---|
| GET | `/login` | `nuki.auth.login` | [LoginPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginPage.php) | — |
| GET | `/login/otp` | `nuki.auth.otp` | [LoginOtpPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginOtpPage.php) | — |
| GET | `/register` | `nuki.auth.register` | [RegisterPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/RegisterPage.php) | `auth_users.register_enabled = true` (default `false`) |
| GET | `/password/forgot` | `nuki.auth.password.forgot` | [ForgotPasswordPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/ForgotPasswordPage.php) | `auth_users.password_reset.enabled = true` |
| GET | `/password/reset/{token}` | `nuki.auth.password.reset` | [ResetPasswordPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/ResetPasswordPage.php) | `auth_users.password_reset.enabled = true` |
| GET | `/email/verify` | `nuki.auth.verify.notice` | [VerifyEmailNoticePage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/VerifyEmailNoticePage.php) | `auth_users.email_verification.enabled = true` |
| GET | `/email/verify/{id}/{hash}` | `nuki.auth.verify` | [NukiVerifyEmailController](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Controllers/NukiVerifyEmailController.php) (extra `signed` middleware) | `auth_users.email_verification.enabled = true` |

The notice page reads `session('nuki.pending_verification_user_id')` (set on
registration / a login attempt by an unverified account) and offers a
throttled resend. The signed link marks the account verified and redirects to
`nuki.auth.login` with a `status` flash. Unverified accounts cannot complete
login: they are bounced back to the notice page.

## Authenticated routes (`auth:darvis-nuki`)

| Method | Path | Name | Component |
|---|---|---|---|
| POST | `/logout` | `nuki.auth.logout` | [NukiLogoutController](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Controllers/NukiLogoutController.php) |
| GET | `/profile` | `nuki.profile` | [ProfilePage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/ProfilePage.php) |
| GET | `/sub-users` | `nuki.sub-users.index` | [SubUsersIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUsersIndex.php) |
| GET | `/sub-users/{id}` (numeric) | `nuki.sub-users.show` | [SubUserShow](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUserShow.php) |

The logout endpoint redirects to `auth_users.redirect_after_logout`
(default `/nuki/login`).

## UI routes (auto-wrapped)

These live in [routes/web.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/web.php). When `auth_users.enabled`
is `true`, they get `auth:darvis-nuki` automatically. See
[Where a guest is sent](#where-a-guest-is-sent) for what that does to a visitor who is not signed
in.

| Method | Path | Name | Component |
|---|---|---|---|
| GET | `/` | `nuki.smartlocks.index` | [SmartlocksIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SmartlocksIndex.php) |
| GET | `/dashboard` | `nuki.dashboard` | [Dashboard](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Dashboard.php) |
| GET | `/activity` | `nuki.activity.index` | [ActivityTimeline](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/ActivityTimeline.php) |
| GET | `/smartlocks/{smartlockId}` (numeric) | `nuki.smartlocks.show` | [SmartlockShow](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SmartlockShow.php) |
| GET | `/webhooks` | `nuki.webhooks.index` | [WebhooksIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/WebhooksIndex.php) |
| GET | `/oauth/connect` | `nuki.oauth.connect` | [OAuthConnect](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/OAuthConnect.php) |
| GET | `/accounts` | `nuki.accounts.index` | [AccountsIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/AccountsIndex.php) |

URL prefix: `nuki.ui.prefix` (default `nuki`). Route name prefix: `nuki.`.

With `auth_users.enabled`, `/webhooks`, `/oauth/connect` and `/accounts` are for a main user
only: a sub user gets `403` and does not see the links.

## Where a guest is sent

`auth:darvis-nuki` is Laravel's own `auth` middleware. For a visitor who is not signed in it
redirects to the route named `login` of **your application**, not to `/nuki/login`. Without such a
route the visitor gets the error `Route [login] not defined.`

To send guests of the package pages to the package's login page, tell Laravel in
`bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn (Request $request) => $request->is('nuki', 'nuki/*')
        ? route('nuki.auth.login')
        : route('login'));
})
```

Use your own prefix in place of `nuki` when you changed `ui.prefix`, and leave out the
`route('login')` half when your application has no login of its own (return
`route('nuki.auth.login')` for everything).

## Redirect targets

- `auth_users.redirect_after_login` — default `/nuki`. Both the login without a code and the
  login after a code redirect here. It is a fixed path: a changed `ui.prefix` does not change it.
- `auth_users.redirect_after_logout` — default `/nuki/login`. Where
  `POST /logout` sends the user.

Change these to integrate with your own host application's chrome (e.g. send
users back to your own dashboard).

## Custom middleware

To wrap the auth routes in extra middleware (rate-limit, IP allow-list,
something app-specific):

```php
// config/nuki.php
'auth_users' => [
    'routes' => [
        'middleware' => ['web', 'throttle:5,1', \App\Http\Middleware\AllowOnlyOffice::class],
        'prefix'     => 'admin/nuki',  // also moves the URLs
    ],
],
```

`SetLocale` is appended automatically; you don't need to add it.

## Bypassing the bundled UI entirely

You can use the `darvis-nuki` guard from your own routes:

```php
Route::middleware('auth:darvis-nuki')->group(function () {
    Route::get('/my/dashboard', MyDashboard::class);
});
```

Or switch the bundled pages off (`NUKI_UI_ENABLED=false`) and keep the auth routes. The guard and
the login pages stay, and you write your own screens against the `NukiUser` model. Two things to
set then:

- `auth_users.redirect_after_login`, because `/nuki` no longer exists.
- `ui.layout`, to a layout of your own. `/profile`, `/sub-users` and `/sub-users/{id}` render in
  `ui.layout`, and the package's `nuki::layouts.app` links to the pages you switched off, so with
  the default layout those three pages fail with `Route [nuki.dashboard] not defined.`
