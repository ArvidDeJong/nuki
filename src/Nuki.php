<?php

declare(strict_types=1);

namespace Darvis\Nuki;

use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\Http\HttpClient;
use Darvis\Nuki\Resources\Account;
use Darvis\Nuki\Resources\OAuth;
use Darvis\Nuki\Resources\SmartlockAuths;
use Darvis\Nuki\Resources\SmartlockLogs;
use Darvis\Nuki\Resources\SmartLocks;
use Darvis\Nuki\Resources\Webhooks;

class Nuki
{
    private string $accountKey = 'default';

    public function __construct(
        private readonly HttpClient $http,
        private readonly TokenStore $tokens,
        private readonly array $oauthConfig,
    ) {}

    /**
     * Scope subsequent calls to a specific account (OAuth token storage key).
     */
    public function as(string $accountKey): self
    {
        $clone = clone $this;
        $clone->accountKey = $accountKey;

        return $clone;
    }

    public function currentAccount(): string
    {
        return $this->accountKey;
    }

    public function smartlocks(): SmartLocks
    {
        return new SmartLocks($this->http, $this->accountKey);
    }

    public function logs(): SmartlockLogs
    {
        return new SmartlockLogs($this->http, $this->accountKey);
    }

    public function auths(): SmartlockAuths
    {
        return new SmartlockAuths($this->http, $this->accountKey);
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this->http, $this->accountKey);
    }

    public function oauth(): OAuth
    {
        return new OAuth($this->oauthConfig, $this->tokens);
    }

    public function account(): Account
    {
        return new Account($this->http, $this->accountKey);
    }
}
