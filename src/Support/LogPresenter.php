<?php

declare(strict_types=1);

namespace Darvis\Nuki\Support;

use Darvis\Nuki\DTOs\LogEntry;

/**
 * Maps NUKI log actions onto a label/icon/color triple so the timeline and
 * detail views share the same presentation.
 */
final class LogPresenter
{
    /**
     * @return array{label: string, icon: string, color: string}
     */
    public static function describe(LogEntry $entry): array
    {
        $base = match ($entry->action) {
            1 => ['key' => 'unlocked',           'icon' => 'lock-open',                  'color' => 'lime'],
            2 => ['key' => 'locked',             'icon' => 'lock-closed',                'color' => 'sky'],
            3 => ['key' => 'unlatch',            'icon' => 'arrow-up-right',             'color' => 'amber'],
            4 => ['key' => 'lock_and_go',        'icon' => 'bolt',                       'color' => 'violet'],
            5 => ['key' => 'lock_and_go_unlatch', 'icon' => 'bolt',                       'color' => 'violet'],
            208 => ['key' => 'door_opened',      'icon' => 'arrow-top-right-on-square',  'color' => 'amber'],
            209 => ['key' => 'door_closed',      'icon' => 'check',                      'color' => 'emerald'],
            default => ['key' => 'activity',     'icon' => 'bell',                       'color' => 'zinc'],
        };

        $label = (string) __('nuki::nuki.actions.'.$base['key']);

        if ($entry->autoUnlock === true) {
            $label .= ' '.(string) __('nuki::nuki.actions.auto_suffix');
            $base['icon'] = 'sparkles';
        }

        return [
            'label' => $label,
            'icon' => $base['icon'],
            'color' => $base['color'],
        ];
    }

    public static function triggerLabel(?int $trigger): string
    {
        $key = match ($trigger) {
            0 => 'manual',
            1 => 'auto_unlock',
            2 => 'button',
            3 => 'auto_lock',
            4 => 'webapp',
            5 => 'system',
            default => null,
        };

        return $key === null ? '—' : (string) __('nuki::nuki.triggers.'.$key);
    }
}
