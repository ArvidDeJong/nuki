<?php

declare(strict_types=1);

namespace Darvis\Nuki\DTOs;

use Carbon\CarbonImmutable;

final readonly class SmartLock
{
    /**
     * Device types as documented by NUKI.
     */
    public const TYPE_SMARTLOCK = 0;

    public const TYPE_OPENER = 2;

    public const TYPE_SMARTDOOR = 3;

    public const TYPE_SMARTLOCK_3 = 4;

    /**
     * Mapping of NUKI door-state code to translation key under
     * `nuki::nuki.door_states.*` so the label is locale-aware.
     *
     * @var array<int, string>
     */
    private const DOOR_STATE_KEYS = [
        1 => 'off',
        2 => 'closed',
        3 => 'open',
        5 => 'calibrating',
    ];

    /**
     * @var array<int, array<int, string>>
     */
    private const STATE_NAMES = [
        // Smart Lock 1.0 / 2.0
        0 => [
            0 => 'uncalibrated',
            1 => 'locked',
            2 => 'unlocking',
            3 => 'unlocked',
            4 => 'locking',
            5 => 'unlatched',
            6 => 'unlocked (lock & go)',
            7 => 'unlatching',
            254 => 'motor blocked',
            255 => 'undefined',
        ],
        // Opener
        2 => [
            0 => 'untrained',
            1 => 'online',
            3 => 'rto active',
            5 => 'open',
            7 => 'opening',
            253 => 'boot run',
            255 => 'undefined',
        ],
        // Smart Door
        3 => [
            0 => 'unknown',
            1 => 'locked',
            2 => 'unlocking',
            3 => 'unlocked',
            4 => 'locking',
            5 => 'open',
            7 => 'opening',
            254 => 'motor blocked',
            255 => 'undefined',
        ],
        // Smart Lock 3.0 / 4.0 / Pro / Go
        4 => [
            0 => 'unknown',
            1 => 'locked',
            2 => 'unlocking',
            3 => 'unlocked',
            4 => 'locking',
            5 => 'unlatched',
            6 => 'unlocked (lock & go)',
            7 => 'unlatching',
            254 => 'motor blocked',
            255 => 'undefined',
        ],
    ];

    public function __construct(
        public int $smartlockId,
        public ?int $accountId,
        public int $type,
        public ?int $authId,
        public string $name,
        public ?float $favourite,
        public ?int $state,
        public ?string $stateName,
        public ?int $batteryCharge,
        public ?bool $batteryCritical,
        public ?bool $batteryCharging,
        public ?bool $keypadBatteryCritical,
        public ?bool $doorsensorBatteryCritical,
        public ?int $firmwareVersion,
        public ?int $hardwareVersion,
        public ?int $doorState,
        public ?int $serverState,
        public ?CarbonImmutable $creationDate,
        public ?CarbonImmutable $updateDate,
        public array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        $type = (int) ($data['type'] ?? 0);
        $stateValue = isset($data['state']['state']) ? (int) $data['state']['state'] : null;
        $stateName = $data['state']['stateName'] ?? null;

        if ($stateName === null && $stateValue !== null) {
            $stateName = self::STATE_NAMES[$type][$stateValue] ?? null;
        }

        return new self(
            smartlockId: (int) $data['smartlockId'],
            accountId: isset($data['accountId']) ? (int) $data['accountId'] : null,
            type: $type,
            authId: isset($data['authId']) ? (int) $data['authId'] : null,
            name: (string) ($data['name'] ?? ''),
            favourite: isset($data['favourite']) ? (float) $data['favourite'] : null,
            state: $stateValue,
            stateName: $stateName,
            batteryCharge: isset($data['state']['batteryCharge']) ? (int) $data['state']['batteryCharge'] : null,
            batteryCritical: $data['state']['batteryCritical'] ?? null,
            batteryCharging: $data['state']['batteryCharging'] ?? null,
            keypadBatteryCritical: $data['state']['keypadBatteryCritical'] ?? null,
            doorsensorBatteryCritical: $data['state']['doorsensorBatteryCritical'] ?? null,
            firmwareVersion: isset($data['firmwareVersion']) ? (int) $data['firmwareVersion'] : null,
            hardwareVersion: isset($data['hardwareVersion']) ? (int) $data['hardwareVersion'] : null,
            doorState: isset($data['state']['doorState']) ? (int) $data['state']['doorState'] : null,
            serverState: isset($data['serverState']) ? (int) $data['serverState'] : null,
            creationDate: isset($data['creationDate']) ? CarbonImmutable::parse($data['creationDate']) : null,
            updateDate: isset($data['updateDate']) ? CarbonImmutable::parse($data['updateDate']) : null,
            raw: $data,
        );
    }

    public function isLocked(): bool
    {
        return $this->state === 1;
    }

    public function isUnlocked(): bool
    {
        return in_array($this->state, [3, 5, 6], true);
    }

    public function doorStateLabel(): ?string
    {
        if ($this->doorState === null || $this->doorState === 0) {
            return null;
        }

        $key = self::DOOR_STATE_KEYS[$this->doorState] ?? null;
        if ($key === null) {
            return null;
        }

        return (string) __('nuki::nuki.door_states.'.$key);
    }
}
