<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\OpenSRS;
use Utopia\Domains\Zone\OpenSRS as OpenSRSZone;

class OpenSRSTest extends TestCase
{
  private OpenSRS $client;
  private OpenSRSZone $zoneClient;
  private string $domain;

  protected function setUp(): void
  {
    $env = 'DEV';
    $key = getenv('OPENSRS_DEV_KEY');
    $secret = getenv('OPENSRS_USERNAME');

    $this->assertNotEmpty($key);
    $this->assertNotEmpty($secret);

    $this->domain = Self::generateRandomString() . '.net';
    $this->client = new OpenSRS($env, $key, $secret);
    $this->zoneClient = new OpenSRSZone($env, $key, $secret);
  }

  public function testAvailable()
  {
    $result = $this->client->available($this->domain);

    $this->assertTrue($result);
  }

  public function testPurchase()
  {
    $domain = $this->domain;

    $result = $this->client->purchase($domain, [
      'contacts' => Self::purchaseContact(),
    ]);

    $this->assertTrue($result['successful']);

    return $domain;
  }

  /** @depends testPurchase */
  public function testUpdateNameservers($domain)
  {
    $result = $this->client->updateNameservers($domain, [
      'ns1.hover.com',
      'ns2.hover.com',
    ]);

    $this->assertTrue($result['successful']);
  }

  private static function purchaseContact() 
  {
    $contact = [
      "firstname" => "Test",
      "lastname" => "Tester",
      "phone" => "+1.8031234567",
      "email" => "testing@test.com",
      "address1" => "123 Main St",
      "address2" => "Suite 100",
      "address3" => "",
      "city" => "San Francisco",
      "state" => "CA",
      "country" => "US",
      "postalcode" => "94105",
      "org" => "Test Inc",
      "owner" => "Test Tester",
    ];


    return [
      "owner" => $contact,
      "admin" => $contact,
      "tech" => $contact,
      "billing" => $contact,
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