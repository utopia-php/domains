<?php

namespace Utopia\Domains;

use Utopia\Domains\Registrar\Adapter as RegistrarAdapter;
use \Utopia\Domains\Contact;

class Registrar
{
    /**
     * Registration Types
     */
    public const REG_TYPE_NEW = RegistrarAdapter::REG_TYPE_NEW;
    public const REG_TYPE_TRANSFER = RegistrarAdapter::REG_TYPE_TRANSFER;
    public const REG_TYPE_RENEWAL = RegistrarAdapter::REG_TYPE_RENEWAL;
    public const REG_TYPE_TRADE = RegistrarAdapter::REG_TYPE_TRADE;

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

    public function purchase(string $domain, array|Contact $contacts, array $nameservers = []): array
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

    public function updateDomain(string $domain, array|Contact $contacts, array $details): bool
    {
        return $this->adapter->updateDomain($domain, $contacts, $details);
    }

    public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): array
    {
        return $this->adapter->getPrice($domain, $period, $regType, $ttl);
    }

    public function renew(string $domain, int $years): array
    {
        return $this->adapter->renew($domain, $years);
    }

    public function transfer(string $domain, string $authCode, array|Contact $contacts, array $nameservers = []): array
    {
        return $this->adapter->transfer($domain, $authCode, $contacts, $nameservers);
    }

    public function getAuthCode(string $domain): string
    {
        return $this->adapter->getAuthCode($domain);
    }
}
