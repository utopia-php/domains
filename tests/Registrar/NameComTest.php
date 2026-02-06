<?php

namespace Utopia\Tests\Registrar;

use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar;
use Utopia\Domains\Registrar\Exception\AuthException;
use Utopia\Domains\Registrar\Adapter\NameCom;
use Utopia\Domains\Registrar\UpdateDetails;

class NameComTest extends Base
{
    private Registrar $registrar;
    private Registrar $registrarWithCache;
    private NameCom $adapter;

    protected function setUp(): void
    {
        $username = getenv('NAMECOM_USERNAME');
        $token = getenv('NAMECOM_TOKEN');
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

        $this->assertNotEmpty($username, 'NAMECOM_USERNAME environment variable must be set');
        $this->assertNotEmpty($token, 'NAMECOM_TOKEN environment variable must be set');

        $this->adapter = new NameCom(
            $username,
            $token,
            'https://api.dev.name.com'
        );

        $this->registrar = new Registrar(
            $this->adapter,
            [
                'ns1.name.com',
                'ns2.name.com',
            ]
        );

        $this->registrarWithCache = new Registrar(
            $this->adapter,
            [
                'ns1.name.com',
                'ns2.name.com',
            ],
            $cache
        );
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
        // For tests that need an existing domain, we'll purchase one on the fly
        // or return a domain we know exists
        $testDomain = $this->generateRandomString() . '.com';
        $this->registrar->purchase($testDomain, $this->getPurchaseContact(), 1);
        return $testDomain;
    }

    protected function getExpectedAdapterName(): string
    {
        return 'namecom';
    }

    protected function getDefaultNameservers(): array
    {
        return [
            'ns1.name.com',
            'ns2.name.com',
        ];
    }

    protected function getUpdateDetails(?bool $autoRenew = null): UpdateDetails
    {
        return new UpdateDetails($autoRenew);
    }

    protected function getPricingTestDomain(): string
    {
        // Name.com doesn't like 'example.com' for pricing
        return 'example-test-domain.com';
    }

    // NameCom-specific tests

    public function testPurchaseWithInvalidCredentials(): void
    {
        $adapter = new NameCom(
            'invalid-username',
            'invalid-token',
            'https://api.dev.name.com'
        );

        $registrar = new Registrar(
            $adapter,
            [
                'ns1.name.com',
                'ns2.name.com',
            ]
        );

        $domain = $this->generateRandomString() . '.com';

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage("Failed to send request to Name.com: Unauthorized");

        $registrar->purchase($domain, $this->getPurchaseContact(), 1);
    }

    public function testSuggestPremiumDomains(): void
    {
        $result = $this->registrar->suggest(
            'business',
            ['com'],
            5,
            'premium',
            10000,
            100
        );

        $this->assertIsArray($result);

        foreach ($result as $domain => $data) {
            $this->assertEquals('premium', $data['type']);
            if ($data['price'] !== null) {
                $this->assertGreaterThanOrEqual(100, $data['price']);
                $this->assertLessThanOrEqual(10000, $data['price']);
            }
        }
    }

    public function testSuggestWithFilter(): void
    {
        $result = $this->registrar->suggest(
            'testdomain',
            ['com'],
            5,
            'suggestion'
        );

        $this->assertIsArray($result);

        foreach ($result as $domain => $data) {
            $this->assertEquals('suggestion', $data['type']);
        }
    }

    public function testCheckTransferStatus(): void
    {
        $this->markTestSkipped('Name.com for some reason always returning 404 (Not Found) for transfer status check. Investigate later.');
    }
}
