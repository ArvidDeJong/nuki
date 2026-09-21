---
title: Home
nav_order: 1
description: "darvis/nuki wraps the NUKI Web API in a Laravel package: smartlocks, activity logs, authorizations, a signed webhook receiver and a Livewire/Flux UI."
permalink: /
---

# NUKI for Laravel

A NUKI smart lock is opened over the NUKI Web API. This package puts that API behind a Laravel
facade, adds a webhook receiver that verifies what it is sent, and ships the screens you would
otherwise build yourself.

```bash
composer require darvis/nuki
php artisan vendor:publish --tag=nuki-config
```

Requires PHP 8.2 or higher, Laravel 11, 12 or 13, Livewire 3 or 4 and Flux 2.

## Your first call

Put a personal API token from [web.nuki.io](https://web.nuki.io/) in your `.env`:

```dotenv
NUKI_API_TOKEN=your-personal-token
NUKI_TOKEN_RESOLVER=config
```

Then ask the account what it has:

```php
use Darvis\Nuki\Facades\Nuki;

foreach (Nuki::smartlocks()->all() as $lock) {
    echo $lock->name.' is '.($lock->isLocked() ? 'locked' : 'open').PHP_EOL;
}

Nuki::smartlocks()->unlock($lockId);
```

Every response comes back as a typed object, not an array, so a field that the API stops sending
breaks in one place instead of somewhere deep in a Blade template.

## What else is in the box

- A **Livewire UI** under `/nuki`: dashboard, smartlocks, activity timeline, webhook subscriptions
  and accounts, built with Flux and translatable.
- A **webhook receiver** that checks the HMAC signature, ignores repeat deliveries and dispatches
  one event for you to listen to.
- **Multi account support**, either with a token per customer in the database or with OAuth 2.0.
- **Optional package users**: its own auth guard, sub users and per smartlock permissions that
  live in your database and are never written back to NUKI.
- **Demo mode**, so you can show the whole thing without a lock in the room.

## Where to go next

### Getting started

- [Getting started](getting-started.md) — requirements, install, publish, and a first call against
  the NUKI Web API.
- [Configuration](configuration.md) — every `config/nuki.php` key and `NUKI_*` variable, with
  defaults and effects.

### The NUKI Web API

- [API authentication](nuki-api-authentication.md) — token mode against OAuth 2.0, the swappable
  contracts, and how scoping to one account works.
- [API reference](api-reference.md) — every public method on the resources and the objects they
  return.

### Your own users

- [Users and permissions](users-and-permissions.md) — the optional guard, main and sub users, the
  permission matrix, validity windows and the weekday bitmask.
- [Auth routes](auth-routes.md) — every route the package registers and how the middleware is
  wired.

### Running it

- [Webhooks](webhooks.md) — registering a callback, the signature check, deduplication and the
  event.
- [UI and localization](ui-and-localization.md) — the bundled pages, the four languages and
  swapping the layout.
- [Demo mode](demo-mode.md) — the canned fixtures and the seeder behind them.
- [Troubleshooting](troubleshooting.md) — what the common errors mean.
- [FAQ](faq.md) — the short answers.
