# Users and permissions

[← Documentation index](README.md)

This page covers the package's **own** end-user login system: the
`darvis-nuki` auth guard, the `NukiUser` model, main/sub-user hierarchy,
per-smartlock permissions, the validity window and the weekday bitmask. It is
completely independent from how the package authenticates against the NUKI
Web API — for that, see [NUKI API authentication](nuki-api-authentication.md).

Everything on this page is opt-in. Without `NUKI_AUTH_USERS_ENABLED=true`,
none of the tables, routes or guards are touched.

## TL;DR

```
┌── NukiUser (main, parent_id = NULL) ──────────────────────────────────────┐
│   accessible accounts: own ∪ (own subs' parent accounts is N/A here)       │
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

## 1. Feature flag

Enable in `.env`:

```dotenv
NUKI_AUTH_USERS_ENABLED=true
NUKI_AUTH_USERS_MAIL_FROM_ADDRESS=noreply@yourapp.example
NUKI_AUTH_USERS_MAIL_FROM_NAME="Your App"
```

When this flag is on, [NukiServiceProvider](../src/NukiServiceProvider.php):

1. Calls [AuthConfigRegistrar::register()](../src/Auth/Users/AuthConfigRegistrar.php),
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

2. Loads [routes/auth.php](../routes/auth.php) — see [Auth routes](auth-routes.md).

3. Wraps every UI route from [routes/web.php](../routes/web.php) in the
   `auth:darvis-nuki` middleware so unauthenticated visitors are redirected to
   `/nuki/login`.

4. Registers the auth Livewire components (`nuki.auth.login`,
   `nuki.auth.otp`, `nuki.auth.register`, `nuki.auth.forgot-password`,
   `nuki.auth.reset-password`, `nuki.profile`, `nuki.sub-users-index`,
   `nuki.sub-user-show`).

Constants for hard-coded use:

```php
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;

AuthConfigRegistrar::GUARD;    // 'darvis-nuki'
AuthConfigRegistrar::PROVIDER; // 'darvis-nuki-users'
```

## 2. The `NukiUser` model

Source: [src/Models/NukiUser.php](../src/Models/NukiUser.php). Table:
[nuki_users](../database/migrations/2026_05_12_000000_create_nuki_users_table.php).

### Columns

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `parent_id` | bigint, nullable, FK → `nuki_users.id` | `NULL` = main user, otherwise sub. |
| `name` | string | Display name. |
| `email` | string, unique | Login identifier. |
| `email_verified_at` | timestamp, nullable | Reserved; the package does not currently force email verification before login. |
| `password` | string | Hashed via the `hashed` cast. |
| `remember_token` | rememberToken | Standard Laravel. |
| `two_factor_enabled` | bool, default `true` | Per-user 2FA switch. Combined with `auth_users.otp.enabled` globally. |
| `is_active` | bool, default `true` | When `false`, the user cannot log in (the `LoginPage` filters on this). |
| `last_login_at` | timestamp, nullable | Updated on successful login. |
| `locale` | string(5), nullable | Preferred UI locale; falls back to host app / `nuki.ui.default_locale`. |
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

## 3. The permission matrix

Source: [NukiUserSmartlockAccess](../src/Models/NukiUserSmartlockAccess.php).
Table: [nuki_user_smartlock](../database/migrations/2026_05_12_000400_create_nuki_user_smartlock_table.php).

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
| `allowed_weekdays` | tinyint, nullable | NUKI weekday bitmask. `NULL` or `0` = no weekday restriction. |
| `is_active` | bool, default `true` | Master switch for this row. |
| `timestamps` | | |

### `isCurrentlyAllowed(?CarbonImmutable $now = null): bool`

Returns `true` only when all of:

1. `is_active` is true.
2. `allowed_from` is null **or** in the past.
3. `allowed_until` is null **or** in the future.
4. `allowed_weekdays` is null/0 **or** matches today via
   [WeekdayBitmask::matchesDate()](../src/Support/WeekdayBitmask.php).

### `hasPermission(string $permission): bool`

Permission strings: `'lock'`, `'unlock'`, `'view_logs'`, `'manage_auths'`
(constant: `NukiUserSmartlockAccess::PERMISSIONS`). The method maps each
string to the matching `can_*` column. Anything outside the four permitted
values returns `false`.

### Weekday bitmask

Source: [WeekdayBitmask](../src/Support/WeekdayBitmask.php). Follows the NUKI
Web API convention (the same field is named `allowedWeekDays` there):

| Day | Bit |
|---|---|
| ma | 64 |
| di | 32 |
| wo | 16 |
| do | 8 |
| vr | 4 |
| za | 2 |
| zo | 1 |

```php
use Darvis\Nuki\Support\WeekdayBitmask;

WeekdayBitmask::fromDays(['ma', 'wo', 'vr']);   // 64 | 16 | 4 = 84
WeekdayBitmask::toDays(84);                      // ['ma', 'wo', 'vr']
WeekdayBitmask::matchesDate(84, now());          // true on Mon/Wed/Fri
```

The same bitmask is what NUKI expects in the `allowedWeekDays` field on
authorizations (see [api-reference.md](api-reference.md#smartlockauths)).
That is intentional — the package's own permissions are deliberately
encoded with NUKI's wire format so the two stay aligned.

## 4. Authorizing access in your code

The trait [AuthorizesSmartlockAccess](../src/Concerns/AuthorizesSmartlockAccess.php)
is mixed into the bundled Livewire components and is the canonical way to
gate access. Use it from your own controllers / components too.

```php
use Darvis\Nuki\Concerns\AuthorizesSmartlockAccess;

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
| `userAccessibleSmartlockIds(string $accountKey): ?array` | `null` = no auth user / main user / unknown account (caller trusts the full list); array of ints = explicit allow-list. Use this to filter `Nuki::smartlocks()->all()` for sub users. |
| `userCanAccessSmartlock(string $accountKey, int $smartlockId, string $permission): bool` | `true` when no auth user is active, or the user is a main, or the sub has a matching active pivot row. |
| `assertCan(string $accountKey, int $smartlockId, string $permission): void` | Calls `userCanAccessSmartlock`; `abort(403)` on `false`. |
| `currentNukiAuthUser(): ?NukiUser` | The user behind the `darvis-nuki` guard, or `null` when the feature is off. |

**Always re-check in the action handler**, even if the UI hides the button —
the trait does this for `SmartlocksIndex`, `SmartlockShow` and others. The
UI is a hint; the server is the line of defence.

## 5. Email OTP (2FA)

[LoginPage](../src/Livewire/Auth/LoginPage.php) handles the password step. On
a valid password it:

1. Checks `nuki.auth_users.otp.enabled` (global) AND `$user->two_factor_enabled`
   (per-user). If either is `false`, the user is logged in immediately.
2. Throttles via `LoginThrottle` (`auth_users.otp.rate_limit.*`).
3. Generates a code through `NukiUserOtpCode::generate(...)`. The plain code
   is mailed; only the hash is stored on `nuki_user_otp_codes`.
4. Mails [NukiLoginOtpMail](../src/Mail/NukiLoginOtpMail.php) in the user's
   `locale`.
5. Stashes pending state in the session and redirects to `/nuki/login/otp`.

[LoginOtpPage](../src/Livewire/Auth/LoginOtpPage.php) validates the code
against the stored hash, checks `expires_at`, marks `consumed_at`, and
finishes the login on success. Throttles further attempts.

Relevant config: `auth_users.otp.enabled`, `auth_users.otp.expiry_minutes`,
`auth_users.otp.length`, `auth_users.otp.rate_limit.*`. See
[Configuration reference → auth_users.\*](configuration.md#auth_users--package-user-authentication).

### Storage

[nuki_user_otp_codes](../database/migrations/2026_05_12_000100_create_nuki_user_otp_codes_table.php):
`code_hash`, `purpose` (default `'login'`), `expires_at`, `consumed_at`,
`ip`, `user_agent`. Indexed on `(nuki_user_id, consumed_at)` and
`expires_at`.

## 6. Password reset

[NukiPasswordResetService](../src/Auth/Users/NukiPasswordResetService.php)
runs the flow:

- `sendResetLink(string $email)` — finds the active user, generates a
  64-char token, hashes it into `nuki_password_resets`, mails
  [NukiPasswordResetMail](../src/Mail/NukiPasswordResetMail.php).
- `findUserForToken(string $email, string $token)` — used by the reset page
  to validate the link before showing the form.
- `reset(string $email, string $token, string $newPassword)` — updates the
  password, refreshes the `remember_token`, deletes the reset row.

The service deliberately does **not** use Laravel's `PasswordBroker`, which
avoids `config/auth.passwords` merging issues across Laravel 11/12/13. Token
lifetime is controlled by `auth_users.password_reset.token_lifetime_minutes`
(default 60).

Table:
[nuki_password_resets](../database/migrations/2026_05_12_000200_create_nuki_password_resets_table.php).
Primary key is `email` (one outstanding reset per address).

## 7. Creating and managing users

### First main user — CLI

```bash
php artisan nuki:user-create \
    --email=admin@example.com \
    --name="Admin" \
    --password=secret123
```

Add `--no-2fa` to disable email OTP for this user.

Source: [NukiUserCreateCommand](../src/Console/Commands/NukiUserCreateCommand.php).
Always creates a **main** user (`parent_id = null`, `is_active = true`).

### Sub-users — UI

Once the main user logs in, `/nuki/sub-users` lists their subs and
`/nuki/sub-users/{id}` is the per-sub editor (account assignments,
smartlock pivots, weekday grid). Components:
[SubUsersIndex](../src/Livewire/SubUsersIndex.php),
[SubUserShow](../src/Livewire/SubUserShow.php).

### Sub-users — programmatic

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

## 8. Account binding (`nuki_user_account`)

Pivot for user ↔ account, with a free-form `role` column (default `member`).
Source: [migration](../database/migrations/2026_05_12_000300_create_nuki_user_account_table.php).

Use the relation to attach:

```php
$user->accounts()->syncWithoutDetaching([
    $account->id => ['role' => 'owner'],
]);
```

Sub users inherit account access from their parent via
`NukiUser::accessibleAccounts()`, so you usually only need to assign accounts
to mains. Direct sub assignments are still respected if you create them.

## 9. Account switching at runtime

[UsesNukiAccount](../src/Concerns/UsesNukiAccount.php) trait, used by
[AccountSwitcher](../src/Livewire/AccountSwitcher.php) and every account-aware
component:

- Stores the active account key in `session('nuki.current_account')`.
- `AccountSwitcher` dispatches the `nuki-account-changed` Livewire event.
- Listening components use `#[On('nuki-account-changed')]` and reset their
  state when the user switches.
- The trait also gracefully falls back to `'default'` when the auth user has
  no accessible accounts.
