<?php

namespace App\Support\Sunat;

use DateTimeImmutable;
use DateTimeInterface;
use Greenter\Services\Api\BasicToken;
use Greenter\Services\Api\TokenStoreInterface;
use Illuminate\Contracts\Cache\Repository;

final class CacheTokenStore implements TokenStoreInterface
{
    public function __construct(private readonly Repository $cache) {}

    public function get(?string $id): ?BasicToken
    {
        $payload = $this->cache->get($this->key($id));

        if (! is_array($payload) || ! isset($payload['value'], $payload['expire'])) {
            return null;
        }

        return new BasicToken(
            $payload['value'],
            new DateTimeImmutable((string) $payload['expire']),
        );
    }

    public function set(?string $id, BasicToken $token): void
    {
        $expire = $token->getExpire();
        $ttl = $expire instanceof DateTimeInterface
            ? max(60, $expire->getTimestamp() - time())
            : 3000;

        $this->cache->put($this->key($id), [
            'value' => $token->getValue(),
            'expire' => $expire?->format(DateTimeInterface::ATOM),
        ], $ttl);
    }

    private function key(?string $id): string
    {
        return 'sunat.gre.oauth.'.hash('sha256', $id ?? 'default');
    }
}
