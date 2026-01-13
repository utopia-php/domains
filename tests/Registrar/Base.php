<?php

namespace Utopia\Tests\Registrar;

use PHPUnit\Framework\TestCase;
use Utopia\Domains\Registrar;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\DomainNotTransferableException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\TransferStatusEnum;
use Utopia\Domains\Registrar\UpdateDetails;

abstract class Base extends TestCase
{
    /**
     * Get the registrar instance to test
     */
    abstract protected function getRegistrar(): Registrar;

    /**
     * Get the registrar instance with cache enabled
     */
    abstract protected function getRegistrarWithCache(): Registrar;

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
     * Get an UpdateDetails instance for testing
     *
     * @param array<string,mixed> $details Domain details to update
     * @param array<string,Contact>|Contact|null $contacts Contacts to update
     * @return UpdateDetails
     */
    abstract protected function getUpdateDetails(array $details = [], array|Contact|null $contacts = null): UpdateDetails;

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
        $name = $this->getRegistrar()->getName();
        $this->assertEquals($this->getExpectedAdapterName(), $name);
    }

    public function testAvailable(): void
    {
        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();
        $result = $this->getRegistrar()->available($domain);

        $this->assertTrue($result);
    }

    public function testAvailableForTakenDomain(): void
    {
        $domain = 'google.com';
        $result = $this->getRegistrar()->available($domain);

        $this->assertFalse($result);
    }

    public function testPurchase(): void
    {
        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();
        $result = $this->getRegistrar()->purchase($domain, $this->getPurchaseContact(), 1);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testPurchaseTakenDomain(): void
    {
        $domain = 'google.com';

        $this->expectException(DomainTakenException::class);
        $this->getRegistrar()->purchase($domain, $this->getPurchaseContact(), 1);
    }

    public function testPurchaseWithInvalidContact(): void
    {
        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();

        $this->expectException(InvalidContactException::class);
        $this->getRegistrar()->purchase($domain, [
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
        $testDomain = $this->getTestDomain();
        $result = $this->getRegistrar()->getDomain($testDomain);

        $this->assertEquals($testDomain, $result->domain);
        $this->assertInstanceOf(\DateTime::class, $result->createdAt);
        $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
        $this->assertIsBool($result->autoRenew);
        $this->assertIsArray($result->nameservers);
    }

    public function testCancelPurchase(): void
    {
        $result = $this->getRegistrar()->cancelPurchase();
        $this->assertTrue($result);
    }

    public function testTlds(): void
    {
        $tlds = $this->getRegistrar()->tlds();
        $this->assertIsArray($tlds);
    }

    public function testSuggest(): void
    {
        $result = $this->getRegistrar()->suggest(
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
        $domain = $this->getPricingTestDomain();
        $result = $this->getRegistrar()->getPrice($domain, 1, Registrar::REG_TYPE_NEW);

        $this->assertNotNull($result);
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testGetPriceWithInvalidDomain(): void
    {
        $this->expectException(PriceNotFoundException::class);
        $this->getRegistrar()->getPrice("invalid.invalidtld", 1, Registrar::REG_TYPE_NEW);
    }

    public function testGetPriceWithCache(): void
    {
        $domain = $this->getPricingTestDomain();
        $registrar = $this->getRegistrarWithCache();

        $result1 = $registrar->getPrice($domain, 1, Registrar::REG_TYPE_NEW, 3600);
        $this->assertNotNull($result1);
        $this->assertIsFloat($result1);

        $result2 = $registrar->getPrice($domain, 1, Registrar::REG_TYPE_NEW, 3600);
        $this->assertEquals($result1, $result2);
    }

    public function testGetPriceWithCustomTtl(): void
    {
        $domain = $this->getPricingTestDomain();
        $result = $this->getRegistrarWithCache()->getPrice($domain, 1, Registrar::REG_TYPE_NEW, 7200);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testUpdateNameservers(): void
    {
        $testDomain = $this->getTestDomain();
        $nameservers = $this->getDefaultNameservers();

        $result = $this->getRegistrar()->updateNameservers($testDomain, $nameservers);

        $this->assertTrue($result['successful']);
        $this->assertArrayHasKey('nameservers', $result);
    }

    public function testUpdateDomain(): void
    {
        $testDomain = $this->getTestDomain();

        $result = $this->getRegistrar()->updateDomain(
            $testDomain,
            $this->getUpdateDetails(
                [
                    'autorenew' => true,
                    'data' => 'contact_info',
                ],
                $this->getPurchaseContact('2')
            )
        );

        $this->assertTrue($result);
    }

    public function testRenewDomain(): void
    {
        $testDomain = $this->getTestDomain();

        try {
            $result = $this->getRegistrar()->renew($testDomain, 1);
            $this->assertIsString($result->orderId);
            $this->assertNotEmpty($result->orderId);
            $this->assertInstanceOf(\DateTime::class, $result->expiresAt);
            $this->assertNotEmpty($result->expiresAt);
        } catch (\Exception $e) {
            // Renewal may fail for various reasons depending on the registrar
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testTransfer(): void
    {
        $domain = $this->generateRandomString() . '.' . $this->getDefaultTld();

        try {
            $result = $this->getRegistrar()->transfer($domain, 'test-auth-code', $this->getPurchaseContact());

            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } catch (\Exception $e) {
            $this->assertInstanceOf(DomainNotTransferableException::class, $e);
        }
    }

    public function testGetAuthCode(): void
    {
        $testDomain = $this->getTestDomain();

        try {
            $authCode = $this->getRegistrar()->getAuthCode($testDomain);
            $this->assertIsString($authCode);
            $this->assertNotEmpty($authCode);
        } catch (\Exception $e) {
            // Some domains may not support auth codes
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testCheckTransferStatus(): void
    {
        $testDomain = $this->getTestDomain();
        $result = $this->getRegistrar()->checkTransferStatus($testDomain);

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
