---
title: "Webhooks"
nav_order: 9
description: "Receive NUKI webhooks in Laravel with darvis/nuki: switch the receiver on, how the signature is checked, duplicates, and the NukiWebhookReceived event."
---

# Webhooks

A webhook is a request NUKI sends to your application when something happens, for example when a
door is opened. The package has a receiver for those requests and a command that registers your
URL with NUKI. Both are off until you switch them on.

## 1. Switch the receiver on

```dotenv
NUKI_WEBHOOK_ENABLED=true
NUKI_WEBHOOK_SECRET=a-long-random-string
```

Write `true`, not `1`: only the boolean `true` registers the route. Run
`php artisan config:clear` when your config is cached.

The package then registers one route from
[routes/webhooks.php](https://github.com/ArvidDeJong/nuki/blob/main/routes/webhooks.php):

| Method | Path | Name | Middleware |
|---|---|---|---|
| POST | `webhook.route` (default `/nuki/webhook`) | `nuki.webhook` | `webhook.middleware` (default `['api']`) |

The `api` middleware group has no session and no CSRF check, which is what a request from another
server needs.

**Without a secret the receiver rejects every request with `401`**, so the route is never open by
accident.

## 2. What the receiver does with a request

[WebhookController](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/Controllers/WebhookController.php) does, in
this order:

1. **Signature check.** It computes `hash_hmac('sha256', <raw body>, <webhook.secret>)` and
   compares it with the header named in `webhook.signature_header` (default `X-Nuki-Signature`),
   using `hash_equals`. No secret, no header or a mismatch gives
   `401 {"error":"invalid signature"}`.
2. **Event id.** `id` from the body, otherwise `eventId`, otherwise a SHA-1 of the whole body.
3. **Duplicates.** `Cache::add('nuki:webhook:<id>', …)` remembers the id for `webhook.dedup_ttl`
   seconds (default 600). A repeat gets `200 {"status":"duplicate"}` and no event.
4. **Dispatch.** The event
   [NukiWebhookReceived](https://github.com/ArvidDeJong/nuki/blob/main/src/Events/NukiWebhookReceived.php) is
   dispatched and the answer is `200 {"status":"ok"}`.

The secret and the header name have to match what NUKI really sends for your kind of webhook.
Check the [NUKI Web API documentation](https://developer.nuki.io/) for both, and set
`NUKI_WEBHOOK_SECRET` and `NUKI_WEBHOOK_SIGNATURE_HEADER` to match.

To accept unsigned requests, for example behind a gateway that already authenticates the caller,
switch the check off on purpose with `NUKI_WEBHOOK_VERIFY_SIGNATURE=false`. Only the boolean
`false` does that.

## 3. Listen for the event

The package does nothing with the content of a webhook. Your application listens for the event,
for example in the `boot()` method of `app/Providers/AppServiceProvider.php`:

```php
use App\Jobs\ProcessNukiLog;
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (NukiWebhookReceived $event): void {
    if ($event->type !== 'DEVICE_LOGS') {
        return;
    }

    ProcessNukiLog::dispatch($event->payload, $event->accountKey);
});
```

`ProcessNukiLog` stands for a queued job of your own.

| Property | Content |
|---|---|
| `$event->type` | `event` from the body, otherwise `type`, otherwise `'unknown'`. |
| `$event->payload` | The whole body as an array. |
| `$event->accountKey` | The `?account=` query parameter of the callback URL, or `null`. |

## 4. Register the callback URL with NUKI

Your URL has to be reachable from the internet; in development that takes a tunnel. Then:

```bash
php artisan nuki:webhook-register
```

Without arguments the command uses `APP_URL` plus `webhook.route`, the account key `default` and
the events `DEVICE_STATUS`, `DEVICE_CONFIG`, `DEVICE_LOGS` and `ACCOUNT_USER`. To choose yourself:

```bash
php artisan nuki:webhook-register "https://example.com/nuki/webhook?account=tenant-42" \
    --account=tenant-42 \
    --events=DEVICE_LOGS --events=ACCOUNT_USER
```

Quote the URL, because of the `?`. `--account` is the account whose token is used for the call to
NUKI; the `?account=` in the URL is what comes back in `$event->accountKey`. The event names are
passed to NUKI as they are; the NUKI Web API documentation has the list.

Source: [NukiWebhookRegisterCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiWebhookRegisterCommand.php).

List and remove subscriptions:

```php
use Darvis\Nuki\Facades\Nuki;

foreach (Nuki::as('tenant-42')->webhooks()->all() as $subscription) {
    if ($subscription->id !== '') {
        Nuki::as('tenant-42')->webhooks()->unsubscribe($subscription->id);
    }
}
```

`WebhookSubscription` fills `id` from the key `id` of the NUKI answer. When NUKI names it
differently the value is only in `$subscription->raw`, hence the check for an empty id. The
bundled `/nuki/webhooks` page does the same from the browser.

## Things that go wrong in production

- **The event is handled twice.** The duplicate check uses your default cache store. With the
  `array` store nothing is remembered between requests, so every delivery is new. Use a store that
  is shared by all your workers, such as `redis` or `database`.
- **A failed listener loses the event.** The id is stored before your listener runs. A listener
  that throws gives NUKI a `500`, and a retry is then dropped as a duplicate. Keep the listener to
  dispatching a queued job.
- **Two different events count as one.** A body without `id` or `eventId` is recognised by its
  content. Two identical bodies within `dedup_ttl` seconds are one event.
- **`accountKey` is a hint.** `?account=` is not part of the signed body. Check it against the
  `smartlockId` in the payload before you act on it.
- **A captured request can be replayed.** The signature covers the body only, there is no
  timestamp. After `dedup_ttl` seconds the same request is accepted again, so make the handling
  idempotent: doing it twice must give the same result as doing it once.

## Test your listener

See [Testing → Test a webhook listener](testing.md#test-a-webhook-listener). In short: dispatch
the event yourself, or send the raw JSON body with `call()`; `post()` with an array never matches
the signature.
