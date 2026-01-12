<?php

namespace Utopia\Tests\Registrar;

use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar\Exception\AuthException;
use Utopia\Domains\Registrar\Exception\DomainNotTransferableException;
use Utopia\Domains\Registrar\Adapter\OpenSRS;

class OpenSRSTest extends Base
{
    private OpenSRS $client;
    private OpenSRS $clientWithCache;
    private string $testDomain = 'kffsfudlvc.net';

    protected function setUp(): void
    {
        $key = getenv('OPENSRS_KEY');
        $username = getenv('OPENSRS_USERNAME');
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

        $this->assertNotEmpty($key);
        $this->assertNotEmpty($username);

        $this->client = new OpenSRS(
            $key,
            $username,
            $this->generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ]
        );
        $this->clientWithCache = new OpenSRS(
            $key,
            $username,
            $this->generateRandomString(),
            [
                'ns1.systemdns.com',
                'ns2.systemdns.com',
            ],
            'https://horizon.opensrs.net:55443',
            $cache
        );
    }

    protected function getAdapter(): OpenSRS
    {
        return $this->client;
    }

    protected function getAdapterWithCache(): OpenSRS
    {
        return $this->clientWithCache;
    }

    protected function getTestDomain(): string
    {
        return $this->testDomain;
    }

    protected function getExpectedAdapterName(): string
    {
        return 'opensrs';
    }

    protected function getDefaultTld(): string
    {
        return 'net';
    }

    protected function getDefaultNameservers(): array
    {
        return [
            'ns1.systemdns.com',
            'ns2.systemdns.com',
        ];
    }



    // OpenSRS-specific tests

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

        $domain = $this->generateRandomString() . '.net';
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage("Failed to purchase domain: Invalid password");
        $client->purchase($domain, $this->getPurchaseContact(), 1);
    }

    public function testSuggestWithMultipleKeywords(): void
    {
        // Test suggestion domains only with prices
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
    }

    public function testSuggestPremiumWithPriceFilter(): void
    {
        // Premium domains with price filters
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
    }

    public function testTransferNotRegistered(): void
    {
        $domain = $this->generateRandomString() . '.net';

        try {
            $result = $this->client->transfer($domain, 'test-auth-code', $this->getPurchaseContact());
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
            $result = $this->client->transfer($this->testDomain, 'test-auth-code', $this->getPurchaseContact());
            $this->assertTrue($result->successful);
            $this->assertNotEmpty($result->code);
        } catch (DomainNotTransferableException $e) {
            $this->assertEquals(OpenSRS::RESPONSE_CODE_DOMAIN_NOT_TRANSFERABLE, $e->getCode());
            $this->assertStringContainsString('Domain is not transferable: Domain already exists', $e->getMessage());
        }
    }
}
