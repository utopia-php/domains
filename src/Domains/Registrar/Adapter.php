<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Adapter as DomainsAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar;

abstract class Adapter extends DomainsAdapter
{
    /**
     * Default nameservers for domain registration
     */
    protected array $defaultNameservers = [];

    /**
     * Cache instance
     */
    protected ?Cache $cache = null;

    /**
     * Connection timeout in seconds
     */
    protected int $connectTimeout = 5;

    /**
     * Request timeout in seconds
     */
    protected int $timeout = 10;

    /**
     * Set default nameservers
     *
     * @param array $nameservers
     * @return void
     */
    public function setDefaultNameservers(array $nameservers): void
    {
        $this->defaultNameservers = $nameservers;
    }

    /**
     * Set cache instance
     *
     * @param Cache|null $cache
     * @return void
     */
    public function setCache(?Cache $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Set connection timeout
     *
     * @param int $connectTimeout
     * @return void
     */
    public function setConnectTimeout(int $connectTimeout): void
    {
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Set request timeout
     *
     * @param int $timeout
     * @return void
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Get the name of the adapter
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Check if a domain is available
     *
     * @param  string  $domain
     * @return bool
     */
    abstract public function available(string $domain): bool;

    /**
     * Purchase a domain
     *
     * @param  string  $domain
     * @param  array|Contact  $contacts
     * @param  int  $periodYears
     * @param  array  $nameservers
     * @return string Order ID
     */
    abstract public function purchase(string $domain, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string;

    /**
     * Suggest domain names
     *
     * @param  array  $query
     * @param  array  $tlds
     * @param  int|null $limit
     * @param  string|null $filterType Filter results by type: 'premium', 'suggestion', or null for both
     * @param  int|null $priceMax
     * @param  int|null $priceMin
     * @return array
     */
    abstract public function suggest(array|string $query, array $tlds = [], int|null $limit = null, string|null $filterType = null, int|null $priceMax = null, int|null $priceMin = null): array;

    /**
     * Get the TLDs supported by the adapter
     *
     * @return array
     */
    abstract public function tlds(): array;

    /**
     * Get the domain information
     *
     * @param  string  $domain
     * @return Domain
     */
    abstract public function getDomain(string $domain): Domain;

    /**
     * Update the domain information
     *
     * @param  string  $domain
     * @param  array $details
     * @param  array|Contact|null $contacts
     * @return bool
     */
    abstract public function updateDomain(string $domain, array $details, array|Contact|null $contacts = null): bool;

    /**
     * Update the nameservers for a domain
     *
     * @param string $domain
     * @param array $nameservers
     * @return array
     * @throws \Exception
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        throw new \Exception('Method not implemented');
    }

    /**
     * Get the price of a domain
     *
     * @param  string  $domain
     * @param  int  $periodYears
     * @param  string  $regType
     * @param  int  $ttl
     * @return float
     */
    abstract public function getPrice(string $domain, int $periodYears = 1, string $regType = Registrar::REG_TYPE_NEW, int $ttl = 3600): float;

    /**
     * Renew a domain
     *
     * @param  string  $domain
     * @param  int  $periodYears
     * @return Renewal
     */
    abstract public function renew(string $domain, int $periodYears): Renewal;

    /**
     * Transfer a domain
     *
     * @param  string  $domain
     * @param  string  $authCode
     * @param  array|Contact  $contacts
     * @param  int  $periodYears
     * @param  array  $nameservers
     * @return string Order ID
     */
    abstract public function transfer(string $domain, string $authCode, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string;

    /**
     * Get the authorization code for an EPP domain
     *
     * @param  string  $domain
     * @return string
     */
    abstract public function getAuthCode(string $domain): string;

    /**
     * Check transfer status for a domain
     *
     * @param  string  $domain
     * @param  bool  $checkStatus
     * @param  bool  $getRequestAddress
     * @return TransferStatus
     */
    abstract public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): TransferStatus;

    /**
     * Cancel pending purchase orders
     *
     * @return bool
     */
    abstract public function cancelPurchase(): bool;
}
