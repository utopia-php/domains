<?php

namespace Utopia\Domains\Registrar;

use Exception;
use SimpleXMLElement;
use Utopia\Domains\Registrar\Adapter;


class OpenSRS extends Adapter {

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
  public function __construct(string $env, string $apiKey, string $username)
  {
      $this->endpoint = 
        $env == 'DEV' 
        ? 'https://horizon.opensrs.net:55443' 
        : 'https://rr-n1-tor.opensrs.net:55443';
      
      $this->apiKey = $apiKey;
      $this->apiSecret = $username;

      $this->headers = [
        'Content-Type:text/xml',
        'X-Username:' . $this->apiSecret,
      ];

  }

  public function send(array $params = []): array|string
  {
    $object = $params['object'];
    $action = $params['action'];
    $domain = $params['domain'] ?? null;
    $attributes = $params['attributes'];


    $xml = $this->buildEnvelop($object, $action, $attributes, $domain);

    $headers = array_merge($this->headers, [
      'X-Signature:' . md5(md5($xml . $this->apiKey) .  $this->apiKey)
    ]);

    $ch = curl_init($this->endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

    return curl_exec($ch);
  }
  
  
  public function available(string $domain)
  {
    $result = $this->send([
      'object' => 'DOMAIN',
      'action' => 'LOOKUP',
      'attributes' => [
        'domain' => $domain,
      ]
    ]);

    $result = simplexml_load_string($result);
    $result = $result->body->data_block->dt_assoc->item;

    $available = false;

    foreach($result as $r) {
      if($r == "Domain available") {
        $available = true;
        break;
      }
    }

    return $available;
  }
  
  public function updateNameservers(string $domain, array $nameservers)
  {
    $message = [
      'object' => 'DOMAIN',
      'action' => 'advanced_update_nameservers',
      'domain' => $domain,
      'attributes' => [
        'add_ns' => $nameservers,
        'op_type' => 'add_remove',
      ]
    ];

    // $xml = $this->buildEnvelop($message['object'], $message['action'], $message['attributes'], $message['domain']);
    // var_dump($xml);
    // die;
    
    $result = $this->send($message);
    $result = simplexml_load_string($result);
    
    $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="is_success"]');
    $successful = "{$elements[0]}" == '1' ? true : false;

    $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_text"]');
    $text = "{$elements[0]}";

    $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_code"]');
    $code = "{$elements[0]}";

    return [
      "code" => $code,
      "text" => $text,
      "successful" => $successful,
    ];
  }

  private function register(string $domain, string $regType, array $user, array $contacts, array $nameservers = [])
  {
    $hasNameservers = empty($nameservers) ? 0 : 1;

    $message = [
      'object' => 'DOMAIN',
      'action' => 'SW_REGISTER',
      'attributes' => [
        'domain' => $domain,
        'period' => 1,
        'contact_set' => $contacts,
        'custom_tech_contact' => 0,
        'custom_nameservers' => $hasNameservers,
        'reg_username' => $user['username'],
        'reg_password' => $user['password'],
        'reg_type' => $regType,
        'handle' => 'process',
        'f_whois_privacy' => 1,
        'auto_renew' => 0,
      ]
    ];

    if($hasNameservers) {
      $message['attributes']['nameserver_list'] = $nameservers;
    }

    $result = $this->send($message);
    $result = $this->response($result);
    
    return $result;
  }
  
  public function purchase(string $domain, array $details)
  {
    $nameservers = $details['nameservers'] ?? [
      'ns1.appwrite.com',
      'ns2.appwrite.com'
    ];

    $contacts = $details['contacts'];

    $user = $details['user'] ?? [
      'username' => 'appwrite',
      'password' => 'abdcefghijk'
    ];

    $regType = $details['regType'] ?? 'new';

    $result = $this->register($domain, $regType, $user, $contacts, $nameservers);

    return $result;
  }

  public function cancelPurchase(string $domain)
  {
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
 
  private function response($xml)
  {
    $doc = simplexml_load_string($xml);
    $elements = $doc->xpath('//data_block/dt_assoc/item[@key="response_code"]');
    $responseCode = "{$elements[0]}";

    $elements = $doc->xpath('//data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="id"]');
    $responseId = "{$elements[0]}";

    $elements = $doc->xpath('//data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="domain_id"]');
    $responseDomainId = "{$elements[0]}";

    $elements = $doc->xpath('//data_block/dt_assoc/item[@key="is_success"]');
    $responseSuccessful = "{$elements[0]}" === '1' ? true : false;

    return [
      "code" => $responseCode,
      "id" => $responseId,
      "domainId" => $responseDomainId,
      "successful" => $responseSuccessful,
    ];
  }
  
  private function createEnvelopItem(string $key, string|int $value)
  {
    return "<item key='{$key}'>{$value}</item>";
  }

  private function validateContact(array $contact) 
  {
    $required = [
      'firstname',
      'lastname',
      'email',
      'phone',
      'address1',
      'city',
      'state',
      'postalcode',
      'country',
      'owner',
      'org',
    ];

    $filter = [
      'firstname' => 'first_name',
      'lastname' => 'last_name',
      'org' => 'org_name',
      'postalcode' => 'postal_code',
    ];

    $result = [];

    foreach($required as $key) {
      $key = strtolower($key);
      
      if(!isset($contact[$key])) {
        throw new Exception("Contact is missing required field: {$key}");
      }
      
      $filtered_key = $filter[$key] ?? $key;

      $result[$filtered_key] = $contact[$key];
    }

    return $result;
  }

  private function createContact(string $type, array $contact) 
  {
    $contact = $this->validateContact($contact);
    $result = [
      "<item key='{$type}'>",
      '<dt_assoc>',
    ];

    foreach ($contact as $key => $value) {
      $result[] = $this->createEnvelopItem($key, $value);
    }

    $result[] = '</dt_assoc>';
    $result[] = '</item>';

    $xml = implode(PHP_EOL, $result);

    return $xml;
  }

  private function createContactSet(array $contacts)
  {
    $result = [
      '<item key="contact_set">',
      '<dt_assoc>',
    ];

    foreach($contacts as $type => $contact) {
      $result[] = $this->createContact($type, $contact);
    }

    $result[] = '</dt_assoc>';
    $result[] = '</item>';

    return implode(PHP_EOL, $result);
  }

  private function createNameserver(string $name, int $sortOrder)
  {
    return implode(PHP_EOL, [
      '<dt_assoc>',
      $this->createEnvelopItem('name', $name),
      $this->createEnvelopItem('sortorder', $sortOrder),
      '</dt_assoc>',
    ]);
  }

  private function createNameserverList(array $nameservers)
  {
    $result = [
      '<item key="nameserver_list">',
      '<dt_array>',
    ];

    for($index = 0; $index < count($nameservers); $index++) {
      $result[] = $this->createNameserver($nameservers[$index], $index);
    }

    $result[] = '</dt_array>';
    $result[] = '</item>';

    return implode(PHP_EOL, $result);
  }

  private function createNamespaceAssign(array $nameservers)
  {
    $result = [
      '<item key="add_ns">',
      '<dt_array>',
    ];

    for($index = 0; $index < count($nameservers); $index++) {
      $result[] = $this->createEnvelopItem($index, $nameservers[$index]);
    }

    $result[] = '</dt_array>';
    $result[] = '</item>';

    return implode(PHP_EOL, $result);
  }

  private function buildEnvelop(string $object, string $action, array $attributes, string $domain = null)
  {
    // $object = strtoupper($object);
    // $action = strtoupper($action);

    $result = [
      '<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
      "<!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>",
      '<OPS_envelope>',
      '<header>',
      '<version>0.9</version>',
      '</header>',
      '<body>',
      '<data_block>',
      '<dt_assoc>',
      $this->createEnvelopItem('protocol', 'XCP'),
      $this->createEnvelopItem('object', $object),
      $this->createEnvelopItem('action', $action),
      (
        is_null($domain)
        ? ''
        : $this->createEnvelopItem('domain', $domain)
      ),
      '<item key="attributes">',
      '<dt_assoc>',
    ];

    foreach($attributes as $key => $value) {
      switch($key) {
        case 'contact_set':
          $result[] = $this->createContactSet($value);
          break;
        case 'nameserver_list':
          $result[] = $this->createNameserverList($value);
          break;
        case 'assign_ns':
        case 'add_ns':
        case 'remove_ns':
          $result[] = $this->createNamespaceAssign($value);
          break;
        default:
          $result[] = $this->createEnvelopItem($key, $value);
      }
    }

    $closing = [
      '</dt_assoc>',
      '</item>',
      '</dt_assoc>',
      '</data_block>',
      '</body>',
      '</OPS_envelope>',
    ];

    foreach($closing as $line) {
      $result[] = $line;
    }

    $xml = implode(PHP_EOL, $result);

    return $xml;
  }
 }
