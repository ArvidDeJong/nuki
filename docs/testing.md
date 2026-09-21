---
title: "Testing"
nav_order: 12
description: "Test Laravel code that uses darvis/nuki without calling the NUKI Web API: fake the HTTP layer, assert the request, and test a webhook listener."
---

# Testing your own code

A test must never reach the real NUKI Web API: it would operate a real door. The package sends
every call through Laravel's HTTP client, so `Http::fake()` is all you need. See the
[Laravel documentation on faking responses](https://laravel.com/docs/http-client#testing).

## A complete test

This tests the `DoorController` from the [Quick start](quickstart.md). In
`tests/Feature/DoorControllerTest.php` (Pest):

```php
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Any call that is not faked below fails the test instead of reaching NUKI.
    Http::preventStrayRequests();

    config()->set('nuki.auth', 'token');
    config()->set('nuki.token_resolver', 'config');
    config()->set('nuki.token', 'test-token');
    config()->set('nuki.http.retries', 1);

    $this->actingAs(User::factory()->create());
});

it('lists the locks of the account', function () {
    Http::fake([
        'api.nuki.io/smartlock' => Http::response([
            ['smartlockId' => 17, 'name' => 'Front door', 'type' => 4, 'state' => ['state' => 1, 'batteryCharge' => 80]],
        ]),
    ]);

    $this->get('/doors')->assertOk()->assertSee('Front door')->assertSee('locked');
});

it('sends the unlock command to NUKI', function () {
    Http::fake(['api.nuki.io/smartlock/17/action' => Http::response('', 204)]);

    $this->from('/doors')->post('/doors/17/unlock')
        ->assertRedirect('/doors')
        ->assertSessionHas('status');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://api.nuki.io/smartlock/17/action'
        && $request['action'] === 1
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('shows an error when NUKI is down', function () {
    Http::fake(['api.nuki.io/*' => Http::response('', 503)]);

    $this->from('/doors')->post('/doors/17/lock')
        ->assertRedirect('/doors')
        ->assertSessionHas('error');

    Http::assertSentCount(1);
});
```

What matters in it:

- **Set the config before the first `Nuki::` call.** The manager, the HTTP client and the token
  lookup are singletons that read their settings once, when they are first used.
- **`nuki.token_resolver` set to `config`** keeps the test away from the `nuki_accounts` table.
- **`nuki.http.retries` set to `1`.** The number is the total number of attempts. With the default
  of `3` a faked 503 is sent three times with a pause in between, and `assertSentCount(1)` fails.
- **A list answer is a JSON array of rows.** `smartlockId` is the only key a `SmartLock` needs;
  `state.state` `1` with `type` `4` gives `stateName` `locked`.
- **Action numbers:** unlock is `1`, lock is `2`. The constants are on
  `Darvis\Nuki\Resources\SmartLocks` (`ACTION_UNLOCK`, `ACTION_LOCK`, and so on).

## Payloads that look like the real thing

`Darvis\Nuki\Support\DemoFixtures` holds the made up data of [demo mode](demo-mode.md). Its public
methods return arrays in the shape of the NUKI answers, ready for `Http::response()`:

| Method | Shape of |
|---|---|
| `DemoFixtures::smartlocks()` | `GET /smartlock` (five locks, ids `17000000001` to `17000000005`) |
| `DemoFixtures::logsFor(int $smartlockId)` | `GET /smartlock/{id}/log` |
| `DemoFixtures::authsFor(int $smartlockId)` | `GET /smartlock/{id}/auth` |
| `DemoFixtures::accountLogs()` | `GET /smartlock/log` |
| `DemoFixtures::accountAuths()` | `GET /smartlock/auth` |
| `DemoFixtures::account()` | `GET /account` |
| `DemoFixtures::webhooks()` | `GET /api/notification` |

```php
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Support\Facades\Http;

Http::fake(['api.nuki.io/smartlock' => Http::response(DemoFixtures::smartlocks())]);
```

Do not switch `nuki.demo.enabled` on in a test. It installs its own `Http::fake()` for
`api.nuki.io/*` when the application boots, and your fakes then come second.

## Test a webhook listener

To test the listener alone, dispatch the event yourself:

```php
use Darvis\Nuki\Events\NukiWebhookReceived;

NukiWebhookReceived::dispatch('DEVICE_LOGS', ['smartlockId' => 17], 'tenant-42');
```

To go through the real route, sign the exact body you send. `post()` with an array sends form
fields, so the signature never matches and the answer is `401`. Send the raw JSON with `call()`:

```php
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::fake([NukiWebhookReceived::class]);

$body = json_encode(['event' => 'DEVICE_LOGS', 'id' => 'evt-1', 'smartlockId' => 17]);

$this->call('POST', route('nuki.webhook', ['account' => 'tenant-42']), server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_NUKI_SIGNATURE' => hash_hmac('sha256', $body, 'test-secret'),
], content: $body)->assertOk()->assertJson(['status' => 'ok']);

Event::assertDispatched(fn (NukiWebhookReceived $event) => $event->accountKey === 'tenant-42');
```

The route only exists when `nuki.webhook.enabled` was `true` while the application booted. Set it
in `phpunit.xml`, not inside the test:

```xml
<env name="NUKI_WEBHOOK_ENABLED" value="true"/>
<env name="NUKI_WEBHOOK_SECRET" value="test-secret"/>
```

Use a new `id` in every request. The receiver remembers an id for ten minutes and answers
`{"status":"duplicate"}` for a repeat, without dispatching the event.

## Test a page of the bundled UI

The `testing` environment is not `local`, so `/nuki` answers `403` in a test. Open it in the test
that needs it:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewNuki', fn (?User $user = null) => true);
```
