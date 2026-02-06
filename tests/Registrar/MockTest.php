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
use Utopia\Domains\Registrar\UpdateDetails;

class MockTest extends Base
{
    private Registrar $registrar;
    private Registrar $registrarWithCache;
    private Mock $adapter;

    protected function setUp(): void
    {
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

        $this->adapter = new Mock();
        $this->registrar = new Registrar($this->adapter);
        $this->registrarWithCache = new Registrar($this->adapter, [], $cache);
    }

    protected function tearDown(): void
    {
        $this->adapter->reset();
    }

    protected function getRegistrar(): Registrar
    {
        return $this->registrar;
    }

    protected function getRegistrarWithCache(): Registrar
    {
        return $this->registrarWithCache;
    }

    protected function getTestDomain(): string
    {
        // For mock, we purchase a domain on the fly
        $testDomain = $this->generateRandomString() . '.com';
        $this->registrar->purchase($testDomain, $this->getPurchaseContact(), 1);
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

    protected function getUpdateDetails(?bool $autoRenew = null): UpdateDetails
    {
        return new UpdateDetails($autoRenew);
    }

    // Mock-specific tests

    public function testPurchaseWithNameservers(): void
    {
        $domain = 'testdomain.com';
        $contact = $this->getPurchaseContact();
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->registrar->purchase($domain, $contact, 1, $nameservers);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testTransferWithNameservers(): void
    {
        $domain = 'transferdomain.com';
        $contact = $this->getPurchaseContact();
        $authCode = 'test-auth-code-12345';
        $nameservers = ['ns1.example.com', 'ns2.example.com'];

        $result = $this->registrar->transfer($domain, $authCode, $contact, 1, $nameservers);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testTransferAlreadyExists(): void
    {
        $domain = 'alreadyexists.com';
        $contact = $this->getPurchaseContact();
        $authCode = 'test-auth-code-12345';

        $this->registrar->purchase($domain, $contact, 1);

        $this->expectException(DomainTakenException::class);
        $this->expectExceptionMessage('Domain ' . $domain . ' is already in this account');
        $this->registrar->transfer($domain, $authCode, $contact);
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

        $this->registrar->transfer('transfer.com', 'auth-code', [$invalidContact]);
    }

    public function testCheckTransferStatusWithRequestAddress(): void
    {
        $domain = 'example.com';
        $result = $this->registrar->checkTransferStatus($domain);

        $this->assertInstanceOf(\Utopia\Domains\Registrar\TransferStatusEnum::class, $result->status);
    }
}
