---
title: "Demo mode"
nav_order: 11
description: "Run darvis/nuki on made up locks, logs and keypad codes with NUKI_DEMO=true, without a NUKI account: what is faked, the seeder and the limits."
---

# Demo mode

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
[NukiServiceProvider](https://github.com/ArvidDeJong/nuki/blob/main/src/NukiServiceProvider.php):

1. If `nuki.token` is empty, it's stubbed to `'demo-token'` so the bearer
   authenticator does not throw before the HTTP fake intercepts.
2. [DemoFixtures::register()](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/DemoFixtures.php) installs
   `Http::fake(['api.nuki.io/*' => closure])` returning canned responses.

## What gets faked

Every endpoint the package currently talks to:

- `GET  /smartlock` — five locks, ids `17000000001` to `17000000005`.
- `GET  /smartlock/{id}` — single lock; an unknown id gets the first lock.
- `GET  /smartlock/{id}/log` — per-lock activity.
- `GET  /smartlock/log` — account-wide activity.
- `GET  /smartlock/{id}/auth` — per-lock authorizations.
- `GET  /smartlock/auth` — account-wide authorizations.
- `POST /smartlock/{id}/action` — accepted; nothing changes, the lock keeps its state.
- `PUT  /smartlock/{id}/auth`, `POST` and `DELETE /smartlock/{id}/auth/{authId}` — accepted; nothing is stored.
- `POST /smartlock/{id}` — name updates accepted.
- `POST /smartlock/{id}/sync` — accepted.
- `GET  /account` — account info.
- `GET  /api/notification` — webhook subscriptions.
- `PUT  /api/notification` — accepts new subscription.
- `DELETE /api/notification/{id}` — accepts removal.
- `POST /oauth/token` — returns a demo token (only when `oauth.token_url` is on `api.nuki.io`, the default).

Any other path on `api.nuki.io` gets an empty `200` answer.

The data has Dutch names and one lock with `batteryCritical: true`, so the warning badge has
something to show.

## Seed the multi-account switcher

The locks come from the fixtures, the accounts from your database. Without rows in
`nuki_accounts` the switcher only offers `default`. Seed four demo accounts (`default`,
`werkplaats`, `vakantiehuis` and `klant-bakkerij`):

```bash
php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
```

To customise the seeded accounts, publish the seeder first and edit it in
your app:

```bash
php artisan vendor:publish --tag=nuki-seeders
```

## Auth users in demo mode

Demo mode seeds no users. With `NUKI_AUTH_USERS_ENABLED=true`, create a main user with
`php artisan nuki:user-create --account=werkplaats --account=vakantiehuis --account=klant-bakkerij`
after the seeder has run; the `--account` option
[attaches the user to the seeded accounts](users-and-permissions.md#attach-a-main-user-to-an-account).
Without it the user only has `default`. Then add sub users on `/nuki/sub-users`.

## Who may open the demo

Demo mode does not open the pages. Outside the `local` environment `/nuki` still answers `403`
until your application defines the `viewNuki` gate; see
[Who may open the UI](ui-and-localization.md#who-may-open-the-ui).

## Keep it off in tests

Demo mode installs its own `Http::fake()` for `api.nuki.io/*` while the application boots, and the
fakes of your test then come second. Leave `NUKI_DEMO` out of `phpunit.xml`; use the fixture data
directly instead, see [Testing](testing.md#payloads-that-look-like-the-real-thing).

## For contributors: a new endpoint needs a fixture

When you add a method to a resource in the package, also add a matching branch to
[`DemoFixtures::respondTo()`](https://github.com/ArvidDeJong/nuki/blob/main/src/Support/DemoFixtures.php). Otherwise demo
mode answers that call with an empty array.
