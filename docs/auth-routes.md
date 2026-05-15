# Auth routes

[← Documentation index](README.md)

When `NUKI_AUTH_USERS_ENABLED=true`, [routes/auth.php](../routes/auth.php) is
loaded by the service provider and the bundled UI routes from
[routes/web.php](../routes/web.php) are wrapped in `auth:darvis-nuki`. This
page is the canonical list.

## Common middleware

All routes registered under [routes/auth.php](../routes/auth.php) use:

- The middleware group from `nuki.auth_users.routes.middleware` (default `['web']`).
- [SetLocale](../src/Http/Middleware/SetLocale.php), always appended.

URL prefix: `nuki.auth_users.routes.prefix` (default `nuki`).
Route name prefix: `nuki.` (declared by the route group).

UI routes from [routes/web.php](../routes/web.php) use
`nuki.ui.middleware` (default `['web']`) and — when `auth_users.enabled` is on
— also get `auth:darvis-nuki` and `SetLocale` appended.

## Guest routes (`guest:darvis-nuki`)

These return 302 to `auth_users.redirect_after_login` when an authenticated
user hits them.

| Method | Path | Name | Component | Conditional on |
|---|---|---|---|---|
| GET | `/login` | `nuki.auth.login` | [LoginPage](../src/Livewire/Auth/LoginPage.php) | — |
| GET | `/login/otp` | `nuki.auth.otp` | [LoginOtpPage](../src/Livewire/Auth/LoginOtpPage.php) | — |
| GET | `/register` | `nuki.auth.register` | [RegisterPage](../src/Livewire/Auth/RegisterPage.php) | `auth_users.register_enabled = true` |
| GET | `/password/forgot` | `nuki.auth.password.forgot` | [ForgotPasswordPage](../src/Livewire/Auth/ForgotPasswordPage.php) | `auth_users.password_reset.enabled = true` |
| GET | `/password/reset/{token}` | `nuki.auth.password.reset` | [ResetPasswordPage](../src/Livewire/Auth/ResetPasswordPage.php) | `auth_users.password_reset.enabled = true` |
| GET | `/email/verify` | `nuki.auth.verify.notice` | [VerifyEmailNoticePage](../src/Livewire/Auth/VerifyEmailNoticePage.php) | `auth_users.email_verification.enabled = true` |
| GET | `/email/verify/{id}/{hash}` | `nuki.auth.verify` | [NukiVerifyEmailController](../src/Http/Controllers/NukiVerifyEmailController.php) (extra `signed` middleware) | `auth_users.email_verification.enabled = true` |

The notice page reads `session('nuki.pending_verification_user_id')` (set on
registration / a login attempt by an unverified account) and offers a
throttled resend. The signed link marks the account verified and redirects to
`nuki.auth.login` with a `status` flash. Unverified accounts cannot complete
login: they are bounced back to the notice page.

## Authenticated routes (`auth:darvis-nuki`)

| Method | Path | Name | Component |
|---|---|---|---|
| POST | `/logout` | `nuki.auth.logout` | [NukiLogoutController](../src/Http/Controllers/NukiLogoutController.php) |
| GET | `/profile` | `nuki.profile` | [ProfilePage](../src/Livewire/ProfilePage.php) |
| GET | `/sub-users` | `nuki.sub-users.index` | [SubUsersIndex](../src/Livewire/SubUsersIndex.php) |
| GET | `/sub-users/{id}` (numeric) | `nuki.sub-users.show` | [SubUserShow](../src/Livewire/SubUserShow.php) |

The logout endpoint redirects to `auth_users.redirect_after_logout`
(default `/nuki/login`).

## UI routes (auto-wrapped)

These live in [routes/web.php](../routes/web.php). When `auth_users.enabled`
is `true`, they are appended with `auth:darvis-nuki` automatically, so an
anonymous visitor is redirected to `/nuki/login`.

| Method | Path | Name | Component |
|---|---|---|---|
| GET | `/` | `nuki.smartlocks.index` | [SmartlocksIndex](../src/Livewire/SmartlocksIndex.php) |
| GET | `/dashboard` | `nuki.dashboard` | [Dashboard](../src/Livewire/Dashboard.php) |
| GET | `/activity` | `nuki.activity.index` | [ActivityTimeline](../src/Livewire/ActivityTimeline.php) |
| GET | `/smartlocks/{smartlockId}` (numeric) | `nuki.smartlocks.show` | [SmartlockShow](../src/Livewire/SmartlockShow.php) |
| GET | `/webhooks` | `nuki.webhooks.index` | [WebhooksIndex](../src/Livewire/WebhooksIndex.php) |
| GET | `/oauth/connect` | `nuki.oauth.connect` | [OAuthConnect](../src/Livewire/OAuthConnect.php) |
| GET | `/accounts` | `nuki.accounts.index` | [AccountsIndex](../src/Livewire/AccountsIndex.php) |

URL prefix: `nuki.ui.prefix` (default `nuki`). Route name prefix: `nuki.`.

## Redirect targets

- `auth_users.redirect_after_login` — default `/nuki`. Both the password-only
  login path and the OTP completion path redirect here.
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

Or disable the bundled UI entirely (`NUKI_UI_ENABLED=false`) and only keep
the auth routes. The package will still register the guard and you can write
your own screens against the `NukiUser` model.
