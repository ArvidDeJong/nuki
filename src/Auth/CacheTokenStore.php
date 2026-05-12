<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth;

use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\DTOs\NukiToken;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CacheTokenStore implements TokenStore
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $prefix = 'nuki:oauth:',
    ) {}

    public function get(string $accountKey): ?NukiToken
    {
        $data = $this->cache->get($this->key($accountKey));

        if (! is_array($data)) {
            return null;
        }

        return NukiToken::fromArray($data);
    }

    public function put(string $accountKey, NukiToken $token): void
    {
        $ttl = max(60, $token->expiresAt->diffInSeconds(null, true) + 86400);
        $this->cache->put($this->key($accountKey), $token->toArray(), $ttl);
    }

    public function forget(string $accountKey): void
    {
        $this->cache->forget($this->key($accountKey));
    }

    private function key(string $accountKey): string
    {
        return $this->prefix.$accountKey;
    }
}
