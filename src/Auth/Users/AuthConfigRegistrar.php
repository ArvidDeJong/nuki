<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth\Users;

use Darvis\Nuki\Models\NukiUser;
use Illuminate\Config\Repository;

/**
 * Mergt de package-guard en -provider in de Laravel auth-config tijdens
 * runtime, zodat consumers niet zelf config/auth.php hoeven aan te passen.
 *
 * Idempotent: een bestaande consumer-definitie blijft leidend.
 */
final class AuthConfigRegistrar
{
    public const GUARD = 'darvis-nuki';

    public const PROVIDER = 'darvis-nuki-users';

    public static function register(Repository $config): void
    {
        $guards = (array) $config->get('auth.guards', []);

        if (! array_key_exists(self::GUARD, $guards)) {
            $config->set('auth.guards.'.self::GUARD, [
                'driver' => 'session',
                'provider' => self::PROVIDER,
            ]);
        }

        $providers = (array) $config->get('auth.providers', []);

        if (! array_key_exists(self::PROVIDER, $providers)) {
            $config->set('auth.providers.'.self::PROVIDER, [
                'driver' => 'eloquent',
                'model' => NukiUser::class,
            ]);
        }
    }
}
