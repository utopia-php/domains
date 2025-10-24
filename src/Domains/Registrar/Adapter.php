<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Adapter as DomainsAdapter;

abstract class Adapter extends DomainsAdapter
{
    /**
     * Registration Types
     */
    public const REG_TYPE_NEW = 'new';
    public const REG_TYPE_TRANSFER = 'transfer';
    public const REG_TYPE_RENEWAL = 'renewal';
    public const REG_TYPE_TRADE = 'trade';

    /**
     * @return string
     */
    abstract public function getName(): string;

    /**
     * @param  string  $domain
     * @return bool
     */
    abstract public function available(string $domain): bool;

    /**
     * @param  string  $domain
     * @param  array<\Utopia\Domains\Contact>  $contacts
     * @param  array  $nameservers
     * @return array
     */
    abstract public function purchase(string $domain, array $contacts, array $nameservers = []): array;

    /**
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
     * @return array
     */
    abstract public function tlds(): array;

    /**
     * @param  string  $domain
     * @return array
     */
    abstract public function getDomain(string $domain): array;

    /**
     * @param  string  $domain
     * @param  array $contacts
     * @param  array $details
     * @return bool
     */
    abstract public function updateDomain(string $domain, array $contacts, array $details): bool;

    /**
     * @param  string  $domain
     * @param  int  $period
     * @param  string  $regType
     * @param  int  $ttl
     * @return array
     */
    abstract public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): array;

    /**
     * @param  string  $domain
     * @param  int  $years
     * @return array
     */
    abstract public function renew(string $domain, int $years): array;

    /**
     * @param  string  $domain
     * @param  string  $authCode
     * @param  array<\Utopia\Domains\Contact>  $contacts
     * @param  array  $nameservers
     * @return array
     */
    abstract public function transfer(string $domain, string $authCode, array $contacts, array $nameservers = []): array;
}
