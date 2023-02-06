<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Registrar\Adapter;


class GoDaddy extends Adapter {

  private string $shopperId;
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
  public function __construct(string $env = 'DEV', string $apiKey, string $apiSecret, string $shopperId = '')
  {
      $endpoint = 
        $env == 'DEV' 
        ? 'https://api.ote-godaddy.com/v1/' 
        : 'https://api.godaddy.com/v1/';
      
      $this->apiKey = $apiKey;
      $this->apiSecret = $apiSecret;

      parent::__construct($endpoint, $apiKey, $apiSecret);

      $this->shopperId = $shopperId;

      $this->headers = array_merge([
        'Authorization' => 'sso-key ' . $this->apiKey . ':' . $this->apiSecret,
        'X-Shopper-Id' => $this->shopperId,
      ], $this->headers);
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