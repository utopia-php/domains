<?php

namespace Utopia\Domains;

use Utopia\Domains\Registrar\Adapter as RegistrarAdapter;
use Utopia\Domains\Registrar\Domain;
use Utopia\Domains\Registrar\Renewal;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\TransferStatus;

class Registrar
{
    /**
     * Registration Types
     */
    public const REG_TYPE_NEW = 'new';
    public const REG_TYPE_TRANSFER = 'transfer';
    public const REG_TYPE_RENEWAL = 'renewal';
    public const REG_TYPE_TRADE = 'trade';

    protected RegistrarAdapter $adapter;

    /**
     * Constructor
     *
     * @param RegistrarAdapter $adapter The registrar adapter to use
     * @param array $defaultNameservers Default nameservers for domain registration
     * @param Cache|null $cache Optional cache instance
     * @param int $connectTimeout Connection timeout in seconds
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(
        RegistrarAdapter $adapter,
        array $defaultNameservers = [],
        ?Cache $cache = null,
        int $connectTimeout = 5,
        int $timeout = 10
    ) {
        $this->adapter = $adapter;

        if (!empty($defaultNameservers)) {
            $this->adapter->setDefaultNameservers($defaultNameservers);
        }

        if ($cache !== null) {
            $this->adapter->setCache($cache);
        }

        $this->adapter->setConnectTimeout($connectTimeout);
        $this->adapter->setTimeout($timeout);
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
     * @param int $periodYears
     * @param array|Contact $contacts
     * @param array $nameservers
     * @return string Order ID
     */
    public function purchase(string $domain, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string
    {
        return $this->adapter->purchase($domain, $contacts, $periodYears, $nameservers);
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
     * @return Domain
     */
    public function getDomain(string $domain): Domain
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
     * Update nameservers of a domain
     *
     * @param string $domain
     * @param array $nameservers
     * @return array
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        return $this->adapter->updateNameservers($domain, $nameservers);
    }

    /**
     * Get the price of a domain
     *
     * @param string $domain
     * @param int $periodYears
     * @param string $regType
     * @param int $ttl
     * @return float
     */
    public function getPrice(string $domain, int $periodYears = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): float
    {
        return $this->adapter->getPrice($domain, $periodYears, $regType, $ttl);
    }

    /**
     * Renewal a domain
     *
     * @param string $domain
     * @param int $periodYears
     * @return Renewal
     */
    public function renew(string $domain, int $periodYears): Renewal
    {
        return $this->adapter->renew($domain, $periodYears);
    }

    /**
     * Transfer a domain
     *
     * @param string $domain
     * @param string $authCode
     * @param array|Contact $contacts
     * @param array $nameservers
     * @return string Order ID
     */
    public function transfer(string $domain, string $authCode, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string
    {
        return $this->adapter->transfer($domain, $authCode, $contacts, $periodYears, $nameservers);
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

    /**
     * Cancel pending purchase orders
     *
     * @return bool
     */
    public function cancelPurchase(): bool
    {
        return $this->adapter->cancelPurchase();
    }

    /**
     * Check transfer status for a domain
     *
     * @param string $domain
     * @param bool $checkStatus
     * @param bool $getRequestAddress
     * @return TransferStatus
     */
    public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): TransferStatus
    {
        return $this->adapter->checkTransferStatus($domain, $checkStatus, $getRequestAddress);
    }
}
