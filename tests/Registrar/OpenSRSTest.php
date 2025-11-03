<?php

namespace Utopia\Tests\Registrar;

use PHPUnit\Framework\TestCase;
use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\DomainNotTransferableException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\AuthException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\Adapter\OpenSRS;
use Utopia\Domains\Registrar;
use Utopia\Domains\Registrar\TransferStatusEnum;

class OpenSRSTest extends TestCase
{
    private OpenSRS $client;
    private OpenSRS $clientWithCache;
    private string $domain;

    protected function setUp(): void
    {
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

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
        $this->clientWithCache = new OpenSRS(
            $key,
            $username,
            self::generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
            'https://horizon.opensrs.net:55443',
            $cache
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('opensrs', $this->client->getName());
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
        $result = $this->client->purchase($domain, self::purchaseContact(), 1);
        $this->assertTrue($result->successful);

        $domain = 'google.com';
        $this->expectException(DomainTakenException::class);
        $this->expectExceptionMessage("Failed to purchase domain: Domain taken");
        $this->client->purchase($domain, self::purchaseContact(), 1);
    }

    public function testPurchaseWithInvalidContact(): void
    {
        $domain = self::generateRandomString() . '.net';
        $this->expectException(InvalidContactException::class);
        $this->expectExceptionMessage("Failed to purchase domain: Invalid data");
        $this->client->purchase($domain, [
            new Contact(
                'John',
                'Doe',
                '+1.8031234567',
                'testing@test.com',
                '123 Main St',
                'Suite 100',
                '',
                'San Francisco',
                'CA',
                'India',
                '94105',
                'Test Inc',
            )
        ]);
    }

    public function testPurchaseWithInvalidPassword(): void
    {
        $client = new OpenSRS(
            getenv('OPENSRS_KEY'),
            getenv('OPENSRS_USERNAME'),
            'password',
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
        );

        $domain = self::generateRandomString() . '.net';
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage("Failed to purchase domain: Invalid password");
        $client->purchase($domain, self::purchaseContact(), 1);
    }

    public function testDomainInfo(): void
    {
        $result = $this->client->getDomain($this->domain);

        $this->assertEquals($this->domain, $result->domain);
        $this->assertInstanceOf(\DateTime::class, $result->createdAt);
        $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
        $this->assertIsBool($result->autoRenew);
        $this->assertIsArray($result->nameservers);
    }

    public function testCancelPurchase(): void
    {
        $result = $this->client->cancelPurchase();

        $this->assertTrue($result);
    }

    public function testTlds(): void
    {
        $tlds = $this->client->tlds();
        $this->assertEmpty($tlds);
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
        $result = $this->client->getPrice($this->domain, 1, Registrar::REG_TYPE_NEW);
        $this->assertNotNull($result);
        $this->assertIsFloat($result);

        $this->expectException(PriceNotFoundException::class);
        $this->expectExceptionMessage("Failed to get price for domain: get_price_domain API is not supported for 'invalid domain'");
        $this->client->getPrice("invalid domain", 1, Registrar::REG_TYPE_NEW);
    }

    public function testGetPriceWithCache(): void
    {
        $result1 = $this->clientWithCache->getPrice($this->domain, 1, Registrar::REG_TYPE_NEW, 3600);
        $this->assertNotNull($result1);
        $this->assertIsFloat($result1);

        $result2 = $this->clientWithCache->getPrice($this->domain, 1, Registrar::REG_TYPE_NEW, 3600);
        $this->assertEquals($result1, $result2);
    }

    public function testGetPriceWithCustomTtl(): void
    {
        $result = $this->clientWithCache->getPrice($this->domain, 1, Registrar::REG_TYPE_NEW, 7200);
        $this->assertIsFloat($result);
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
            [
                'data' => 'contact_info',
            ],
            self::purchaseContact('2')
        );

        $this->assertTrue($result);
    }

    public function testRenewDomain(): void
    {
        $result = $this->client->renew($this->domain, 1);

        // receive false because renew is not possible
        $this->assertFalse($result->successful);
    }

    public function testTransfer(): void
    {
        $domain = self::generateRandomString() . '.net';

        // This will always fail mainly because it's a test env,
        // but also because:
        // - we use random domains to test
        // - transfer lock is default
        // - unable to unlock transfer because domains (in tests) are new.
        // ** Even when testing against my own live domains, it failed.
        // So we test for a proper formatted response,
        // with "successful" being "false".
        try {
            $result = $this->client->transfer($domain, 'test-auth-code', self::purchaseContact());
            $this->assertTrue($result->successful);
            $this->assertNotEmpty($result->code);
        } catch (DomainNotTransferableException $e) {
            $this->assertEquals(OpenSRS::RESPONSE_CODE_DOMAIN_NOT_TRANSFERABLE, $e->getCode());
            $this->assertEquals('Domain is not transferable: Domain not registered', $e->getMessage());
        }
    }

    public function testTransferAlreadyExists(): void
    {
        try {
            $result = $this->client->transfer($this->domain, 'test-auth-code', self::purchaseContact());
            $this->assertTrue($result->successful);
            $this->assertNotEmpty($result->code);
        } catch (DomainNotTransferableException $e) {
            $this->assertEquals(OpenSRS::RESPONSE_CODE_DOMAIN_NOT_TRANSFERABLE, $e->getCode());
            $this->assertStringContainsString('Domain is not transferable: Domain already exists', $e->getMessage());
        }
    }

    public function testGetAuthCode(): void
    {
        $authCode = $this->client->getAuthCode($this->domain);

        $this->assertIsString($authCode);
        $this->assertNotEmpty($authCode);
    }

    public function testCheckTransferStatus(): void
    {
        $result = $this->client->checkTransferStatus($this->domain, true, true);

        $this->assertInstanceOf(TransferStatusEnum::class, $result->status);

        if ($result->status !== TransferStatusEnum::Transferrable) {
            $this->assertNotNull($result->reason);
            $this->assertIsString($result->reason);
        }

        $this->assertContains($result->status, [
            TransferStatusEnum::Transferrable,
            TransferStatusEnum::NotTransferrable,
            TransferStatusEnum::PendingOwner,
            TransferStatusEnum::PendingAdmin,
            TransferStatusEnum::PendingRegistry,
            TransferStatusEnum::Completed,
            TransferStatusEnum::Cancelled,
            TransferStatusEnum::ServiceUnavailable,
        ]);
    }

    public function testCheckTransferStatusWithRequestAddress(): void
    {
        $result = $this->client->checkTransferStatus($this->domain, false, true);

        $this->assertInstanceOf(TransferStatusEnum::class, $result->status);
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
