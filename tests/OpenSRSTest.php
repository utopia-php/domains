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
    $key = getenv('OPENSRS_KEY');
    $secret = 'wesscope'; //getenv('OPENSRS_USERNAME');

    $this->assertNotEmpty($key);
    $this->assertNotEmpty($secret);

    $this->domain = Self::generateRandomString() . '.net';
    $this->client = new OpenSRS($env, $key, $secret);
    $this->zoneClient = new OpenSRSZone($env, $key, $secret);
  }

  public function testAvailable()
  {
    $result = $this->client->available($this->domain);

    // $this->assertTrue($result);
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