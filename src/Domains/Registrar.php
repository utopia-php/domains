<?php

namespace Utopia\Domains;

use Utopia\Domains\Registrar\Adapter as RegistrarAdapter;

class Registrar
{
    protected RegistrarAdapter $adapter;

    public function __construct(RegistrarAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function available(string $domain): bool
    {
        return $this->adapter->available($domain);
    }

    public function purchase(string $domain, array $contacts, array $nameservers = []): array
    {
        return $this->adapter->purchase($domain, $contacts, $nameservers);
    }

    public function suggest(array $query, array $tlds = [], $minLength = 1, $maxLength = 100): array
    {
        return $this->adapter->suggest($query, $tlds, $minLength, $maxLength);
    }

    public function tlds(): array
    {
        return $this->adapter->tlds();
    }

    public function getDomain(string $domain): array
    {
        return $this->adapter->getDomain($domain);
    }

    public function renew(string $domain, int $years): array
    {
        return $this->adapter->renew($domain, $years);
    }

    public function transfer(string $domain, array $contacts, array $nameservers = []): array
    {
        return $this->adapter->transfer($domain, $contacts, $nameservers);
    }
}
