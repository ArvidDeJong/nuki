# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/nuki/issues/new/choose) with the call, the page or the webhook payload that reproduces it. Never paste an API token.
- **Features:** open an issue first. The package wraps the NUKI Web API; let's agree a feature belongs here and not in your own application before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/nuki.git
cd nuki
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2-8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

The suite never calls the NUKI API. Fake it with `Http::fake()`; `Darvis\Nuki\Support\DemoFixtures` holds payloads shaped like the real responses.

## Pull requests

- Add or update tests for every change in behaviour. The tests use the package defaults; don't override the config in `TestCase`, set it in the test that needs it.
- Keep the public API compatible within 1.x: the `Nuki` facade and the resource classes behind it, the DTOs and their properties, the `NukiWebhookReceived` event, the Livewire component names, the route names, the models and their tables, the config keys and the publish tags.
- A change a site owner notices (another default, a different page, another permission check) is a minor release, not a patch.
- Write code, comments and messages in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Wrap Blade examples in `{% raw %}` … `{% endraw %}`, or Jekyll renders them.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
