<?php

namespace Utopia\Domains\Zones;

use Utopia\Domains\Zones\Adapter;


class GoDaddy extends Adapter 
{
  public function updateRecords(string $domain, array $records)
  {
    $result = $this->call('PATCH', 'domains/' . $domain . '/records', $records);

    return $result;
  }
  
  public function replaceRecords(string $domain, array $records)
  {
    $result = $this->call('PUT', 'domains/' . $domain . '/records', $records);

    return $result;
  }
  
  public function domainRecord(string $domain, string $type, string $name)
  {
    $result = $this->call('GET', 'domains/' . $domain . '/records/' . $type . '/' . $name);
  }
  
  public function addDomainRecord(string $domain, string $type, string $name)
  {
    $result = $this->call('POST', 'domains/' . $domain . '/records/' . $type . '/' . $name);

    return $result;
  }
  
  public function updateDomainRecord(string $domain, string $type, string $name)
  {
    $result = $this->call('PATCH', 'domains/' . $domain . '/records/' . $type . '/' . $name);

    return $result;
  }
  
  public function deleteDomainRecord(string $domain, string $type, string $name)
  {
    $result = $this->call('DELETE', 'domains/' . $domain . '/records/' . $type . '/' . $name);

    return $result;
  }
  
  public function replaceDomainRecords(string $domain, string $type, array $records)
  {
    $result = $this->call('PUT', 'domains/' . $domain . '/records/' . $type, $records);

    return $result;
  }

}
