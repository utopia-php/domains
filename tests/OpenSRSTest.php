<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Contact;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\PriceNotFound;
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

    public function testPurchase(): void
    {
        $domain = self::generateRandomString() . '.net';
        $result = $this->client->purchase($domain, self::purchaseContact());
        $this->assertTrue($result['successful']);

        $domain = 'google.com';
        $this->expectException(DomainTaken::class);
        $this->expectExceptionMessage("Failed to purchase domain: Domain taken");
        $result = $this->client->purchase($domain, self::purchaseContact());
    }

    public function testDomainInfo(): void
    {
        $result = $this->client->getDomain($this->domain);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('registry_createdate', $result);
    }

    public function testCancelPutestPurchaserchase(): void
    {
        $result = $this->client->cancelPurchase();

        $this->assertTrue($result);
    }

    public function testSuggest(): void
    {
        // Test 1: Suggestion domains only with prices
        $result = $this->client->suggest(
            [
                'monkeys',
                'kittens',
            ],
            [
                'com',
                'net',
                'org',
            ],
            5,
            'suggestion'
        );

        $this->assertIsArray($result);
        foreach ($result as $domain => $data) {
            $this->assertEquals('suggestion', $data['type']);
            if ($data['available'] && $data['price'] !== null) {
                $this->assertIsFloat($data['price']);
                $this->assertGreaterThan(0, $data['price']);
            }
        }

        // Test 2: Mixed results (default behavior - both premium and suggestions)
        $result = $this->client->suggest(
            'monkeys',
            [
                'com',
                'net',
                'org',
            ],
            5
        );

        $this->assertIsArray($result);
        $this->assertCount(5, $result);

        foreach ($result as $domain => $data) {
            if ($data['type'] === 'premium') {
                $this->assertIsFloat($data['price']);
                $this->assertGreaterThan(0, $data['price']);
            } elseif ($data['available'] && $data['price'] !== null) {
                $this->assertIsFloat($data['price']);
            }
        }

        // Test 3: Premium domains only with price filters
        $result = $this->client->suggest(
            'computer',
            [
                'com',
                'net',
            ],
            5,
            'premium',
            10000,
            100
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));

        foreach ($result as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            if ($data['price'] !== null) {
                $this->assertIsFloat($data['price']);
                $this->assertGreaterThanOrEqual(100, $data['price']);
                $this->assertLessThanOrEqual(10000, $data['price']);
            }
        }

        // Test 4: Premium domains without price filters
        $result = $this->client->suggest(
            'business',
            [
                'com',
            ],
            5,
            'premium'
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));

        foreach ($result as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            if ($data['price'] !== null) {
                $this->assertIsFloat($data['price']);
            }
        }

        // Test 5: Single TLD search
        $result = $this->client->suggest(
            'example',
            ['org'],
            3,
            'suggestion'
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));

        foreach ($result as $domain => $data) {
            $this->assertEquals('suggestion', $data['type']);
            $this->assertStringEndsWith('.org', $domain);
        }
    }

    public function testGetPrice(): void
    {
        $result = $this->client->getPrice($this->domain, 1, 'new');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('base_price', $result);
        $this->assertArrayHasKey('markup', $result);
        $this->assertArrayHasKey('is_registry_premium', $result);
        $this->assertArrayHasKey('registry_premium_group', $result);
        $this->assertIsFloat($result['price']);
        $this->assertIsFloat($result['base_price']);
        $this->assertIsFloat($result['markup']);
        $this->assertIsBool($result['is_registry_premium']);

        // Verify no markup is applied by default (markup = 0, cap = 0)
        $basePrice = $result['base_price'];
        $markup = $result['markup'];
        $price = $result['price'];
        $this->assertEquals(0.0, $markup);
        $this->assertEquals($basePrice, $price);

        $this->expectException(PriceNotFound::class);
        $this->expectExceptionMessage("Failed to get price for domain: get_price_domain API is not supported for 'invalid domain'");
        $this->client->getPrice("invalid domain", 1, 'new');
    }

    public function testGetPriceWithMarkup(): void
    {
        // Create client with markup = 0.15 (15%) and cap = 5.0
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');

        $clientWithMarkup = new OpenSRS(
            $key,
            $username,
            self::generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
            false,
            0.15, // 15% markup
            5.0   // $5 cap
        );

        $result = $clientWithMarkup->getPrice($this->domain, 1, 'new');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('base_price', $result);
        $this->assertArrayHasKey('markup', $result);
        $this->assertArrayHasKey('price', $result);

        $basePrice = $result['base_price'];
        $markup = $result['markup'];
        $price = $result['price'];

        // Markup should be capped at 5.0 or 15% of base price, whichever is smaller
        $expectedMarkup = min($basePrice * 0.15, 5.0);
        $this->assertEquals($expectedMarkup, $markup);
        $expectedPrice = round($basePrice + $expectedMarkup, 2);
        $this->assertEquals($expectedPrice, $price);
        $this->assertGreaterThan(0, $markup);
    }

    public function testGetPriceMarkupCalculation(): void
    {
        // Test with markup but no cap (cap = 0 means no cap limit)
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');

        $clientWithMarkup = new OpenSRS(
            $key,
            $username,
            self::generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
            false,
            0.20, // 20% markup
            0.0   // No cap
        );

        $result = $clientWithMarkup->getPrice($this->domain, 1, 'new');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('base_price', $result);
        $this->assertArrayHasKey('markup', $result);
        $this->assertArrayHasKey('price', $result);

        $basePrice = $result['base_price'];
        $markup = $result['markup'];

        // With no cap, markup should equal exactly basePrice * 0.20
        $this->assertEqualsWithDelta($basePrice * 0.20, $markup, 0.01);
        $expectedPrice = round($basePrice + $markup, 2);
        $this->assertEquals($expectedPrice, $result['price']);
    }

    public function testGetPriceMarkupCap(): void
    {
        // Test that the cap is enforced when markup would exceed it
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');

        $clientWithMarkup = new OpenSRS(
            $key,
            $username,
            self::generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
            false,
            0.50, // 50% markup (high percentage to test cap)
            2.0   // $2 cap (low cap to ensure it's hit)
        );

        $result = $clientWithMarkup->getPrice($this->domain, 1, 'new');

        $basePrice = $result['base_price'];
        $markup = $result['markup'];

        // If 50% of base price exceeds $2, markup should be capped at $2
        if ($basePrice * 0.50 > 2.0) {
            $this->assertEquals(2.0, $markup, "Markup should be capped at 2.0");
        } else {
            $this->assertEqualsWithDelta($basePrice * 0.50, $markup, 0.01);
        }

        // Markup should never exceed the cap
        $this->assertLessThanOrEqual(2.0, $markup);
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
