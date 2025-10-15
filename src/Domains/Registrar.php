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

    public function getName(): string
    {
        return $this->adapter->getName();
    }

    public function available(string $domain): bool
    {
        return $this->adapter->available($domain);
    }

    public function purchase(string $domain, array $contacts, array $nameservers = []): array
    {
        return $this->adapter->purchase($domain, $contacts, $nameservers);
    }

    public function suggest(array|string $query, array $tlds = [], int|null $limit = null, string|null $filterType = null, int|null $priceMax = null, int|null $priceMin = null): array
    {
        return $this->adapter->suggest($query, $tlds, $limit, $filterType, $priceMax, $priceMin);
    }

    public function tlds(): array
    {
        return $this->adapter->tlds();
    }

    public function getDomain(string $domain): array
    {
        return $this->adapter->getDomain($domain);
    }

    public function getPrice(string $domain): array
    {
        return $this->adapter->getPrice($domain);
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
