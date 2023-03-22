<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Adapter as DomainsAdapter;

abstract class Adapter extends DomainsAdapter
{
    /**
     * @param  string  $domain
     * @return bool
     */
    abstract public function available(string $domain): bool;

    /**
     * @param  string  $domain
     * @param  Utopia\Domains\Contact[]  $contacts
     * @param  array  $nameservers
     * @return array
     */
    abstract public function purchase(string $domain, array $contacts, array $nameservers = []): array;

    /**
     * @param  array  $query
     * @param  array  $tlds
     * @param  int  $minLength
     * @param  int  $maxLength
     * @return array
     */
    abstract public function suggest(array|string $query, array $tlds = [], $minLength = 1, $maxLength = 100): array;

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
     * @param  int  $years
     * @return array
     */
    abstract public function renew(string $domain, int $years): array;

    /**
     * @param  string  $domain
     * @param  Utopia\Domains\Contact[]  $contacts
     * @param  array  $nameservers
     * @return array
     */
    abstract public function transfer(string $domain, array $contacts, array $nameservers = []): array;
}
