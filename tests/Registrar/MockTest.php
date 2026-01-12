<?php

namespace Utopia\Tests\Registrar;

use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Adapter\Mock;

class MockTest extends BaseRegistrarTest
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

    protected function getAdapter(): Registrar
    {
        return $this->adapter;
    }

    protected function getAdapterWithCache(): Registrar
    {
        return $this->adapterWithCache;
    }

    protected function getTestDomain(): string
    {
        // For mock, we purchase a domain on the fly
        $testDomain = $this->generateRandomString() . '.com';
        $this->adapter->purchase($testDomain, $this->getPurchaseContact(), 1);
        return $testDomain;
    }

    protected function getExpectedAdapterName(): string
    {
        return 'mock';
    }

    protected function getDefaultNameservers(): array
    {
        return [
            'ns1.example.com',
            'ns2.example.com',
        ];
    }

    // Mock-specific tests

    public function testPurchaseWithNameservers(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->getPurchaseContact();
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->purchase($domain, $contact, 1, $nameservers);

        $this->assertTrue($result->successful);
        $this->assertEquals($nameservers, $result->nameservers);
    }

    public function testTransferWithNameservers(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->getPurchaseContact();
        $authCode = 'test-auth-code-12345';
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->adapter->transfer($domain, $authCode, $contact, 1, $nameservers);

        $this->assertTrue($result->successful);
        $this->assertEquals($nameservers, $result->nameservers);
    }

    public function testTransferAlreadyExists(): void
    {
        $domain = 'alreadyexists.com';
        $contact = $this->getPurchaseContact();
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

        $this->adapter->transfer('transfer.com', 'auth-code', [$invalidContact]);
    }

    public function testUpdateDomainWithInvalidContact(): void
    {
        $domain = 'testdomain.com';
        $this->adapter->purchase($domain, $this->getPurchaseContact(), 1);

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

    public function testCheckTransferStatusWithRequestAddress(): void
    {
        $domain = 'example.com';
        $result = $this->adapter->checkTransferStatus($domain, false, true);

        $this->assertInstanceOf(\Utopia\Domains\Registrar\TransferStatusEnum::class, $result->status);
    }
}
