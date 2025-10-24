<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Contact;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\InvalidContact;
use Utopia\Domains\Registrar\Exception\PriceNotFound;
use Utopia\Domains\Registrar\Mock;
use Utopia\Domains\Exception as DomainsException;

class MockTest extends TestCase
{
    private Mock $adapter;
    private Mock $adapterWithCache;

    protected function setUp(): void
    {
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

        $this->adapter = new Mock();
        $this->adapterWithCache = new Mock([], [], 12.99, $cache);
    }

    protected function tearDown(): void
    {
        $this->adapter->reset();
    }

    public function testGetName(): void
    {
        $this->assertEquals('mock', $this->adapter->getName());
    }

    public function testAvailable(): void
    {
        // Test available domain
        $this->assertTrue($this->adapter->available('example.com'));

        // Test taken domain
        $this->assertFalse($this->adapter->available('google.com'));

        // Test domain becomes unavailable after purchase
        $this->adapter->purchase('newdomain.com', $this->createContact());
        $this->assertFalse($this->adapter->available('newdomain.com'));
    }

    public function testPurchase(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->createContact();

        $result = $this->adapter->purchase($domain, $contact);

        $this->assertTrue($result['successful']);
        $this->assertEquals($domain, $result['domain']);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('domainId', $result);

        // Verify domain is in purchased list
        $this->assertContains($domain, $this->adapter->getPurchasedDomains());
    }

    public function testPurchaseTakenDomain(): void
    {
        $this->expectException(DomainTaken::class);
        $this->expectExceptionMessage('Domain google.com is not available for registration');

        $this->adapter->purchase('google.com', $this->createContact());
    }

    public function testSuggest(): void
    {
        // Test basic suggestions
        $results = $this->adapter->suggest('test', ['com', 'net'], 5);

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(5, count($results));

        foreach ($results as $domain => $data) {
            $this->assertArrayHasKey('available', $data);
            $this->assertArrayHasKey('price', $data);
            $this->assertArrayHasKey('type', $data);
        }
    }

    public function testSuggestSuggestionOnly(): void
    {
        $results = $this->adapter->suggest('example', ['com', 'net', 'org'], 3, 'suggestion');

        $this->assertCount(3, $results);

        foreach ($results as $domain => $data) {
            $this->assertEquals('suggestion', $data['type']);
            $this->assertNull($data['price']);
        }
    }

    public function testSuggestPremiumOnly(): void
    {
        $results = $this->adapter->suggest('test', ['com'], 10, 'premium');

        foreach ($results as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            $this->assertIsFloat($data['price']);
            $this->assertGreaterThan(0, $data['price']);
        }
    }

    public function testSuggestWithPriceFilter(): void
    {
        $results = $this->adapter->suggest('test', ['com'], 10, 'premium', 6000, 1000);

        foreach ($results as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            if ($data['price'] !== null) {
                $this->assertGreaterThanOrEqual(1000, $data['price']);
                $this->assertLessThanOrEqual(6000, $data['price']);
            }
        }
    }

    public function testTlds(): void
    {
        $tlds = $this->adapter->tlds();

        $this->assertIsArray($tlds);
        $this->assertContains('com', $tlds);
        $this->assertContains('net', $tlds);
        $this->assertContains('org', $tlds);
    }

    public function testCustomTlds(): void
    {
        $customAdapter = new Mock([], ['xyz', 'app', 'dev']);
        $tlds = $customAdapter->tlds();

        $this->assertCount(3, $tlds);
        $this->assertContains('xyz', $tlds);
        $this->assertContains('app', $tlds);
        $this->assertContains('dev', $tlds);
    }

    public function testGetPrice(): void
    {
        $result = $this->adapter->getPrice('example.com', 1, Mock::REG_TYPE_NEW);

        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('is_registry_premium', $result);
        $this->assertArrayHasKey('registry_premium_group', $result);
        $this->assertIsFloat($result['price']);
        $this->assertFalse($result['is_registry_premium']);
    }

    public function testGetPricePremiumDomain(): void
    {
        $result = $this->adapter->getPrice('premium.com');

        $this->assertTrue($result['is_registry_premium']);
        $this->assertEquals('premium', $result['registry_premium_group']);
        $this->assertEquals(5000.00, $result['price']);
    }

    public function testGetPriceMultipleYears(): void
    {
        $result = $this->adapter->getPrice('example.com', 3);

        $this->assertGreaterThan(12.99, $result['price']);
        $this->assertEquals(12.99 * 3, $result['price']);
    }

    public function testGetPriceInvalidDomain(): void
    {
        $this->expectException(PriceNotFound::class);
        $this->expectExceptionMessage('Invalid domain format');

        $this->adapter->getPrice('invalid');
    }

    public function testGetPriceUnsupportedTld(): void
    {
        $this->expectException(PriceNotFound::class);
        $this->expectExceptionMessage('TLD .xyz is not supported');

        $this->adapter->getPrice('example.xyz');
    }

    public function testGetDomain(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact());

        $result = $this->adapter->getDomain($domain);

        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('registry_createdate', $result);
        $this->assertArrayHasKey('registry_expiredate', $result);
        $this->assertEquals($domain, $result['domain']);
    }

    public function testGetDomainNotFound(): void
    {
        $this->expectException(DomainsException::class);
        $this->expectExceptionMessage('Domain notfound.com not found in mock registry');

        $this->adapter->getDomain('notfound.com');
    }

    public function testRenew(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact());

        $result = $this->adapter->renew($domain, 2);

        $this->assertTrue($result['successful']);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('new_expiration', $result);
        $this->assertEquals($domain, $result['domain']);
    }

    public function testRenewNotFound(): void
    {
        $this->expectException(DomainsException::class);
        $this->expectExceptionMessage('Domain notfound.com not found in mock registry');

        $this->adapter->renew('notfound.com', 1);
    }

    public function testTransfer(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->createContact();
        $authCode = 'test-auth-code-12345';

        $result = $this->adapter->transfer($domain, $authCode, $contact);

        $this->assertTrue($result['successful']);
        $this->assertEquals($domain, $result['domain']);
        $this->assertContains($domain, $this->adapter->getTransferredDomains());
        $this->assertContains($domain, $this->adapter->getPurchasedDomains());
    }

    public function testTransferAlreadyOwned(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact());

        $this->expectException(DomainTaken::class);
        $this->expectExceptionMessage('Domain testdomain.com is already in this account');

        $this->adapter->transfer($domain, 'test-auth-code', $this->createContact());
    }

    public function testReset(): void
    {
        $this->adapter->purchase('test1.com', $this->createContact());
        $this->adapter->purchase('test2.com', $this->createContact());
        $this->adapter->transfer('test3.com', 'auth-code', $this->createContact());

        $this->assertCount(3, $this->adapter->getPurchasedDomains());
        $this->assertCount(1, $this->adapter->getTransferredDomains());

        $this->adapter->reset();

        $this->assertCount(0, $this->adapter->getPurchasedDomains());
        $this->assertCount(0, $this->adapter->getTransferredDomains());
    }

    public function testAddTakenDomain(): void
    {
        $domain = 'newtaken.com';
        $this->assertTrue($this->adapter->available($domain));

        $this->adapter->addTakenDomain($domain);
        $this->assertFalse($this->adapter->available($domain));
    }

    public function testAddPremiumDomain(): void
    {
        $domain = 'newpremium.com';
        $price = 15000.00;

        $this->adapter->addPremiumDomain($domain, $price);

        $result = $this->adapter->getPrice($domain);
        $this->assertTrue($result['is_registry_premium']);
        $this->assertEquals($price, $result['price']);
    }

    public function testCustomDefaultPrice(): void
    {
        $customAdapter = new Mock([], [], 25.00);
        $result = $customAdapter->getPrice('example.com');

        $this->assertEquals(25.00, $result['price']);
    }

    public function testGetPriceWithCache(): void
    {
        $result1 = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 3600);
        $this->assertArrayHasKey('price', $result1);
        $this->assertEquals(12.99, $result1['price']);

        $result2 = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 3600);
        $this->assertEquals($result1, $result2);
    }

    public function testGetPriceWithTtl(): void
    {
        $result = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 7200);
        $this->assertArrayHasKey('price', $result);
        $this->assertEquals(12.99, $result['price']);
    }

    public function testPurchaseWithNameservers(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->createContact();
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->purchase($domain, $contact, $nameservers);

        $this->assertTrue($result['successful']);
        $this->assertEquals($nameservers, $result['nameservers']);
    }

    public function testTransferWithNameservers(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->createContact();
        $authCode = 'test-auth-code-12345';
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->transfer($domain, $authCode, $contact, $nameservers);

        $this->assertTrue($result['successful']);
        $this->assertEquals($nameservers, $result['nameservers']);
    }

    public function testPurchaseWithInvalidContact(): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessage('missing required field');

        $invalidContact = new Contact(
            '', // Empty firstname
            'Doe',
            '+1.5551234567',
            'john.doe@example.com',
            '123 Main St',
            'Suite 100',
            '',
            'San Francisco',
            'CA',
            'US',
            '94105',
            'Test Inc'
        );

        $this->adapter->purchase('test.com', $invalidContact);
    }

    public function testPurchaseWithInvalidEmail(): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessage('invalid email format');

        $invalidContact = new Contact(
            'John',
            'Doe',
            '+1.5551234567',
            'invalid-email', // Invalid email
            '123 Main St',
            'Suite 100',
            '',
            'San Francisco',
            'CA',
            'US',
            '94105',
            'Test Inc'
        );

        $this->adapter->purchase('test.com', $invalidContact);
    }

    public function testTransferWithInvalidContact(): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessage('missing required field');

        $invalidContact = new Contact(
            'John',
            'Doe',
            '+1.5551234567',
            'john.doe@example.com',
            '123 Main St',
            'Suite 100',
            '',
            '', // Empty city
            'CA',
            'US',
            '94105',
            'Test Inc'
        );

        $this->adapter->transfer('transfer.com', 'auth-code', $invalidContact);
    }

    public function testUpdateDomain(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact());

        $updatedContact = new Contact(
            'Jane',
            'Smith',
            '+1.5559876543',
            'jane.smith@example.com',
            '456 Oak Ave',
            'Apt 200',
            '',
            'Los Angeles',
            'CA',
            'US',
            '90001',
            'Smith Corp'
        );

        $result = $this->adapter->updateDomain(
            $domain,
            [$updatedContact],
            [
                'data' => 'contact_info',
            ]
        );

        $this->assertTrue($result);
    }

    public function testUpdateDomainNotFound(): void
    {
        $this->expectException(DomainsException::class);
        $this->expectExceptionMessage('Domain notfound.com not found in mock registry');

        $this->adapter->updateDomain(
            'notfound.com',
            [$this->createContact()],
            ['data' => 'contact_info']
        );
    }

    public function testUpdateDomainWithInvalidContact(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact());

        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessage('missing required field');

        $invalidContact = new Contact(
            '',  // Empty firstname
            'Doe',
            '+1.5551234567',
            'john.doe@example.com',
            '123 Main St',
            'Suite 100',
            '',
            'San Francisco',
            'CA',
            'US',
            '94105',
            'Test Inc'
        );

        $this->adapter->updateDomain(
            $domain,
            [$invalidContact],
            ['data' => 'contact_info']
        );
    }

    private function createContact(): Contact
    {
        return new Contact(
            'John',
            'Doe',
            '+1.5551234567',
            'john.doe@example.com',
            '123 Main St',
            'Suite 100',
            '',
            'San Francisco',
            'CA',
            'US',
            '94105',
            'Test Inc'
        );
    }
}
