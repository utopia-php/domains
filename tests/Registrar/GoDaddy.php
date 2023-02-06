<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\GoDaddy;

class GodaddyTest extends TestCase
{
  private GoDaddy $client;

  protected function setUp(): void
  {
    $env = 'DEV';
    $key = getenv('GODADDY_KEY');
    $secret = getenv('GODADDY_SECRET');

    $this->client = new GoDaddy($env, $key,  $secret);
  }

  protected function testAvailable()
  {
    $this->assertTrue($this->client->available($this->generateRandomString() . '.net'));
  }

  private function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}
}