<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\GoDaddy;
use Utopia\Domains\Zone\GoDaddy as GoDaddyZone;

class GoDaddyTest extends TestCase
{
  private GoDaddy $client;
  private GoDaddyZone $zoneClient;
  private string $domain;

  protected function setUp(): void
  {
    $env = 'DEV';
    $key = getenv('GODADDY_KEY');
    $secret = getenv('GODADDY_SECRET');

    $this->assertNotEmpty($key);
    $this->assertNotEmpty($secret);

    $this->domain = Self::generateRandomString() . '.net';
    $this->client = new GoDaddy($env, $key,  $secret);
    $this->zoneClient = new GoDaddyZone($env, $key,  $secret);
  }

  public function testTlds()
  {
    $result = $this->client->tlds();

    $this->assertTrue(count($result) > 0);
  }

  public function testSuggest()
  {
   $keywords = ['dogs'];
   $tlds = ['com', 'net', 'org', 'io', 'dev', 'app', '.me', 'us'];

   $result = $this->client->suggest($keywords, $tlds);

   $this->assertTrue(count($result) > 0);
  }

  public function testAvailable()
  {
    $result = $this->client->available($this->domain);

    $this->assertTrue($result);
  }

  public function testAgreements()
  {
    $result = $this->client->agreements('net', false);

    $this->assertTrue(count($result) > 0);
  }

  public function testPurchase()
  {
    $details = $this->purchaseDetails($this->domain);

    $result = $this->client->purchase($this->domain, $details);

    $this->assertTrue(key_exists('orderId', $result));
  }

  /** @depends testPurchase */
   public function testList()
   {
    $result = $this->client->list();

    $this->assertTrue(count($result) > 0);

    return $result;
   }

   /** @depends testList */
   public function testDomain($list)
   {
    $domain = $list[0]['domain'];
    $result = $this->client->domain($domain);

    $this->assertTrue(key_exists('authCode', $result));
   }

   /** @depends testList */
   public function testUpdateDomain($list)
   {
    // $data = $list[0];
    // $domain = $data['domain'];
    // $exposeWhois = $data['exposeWhois'];
    // $locked = $data['locked'];
    // $renewAuto = $data['renewAuto'];
    // $consent = Self::purchaseDetails($domain)['consent'];

    // $consent['agreementKeys'] = ['EXPOSE_WHOIS'];

    // $result = $this->client->updateDomain($domain, [
    //   'nameServers' => [
    //     'ns1.digitalocean.com',
    //     'ns2.digitalocean.com',
    //     'ns3.digitalocean.com',
    //   ],
    //   'consent' => $consent,
    //   'exposeWhois' => $exposeWhois,
    //   'locked' => $locked,
    //   'renewAuto' => $renewAuto,
    // ]);

    $this->markTestIncomplete(
      'Incomplete and failing due to GoDaddy API documentation/invalid response.'
    );
   }

  /** @depends testList */
  public function testRenew($list)
  {
    $data = $list[0];
    $domain = $data['domain'];

    $result = $this->client->renew($domain, 5);

    $this->assertTrue(key_exists('orderId', $result));
  }

  public function testTransfer()
  {
    // $domain = $this->generateRandomString() . '.net';
    // $authCode = "12345"; //$data['authCode'];
    // $details = Self::purchaseDetails($domain);
    
    // $data = array_merge([
    //   'authCode' => $authCode,
    //   // 'domain' => $domain,
    //   'period' => 1,
    //   'privacy' => true,
    //   'renewAuto' => false,
    // ], $details);

    // $result = $this->client->transfer($domain, $data);

    // $this->assertTrue(key_exists('orderId', $result));

    $this->markTestIncomplete(
      'Incomplete and failing due to GoDaddy API documentation/invalid response.'
    );
  }

  ////# ZONE TESTS


  /** @depends testList */
  public function testUpdateRecords($list)
  {
    $domain = $list[0]['domain'];
    $record = $this->createRecord();
    $results = $this->zoneClient->updateRecords($domain, [$record]);

    $this->assertTrue(empty($results));

    return [$domain, $record];
  }

  /** @depends testUpdateRecords */
  public function testReplaceRecords($payload)
  {
    // $domain = $payload[0];
    // $record = $payload[1];
    // $record['data'] = '10.0.0.1';
    // $results = $this->zoneClient->replaceRecords($domain, [$record]);

    // var_dump($results);
    // die;

    // $this->assertTrue(empty($results));

    $this->markTestIncomplete(
      'Incomplete and failing due to GoDaddy API documentation/invalid request params.'
    );

  }

  /** @depends testUpdateRecords */
  public function testDomainRecord($payload)
  {
    $domain = $payload[0];
    $record = $payload[1];
    $name = $record['name'];
    $type = $record['type'];
    
    $result = $this->zoneClient->domainRecord($domain, $type, $name);

    $this->assertTrue(key_exists('data', $result[0]));
  }

  private static function purchaseDetails($domain) 
  {
    $contactInfo = [
      "addressMailing" => [
        "address1" => "123 Main St",
        "city" => "San Francisco",
        "country" => "US",
        "postalCode" => "94105",
        "state" => "CA"
      ],
      "email" => "mr.test@testing.com",
      "nameFirst" => "Test",
      "nameLast" => "Tester",
      "phone" => "+1.8031234567",
    ];

    $timestamp = date('Y-m-d\TH:i:s.000') . 'Z';

    return [
      "consent" => [
        "agreedAt" => $timestamp,
        "agreedBy" => "127.0.0.1",
        "agreementKeys" => [
          "DNRA"
        ]
      ],
      "contactAdmin" => $contactInfo,
      "contactBilling" => $contactInfo,
      "contactRegistrant" => $contactInfo,
      "contactTech" => $contactInfo,
      "domain" => $domain,
    ];
  }

  private function createRecord()
  {
    return [
      'data' => '127.0.0.1',
      'name' => $this->generateRandomString(5),
      'ttl' => 5700,
      'type' => 'A'
    ];
  }

  private function generateRandomString($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
  }

}