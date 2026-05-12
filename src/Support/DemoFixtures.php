<?php

declare(strict_types=1);

namespace Darvis\Nuki\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Canned NUKI Web API responses for demo/screenshot purposes.
 *
 * Registered by the service provider when `nuki.demo.enabled` is true. All
 * requests against `api.nuki.io` are answered locally — no network traffic.
 */
class DemoFixtures
{
    public static function register(): void
    {
        Http::fake([
            'api.nuki.io/*' => fn (Request $request) => self::respondTo($request),
        ]);
    }

    private static function respondTo(Request $request)
    {
        $path = ltrim((string) parse_url($request->url(), PHP_URL_PATH), '/');
        $method = strtoupper($request->method());

        // Lock action endpoint
        if (preg_match('#^smartlock/\d+/action$#', $path)) {
            return Http::response('', 204);
        }

        // Force-sync endpoint
        if (preg_match('#^smartlock/\d+/sync$#', $path)) {
            return Http::response('', 204);
        }

        // Per-lock authorisation collection / create
        if (preg_match('#^smartlock/(\d+)/auth$#', $path, $m)) {
            if ($method !== 'GET') {
                return Http::response('', 204);
            }

            return Http::response(self::authsFor((int) $m[1]), 200);
        }

        // Per-lock authorisation by id (update/delete)
        if (preg_match('#^smartlock/\d+/auth/[\w-]+$#', $path)) {
            return Http::response('', 204);
        }

        // Per-lock log
        if (preg_match('#^smartlock/(\d+)/log$#', $path, $m)) {
            return Http::response(self::logsFor((int) $m[1]), 200);
        }

        // Single smartlock detail / update
        if (preg_match('#^smartlock/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];

            if ($method === 'POST') {
                return Http::response('', 204);
            }

            $lock = collect(self::smartlocks())->firstWhere('smartlockId', $id);

            return Http::response($lock ?? self::smartlocks()[0], 200);
        }

        // Account-wide collections
        if ($path === 'smartlock') {
            return Http::response(self::smartlocks(), 200);
        }

        if ($path === 'smartlock/log') {
            return Http::response(self::accountLogs(), 200);
        }

        if ($path === 'smartlock/auth') {
            return Http::response(self::accountAuths(), 200);
        }

        // Account info
        if ($path === 'account') {
            return Http::response(self::account(), 200);
        }

        // Webhook subscriptions
        if ($path === 'api/notification') {
            if ($method === 'PUT') {
                return Http::response(['notificationId' => '11111111-2222-3333-4444-555555555555'], 200);
            }

            return Http::response(self::webhooks(), 200);
        }

        if (preg_match('#^api/notification/[\w-]+$#', $path)) {
            return Http::response('', 204);
        }

        // OAuth endpoints — return a usable fake token so the OAuth UI does
        // not blow up during a demo run.
        if ($path === 'oauth/token') {
            return Http::response([
                'access_token' => 'demo-access-token',
                'refresh_token' => 'demo-refresh-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'scope' => 'account smartlock smartlock.readOnly smartlock.action smartlock.auth notification',
            ], 200);
        }

        return Http::response([], 200);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function smartlocks(): array
    {
        $now = CarbonImmutable::now();

        return [
            [
                'smartlockId' => 17000000001,
                'accountId' => 9999,
                'type' => 4, // Smart Lock 3.0 / 4.0 / Pro / Go
                'authId' => 1001,
                'name' => 'Voordeur Hoofdkantoor',
                'favourite' => 1.0,
                'firmwareVersion' => 200707,
                'hardwareVersion' => 30,
                'serverState' => 0,
                'state' => [
                    'state' => 1, // locked
                    'stateName' => 'locked',
                    'batteryCharge' => 82,
                    'batteryCritical' => false,
                    'batteryCharging' => false,
                    'keypadBatteryCritical' => false,
                    'doorsensorBatteryCritical' => false,
                    'doorState' => 2, // dicht
                    'trigger' => 0,
                ],
                'creationDate' => '2026-01-12T09:00:00.000Z',
                'updateDate' => $now->subMinutes(3)->toIso8601String(),
            ],
            [
                'smartlockId' => 17000000002,
                'accountId' => 9999,
                'type' => 4, // Smart Lock 3.0 / 4.0 / Pro / Go
                'authId' => 1002,
                'name' => 'Achterdeur Hoofdkantoor',
                'favourite' => 0.0,
                'firmwareVersion' => 200707,
                'hardwareVersion' => 30,
                'serverState' => 0,
                'state' => [
                    'state' => 3, // unlocked
                    'stateName' => 'unlocked',
                    'batteryCharge' => 67,
                    'batteryCritical' => false,
                    'batteryCharging' => false,
                    'keypadBatteryCritical' => false,
                    'doorsensorBatteryCritical' => false,
                    'doorState' => 3, // open
                    'trigger' => 1,
                ],
                'creationDate' => '2026-01-12T09:05:00.000Z',
                'updateDate' => $now->subMinutes(1)->toIso8601String(),
            ],
            [
                'smartlockId' => 17000000003,
                'accountId' => 9999,
                'type' => 4, // Smart Lock 3.0 / 4.0 / Pro / Go
                'authId' => 1003,
                'name' => 'Magazijn Werkplaats',
                'favourite' => 0.0,
                'firmwareVersion' => 200636,
                'hardwareVersion' => 30,
                'serverState' => 0,
                'state' => [
                    'state' => 1, // locked
                    'stateName' => 'locked',
                    'batteryCharge' => 41,
                    'batteryCritical' => false,
                    'batteryCharging' => false,
                    'keypadBatteryCritical' => false,
                    'doorsensorBatteryCritical' => false,
                    'doorState' => 2,
                    'trigger' => 0,
                ],
                'creationDate' => '2026-02-04T13:20:00.000Z',
                'updateDate' => $now->subMinutes(18)->toIso8601String(),
            ],
            [
                'smartlockId' => 17000000004,
                'accountId' => 9999,
                'type' => 4, // Smart Lock 3.0 / 4.0 / Pro / Go
                'authId' => 1004,
                'name' => 'Kantoor Bovenverdieping',
                'favourite' => 0.0,
                'firmwareVersion' => 200707,
                'hardwareVersion' => 30,
                'serverState' => 0,
                'state' => [
                    'state' => 6, // unlocked (lock & go)
                    'stateName' => 'unlocked (lock & go)',
                    'batteryCharge' => 91,
                    'batteryCritical' => false,
                    'batteryCharging' => true,
                    'keypadBatteryCritical' => false,
                    'doorsensorBatteryCritical' => false,
                    'doorState' => 2,
                    'trigger' => 2,
                ],
                'creationDate' => '2026-02-09T11:00:00.000Z',
                'updateDate' => $now->subMinutes(7)->toIso8601String(),
            ],
            [
                'smartlockId' => 17000000005,
                'accountId' => 9999,
                'type' => 4, // Smart Lock 3.0 / 4.0 / Pro / Go
                'authId' => 1005,
                'name' => 'Garage Julianadorp',
                'favourite' => 0.0,
                'firmwareVersion' => 200636,
                'hardwareVersion' => 30,
                'serverState' => 0,
                'state' => [
                    'state' => 1, // locked
                    'stateName' => 'locked',
                    'batteryCharge' => 12,
                    'batteryCritical' => true,
                    'batteryCharging' => false,
                    'keypadBatteryCritical' => true,
                    'doorsensorBatteryCritical' => false,
                    'doorState' => 2,
                    'trigger' => 0,
                ],
                'creationDate' => '2026-03-18T08:45:00.000Z',
                'updateDate' => $now->subHours(2)->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function logsFor(int $smartlockId): array
    {
        $now = CarbonImmutable::now();
        $authNames = [
            17000000001 => ['Arvid (iPhone)', 'Schoonmaak Janneke', 'PostNL bezorger', 'Auto-unlock', 'Bezoeker ASR'],
            17000000002 => ['Arvid (iPhone)', 'Magazijnmedewerker', 'Storingsdienst', 'DHL Express'],
            17000000003 => ['Werkplaats-team', 'Leverancier ABC', 'Arvid (iPhone)', 'Inkoop Mark'],
            17000000004 => ['Arvid (iPhone)', 'Bezoeker', 'Klant rondleiding', 'Onderhoud HVAC'],
            17000000005 => ['Arvid (iPhone)', 'Tuinman', 'Buurtwacht'],
        ];

        $names = $authNames[$smartlockId] ?? ['Arvid (iPhone)'];

        // Synthetic patroon: 25 events verspreid over de laatste 7 dagen.
        // Combinaties van actie/trigger om de timeline visueel rijk te maken.
        $patterns = [
            ['action' => 1, 'trigger' => 1, 'minutes' => 23],   // auto-unlock 's ochtends
            ['action' => 2, 'trigger' => 3, 'minutes' => 95],   // auto-lock kort daarna
            ['action' => 1, 'trigger' => 2, 'minutes' => 215],  // keypad bezoeker
            ['action' => 4, 'trigger' => 0, 'minutes' => 380],  // lock & go
            ['action' => 1, 'trigger' => 0, 'minutes' => 540],  // handmatig open
            ['action' => 2, 'trigger' => 0, 'minutes' => 720],  // handmatig dicht
            ['action' => 1, 'trigger' => 2, 'minutes' => 1015], // keypad
            ['action' => 1, 'trigger' => 1, 'minutes' => 1470], // auto-unlock dag 2
            ['action' => 2, 'trigger' => 3, 'minutes' => 1530], // auto-lock dag 2
            ['action' => 1, 'trigger' => 2, 'minutes' => 1685], // keypad dag 2
            ['action' => 2, 'trigger' => 0, 'minutes' => 1810], // dag 2 dicht
            ['action' => 1, 'trigger' => 0, 'minutes' => 2155], // dag 2 avond
            ['action' => 1, 'trigger' => 1, 'minutes' => 2890], // dag 3 auto
            ['action' => 4, 'trigger' => 0, 'minutes' => 2965], // dag 3 L&G
            ['action' => 1, 'trigger' => 2, 'minutes' => 3210], // dag 3 keypad
            ['action' => 2, 'trigger' => 3, 'minutes' => 3275], // dag 3 auto-lock
            ['action' => 1, 'trigger' => 0, 'minutes' => 4320], // dag 4
            ['action' => 2, 'trigger' => 0, 'minutes' => 4490], // dag 4
            ['action' => 1, 'trigger' => 1, 'minutes' => 5780], // dag 5 auto
            ['action' => 1, 'trigger' => 2, 'minutes' => 5995], // dag 5 keypad
            ['action' => 2, 'trigger' => 3, 'minutes' => 6080], // dag 5 auto-lock
            ['action' => 1, 'trigger' => 0, 'minutes' => 7215], // dag 6
            ['action' => 4, 'trigger' => 0, 'minutes' => 7335], // dag 6 L&G
            ['action' => 1, 'trigger' => 2, 'minutes' => 8665], // dag 7 keypad
            ['action' => 2, 'trigger' => 0, 'minutes' => 8820], // dag 7 dicht
        ];

        $entries = [];
        foreach ($patterns as $i => $p) {
            $isKeypad = $p['trigger'] === 2;

            $entries[] = [
                'id' => sprintf('%s-%02d', $smartlockId, $i),
                'smartlockId' => $smartlockId,
                'accountUserId' => 9999 + $i,
                'authId' => 1000 + ($i % count($names)),
                'authType' => $isKeypad ? 'keypad' : 'app',
                'name' => $names[$i % count($names)],
                'action' => $p['action'],
                'trigger' => $p['trigger'],
                'state' => 0,
                'autoUnlock' => $p['trigger'] === 1,
                'date' => $now->subMinutes($p['minutes'])->toIso8601String(),
                'source' => $isKeypad ? 'keypad' : 'app',
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function authsFor(int $smartlockId): array
    {
        $now = CarbonImmutable::now();

        return [
            [
                'id' => sprintf('%s-auth-1', $smartlockId),
                'smartlockId' => $smartlockId,
                'authId' => 1001,
                'type' => 0, // app user
                'name' => 'Arvid de Jong',
                'enabled' => true,
                'remoteAllowed' => true,
                'allowedWeekDays' => 127,
                'lastActiveDate' => $now->subMinutes(17)->toIso8601String(),
                'creationDate' => '2026-01-12T09:00:00.000Z',
                'updateDate' => '2026-04-02T14:21:00.000Z',
            ],
            [
                'id' => sprintf('%s-auth-2', $smartlockId),
                'smartlockId' => $smartlockId,
                'authId' => 1010,
                'type' => 13, // keypad code
                'code' => 482913,
                'name' => 'Schoonmaak Maandag',
                'enabled' => true,
                'remoteAllowed' => false,
                'allowedFromDate' => '2026-01-15T07:00:00.000Z',
                'allowedUntilDate' => '2026-12-31T19:00:00.000Z',
                'allowedWeekDays' => 64, // monday
                'lastActiveDate' => $now->subDays(2)->setTime(9, 12)->toIso8601String(),
                'creationDate' => '2026-01-15T07:00:00.000Z',
                'updateDate' => '2026-01-15T07:00:00.000Z',
            ],
            [
                'id' => sprintf('%s-auth-3', $smartlockId),
                'smartlockId' => $smartlockId,
                'authId' => 1011,
                'type' => 13,
                'code' => 728401,
                'name' => 'Postbezorger PostNL',
                'enabled' => true,
                'remoteAllowed' => false,
                'allowedWeekDays' => 62, // mon–fri
                'lastActiveDate' => $now->subHours(4)->toIso8601String(),
                'creationDate' => '2026-02-01T08:00:00.000Z',
                'updateDate' => '2026-02-01T08:00:00.000Z',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function accountLogs(): array
    {
        return collect(self::smartlocks())
            ->flatMap(fn (array $lock) => self::logsFor((int) $lock['smartlockId']))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function accountAuths(): array
    {
        return collect(self::smartlocks())
            ->flatMap(fn (array $lock) => self::authsFor((int) $lock['smartlockId']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function account(): array
    {
        return [
            'accountId' => 9999,
            'email' => 'demo@darvis.nl',
            'name' => 'Darvis Demo',
            'language' => 'nl',
            'creationDate' => '2026-01-01T00:00:00.000Z',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function webhooks(): array
    {
        return [
            [
                'notificationId' => '11111111-2222-3333-4444-555555555555',
                'referenceId' => 'darvis-nuki',
                'pushId' => null,
                'os' => 1,
                'language' => 'nl',
                'wantsConfig' => false,
                'wantsLogs' => true,
                'secret' => null,
                'url' => 'https://example.test/nuki/webhook',
                'eventTypes' => ['DEVICE_STATUS', 'DEVICE_LOGS', 'DEVICE_CONFIG'],
            ],
        ];
    }
}
