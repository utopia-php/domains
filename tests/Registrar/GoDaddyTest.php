<?php

namespace Utopia\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\GoDaddy;

class GoDaddyTest extends TestCase
{
  private GoDaddy $client;
  private string $domain;

  protected function setUp(): void
  {
    $env = 'DEV';
    $key = getenv('GODADDY_KEY');
    $secret = getenv('GODADDY_SECRET');

    $this->client = new GoDaddy($env, $key,  $secret);
    $this->domain = Self::generateRandomString() . '.net';
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
    $data = $list[0];
    // var_dump($data);
    // die;

    $domain = $data['domain'];
    $exposeWhois = $data['exposeWhois'];
    $locked = $data['locked'];
    $renewAuto = $data['renewAuto'];
    $consent = Self::purchaseDetails($domain)['consent'];

    $consent['agreementKeys'] = ['EXPOSE_WHOIS'];

    $result = $this->client->updateDomain($domain, [
      'nameServers' => [
        'ns1.example.com',
        'ns2.example.com',
      ],
      'consent' => $consent,
      'exposeWhois' => $exposeWhois,
      'locked' => $locked,
      'renewAuto' => $renewAuto,
    ]);

    var_dump($result);
    die;

    $this->assertTrue(key_exists('nameServers', $result));
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
}