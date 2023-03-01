<?php

namespace Utopia\Domains\Zone;

use Utopia\Domains\Adapter as DomainsAdapter;

abstract class Adapter extends DomainsAdapter
{
    abstract public function updateRecords(string $domain, array $records);

    abstract public function replaceRecords(string $domain, array $records);

    abstract public function domainRecord(string $domain, string $type, string $name);

    abstract public function addDomainRecord(string $domain, string $destination, string $type, string $name);

    abstract public function updateDomainRecord(string $domain, string $type, string $name);

    abstract public function deleteDomainRecord(string $domain, string $type, string $name);
}
