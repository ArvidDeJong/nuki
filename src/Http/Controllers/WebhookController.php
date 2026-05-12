<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Controllers;

use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $config = config('nuki.webhook');

        if (! $this->verifySignature($request, $config)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->all();
        $type = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $eventId = (string) ($payload['id'] ?? $payload['eventId'] ?? sha1((string) json_encode($payload)));
        $accountKey = $request->query('account');

        $dedupKey = 'nuki:webhook:'.$eventId;
        $ttl = (int) ($config['dedup_ttl'] ?? 600);

        if (! Cache::add($dedupKey, true, $ttl)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        NukiWebhookReceived::dispatch($type, $payload, $accountKey);

        return response()->json(['status' => 'ok'], 200);
    }

    private function verifySignature(Request $request, array $config): bool
    {
        $secret = $config['secret'] ?? null;

        if (empty($secret)) {
            return true;
        }

        $header = (string) $config['signature_header'];
        $provided = $request->header($header);

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
