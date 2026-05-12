# Webhooks

[← Documentation index](README.md)

The package can both receive NUKI webhook callbacks and register subscriptions
on the NUKI side. Both halves are disabled until you flip the env switch.

## 1. Enable the receiver

```dotenv
NUKI_WEBHOOK_ENABLED=true
NUKI_WEBHOOK_SECRET=a-long-random-string
```

The secret is the HMAC-SHA256 key NUKI signs the body with. Treat it like a
password; store it in `.env`, not in source.

With the flag on, [NukiServiceProvider](../src/NukiServiceProvider.php) loads
[routes/webhooks.php](../routes/webhooks.php):

| Method | Path | Name | Middleware |
|---|---|---|---|
| POST | `nuki.webhook.route` (default `/nuki/webhook`) | `nuki.webhook` | `nuki.webhook.middleware` (default `['api']`) |

`api` middleware deliberately skips CSRF and sessions — these are external
POSTs from NUKI's servers.

## 2. The signature flow

[WebhookController](../src/Http/Controllers/WebhookController.php) does, in
order:

1. **Signature check.** Reads the header from `nuki.webhook.signature_header`
   (default `X-Nuki-Signature`), computes
   `hash_hmac('sha256', $request->getContent(), $secret)`, compares with
   `hash_equals`. On mismatch returns `401 invalid signature`. If `secret` is
   empty the check is skipped entirely — only acceptable in local dev.
2. **Event id extraction.** Picks the id from `payload.id`, `payload.eventId`,
   or falls back to `sha1(json_encode($payload))` so duplicates are still
   recognisable.
3. **Dedup.** `Cache::add('nuki:webhook:'.$eventId, true, $dedup_ttl)`. If the
   key was already there, returns `200 {"status":"duplicate"}` without
   dispatching anything. TTL is `nuki.webhook.dedup_ttl` (default 600s).
4. **Dispatch.** Fires [NukiWebhookReceived](../src/Events/NukiWebhookReceived.php)
   with `type`, the raw payload array and the optional `accountKey` from the
   query string. Returns `200 {"status":"ok"}`.

The `accountKey` comes from `?account=…` on the callback URL. Pass it when
registering the subscription so you can tell which account a callback belongs
to in multi-account setups.

## 3. Listen for events

Register a listener anywhere — `EventServiceProvider`, an inline `Event::listen`
call, a queued job listener, whatever your app already does.

```php
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(NukiWebhookReceived::class, function (NukiWebhookReceived $event) {
    if ($event->type === 'DEVICE_LOGS') {
        // $event->payload is the parsed JSON
        // $event->accountKey is the value from ?account=
    }
});
```

The package itself does **no** business logic on the payload. That's
deliberate: every consumer wants different behaviour.

Common NUKI event types (subscribe to any subset):

- `DEVICE_STATUS` — battery, lock state, door state changes.
- `DEVICE_CONFIG` — settings changes.
- `DEVICE_LOGS` — lock / unlock events with auth-user attribution.
- `ACCOUNT_USER` — keypad codes and app users added/removed/edited.

The authoritative list is in the [NUKI Web API documentation](https://developer.nuki.io/page/nuki-web-api-1-1/4/#heading--webhook).

## 4. Register the subscription with NUKI

After your callback URL is reachable from the internet (use `ngrok`,
`expose.dev` or similar in development), register it:

```bash
php artisan nuki:webhook-register
```

Defaults:
- URL = `rtrim(APP_URL, '/') . config('nuki.webhook.route')`
- Events = `DEVICE_STATUS`, `DEVICE_CONFIG`, `DEVICE_LOGS`, `ACCOUNT_USER`

Override:

```bash
php artisan nuki:webhook-register https://example.com/nuki/webhook?account=tenant-42 \
    --account=tenant-42 \
    --events=DEVICE_LOGS --events=ACCOUNT_USER
```

Source: [NukiWebhookRegisterCommand](../src/Console/Commands/NukiWebhookRegisterCommand.php).

Listing and removing subscriptions:

```php
use Darvis\Nuki\Facades\Nuki;

$subs = Nuki::as('tenant-42')->webhooks()->all();

foreach ($subs as $sub) {
    Nuki::as('tenant-42')->webhooks()->unsubscribe($sub->id);
}
```

Or use the bundled `/nuki/webhooks` page.

## 5. Cache driver matters for dedup

`Cache::add` uses your default cache store. With the `array` driver every
request is a fresh process, so dedup will not work across separate worker
processes. Use a persistent driver (`redis`, `database`, `memcached`,
`file`) in production. Pick one that gives you atomic `add` semantics
(redis / database / memcached do; file does not under heavy concurrency).

## 6. Testing locally

In feature tests with `Http::fake()` you don't normally fire the receiver
yourself — the receiver is only called by real NUKI callbacks. To exercise
your listener:

```php
use Darvis\Nuki\Events\NukiWebhookReceived;

Event::fake([NukiWebhookReceived::class]);

$this->post('/nuki/webhook', $payload, [
    'X-Nuki-Signature' => hash_hmac('sha256', json_encode($payload), config('nuki.webhook.secret')),
])->assertOk();

Event::assertDispatched(NukiWebhookReceived::class);
```

Note that `$request->getContent()` is the raw body — use `json_encode` the
same way the request body will be encoded, otherwise the signature won't
match.
