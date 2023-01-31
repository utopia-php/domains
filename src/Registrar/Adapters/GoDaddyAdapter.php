<?php

namespace Utopia\Registrars;

use Utopia\Registrars\RegistrarAdapter;


class GoDaddy extends RegistrarAdapter {

  private array $sources = [
    'CC_TLD', 
    'EXTENSION', 
    'KEYWORD_SPIN', 
    'PREMIUM', 
    'cctld', 
    'extension', 
    'keywordspin', 
    'premium'
  ];

  /**
   * __construct
   * Instantiate a new adapter.
   *
   * @param string $env
   * @param string $apiKey
   * @param string $apiSecret
   */
  public function __construct(string $env = 'DEV', string $apiKey, string $apiSecret, string $shopperId)
  {
      $this->endpoint = 
        $env == 'DEV' 
        ? 'https://api.ote-godaddy.com/v1/' 
        : 'https://api.godaddy.com/v1/';
      
      $this->apiKey = $apiKey;
      $this->apiSecret = $apiSecret;
      $this->shopperId = $shopperId;

      $this->headers = [
        'Authorization' => 'sso-key ' . $this->apiKey . ':' . $this->apiSecret,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-Shopper-Id' => $this->shopperId,
      ];
  }

  public function available(string $domain)
  {
    $result = $this->call('GET', 'domains/available', [
      'domain' => $domain,
      'checkType' => 'FAST',
      'forTransfer' => 'false',
    ]);

    return key_exists('available', $result) && $result['available'] == true;
  }
  
  public function purchase(string $domain, array $details)
  {
    $result = $this->call('POST', 'domains/purchase', $details);

    return $result;
  }
  
  public function cancelPurchase(string $domain)
  {
    $result = $this->call('DELETE', 'domains/' . $domain, [
    ]);

    return $result;
  }
  
  public function suggest(array $query, array $tlds = array(), $minLength = 1, $maxLength = 100)
  {
    $result = $this->call('GET', 'domains/suggest', [
      'query' => $query,
      'tlds' => $tlds,
      'minLength' => $minLength,
      'maxLength' => $maxLength,
      'sources' => $this->sources,
      'limit' => 100,
    ]);

    return $result;
  }
  
  public function tlds():array
  {
    return $this->call('GET', 'domains/tlds');
  }
  
  public function domain(string $domain)
  {
    $result = $this->call('GET', 'domains/' . $domain);

    return $result;
  }
  
  public function updateDomain(string $domain, array $details)
  {
    $result = $this->call('PATCH', 'domains/' . $domain, $details);

    return $result;
  }
  
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

  public function renew(string $domain, int $years)
  { 
    $result = $this->call('POST', 'domains/' . $domain . '/renew', [
      'period' => $years,
    ]);

    return $result;
  }
  
  public function transfer(string $domain, array $details)
  {
    $result = $this->call('POST', 'domains/' . $domain . '/transfer', $details);

    return $result;
  }
  
}