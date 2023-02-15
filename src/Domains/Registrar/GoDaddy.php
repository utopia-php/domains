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

  public function list()
  {
    $result = $this->call('GET', 'domains');
    $result = json_decode($result, true);

    return $result;
  }

  public function available(string $domain)
  {
    $result = $this->call('GET', 'domains/available', [
      'domain' => $domain,
      'checkType' => 'FAST',
      'forTransfer' => 'false',
    ]);

    $result = json_decode($result, true);

    return key_exists('available', $result) && $result['available'] == true;
  }
  
  public function agreements(array|string $tlds, bool $privacy = true)
  {
    $tlds = is_array($tlds) ? $tlds : [$tlds];

    $result = $this->call('GET', 'domains/agreements', [
      'tlds' => implode(',', $tlds),
      'privacy' => $privacy,
    ]);

    $result = json_decode($result, true);
    $result = array_map(function($item) {
      return $item['agreementKey'];
    }, $result);

    return $result;
  }

  public function purchase(string $domain, array $details)
  {
    $result = $this->call('POST', 'domains/purchase', $details);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function suggest(array $keywords, array $tlds = array(), $minLength = 1, $maxLength = 100)
  {
    $result = $this->call('GET', 'domains/suggest', [
      'query' => implode(',', $keywords),
      'tlds' => $tlds,
    ]);

    $result = json_decode($result, true);
    $result = array_map(function($item) {
      return $item['domain'];
    }, $result);

    return $result;
  }
  
  public function tlds():array
  {
    $result = $this->call('GET', 'domains/tlds');
    $result = json_decode($result, true);
    $result = array_map(function($item) {
      return $item['name'];
    }, $result);

    return $result;
  }
  
  public function domain(string $domain)
  {
    $result = $this->call('GET', 'domains/' . $domain);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function updateDomain(string $domain, array $details)
  {
    var_dump($this->headers);

    $result = $this->call('PATCH', 'domains/' . $domain, $details);
    $result = json_decode($result, true);

    return $result;
  }
  
  public function renew(string $domain, int $years)
  { 
    $result = $this->call('POST', 'domains/' . $domain . '/renew', [
      'period' => $years,
    ]);

    $result = json_decode($result, true);

    return $result;
  }
  
  public function transfer(string $domain, array $details)
  {
    $result = $this->call('POST', 'domains/' . $domain . '/transfer', $details);

    $result = json_decode($result, true);
    
    return $result;
  }
  
}