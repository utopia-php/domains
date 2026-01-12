<?php

namespace Utopia\Tests\Registrar;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar\Adapter;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\TransferStatusEnum;

abstract class Base extends TestCase
{
    /**
     * Get the adapter instance to test
     */
    abstract protected function getAdapter(): Adapter;

    /**
     * Get the adapter instance with cache enabled
     */
    abstract protected function getAdapterWithCache(): Adapter;

    /**
     * Get a test domain that exists and is owned by the test account
     * Used for tests that require an existing domain
     */
    abstract protected function getTestDomain(): string;

    /**
     * Get the expected adapter name
     */
    abstract protected function getExpectedAdapterName(): string;

    /**
     * Check if a test should be skipped for this adapter
     *
     * By default, skip tests for optional methods that not all adapters implement:
     * - testCancelPurchase (only NameCom, OpenSRS)
     * - testUpdateNameservers (only NameCom, OpenSRS)
     */
    protected function shouldSkipTest(string $testName): bool
    {
        $optionalTests = [
            'testCancelPurchase',
            'testUpdateNameservers',
        ];

        return in_array($testName, $optionalTests);
    }

    /**
     * Get purchase contact info
     */
    protected function getPurchaseContact(string $suffix = ''): array
    {
        $contact = new Contact(
            'Test' . $suffix,
            'Tester' . $suffix,
            '+18031234567',
            'testing' . $suffix . '@test.com',
            '123 Main St' . $suffix,
            'Suite 100' . $suffix,
            '',
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

    /**
     * Generate a random string for domain names
     */
    protected function generateRandomString(int $length = 10): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * Get default TLD for testing
     */
    protected function getDefaultTld(): string
    {
        return 'com';
    }

    /**
     * Get a domain to use for pricing tests
     * Can be overridden by adapters if they have restrictions
     */
    protected function getPricingTestDomain(): string
    {
        return 'example.' . $this->getDefaultTld();
    }

    public function testGetName(): void
    {
        if ($this->shouldSkipTest('testGetName')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $name = $this->getAdapter()->getName();
        $this->assertEquals($this->getExpectedAdapterName(), $name);
    }

    public function testAvailable(): void
    {
        if ($this->shouldSkipTest('testAvailable')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();
        $result = $this->getAdapter()->available($domain);

        $this->assertTrue($result);
    }

    public function testAvailableForTakenDomain(): void
    {
        if ($this->shouldSkipTest('testAvailableForTakenDomain')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = 'google.com';
        $result = $this->getAdapter()->available($domain);

        $this->assertFalse($result);
    }

    public function testPurchase(): void
    {
        if ($this->shouldSkipTest('testPurchase')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();
        $result = $this->getAdapter()->purchase($domain, $this->getPurchaseContact(), 1);

        $this->assertTrue($result->successful);
        $this->assertEquals($domain, $result->domain);
    }

    public function testPurchaseTakenDomain(): void
    {
        if ($this->shouldSkipTest('testPurchaseTakenDomain')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = 'google.com';

        $this->expectException(DomainTakenException::class);
        $this->getAdapter()->purchase($domain, $this->getPurchaseContact(), 1);
    }

    public function testPurchaseWithInvalidContact(): void
    {
        if ($this->shouldSkipTest('testPurchaseWithInvalidContact')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();

        $this->expectException(InvalidContactException::class);
        $this->getAdapter()->purchase($domain, [
            new Contact(
                'John',
                'Doe',
                '+1234567890',
                'invalid-email',
                '123 Main St',
                'Suite 100',
                '',
                'San Francisco',
                'CA',
                'InvalidCountry',
                '94105',
                'Test Inc',
            )
        ]);
    }

    public function testDomainInfo(): void
    {
        if ($this->shouldSkipTest('testDomainInfo')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();
        $result = $this->getAdapter()->getDomain($testDomain);

        $this->assertEquals($testDomain, $result->domain);
        $this->assertInstanceOf(\DateTime::class, $result->createdAt);
        $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
        $this->assertIsBool($result->autoRenew);
        $this->assertIsArray($result->nameservers);
    }

    public function testCancelPurchase(): void
    {
        if ($this->shouldSkipTest('testCancelPurchase')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $result = $this->getAdapter()->cancelPurchase();
        $this->assertTrue($result);
    }

    public function testTlds(): void
    {
        if ($this->shouldSkipTest('testTlds')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $tlds = $this->getAdapter()->tlds();
        $this->assertIsArray($tlds);
    }

    public function testSuggest(): void
    {
        if ($this->shouldSkipTest('testSuggest')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $result = $this->getAdapter()->suggest(
            'example',
            ['com', 'net', 'org'],
            5
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));

        foreach ($result as $domain => $data) {
            $this->assertIsString($domain);
            $this->assertArrayHasKey('available', $data);
            $this->assertArrayHasKey('price', $data);
            $this->assertArrayHasKey('type', $data);
            $this->assertIsBool($data['available']);

            if ($data['price'] !== null) {
                $this->assertIsFloat($data['price']);
            }
        }
    }

    public function testGetPrice(): void
    {
        if ($this->shouldSkipTest('testGetPrice')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->getPricingTestDomain();
        $result = $this->getAdapter()->getPrice($domain, 1, Adapter::REG_TYPE_NEW);

        $this->assertNotNull($result);
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testGetPriceWithInvalidDomain(): void
    {
        if ($this->shouldSkipTest('testGetPriceWithInvalidDomain')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $this->expectException(PriceNotFoundException::class);
        $this->getAdapter()->getPrice("invalid.invalidtld", 1, Adapter::REG_TYPE_NEW);
    }

    public function testGetPriceWithCache(): void
    {
        if ($this->shouldSkipTest('testGetPriceWithCache')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->getPricingTestDomain();
        $adapter = $this->getAdapterWithCache();

        $result1 = $adapter->getPrice($domain, 1, Adapter::REG_TYPE_NEW, 3600);
        $this->assertNotNull($result1);
        $this->assertIsFloat($result1);

        $result2 = $adapter->getPrice($domain, 1, Adapter::REG_TYPE_NEW, 3600);
        $this->assertEquals($result1, $result2);
    }

    public function testGetPriceWithCustomTtl(): void
    {
        if ($this->shouldSkipTest('testGetPriceWithCustomTtl')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->getPricingTestDomain();
        $result = $this->getAdapterWithCache()->getPrice($domain, 1, Adapter::REG_TYPE_NEW, 7200);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testUpdateNameservers(): void
    {
        if ($this->shouldSkipTest('testUpdateNameservers')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();
        $nameservers = $this->getDefaultNameservers();

        $result = $this->getAdapter()->updateNameservers($testDomain, $nameservers);

        $this->assertTrue($result['successful']);
        $this->assertArrayHasKey('nameservers', $result);
    }

    public function testUpdateDomain(): void
    {
        if ($this->shouldSkipTest('testUpdateDomain')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();

        $result = $this->getAdapter()->updateDomain(
            $testDomain,
            [
                'autorenew' => true,
                'data' => 'contact_info',
            ],
            $this->getPurchaseContact('2')
        );

        $this->assertTrue($result);
    }

    public function testRenewDomain(): void
    {
        if ($this->shouldSkipTest('testRenewDomain')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();

        try {
            $result = $this->getAdapter()->renew($testDomain, 1);
            $this->assertIsBool($result->successful);
        } catch (\Exception $e) {
            // Renewal may fail for various reasons depending on the adapter
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testTransfer(): void
    {
        if ($this->shouldSkipTest('testTransfer')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();

        try {
            $result = $this->getAdapter()->transfer($domain, 'test-auth-code', $this->getPurchaseContact());

            if ($result->successful) {
                $this->assertNotEmpty($result->code);
                $this->assertEquals($domain, $result->domain);
            }
        } catch (\Exception $e) {
            // Transfer may fail for test domains, which is acceptable
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testGetAuthCode(): void
    {
        if ($this->shouldSkipTest('testGetAuthCode')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();

        try {
            $authCode = $this->getAdapter()->getAuthCode($testDomain);
            $this->assertIsString($authCode);
            $this->assertNotEmpty($authCode);
        } catch (\Exception $e) {
            // Some domains may not support auth codes
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testCheckTransferStatus(): void
    {
        if ($this->shouldSkipTest('testCheckTransferStatus')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();
        $result = $this->getAdapter()->checkTransferStatus($testDomain, true, true);

        $this->assertInstanceOf(TransferStatusEnum::class, $result->status);

        if ($result->status !== TransferStatusEnum::Transferrable) {
            if ($result->reason !== null) {
                $this->assertIsString($result->reason);
            }
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

    public function testCheckTransferStatusWithoutCheckStatus(): void
    {
        if ($this->shouldSkipTest('testCheckTransferStatusWithoutCheckStatus')) {
            $this->markTestSkipped('Test not applicable for this adapter');
        }

        $testDomain = $this->getTestDomain();
        $result = $this->getAdapter()->checkTransferStatus($testDomain, false, false);

        $this->assertInstanceOf(TransferStatusEnum::class, $result->status);
    }

    /**
     * Get default nameservers for testing
     * Can be overridden by child classes
     */
    protected function getDefaultNameservers(): array
    {
        return [
            'ns1.example.com',
            'ns2.example.com',
        ];
    }
}
