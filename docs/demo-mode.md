# Demo mode

[← Documentation index](README.md)

Demo mode lets you exercise the entire bundled UI — dashboard, smartlocks,
activity timeline, authorizations, webhooks — without a real NUKI account.
Useful for screenshots, screen recordings, sales demos and local development.

**Never enable in production.** Every outbound NUKI call is replaced with
canned data.

## Enable

```dotenv
NUKI_DEMO=true
```

This flips `nuki.demo.enabled` and triggers two things at boot in
[NukiServiceProvider](../src/NukiServiceProvider.php):

1. If `nuki.token` is empty, it's stubbed to `'demo-token'` so the bearer
   authenticator does not throw before the HTTP fake intercepts.
2. [DemoFixtures::register()](../src/Support/DemoFixtures.php) installs
   `Http::fake(['api.nuki.io/*' => closure])` returning canned responses.

## What gets faked

Every endpoint the package currently talks to:

- `GET  /smartlock` — list (four locks: front door, side door, garage, office).
- `GET  /smartlock/{id}` — single lock.
- `GET  /smartlock/{id}/log` — per-lock activity.
- `GET  /smartlock/log` — account-wide activity.
- `GET  /smartlock/{id}/auth` — per-lock authorizations.
- `GET  /smartlock/auth` — account-wide authorizations.
- `POST /smartlock/{id}/action` — accepted; no state change persisted.
- `POST /smartlock/{id}` — name updates accepted.
- `POST /smartlock/{id}/sync` — accepted.
- `GET  /account` — account info.
- `GET  /api/notification` — webhook subscriptions.
- `PUT  /api/notification` — accepts new subscription.
- `DELETE /api/notification/{id}` — accepts removal.
- `POST /oauth/token` — returns a demo token so the OAuth UI is browsable.

The fixture data is intentionally realistic: Dutch names, plausible battery
levels, **one lock with `batteryCritical: true`** so the warning badge has
something to highlight, varied log triggers.

## Seed the multi-account switcher

The `AccountSwitcher` is empty unless there's data in `nuki_accounts`. Seed
four demo accounts:

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

To customise the seeded accounts, publish the seeder first and edit it in
your app:

```bash
php artisan vendor:publish --tag=nuki-seeders
```

## Auth users in demo mode

If you also enabled `NUKI_AUTH_USERS_ENABLED=true`, run
`php artisan nuki:user-create` to bootstrap a main user — demo mode does
not seed `nuki_users` automatically. You can then create sub-users with
the bundled `/nuki/sub-users` UI and assign them to the seeded accounts to
showcase the full permission model.

## Disabling for tests

Tests rely on `Http::fake()` themselves (see
[tests/TestCase.php](../tests/TestCase.php)). The demo flag should always be
`false` in `phpunit.xml` (it is). Don't toggle it on inside test setup — the
two fakes will collide.

## Adding a new endpoint while in demo mode

When you add a new method to a resource (see [API reference](api-reference.md)),
also add a matching branch to
[`DemoFixtures::respondTo()`](../src/Support/DemoFixtures.php). Otherwise the
demo dashboard silently returns `[]` for that resource and your reviewers
spend an hour looking for the bug.
