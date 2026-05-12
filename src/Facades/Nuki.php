<?php

declare(strict_types=1);

namespace Darvis\Nuki\Facades;

use Darvis\Nuki\Resources\Account;
use Darvis\Nuki\Resources\OAuth;
use Darvis\Nuki\Resources\SmartlockAuths;
use Darvis\Nuki\Resources\SmartlockLogs;
use Darvis\Nuki\Resources\SmartLocks;
use Darvis\Nuki\Resources\Webhooks;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Darvis\Nuki\Nuki as(string $accountKey)
 * @method static string currentAccount()
 * @method static SmartLocks smartlocks()
 * @method static SmartlockLogs logs()
 * @method static SmartlockAuths auths()
 * @method static Webhooks webhooks()
 * @method static OAuth oauth()
 * @method static Account account()
 *
 * @see \Darvis\Nuki\Nuki
 */
class Nuki extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'nuki';
    }
}
