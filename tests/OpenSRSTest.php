<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Contact;
use Utopia\Domains\Registrar\OpenSRS;

class OpenSRSTest extends TestCase
{
    private OpenSRS $client;

    private string $domain;

    protected function setUp(): void
    {
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');

        $this->assertNotEmpty($key);
        $this->assertNotEmpty($username);

        $this->domain = 'kffsfudlvc.net';
        $this->client = new OpenSRS(
            $key,
            $username,
            self::generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ]
        );
    }

    public function testAvailable(): void
    {
        $domain = self::generateRandomString() . '.net';
        $result = $this->client->available($domain);

        $this->assertTrue($result);
    }

    public function testPurchase(): string
    {
        $domain = self::generateRandomString() . '.net';

        $result = $this->client->purchase($domain, self::purchaseContact());

        $this->assertTrue($result['successful']);

        return $domain;
    }

    public function testDomainInfo(): void
    {
        $result = $this->client->getDomain($this->domain);

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
        // Test 1: Basic suggestion without filters
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
        foreach ($result as $domain => $data) {
            if ($data['type'] === 'premium') {
                $this->assertGreaterThan(0, $data['price']);
            } else {
                $this->assertEquals(null, $data['price']);
            }
        }

        // Test 2: Suggestion with limit
        $result = $this->client->suggest(
            'monkeys',
            [
                'com',
                'net',
                'org',
            ],
            10
        );

        $this->assertIsArray($result);
        $this->assertCount(10, $result);

        // Test 3: Premium suggestions with price filters
        $result = $this->client->suggest(
            'computer',
            [
                'com',
                'net',
            ],
            10,
            10000,
            100
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(10, count($result));

        foreach ($result as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            if ($data['price'] !== null) {
                $this->assertGreaterThanOrEqual(100, $data['price']);
                $this->assertLessThanOrEqual(10000, $data['price']);
            }
        }
    }

    public function testUpdateNameservers(): void
    {
        $result = $this->client->updateNameservers($this->domain, [
            'ns1.hover.com',
            'ns2.hover.com',
        ]);

        $this->assertTrue($result['successful']);
    }

    public function testUpdateDomain(): void
    {
        $result = $this->client->updateDomain(
            $this->domain,
            self::purchaseContact('2'),
            [
                'affect_domains' => 0,
                'data' => 'contact_info',
                'contact_set' => self::purchaseContact('2'),
            ]
        );

        $this->assertTrue($result);
    }

    public function testRenewDomain(): void
    {
        $result = $this->client->renew($this->domain, 1);

        if (array_key_exists('forced_pending', $result)) {
            $this->markTestSkipped("Account doesn't have sufficient funds to renew.");
        }

        $this->assertArrayHasKey('order_id', $result);
    }

    public function testTransfer(): void
    {
        // $result = $this->client->transfer($domain, self::purchaseContact());

        // This will always fail mainly because it's a test env,
        // but also because:
        // - we use random domains to test
        // - transfer lock is default
        // - unable to unlock transfer because domains (in tests) are new.
        // ** Even when testing against my own live domains, it failed.
        // So we test for a proper formatted response,
        // with "successful" being "false".

        $this->markTestSkipped("Transfer test skipped because it always fails.");
    }

    private static function purchaseContact(string $suffix = ''): array
    {
        $contact = new Contact(
            'Test' . $suffix,
            'Tester' . $suffix,
            '+1.8031234567' . $suffix,
            'testing@test.com' . $suffix,
            '123 Main St' . $suffix,
            'Suite 100' . $suffix,
            '' . $suffix,
            'San Francisco' . $suffix,
            'CA',
            'US',
            '94105',
            'Test Inc' . $suffix,
        );

        return [
            'owner' => $contact,
            'admin' => $contact,
            'tech' => $contact,
            'billing' => $contact,
        ];
    }

    private static function generateRandomString(int $length = 10): string
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
