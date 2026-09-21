---
title: "Installation"
nav_order: 2
description: "Install darvis/nuki in a Laravel application step by step: requirements, the API token, the config and migrations, and how to check that it works."
---

# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- Livewire 3.5 or higher, or Livewire 4
- Flux 2 (the free edition is enough)

Livewire and Flux are Composer requirements of the package. They are installed with it, also when
you switch the bundled pages off and only use the facade.

You also need a NUKI account that is available in [NUKI Web](https://web.nuki.io/), because the
package talks to the NUKI Web API and never to a lock directly.

## Step 1. Install the package

```bash
composer require darvis/nuki
```

Laravel discovers the service provider `Darvis\Nuki\NukiServiceProvider` and the `Nuki` facade
alias by itself. There is nothing to register.

## Step 2. Publish the config

```bash
php artisan vendor:publish --tag=nuki-config
```

This copies the settings to `config/nuki.php` in your application. The step is optional: every
setting you need to start is an environment variable. [Configuration](configuration.md) lists
them all.

## Step 3. Run the migrations

```bash
php artisan migrate
```

The package loads its migrations from its own folder, so there is nothing to publish first. It
creates seven tables, all starting with `nuki_`, whichever features you use. With the default
settings this step is required: the token lookup reads the `nuki_accounts` table on every call.

Only when you want to change a migration, copy them to your application first:

```bash
php artisan vendor:publish --tag=nuki-migrations
```

Keep the file names as they are. Laravel then runs your copy instead of the package's file; a
renamed copy runs next to it and fails with "table already exists".

## Step 4. Add the API token

Create a personal API token in the NUKI Web account that owns the locks, at
[web.nuki.io](https://web.nuki.io/) under *API*. The token gives access to every lock on that
account, so treat it as a password. Put it in `.env`:

```dotenv
NUKI_API_TOKEN=paste-the-token-here
NUKI_TOKEN_RESOLVER=config
```

| Variable | What it does |
|---|---|
| `NUKI_API_TOKEN` | The token for the account key `default`, the one every call uses until you call `Nuki::as()`. |
| `NUKI_TOKEN_RESOLVER` | `config` reads the token from `NUKI_API_TOKEN` and never touches the database. The default, `database`, looks in the `nuki_accounts` table first and falls back to `NUKI_API_TOKEN` for `default`. |
| `NUKI_AUTH` | `token` (the default) or `oauth`. Leave it out for a personal API token. |

More than one NUKI account, or OAuth instead of a token: see
[API authentication](nuki-api-authentication.md).

If your application caches its config, run `php artisan config:clear` after changing `.env`.

## Step 5. Decide who may open the bundled pages

The package registers pages under `/nuki` that lock and unlock doors. They answer `403` in every
environment except `local` until your application says who may open them. In
`app/Providers/AppServiceProvider.php`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
}
```

Replace `is_admin` with whatever marks an administrator in your application. A gate is Laravel's
way to answer "may this user do this?"; see the
[Laravel documentation on gates](https://laravel.com/docs/authorization#gates). Keep the
parameter nullable (`?User`), otherwise Laravel never asks the gate for a visitor who is not
signed in. Details: [Who may open the UI](ui-and-localization.md#who-may-open-the-ui).

You do not want the pages at all? Set `NUKI_UI_ENABLED=false`.

## Check that it works

Ask NUKI for the locks on the account:

```bash
php artisan tinker --execute="dump(Nuki::smartlocks()->all()->pluck('name')->all());"
```

You should see the names of your locks, for example:

```text
array:2 [
  0 => "Front door"
  1 => "Garage"
]
```

An empty array (`[]`) means the token works and the account has no locks in NUKI Web.

| You see | It means |
|---|---|
| `No NUKI API token configured for account [default].` | The token did not reach the package. Check `NUKI_API_TOKEN` and run `php artisan config:clear`. |
| `NUKI API GET /smartlock returned HTTP 401` | NUKI refused the token. Create a new one in NUKI Web. |
| An SQL error that names the table `nuki_accounts` | Step 3 was skipped. Run `php artisan migrate`, or set `NUKI_TOKEN_RESOLVER=config`. |
| A `ConnectionException` | The server cannot reach `api.nuki.io`. |

[Troubleshooting](troubleshooting.md) has the full list.

### Check it without a NUKI account

Demo mode answers every call with made up data, so you can check the installation before you have
a token:

```bash
NUKI_DEMO=true php artisan tinker --execute="dump(Nuki::smartlocks()->all()->pluck('name')->all());"
```

This prints five lock names, the first one `Voordeur Hoofdkantoor`. See [Demo mode](demo-mode.md),
and never set `NUKI_DEMO` in production.

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline with the
rules of the package and a `nuki-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Where to go next

- [Quick start](quickstart.md): a page in your own application that lists the locks and operates
  one.
- [API reference](api-reference.md): everything the facade can do.
- [Webhooks](webhooks.md): let NUKI tell your application that a door was opened.
- [Users and permissions](users-and-permissions.md): give other people access to one lock.
