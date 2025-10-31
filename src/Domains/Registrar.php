<?php

namespace Utopia\Domains;

use Utopia\Domains\Registrar\Adapter as RegistrarAdapter;
use Utopia\Domains\Registrar\Result\DomainResult;
use Utopia\Domains\Registrar\Result\PriceResult;
use Utopia\Domains\Registrar\Result\PurchaseResult;
use Utopia\Domains\Registrar\Result\RenewResult;
use Utopia\Domains\Registrar\Result\TransferResult;

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

    /**
     * Get the name of the adapter
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->adapter->getName();
    }

    /**
     * Check if a domain is available
     *
     * @param string $domain
     * @return bool
     */
    public function available(string $domain): bool
    {
        return $this->adapter->available($domain);
    }

    /**
     * Purchase a domain
     *
     * @param string $domain
     * @param int $period
     * @param array|Contact $contacts
     * @param array $nameservers
     * @return PurchaseResult
     */
    public function purchase(string $domain, array|Contact $contacts, int $period = 1, array $nameservers = []): PurchaseResult
    {
        return $this->adapter->purchase($domain, $contacts, $period, $nameservers);
    }

    /**
     * Suggest domain names
     *
     * @param array|string $query
     * @param array $tlds
     * @param int|null $limit
     * @param string|null $filterType
     * @param int|null $priceMax
     * @param int|null $priceMin
     * @return array
     */
    public function suggest(array|string $query, array $tlds = [], int|null $limit = null, string|null $filterType = null, int|null $priceMax = null, int|null $priceMin = null): array
    {
        return $this->adapter->suggest($query, $tlds, $limit, $filterType, $priceMax, $priceMin);
    }

    /**
     * Get the list of top-level domains
     *
     * @return array
     */
    public function tlds(): array
    {
        return $this->adapter->tlds();
    }

    /**
     * Get the details of a domain
     *
     * @param string $domain
     * @return DomainResult
     */
    public function getDomain(string $domain): DomainResult
    {
        return $this->adapter->getDomain($domain);
    }

    /**
     * Update the details of a domain
     *
     * @param string $domain
     * @param array $details
     * @param array|Contact|null $contacts
     * @return bool
     */
    public function updateDomain(string $domain, array $details, array|Contact|null $contacts = null): bool
    {
        return $this->adapter->updateDomain($domain, $details, $contacts);
    }

    /**
     * Get the price of a domain
     *
     * @param string $domain
     * @param int $period
     * @param string $regType
     * @param int $ttl
     * @return PriceResult
     */
    public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): PriceResult
    {
        return $this->adapter->getPrice($domain, $period, $regType, $ttl);
    }

    /**
     * Renew a domain
     *
     * @param string $domain
     * @param int $period
     * @return RenewResult
     */
    public function renew(string $domain, int $period): RenewResult
    {
        return $this->adapter->renew($domain, $period);
    }

    /**
     * Transfer a domain
     *
     * @param string $domain
     * @param string $authCode
     * @param array|Contact $contacts
     * @param array $nameservers
     * @return TransferResult
     */
    public function transfer(string $domain, string $authCode, array|Contact $contacts, int $period = 1, array $nameservers = []): TransferResult
    {
        return $this->adapter->transfer($domain, $authCode, $contacts, $period, $nameservers);
    }

    /**
     * Get the auth code of a domain
     *
     * @param string $domain
     * @return string
     */
    public function getAuthCode(string $domain): string
    {
        return $this->adapter->getAuthCode($domain);
    }
}
