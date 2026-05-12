<?php

declare(strict_types=1);

namespace Darvis\Nuki\Support;

use Carbon\CarbonInterface;

/**
 * Weekday bitmask conform de NUKI Web API conventie.
 * Bit-waarden: ma=64, di=32, wo=16, do=8, vr=4, za=2, zo=1.
 */
final class WeekdayBitmask
{
    /** @var array<string, int> */
    public const BITS = [
        'ma' => 64,
        'di' => 32,
        'wo' => 16,
        'do' => 8,
        'vr' => 4,
        'za' => 2,
        'zo' => 1,
    ];

    /**
     * @param  array<int, string>  $days
     */
    public static function fromDays(array $days): ?int
    {
        if (empty($days)) {
            return null;
        }

        $bitmask = 0;
        foreach ($days as $day) {
            $bitmask |= self::BITS[$day] ?? 0;
        }

        return $bitmask > 0 ? $bitmask : null;
    }

    /**
     * @return array<int, string>
     */
    public static function toDays(?int $bitmask): array
    {
        if ($bitmask === null || $bitmask === 0) {
            return [];
        }

        $days = [];
        foreach (self::BITS as $name => $bit) {
            if (($bitmask & $bit) === $bit) {
                $days[] = $name;
            }
        }

        return $days;
    }

    public static function matchesDate(int $bitmask, CarbonInterface $date): bool
    {
        // Carbon: 1 = ma, ..., 7 = zo (ISO).
        $iso = $date->dayOfWeekIso;
        $map = [1 => 64, 2 => 32, 3 => 16, 4 => 8, 5 => 4, 6 => 2, 7 => 1];
        $bit = $map[$iso] ?? 0;

        return ($bitmask & $bit) === $bit;
    }
}
