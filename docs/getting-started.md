# Getting started

[← Documentation index](README.md)

## Requirements

- PHP **8.2+**
- Laravel **11**, **12** or **13**
- Livewire **3.5+** or **4** (only required when you use the bundled UI)
- Flux UI **2.0+** (only required when you use the bundled UI)

You also need a NUKI account with at least one Smartlock paired. Personal API
tokens are generated in the [NUKI Web portal](https://web.nuki.io/) under
*API*. For OAuth, register an application on the
[NUKI Developer Portal](https://developer.nuki.io/).

## Installation

```bash
composer require darvis/nuki
```

The service provider `Darvis\Nuki\NukiServiceProvider` is auto-discovered, and
so is the `Nuki` facade alias. No manual `config/app.php` changes needed.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=nuki-config
```

Run the migrations. The package auto-loads its migrations from the package
path, so a plain `migrate` is enough:

```bash
php artisan migrate
```

If you prefer to copy the migrations into your own `database/migrations/`
folder so you can edit them, publish them first:

```bash
php artisan vendor:publish --tag=nuki-migrations
```

What gets created depends on which features you enable. See
[Configuration reference → Database tables](configuration.md#database-tables)
for the full list.

## Minimal `.env` (token mode, single account)

```dotenv
NUKI_AUTH=token
NUKI_TOKEN_RESOLVER=config
NUKI_API_TOKEN=your-personal-api-token-from-web-nuki-io
```

`NUKI_TOKEN_RESOLVER=config` skips the `nuki_accounts` table entirely — fine
when you only need a single account. For multi-account, use `database` and
manage tokens via the bundled `/nuki/accounts` UI or by inserting rows into
`nuki_accounts` directly. See [NUKI API authentication](nuki-api-authentication.md).

## Hello world

```php
use Darvis\Nuki\Facades\Nuki;

$locks = Nuki::smartlocks()->all();

foreach ($locks as $lock) {
    echo $lock->name.' — '.$lock->stateName.PHP_EOL;
}

// Trigger an action
Nuki::smartlocks()->lock($lockId);
Nuki::smartlocks()->unlock($lockId);
```

That's all the wiring you need. The facade resolves a singleton `Nuki` manager
that hands out [resource objects](api-reference.md) — `smartlocks()`, `logs()`,
`auths()`, `webhooks()`, `oauth()`, `account()`. Each resource talks to the
NUKI Web API through a single retry-aware
[HttpClient](../src/Http/HttpClient.php).

## Verify your installation works (no network required)

```bash
NUKI_DEMO=true php artisan tinker
>>> Nuki::smartlocks()->all()->pluck('name');
```

With `NUKI_DEMO=true`, all HTTP calls to `api.nuki.io/*` are intercepted by
[DemoFixtures](../src/Support/DemoFixtures.php) and answered with realistic
canned data. See [Demo mode](demo-mode.md). Disable this in production.

## Where to next

- Single account, just want to read/write smartlocks → [API reference](api-reference.md).
- Multi-account / OAuth → [NUKI API authentication](nuki-api-authentication.md).
- You want the bundled `/nuki/*` dashboard → see [UI and localization](ui-and-localization.md).
- You want users to log in to your app via the package's own login → see [Users and permissions](users-and-permissions.md).
- You want NUKI to push events to your app → see [Webhooks](webhooks.md).
