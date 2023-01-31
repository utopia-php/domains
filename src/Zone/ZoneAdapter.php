<?php

namespace Utopia\Zones;

use Exception;

abstract class ZoneAdapter
{
  abstract public function updateRecords(string $domain, array $records);
  
  abstract public function replaceRecords(string $domain, array $records);
  
  abstract public function domainRecord(string $domain, string $type, string $name);
  
  abstract public function addDomainRecord(string $domain, string $type, string $name);
  
  abstract public function updateDomainRecord(string $domain, string $type, string $name);
  
  abstract public function deleteDomainRecord(string $domain, string $type, string $name);
}
