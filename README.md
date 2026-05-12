# darvis/nuki

[![Latest version on Packagist](https://img.shields.io/packagist/v/darvis/nuki.svg?style=flat-square)](https://packagist.org/packages/darvis/nuki)
[![Tests](https://img.shields.io/github/actions/workflow/status/ArvidDeJong/nuki/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ArvidDeJong/nuki/actions/workflows/tests.yml)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/nuki.svg?style=flat-square)](https://packagist.org/packages/darvis/nuki)
[![License](https://img.shields.io/packagist/l/darvis/nuki.svg?style=flat-square)](LICENSE)

A Laravel package for the [NUKI Web API](https://developer.nuki.io/). Provides
a clean, typed interface for managing smartlocks, fetching activity logs,
managing authorizations (keypad codes / app users), and receiving webhook
callbacks.

- PHP 8.2+ — Laravel 11, 12, 13
- Bearer (API token) **and** OAuth 2.0 Authorization Code support
- Account-aware: a single application can manage multiple NUKI accounts
- Webhook receiver with HMAC signature verification and idempotent dispatch
- Built-in Livewire 3.5+ / 4 + Flux 2 UI: dashboard, activity timeline,
  smartlocks, keypad authorizations, webhooks and OAuth status
- Optional self-contained user-auth (`darvis-nuki` guard) with email OTP,
  sub-users, per-smartlock permissions and a weekday bitmask

## Documentation

Full developer documentation lives in [docs/](docs/README.md). Quick links:

- [Getting started](docs/getting-started.md) — install, publish, "hello world".
- [Configuration reference](docs/configuration.md) — every `NUKI_*` env var and
  `config/nuki.php` key.
- [NUKI API authentication](docs/nuki-api-authentication.md) — token mode,
  OAuth, multi-account scoping with `Nuki::as()`.
- [API reference](docs/api-reference.md) — every public method on every
  resource, plus DTOs and console commands.
- [Users and permissions](docs/users-and-permissions.md) — package-managed
  users, sub-user permissions, weekday bitmask, OTP, password reset.
- [Auth routes](docs/auth-routes.md) — every URL registered when the auth
  feature is on.
- [Webhooks](docs/webhooks.md) — signature verification, dedup,
  `NukiWebhookReceived` event.
- [UI and localization](docs/ui-and-localization.md) — Livewire components,
  locales, layout override.
- [Demo mode](docs/demo-mode.md) — `NUKI_DEMO=true`, seeded accounts.
- [Troubleshooting](docs/troubleshooting.md) — common errors and fixes.

## Installation

```bash
composer require darvis/nuki
php artisan vendor:publish --tag=nuki-config
php artisan migrate
```

Service provider and `Nuki` facade are auto-discovered.

## Minimal `.env` (token mode, single account)

```dotenv
NUKI_AUTH=token
NUKI_TOKEN_RESOLVER=config
NUKI_API_TOKEN=your-personal-api-token-from-web-nuki-io
```

Generate the token in the [NUKI Web portal](https://web.nuki.io/) under *API*.
For multi-account or OAuth, see
[NUKI API authentication](docs/nuki-api-authentication.md).

## Hello world

```php
use Darvis\Nuki\Facades\Nuki;

$locks = Nuki::smartlocks()->all();          // Collection<SmartLock>
$lock  = Nuki::smartlocks()->find($id);      // SmartLock

Nuki::smartlocks()->lock($id);
Nuki::smartlocks()->unlock($id);

$entries = Nuki::logs()->forSmartlock($id, ['limit' => 50]);

Nuki::auths()->create($id, [
    'name' => 'Cleaning lady',
    'type' => 13,                            // keypad code
    'code' => 123456,
]);
```

For everything else — every method on every resource, the DTO shapes,
multi-account, OAuth, webhooks, the bundled UI, the optional user-auth — see
[docs/](docs/README.md).

## Webhooks

```dotenv
NUKI_WEBHOOK_ENABLED=true
NUKI_WEBHOOK_SECRET=a-long-random-string
```

Listen for inbound events:

```php
use Darvis\Nuki\Events\NukiWebhookReceived;

Event::listen(NukiWebhookReceived::class, function (NukiWebhookReceived $event) {
    // $event->type, $event->payload, $event->accountKey
});
```

See [docs/webhooks.md](docs/webhooks.md) for signature verification,
deduplication and registering the callback with NUKI.

## Demo mode

```dotenv
NUKI_DEMO=true
```

Intercepts every call to `api.nuki.io` and answers with realistic canned data.
Combine with `php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"`
to populate the multi-account switcher. Perfect for screenshots and
walk-throughs; **never enable in production**. See
[docs/demo-mode.md](docs/demo-mode.md).

## User authentication (optional)

```dotenv
NUKI_AUTH_USERS_ENABLED=true
php artisan nuki:user-create --email=admin@example.com --name=Admin --password=secret123
```

Registers a `darvis-nuki` auth guard, gates `/nuki/*` behind it, and ships
login / OTP / register / password-reset Livewire screens. Main users can
create sub-users with per-smartlock permissions, a validity window and a
weekday bitmask. See [docs/users-and-permissions.md](docs/users-and-permissions.md).

## Testing

```bash
composer test
```

## Credits

- Author: **Arvid de Jong** (Darvis) — <info@darvis.nl> — <https://darvis.nl>

## License

MIT — see [LICENSE](LICENSE).
