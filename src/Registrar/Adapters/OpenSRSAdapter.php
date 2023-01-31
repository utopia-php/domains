<?php

namespace Utopia\Registrars;

use Utopia\Registrars\RegistrarAdapter;


class OpenSRS extends RegistrarAdapter {

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
   * @param string $apiSecret - for OpenSRS your reseller_username is your apiSecret.
   */
  public function __construct(string $env = 'DEV', string $apiKey, string $apiSecret, string $shopperId)
  {
      $this->endpoint = 
        $env == 'DEV' 
        ? 'https://horizon.opensrs.net:55443' 
        : 'https://rr-n1-tor.opensrs.net:55443';
      
      $this->apiKey = $apiKey;
      $this->apiSecret = $apiSecret;
      $this->shopperId = $shopperId;

      $this->headers = [
        'Content-Type' => 'text/xml',
        'X-Username:' => $this->apiSecret,
      ];
  }

  public function call(string $method, string $path = '', array|string $params = [], array $headers = array()): array|string
  {
    $xml = '';

    $headers = array_merge(
      $this->headers, array_merge(
        $headers,
        [
          'X-Signature:' => md5(md5($xml . $this->apiKey) .  $this->apiKey)
        ]
      )
    );

    $body = $this->buildBody($params);

    return parent::call($method, $path, $body, $headers);
  }
  
  /**
   * Build an XML body for the request.
   * @param array $params 
   * @return string 
   * 
   * params must contain the following:
   * - object
   * - action
   * - attributes : assoc array of params for the object and action
   */
  private function buildBody(array $params):string
  {
    if(empty($params)) {
      return '';
    }
    
    $xml = <<<EOD
      <?xml version='1.0' encoding='UTF-8' standalone='no' ?>
      <!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
      <OPS_envelope>
      <header>
          <version>0.9</version>
      </header>
      <body>
      <data_block>
          <dt_assoc>
              <item key="protocol">XCP</item>
              <item key="object">{object}</item>
              <item key="action">{action}</item>
              <item key="attributes">
              <dt_assoc>
                      {attributes}
              </dt_assoc>
              </item>
          </dt_assoc>
      </data_block>
      </body>
      </OPS_envelope> 
    EOD;

    $object = $params['object'];
    $action = $params['action'];
    $attributes = array_map(
      fn($key, $value):string => "<item key=\"{$key}\">{$value}</item>",
      $params['attributes']
    );

    return str_replace(
      ['{object}', '{action}', '{attributes}'],
      [$object, $action, implode('', $attributes)],
      $xml
    );
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