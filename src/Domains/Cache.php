<?php

namespace Utopia\Domains;

use Utopia\Cache\Cache as UtopiaCache;

class Cache
{
    public UtopiaCache $cache;

    public function __construct(UtopiaCache $cache)
    {
        $this->cache = $cache;
    }

    private function getKey(string $domain): string
    {
        return 'domain:' . $domain;
    }

    public function load(string $domain, int $ttl): string|array|null
    {
        return $this->cache->load($this->getKey($domain), $ttl);
    }

    public function save(string $domain, string|array $data): void
    {
        $this->cache->save($this->getKey($domain), $data);
    }

    public function purge(string $domain): array|bool
    {
        return $this->cache->purge($this->getKey($domain));
    }
}
