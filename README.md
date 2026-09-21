# NUKI for Laravel

[![Latest version](https://img.shields.io/packagist/v/darvis/nuki.svg)](https://packagist.org/packages/darvis/nuki)
[![Tests](https://github.com/ArvidDeJong/nuki/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/nuki/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/nuki/php.svg)](https://packagist.org/packages/darvis/nuki)
[![License](https://img.shields.io/packagist/l/darvis/nuki.svg)](LICENSE)

A Laravel package for the [NUKI Web API](https://developer.nuki.io/): list, lock and unlock
smartlocks, read the activity log, manage keypad codes and receive webhooks, with a Livewire UI
and optional users with permissions per lock.

## Features

- **A facade for the NUKI Web API** - `Nuki::smartlocks()`, `logs()`, `auths()`, `webhooks()`,
  `oauth()` and `account()`, with answers as typed objects instead of arrays
- **Personal API token or OAuth 2.0** - and more than one NUKI account in one application with
  `Nuki::as('account-key')`, tokens encrypted in the database
- **Retries** - a connection error, a 429 and a 5xx are tried again; a failure is an exception
  with the status and the body
- **Webhook receiver** - checks the HMAC-SHA256 signature, ignores a repeated event id and
  dispatches one event; rejects everything until a secret is set
- **Livewire and Flux pages** - dashboard, smartlocks, activity, keypad codes, webhooks and
  accounts, in English, Dutch, German and Spanish; closed outside `local` until you open them
- **Optional users** - its own guard with an emailed login code, main and sub users, and
  permissions per lock with a period and weekdays, stored in your database only
- **Demo mode** - the whole UI on made up data, without a NUKI account

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- Livewire 3.5 or higher, or Livewire 4
- Flux 2 (the free edition is enough)
- A NUKI account that is available in NUKI Web, with an API token or an OAuth application

Livewire and Flux are Composer requirements, so they are installed with the package.

## Installation

```bash
composer require darvis/nuki
php artisan vendor:publish --tag=nuki-config
php artisan migrate
```

Create a personal API token on [web.nuki.io](https://web.nuki.io/) under *API* and put it in
`.env`:

```dotenv
NUKI_API_TOKEN=paste-the-token-here
NUKI_TOKEN_RESOLVER=config
```

The [installation page](https://arviddejong.github.io/nuki/installation.html) has every step and a
way to check that it works.

## Who may open the bundled UI

The package registers pages under `/nuki` that lock and unlock doors. Outside the `local`
environment they answer `403` until you say who may open them, the way Horizon and Telescope work.
Define the `viewNuki` gate, for example in `AppServiceProvider::boot()`:

```php
Gate::define('viewNuki', fn (?User $user) => $user?->is_admin === true);
```

Keep the parameter nullable, or a guest never reaches the gate. With
`NUKI_AUTH_USERS_ENABLED=true` the package's own login protects the pages and the gate is not
asked. `NUKI_UI_ENABLED=false` removes the pages altogether. See
[Who may open the UI](https://arviddejong.github.io/nuki/ui-and-localization.html#who-may-open-the-ui).

## Quick start

```php
use Darvis\Nuki\Exceptions\NukiException;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Http\Client\ConnectionException;

foreach (Nuki::smartlocks()->all() as $lock) {
    echo $lock->name.': '.($lock->stateName ?? 'unknown').PHP_EOL;
}

try {
    Nuki::smartlocks()->unlock($smartlockId);
} catch (NukiException|ConnectionException $e) {
    report($e);
}
```

`all()` returns a collection of `SmartLock` objects. `unlock()` returns nothing: no exception
means NUKI accepted the command. The
[quick start](https://arviddejong.github.io/nuki/quickstart.html) builds a complete page with
routes, a controller and a view.

## Documentation

The full documentation is on [arviddejong.github.io/nuki](https://arviddejong.github.io/nuki/):

- [Installation](https://arviddejong.github.io/nuki/installation.html) - the steps, and how to
  check that it works
- [Quick start](https://arviddejong.github.io/nuki/quickstart.html) - a page that lists the locks
  and operates one
- [Configuration](https://arviddejong.github.io/nuki/configuration.html) - every key and `NUKI_*`
  variable
- [API authentication](https://arviddejong.github.io/nuki/nuki-api-authentication.html) - token or
  OAuth, several accounts, the callback route you build
- [API reference](https://arviddejong.github.io/nuki/api-reference.html) - every method, object,
  command and exception
- [Users and permissions](https://arviddejong.github.io/nuki/users-and-permissions.html) - main
  and sub users, permissions per lock
- [Auth routes](https://arviddejong.github.io/nuki/auth-routes.html) - the routes and where a
  guest is sent
- [Webhooks](https://arviddejong.github.io/nuki/webhooks.html) - the receiver and the event
- [UI and localization](https://arviddejong.github.io/nuki/ui-and-localization.html) - the pages,
  your own layout, the languages
- [Demo mode](https://arviddejong.github.io/nuki/demo-mode.html) - the UI on made up data
- [Testing](https://arviddejong.github.io/nuki/testing.html) - test your code without calling NUKI
- [Troubleshooting](https://arviddejong.github.io/nuki/troubleshooting.html) - the literal error
  messages, with cause and fix
- [FAQ](https://arviddejong.github.io/nuki/faq.html) - the short answers

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline and a
`nuki-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Report a vulnerability privately, as described in [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
