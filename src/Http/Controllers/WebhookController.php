<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Controllers;

use Darvis\Nuki\Events\NukiWebhookReceived;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->all();
        $type = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $eventId = (string) ($payload['id'] ?? $payload['eventId'] ?? sha1((string) json_encode($payload)));
        $accountKey = $request->query('account');

        $dedupKey = 'nuki:webhook:'.$eventId;

        if (! Cache::add($dedupKey, true, NukiConfig::webhookDedupTtl())) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        NukiWebhookReceived::dispatch($type, $payload, $accountKey);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Requests are rejected until a secret is configured. Set nuki.webhook.verify_signature
     * to false to accept unsigned requests, for example behind a trusted proxy.
     */
    private function verifySignature(Request $request): bool
    {
        if (! NukiConfig::webhookVerifySignature()) {
            return true;
        }

        $secret = NukiConfig::webhookSecret();

        if ($secret === null) {
            return false;
        }

        $provided = $request->header(NukiConfig::webhookSignatureHeader());

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
