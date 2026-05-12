<?php

declare(strict_types=1);

use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

it('rejects requests with invalid signature', function () {
    $this->postJson('/nuki/webhook', ['event' => 'DEVICE_STATUS', 'id' => 'abc'], [
        'X-Nuki-Signature' => 'bogus',
    ])->assertStatus(401);
});

it('dispatches NukiWebhookReceived on valid signature', function () {
    Event::fake();

    $payload = ['event' => 'DEVICE_STATUS', 'id' => 'evt-1', 'smartlockId' => 123];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'webhook-secret');

    $this->call('POST', '/nuki/webhook', [], [], [], [
        'HTTP_X-Nuki-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Event::assertDispatched(NukiWebhookReceived::class, function (NukiWebhookReceived $event) {
        return $event->type === 'DEVICE_STATUS' && $event->payload['smartlockId'] === 123;
    });
});

it('deduplicates repeat events', function () {
    Event::fake();

    $payload = ['event' => 'DEVICE_STATUS', 'id' => 'evt-dedup'];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'webhook-secret');

    $this->call('POST', '/nuki/webhook', [], [], [], [
        'HTTP_X-Nuki-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $this->call('POST', '/nuki/webhook', [], [], [], [
        'HTTP_X-Nuki-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJson(['status' => 'duplicate']);

    Event::assertDispatchedTimes(NukiWebhookReceived::class, 1);
});
