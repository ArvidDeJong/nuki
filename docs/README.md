# Documentation

Developer documentation for the `darvis/nuki` Laravel package — a wrapper around
the [NUKI Web API](https://developer.nuki.io/) with a bundled Livewire/Flux UI,
optional package-user authentication and a webhook receiver.

The top-level [README](../README.md) covers the install one-liner and a few
quick examples. The pages below go deep.

## Getting started

- [Getting started](getting-started.md) — requirements, install, publish, and a
  first "hello world" against the NUKI Web API.
- [Configuration reference](configuration.md) — every `config/nuki.php` key, every
  `NUKI_*` environment variable, with defaults and effects.

## NUKI Web API

- [NUKI API authentication](nuki-api-authentication.md) — token mode vs OAuth 2.0,
  the swappable `Authenticator` / `TokenStore` / `ApiTokenResolver` contracts,
  and how multi-account scoping with `Nuki::as()` works.
- [API reference](api-reference.md) — every public method on `SmartLocks`,
  `SmartlockLogs`, `SmartlockAuths`, `Webhooks`, `OAuth` and `Account`,
  with the DTOs they return.

## Package users & permissions

- [Users and permissions](users-and-permissions.md) — the optional
  `darvis-nuki` auth guard, the main/sub-user hierarchy, the per-smartlock
  permission matrix (lock / unlock / view_logs / manage_auths), validity
  windows and the weekday bitmask.
- [Auth routes](auth-routes.md) — every route registered by
  [routes/auth.php](../routes/auth.php) and the UI middleware wiring.

## Operations

- [Webhooks](webhooks.md) — registering a callback with NUKI, HMAC signature
  verification, dedup, and the `NukiWebhookReceived` event.
- [UI and localization](ui-and-localization.md) — bundled Livewire components,
  the four shipped locales, layout override and the `UsesNukiAccount` trait.
- [Demo mode](demo-mode.md) — `NUKI_DEMO=true`, the seeded demo accounts and
  what gets faked.
- [Troubleshooting](troubleshooting.md) — common errors and how to resolve them.
