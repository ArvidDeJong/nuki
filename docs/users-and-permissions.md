---
title: "Users and permissions"
nav_order: 7
description: "The optional users of darvis/nuki: the darvis-nuki guard, main and sub users, attaching accounts, permissions per smartlock, time windows and the login code."
---

# Users and permissions

This page covers the package's **own** end-user login system: the
`darvis-nuki` auth guard, the `NukiUser` model, main/sub-user hierarchy,
per-smartlock permissions, the validity window and the weekday bitmask. It is
completely independent from how the package authenticates against the NUKI
Web API — for that, see [NUKI API authentication](nuki-api-authentication.md).

Everything on this page is opt-in. Without `NUKI_AUTH_USERS_ENABLED=true` the guard and the routes
are not registered. The tables are created by `php artisan migrate` either way and stay empty.

A guard is Laravel's name for one way of signing in, with its own user model and session; see the
[Laravel documentation on guards](https://laravel.com/docs/authentication#adding-custom-guards).
The package's guard is separate from your application's users: a `NukiUser` is not an
`App\Models\User`.

## From nothing to a signed in main user

1. Set `NUKI_AUTH_USERS_ENABLED=true` in `.env` (the word `true`, not `1`) and run
   `php artisan config:clear` and `php artisan migrate`.
2. Make sure your application can send mail. Signing in needs two mails: a confirmation link the
   first time, and a login code every time.
3. Create the user: `php artisan nuki:user-create --email=admin@example.com --name=Admin`. The
   command asks for the password. Add `--account=<account_key>` for every existing account the
   user works in.
4. Open `/nuki/login` and sign in. The first attempt sends a confirmation link and shows "Confirm
   your email address"; open the link, sign in again and enter the emailed code.
5. Create the NUKI accounts on `/nuki/accounts`; the user who creates one is attached to it. For
   accounts that already exist, see [Attach a main user to an account](#attach-a-main-user-to-an-account).

## The model in one picture

```
┌── NukiUser (main, parent_id = NULL) ──────────────────────────────────────┐
│   accessible accounts: the accounts attached to this user                  │
│   accessible smartlocks: WILDCARD (every smartlock the account exposes)    │
│                                                                            │
│   ├── NukiUser (sub, parent_id = main.id)                                  │
│   │   accessible accounts: own ∪ parent's accounts                          │
│   │   accessible smartlocks: ONLY pivot rows on nuki_user_smartlock         │
│   │                          (with permissions, window, weekday bitmask)    │
│   └── ...                                                                  │
└────────────────────────────────────────────────────────────────────────────┘
```

- **Main users** are wildcard: they see every smartlock in every account they
  belong to.
- **Sub users** inherit *account access* from their parent, but never inherit
  *smartlock access* — every smartlock they can touch is explicitly listed on
  `nuki_user_smartlock` with `can_lock` / `can_unlock` / `can_view_logs` /
  `can_manage_auths` flags.

## What switching it on does

Enable in `.env`:

```dotenv
NUKI_AUTH_USERS_ENABLED=true
NUKI_AUTH_USERS_MAIL_FROM_ADDRESS=noreply@yourapp.example
NUKI_AUTH_USERS_MAIL_FROM_NAME="Your App"
```

When this flag is on, [NukiServiceProvider](https://github.com/ArvidDeJong/nuki/blob/main/src/NukiServiceProvider.php):

1. Calls [AuthConfigRegistrar::register()](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/Users/AuthConfigRegistrar.php),
   which merges into `auth.guards` / `auth.providers` at runtime:

   ```php
   'guards' => [
       'darvis-nuki' => ['driver' => 'session', 'provider' => 'darvis-nuki-users'],
   ],
   'providers' => [
       'darvis-nuki-users' => ['driver' => 'eloquent', 'model' => \Darvis\Nuki\Models\NukiUser::class],
   ],
   ```

   The registrar is **idempotent**: if you have already defined the guard or
   provider in your own `config/auth.php`, your definition wins. This matters
   when another package's service provider reads `auth.guards` before
   `NukiServiceProvider::register()` runs — in that case, define them
   explicitly in `config/auth.php` and the runtime merge becomes a no-op.

2. Loads [routes/auth.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/auth.php) — see [Auth routes](auth-routes.md).

3. Puts every UI route from [routes/web.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/web.php) behind the
   `darvis-nuki` guard. A visitor who is not signed in is redirected to `/nuki/login`; see
   [Where a guest is sent](auth-routes.md#where-a-guest-is-sent) for versions before 1.3.0.

4. Registers the auth Livewire components (`nuki.auth.login`,
   `nuki.auth.otp`, `nuki.auth.register`, `nuki.auth.forgot-password`,
   `nuki.auth.reset-password`, `nuki.auth.verify-email`, `nuki.profile`,
   `nuki.sub-users-index`, `nuki.sub-user-show`).

Constants for hard-coded use:

```php
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;

AuthConfigRegistrar::GUARD;    // 'darvis-nuki'
AuthConfigRegistrar::PROVIDER; // 'darvis-nuki-users'
```

## The `NukiUser` model

Source: [src/Models/NukiUser.php](https://github.com/ArvidDeJong/nuki/blob/main/src/Models/NukiUser.php). Table:
[nuki_users](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000000_create_nuki_users_table.php).

### Columns

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `parent_id` | bigint, nullable, FK → `nuki_users.id` | `NULL` = main user, otherwise sub. |
| `name` | string | Display name. |
| `email` | string, unique | Login identifier. |
| `email_verified_at` | timestamp, nullable | Set when the user opens the confirmation link. While `auth_users.email_verification.enabled` is `true` (the default), a user without it cannot sign in. Not mass assignable. |
| `password` | string | Hashed via the `hashed` cast. |
| `remember_token` | rememberToken | Standard Laravel. |
| `two_factor_enabled` | bool, default `true` | Stored, and **not read at login**: while `auth_users.otp.enabled` is `true` every user gets a login code, whatever this column says. |
| `is_active` | bool, default `true` | When `false`, the user cannot log in (the `LoginPage` filters on this). |
| `last_login_at` | timestamp, nullable | Updated on successful login. |
| `locale` | string(5), nullable | Preferred UI language, set on the profile page. See [Localization](ui-and-localization.md#localization) for the fallbacks. |
| `timestamps` | | |

### Relations

- `parent(): BelongsTo` — the main user this sub belongs to, or `null`.
- `subUsers(): HasMany` — all subs of this main user.
- `accounts(): BelongsToMany` — via `nuki_user_account`, with `role` pivot
  (`'owner'`, `'member'`, or anything you store there).
- `smartlockAccess(): HasMany` — `nuki_user_smartlock` rows. Only meaningful
  for sub users.
- `otpCodes(): HasMany` — issued login OTPs.

### Hierarchy helpers

```php
$user->isMain();   // parent_id === null
$user->isSub();    // parent_id !== null

$user->accessibleAccounts();
//  Collection<NukiAccount>
//  - For mains: their own active account pivots.
//  - For subs: own pivots ∪ parent's active accounts. Deduped by id.

$user->accessibleSmartlockIds(int $nukiAccountId): ?array;
//  - Main users → null (wildcard: trust the account).
//  - Sub users  → array of smartlock_id values where the pivot is active
//                 AND currently allowed (window + weekday match now).

$user->canAccessSmartlock(int $accountId, int $smartlockId, string $permission): bool;
//  - Mains → always true.
//  - Subs  → look up the pivot, check is_active, isCurrentlyAllowed(),
//            hasPermission($permission).
```

## The permission matrix

Source: [NukiUserSmartlockAccess](https://github.com/ArvidDeJong/nuki/blob/main/src/Models/NukiUserSmartlockAccess.php).
Table: [nuki_user_smartlock](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000400_create_nuki_user_smartlock_table.php).

Unique on `(nuki_user_id, nuki_account_id, smartlock_id)` — a sub user has at
most one pivot per (account, lock) combination.

### Columns

| Column | Type | Notes |
|---|---|---|
| `nuki_user_id` | FK → `nuki_users.id` | The sub user this rule belongs to. |
| `nuki_account_id` | FK → `nuki_accounts.id` | Which NUKI account exposes this lock. |
| `smartlock_id` | unsignedBigInt | NUKI smartlock id from the Web API. **Not** a foreign key — smartlocks live in NUKI, not in your DB. |
| `can_lock` | bool, default `false` | |
| `can_unlock` | bool, default `false` | |
| `can_view_logs` | bool, default `false` | |
| `can_manage_auths` | bool, default `false` | Allowed to create/edit/delete keypad codes and other authorizations on this lock. |
| `allowed_from` | timestamp, nullable | Start of the validity window. `NULL` = no lower bound. |
| `allowed_until` | timestamp, nullable | End of the validity window. `NULL` = no upper bound. |
| `allowed_weekdays` | tinyint, nullable | [Weekday bitmask](#weekday-bitmask). `NULL` or `0` = no weekday restriction. |
| `is_active` | bool, default `true` | Master switch for this row. |
| `timestamps` | | |

### `isCurrentlyAllowed(?CarbonImmutable $now = null): bool`

Returns `true` only when all of:

1. `is_active` is true.
2. `allowed_from` is null **or** in the past.
3. `allowed_until` is null **or** in the future.
4. `allowed_weekdays` is null/0 **or** matches today via
   [WeekdayBitmask::matchesDate()](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/WeekdayBitmask.php).

### `hasPermission(string $permission): bool`

Permission strings: `'lock'`, `'unlock'`, `'view_logs'`, `'manage_auths'`
(constant: `NukiUserSmartlockAccess::PERMISSIONS`). The method maps each
string to the matching `can_*` column. Anything outside the four permitted
values returns `false`.

### Weekday bitmask

Source: [WeekdayBitmask](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/WeekdayBitmask.php). A bitmask is one
number that holds seven yes/no answers, one bit per day. The helper takes Dutch day codes:

| Code | Day | Bit |
|---|---|---|
| `ma` | Monday | 64 |
| `di` | Tuesday | 32 |
| `wo` | Wednesday | 16 |
| `do` | Thursday | 8 |
| `vr` | Friday | 4 |
| `za` | Saturday | 2 |
| `zo` | Sunday | 1 |

```php
use Darvis\Nuki\Support\WeekdayBitmask;

WeekdayBitmask::fromDays(['ma', 'wo', 'vr']);   // 64 | 16 | 4 = 84
WeekdayBitmask::toDays(84);                      // ['ma', 'wo', 'vr']
WeekdayBitmask::matchesDate(84, now());          // true on Mon/Wed/Fri
```

`fromDays()` returns `null` for an empty list, which means "no restriction". The bundled smartlock
page sends the same number to NUKI as `allowedWeekDays` when it saves a keypad code (see the
[API reference](api-reference.md#smartlockauths)), so one helper serves both.

## Authorizing access in your code

The trait [AuthorizesSmartlockAccess](https://github.com/ArvidDeJong/nuki/blob/main/src/Concerns/AuthorizesSmartlockAccess.php)
is mixed into the bundled Livewire components and is the canonical way to
gate access. Use it from your own controllers / components too.

In `app/Http/Controllers/MyOwnLockController.php`:

```php
namespace App\Http\Controllers;

use Darvis\Nuki\Concerns\AuthorizesSmartlockAccess;
use Darvis\Nuki\Facades\Nuki;

class MyOwnLockController extends Controller
{
    use AuthorizesSmartlockAccess;

    public function unlock(int $smartlockId)
    {
        $accountKey = session('nuki.current_account', 'default');

        $this->assertCan($accountKey, $smartlockId, 'unlock'); // aborts 403 if not allowed

        Nuki::as($accountKey)->smartlocks()->unlock($smartlockId);
    }
}
```

The trait's surface:

| Method | What it does |
|---|---|
| `userAccessibleSmartlockIds(string $accountKey): ?array` | `null` = no auth user or a main user (caller trusts the full list); array of ints = explicit allow-list for a sub user, empty when the account key has no `nuki_accounts` row. Use this to filter `Nuki::smartlocks()->all()` for sub users. |
| `userCanAccessSmartlock(string $accountKey, int $smartlockId, string $permission): bool` | `true` when no auth user is active, or the user is a main, or the sub has a matching active pivot row. |
| `assertCan(string $accountKey, int $smartlockId, string $permission): void` | Calls `userCanAccessSmartlock`; `abort(403)` on `false`. |
| `currentNukiAuthUser(): ?NukiUser` | The user behind the `darvis-nuki` guard, or `null` when the feature is off. |

**Always re-check in the action handler**, even if the UI hides the button —
the trait does this for `SmartlocksIndex`, `SmartlockShow` and others. The
UI is a hint; the server is the line of defence.

What the bundled pages enforce for a package user:

- `SmartlockShow` loads the lock only with an active row for it, the activity tab only with
  `view_logs` and the authorizations tab (keypad codes included) only with `manage_auths`;
  anything else is a `403`. The `smartlockId` and `accountKey` properties are `#[Locked]`, so the
  browser cannot point a mounted page at another lock or account.
- The account switcher lists the user's accessible accounts only, and switching to any other key
  than `default` or one of those is a `403`. The same check runs in every
  `nuki-account-changed` handler, because the browser can send that event too.
- `AccountsIndex` (API tokens), `WebhooksIndex` and `OAuthConnect` are for a main user: a sub user
  gets a `403` on the page and on every action, and the navigation hides the links.
- `Dashboard` counts and lists only the locks a sub user has an active row for, and its recent
  activity only has entries of locks with `view_logs`.
- `ActivityTimeline` only shows entries of locks with `view_logs`, only offers those locks in its
  filter, and answers `403` for a `?lock=` filter on any other lock.
- `SubUsersIndex` and `SubUserShow` are for a main user, on every request. The sub user editor
  only offers the accounts the main user is attached to, and giving access on another account, or
  changing a row of somebody else's sub user, is refused.

## The emailed login code (OTP)

[LoginPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginPage.php) handles the password step. On
a valid password it:

1. Refuses a user who has not confirmed their email address (while
   `auth_users.email_verification.enabled` is `true`): it mails a new confirmation link and shows
   the notice page.
2. Checks `auth_users.otp.enabled`. When it is `false`, the user is signed in immediately, and that
   goes for everyone. There is **no per user switch**: `two_factor_enabled` and `--no-2fa` are
   stored and not read here.
3. Throttles via `LoginThrottle` (`auth_users.otp.rate_limit.*`): at most `max_per_window` codes
   per email address and IP per window.
4. Generates a code through `NukiUserOtpCode::generate(...)`. The plain code
   is mailed; only the hash is stored on `nuki_user_otp_codes`.
5. Mails [NukiLoginOtpMail](https://github.com/ArvidDeJong/nuki/blob/main/src/Mail/NukiLoginOtpMail.php) in the user's
   `locale`.
6. Keeps the pending login in the session and redirects to `/nuki/login/otp`. The pending login
   is valid for 15 minutes.

[LoginOtpPage](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/Auth/LoginOtpPage.php) validates the code
against the stored hash, checks `expires_at`, marks `consumed_at`, and
finishes the login on success. Throttles further attempts.

Relevant config: `auth_users.otp.enabled`, `auth_users.otp.expiry_minutes`,
`auth_users.otp.length`, `auth_users.otp.rate_limit.*`. See
[Configuration reference → auth_users.\*](configuration.md#auth_users--package-user-authentication).

### Storage

[nuki_user_otp_codes](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000100_create_nuki_user_otp_codes_table.php):
`code_hash`, `purpose` (default `'login'`), `expires_at`, `consumed_at`,
`ip`, `user_agent`. Indexed on `(nuki_user_id, consumed_at)` and
`expires_at`.

## Password reset

[NukiPasswordResetService](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/Users/NukiPasswordResetService.php)
runs the flow:

- `sendResetLink(string $email)` — finds the active user, generates a
  64-char token, hashes it into `nuki_password_resets`, mails
  [NukiPasswordResetMail](https://github.com/ArvidDeJong/nuki/blob/main/src/Mail/NukiPasswordResetMail.php).
- `findUserForToken(string $email, string $token)` — used by the reset page
  to validate the link before showing the form.
- `reset(string $email, string $token, string $newPassword)` — updates the
  password, refreshes the `remember_token`, deletes the reset row.

The service deliberately does **not** use Laravel's `PasswordBroker`, which
avoids `config/auth.passwords` merging issues across Laravel 11/12/13. Token
lifetime is controlled by `auth_users.password_reset.token_lifetime_minutes`
(default 60).

Table:
[nuki_password_resets](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000200_create_nuki_password_resets_table.php).
Primary key is `email` (one outstanding reset per address).

## Creating and managing users

### First main user — CLI

```bash
php artisan nuki:user-create \
    --email=admin@example.com \
    --name="Admin" \
    --password=secret123
```

Every option you leave out is asked for. `--no-2fa` stores `two_factor_enabled = false` on the
user; that column is not read at login, so the user still gets a login code while
`auth_users.otp.enabled` is `true`.

The new user has no confirmed email address. With the default settings the first sign in mails a
confirmation link, and the user can only sign in after opening it.

The command is the way to create a main user. The `/nuki/register` page does the same for any
visitor, which is why it is off by default: a main user may operate every lock. Switch it on with
`NUKI_AUTH_USERS_REGISTER_ENABLED=true` only when every visitor with a mailbox may do that.

Source: [NukiUserCreateCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiUserCreateCommand.php).
Always creates a **main** user (`parent_id = null`, `is_active = true`).

### Sub users in the UI

Once the main user logs in, `/nuki/sub-users` lists their sub users and
`/nuki/sub-users/{id}` is the editor for one of them: a row per smartlock with the account it is
on, the four permissions, the period and the weekday grid. The editor offers the accounts the
main user is attached to, so attach the main user first. Components:
[SubUsersIndex](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUsersIndex.php),
[SubUserShow](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/SubUserShow.php).

A new sub user has no confirmed email address either: the first sign in mails the confirmation
link. The "two factor" switch in the sub user form fills `two_factor_enabled`, which is not read
at login.

### Sub users in code

```php
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\WeekdayBitmask;

$main = NukiUser::firstWhere('email', 'admin@example.com');

$sub = NukiUser::create([
    'parent_id'          => $main->id,
    'name'               => 'Cleaning crew',
    'email'              => 'crew@example.com',
    'password'           => 'temp-password-they-will-reset',
    'two_factor_enabled' => true,
    'is_active'          => true,
]);

// Subs inherit account access from the parent, but you can still grant
// extra direct accounts via the pivot if you want.

$account = NukiAccount::firstWhere('account_key', 'tenant-42');

NukiUserSmartlockAccess::create([
    'nuki_user_id'     => $sub->id,
    'nuki_account_id'  => $account->id,
    'smartlock_id'     => 17_123_456_789,
    'can_lock'         => true,
    'can_unlock'       => true,
    'can_view_logs'    => true,
    'can_manage_auths' => false,
    'allowed_from'     => now()->startOfMonth(),
    'allowed_until'    => now()->endOfMonth(),
    'allowed_weekdays' => WeekdayBitmask::fromDays(['ma', 'wo', 'vr']),
    'is_active'        => true,
]);
```

That sub user will now see exactly one lock in the bundled UI, can lock and
unlock it but cannot manage keypad codes, and only between the start and end
of this month, and only on Mondays, Wednesdays and Fridays.

## Attach a main user to an account

The table `nuki_user_account` links a user to a NUKI account, with a free-form `role` column
(default `member`; the package does not read the role). Source:
[migration](https://github.com/ArvidDeJong/nuki/blob/main/database/migrations/2026_05_12_000300_create_nuki_user_account_table.php).

A package user only sees, and can only switch to, the accounts they are attached to (plus
`default`). Since version 1.3.0 the package attaches in two places, both with the role `owner`
(`NukiAccount::ROLE_OWNER`):

- A main user who creates an account on the `/nuki/accounts` page is attached to it.
- `php artisan nuki:user-create --account=office --account=workshop` attaches the new main user
  to existing accounts. An unknown key is an error, and nothing is created then.

Everything else is still yours to attach: an account made before 1.3.0 or in code, a second main
user on an existing account, a sub user who needs an account their parent does not have. **If you
attached users by hand before 1.3.0, nothing changes for you**; those rows are left as they are.
In `php artisan tinker` or in a seeder:

```php
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;

$user = NukiUser::firstWhere('email', 'admin@example.com');
$account = NukiAccount::findByKey('tenant-42');

$user->accounts()->syncWithoutDetaching([
    $account->id => ['role' => 'owner'],
]);
```

The account shows up in the user's switcher on the next page load. Only accounts with `is_active`
true count.

Sub users inherit account access from their parent via
`NukiUser::accessibleAccounts()`, so you usually only need to assign accounts
to mains. Direct sub assignments are still respected if you create them.

## Account switching at runtime

[UsesNukiAccount](https://github.com/ArvidDeJong/nuki/blob/main/src/Concerns/UsesNukiAccount.php) trait, used by
[AccountSwitcher](https://github.com/ArvidDeJong/nuki/blob/main/src/Livewire/AccountSwitcher.php) and every account-aware
component:

- Stores the active account key in `session('nuki.current_account')`.
- `AccountSwitcher` dispatches the `nuki-account-changed` Livewire event.
- Listening components use `#[On('nuki-account-changed')]` and reset their
  state when the user switches.
- For a package user the trait falls back to the first accessible account, or to `'default'` when
  the user has none.
- A package user can only switch to `default` or to an accessible account; anything else is a
  `403`.
