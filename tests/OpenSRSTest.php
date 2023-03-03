<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\OpenSRS;
use Utopia\Domains\Zone\OpenSRS as OpenSRSZone;

class OpenSRSTest extends TestCase
{
    private OpenSRS $client;
    private string $domain;

    protected function setUp(): void
    {
        $env = 'DEV';
        $key = getenv('OPENSRS_DEV_KEY');
        $secret = getenv('OPENSRS_USERNAME');

        $this->assertNotEmpty($key);
        $this->assertNotEmpty($secret);

        $this->domain = self::generateRandomString().'.net';
        $this->client = new OpenSRS($env, $key, $secret);
    }

    public function testAvailable(): void
    {
        $result = $this->client->available($this->domain);

        $this->assertTrue($result);
    }

    public function testPurchase(): string
    {
        $domain = $this->domain;

        $result = $this->client->purchase($domain, [
            'contacts' => self::purchaseContact(),
        ]);

        $this->assertTrue($result['successful']);

        return $domain;
    }

    /** @depends testPurchase */
    public function testDomainInfo(string $domain): void
    {
        $result = $this->client->domain($domain);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('registry_createdate', $result);
    }

    public function testCancelPurchase(): void
    {
        $result = $this->client->cancelPurchase();

        $this->assertTrue($result);
    }

    public function testSuggest(): void
    {
        $result = $this->client->suggest(
            [
                'monkeys',
                'kittens',
            ],
            [
                'com',
                'net',
                'org',
            ]
        );

        $this->assertIsArray($result);
    }

    /** @depends testPurchase */
    public function testUpdateNameservers(string $domain): void
    {
        $result = $this->client->updateNameservers($domain, [
            'ns1.hover.com',
            'ns2.hover.com',
        ]);

        $this->assertTrue($result['successful']);
    }

    /** @depends testPurchase */
    public function testUpdateDomain(string $domain): void
    {
        $result = $this->client->updateDomain($domain, [
            'affect_domains' => 0,
            'data' => 'contact_info',
            'contact_set' => self::purchaseContact('2'),
        ]);

        $this->assertTrue($result);
    }

    /** @depends testPurchase */
    public function testRenewDomain(string $domain): void
    {
        $result = $this->client->renew($domain, 1);

        $this->assertArrayHasKey('order_id', $result);
    }

    /** @depends testPurchase */
    public function testTransfer(string $domain): void
    {
        $result = $this->client->transfer($domain, [
            'contacts' => self::purchaseContact(),
        ]);

        // This will always fail mainly because it's a test env,
        // but also because:
        // - we use random domains to test
        // - transfer lock is default
        // - unable to unlock transfer because domains (in tests) are new.
        // ** Even when testing against my own live domains, it failed.
        // So we test for a proper formatted response,
        // with "successful" being "false".

        $this->assertFalse($result['successful']);
    }

    private static function purchaseContact(string $suffix = ''): array
    {
        $contact = [
            'firstname' => 'Test'.$suffix,
            'lastname' => 'Tester'.$suffix,
            'phone' => '+1.8031234567'.$suffix,
            'email' => 'testing@test.com'.$suffix,
            'address1' => '123 Main St'.$suffix,
            'address2' => 'Suite 100'.$suffix,
            'address3' => ''.$suffix,
            'city' => 'San Francisco'.$suffix,
            'state' => 'CA',
            'country' => 'US',
            'postalcode' => '94105',
            'org' => 'Test Inc'.$suffix,
            'owner' => 'Test Tester'.$suffix,
        ];

        return [
            'owner' => $contact,
            'admin' => $contact,
            'tech' => $contact,
            'billing' => $contact,
        ];
    }

 private function generateRandomString(int $length = 10): string
 {
     $characters = 'abcdefghijklmnopqrstuvwxyz';
     $charactersLength = strlen($characters);
     $randomString = '';

     for ($i = 0; $i < $length; $i++) {
         $randomString .= $characters[random_int(0, $charactersLength - 1)];
     }

     return $randomString;
 }
}
