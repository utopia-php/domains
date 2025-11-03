<?php

namespace Utopia\Tests\Registrar;

use PHPUnit\Framework\TestCase;
use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\Adapter\Mock;
use Utopia\Domains\Registrar\TransferStatusEnum;

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
        $this->assertTrue($this->adapter->available('example.com'));
        $this->assertFalse($this->adapter->available('google.com'));
    }

    public function testPurchase(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->createContact();

        $result = $this->adapter->purchase($domain, $contact, 1);

        $this->assertTrue($result->successful);
        $this->assertEquals($domain, $result->domain);
        $this->assertNotEmpty($result->id);
        $this->assertNotEmpty($result->domainId);

        $this->expectException(DomainTakenException::class);
        $this->expectExceptionMessage('Domain google.com is not available for registration');
        $this->adapter->purchase('google.com', $this->createContact(), 1);
    }

    public function testPurchaseWithInvalidContact(): void
    {
        $this->expectException(InvalidContactException::class);
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

        $this->adapter->purchase('test.com', $invalidContact, 1);
    }

    public function testDomainInfo(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact(), 1);

        $result = $this->adapter->getDomain($domain);

        $this->assertEquals($domain, $result->domain);
        $this->assertInstanceOf(\DateTime::class, $result->createdAt);
        $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
        $this->assertIsBool($result->autoRenew);
        $this->assertIsArray($result->nameservers);
    }

    public function testTlds(): void
    {
        $tlds = $this->adapter->tlds();

        $this->assertIsArray($tlds);
        $this->assertContains('com', $tlds);
        $this->assertContains('net', $tlds);
    }

    public function testSuggest(): void
    {
        $result = $this->adapter->suggest('test', ['com', 'net'], 5);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));

        foreach ($result as $domain => $data) {
            $this->assertArrayHasKey('available', $data);
            $this->assertArrayHasKey('price', $data);
            $this->assertArrayHasKey('type', $data);
        }
    }

    public function testGetPrice(): void
    {
        $result = $this->adapter->getPrice('example.com', 1, Mock::REG_TYPE_NEW);

        $this->assertNotNull($result);
        $this->assertIsFloat($result);

        $this->expectException(PriceNotFoundException::class);
        $this->expectExceptionMessage('Invalid domain format');
        $this->adapter->getPrice('invalid');
    }

    public function testGetPriceWithCache(): void
    {
        $result1 = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 3600);
        $this->assertNotNull($result1);
        $this->assertIsFloat($result1);

        $result2 = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 3600);
        $this->assertEquals($result1, $result2);
    }

    public function testGetPriceWithCustomTtl(): void
    {
        $result = $this->adapterWithCache->getPrice('example.com', 1, Mock::REG_TYPE_NEW, 7200);
        $this->assertIsFloat($result);
    }

    public function testUpdateDomain(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact(), 1);

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
            [
                'data' => 'contact_info',
            ],
            [$updatedContact]
        );

        $this->assertTrue($result);
    }

    public function testRenewDomain(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact(), 1);

        $result = $this->adapter->renew($domain, 1);

        $this->assertTrue($result->successful);
        $this->assertNotEmpty($result->orderId);
        $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
    }

    public function testPurchaseWithNameservers(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->createContact();
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->purchase($domain, $contact, 1, $nameservers);

        $this->assertTrue($result->successful);
        $this->assertEquals($nameservers, $result->nameservers);
    }

    public function testTransfer(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->createContact();
        $authCode = 'test-auth-code-12345';

        $result = $this->adapter->transfer($domain, $authCode, $contact);

        $this->assertTrue($result->successful);
        $this->assertEquals($domain, $result->domain);
    }

    public function testTransferWithNameservers(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->createContact();
        $authCode = 'test-auth-code-12345';
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->transfer($domain, $authCode, $contact, 1, $nameservers);

        $this->assertTrue($result->successful);
        $this->assertEquals($nameservers, $result->nameservers);
    }

    public function testTransferAlreadyExists(): void
    {
        $domain = 'alreadyexists.com';
        $contact = $this->createContact();
        $authCode = 'test-auth-code-12345';

        $this->adapter->purchase($domain, $contact, 1);

        $this->expectException(DomainTakenException::class);
        $this->expectExceptionMessage('Domain ' . $domain . ' is already in this account');
        $this->adapter->transfer($domain, $authCode, $contact);
    }

    public function testTransferWithInvalidContact(): void
    {
        $this->expectException(InvalidContactException::class);
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

    public function testUpdateDomainWithInvalidContact(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact(), 1);

        $this->expectException(InvalidContactException::class);
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
            ['data' => 'contact_info'],
            [$invalidContact]
        );
    }

    public function testGetAuthCode(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->createContact(), 1);

        $authCode = $this->adapter->getAuthCode($domain);

        $this->assertIsString($authCode);
        $this->assertNotEmpty($authCode);
    }

    public function testCheckTransferStatus(): void
    {
        $domain = 'transferable.com';
        $result = $this->adapter->checkTransferStatus($domain, true, true);

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
        $domain = 'example.com';
        $result = $this->adapter->checkTransferStatus($domain, false, true);

        $this->assertInstanceOf(TransferStatusEnum::class, $result->status);
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
