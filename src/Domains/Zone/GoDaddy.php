<?php

namespace Utopia\Domains\Zone;

use Utopia\Domains\Zone\Adapter;


class GoDaddy extends Adapter 
{
  public function __construct(string $env, string $apiKey, string $apiSecret, string $shopperId = '')
  {
      $endpoint = 
        $env == 'DEV' 
        ? 'https://api.ote-godaddy.com/v1/' 
        : 'https://api.godaddy.com/v1/';
      
      $this->apiKey = $apiKey;
      $this->apiSecret = $apiSecret;

      parent::__construct($endpoint, $apiKey, $apiSecret);

      $headers = [
        'Authorization' => 'sso-key ' . $this->apiKey . ':' . $this->apiSecret,        
      ];

      if($shopperId !== '')
        $headers = array_merge($headers, ['X-Shopper-Id' => $shopperId]);
        

      $this->headers = array_merge($headers, $this->headers);
  }

  public function updateRecords(string $domain, array $records)
  {
    $result = $this->call('PATCH', 'domains/' . $domain . '/records', $records);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function replaceRecords(string $domain, array $records)
  {
    $result = $this->call('PUT', 'domains/' . $domain . '/records', $records);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function domainRecord(string $domain, string $type, string $name)
  {
    $endpoint = 'domains/' . $domain . '/records/' . $type . '/' . $name;
    $result = $this->call('GET', $endpoint);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function addDomainRecord(string $domain, string $destination, string $type, string $name)
  {
    $result = $this->updateRecords($domain,
      [[
        'type' => $type,
        'name' => $name,
        'data' => $destination,
      ]]
    );

    $result = json_decode($result, true);

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
