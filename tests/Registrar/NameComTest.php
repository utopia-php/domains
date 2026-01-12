<?php

namespace Utopia\Tests\Registrar;

use Utopia\Cache\Cache as UtopiaCache;
use Utopia\Cache\Adapter\None as NoneAdapter;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar;
use Utopia\Domains\Registrar\Exception\AuthException;
use Utopia\Domains\Registrar\Adapter\NameCom;

class NameComTest extends BaseRegistrarTest
{
    private NameCom $client;
    private NameCom $clientWithCache;

    protected function setUp(): void
    {
        $username = getenv('NAMECOM_USERNAME');
        $token = getenv('NAMECOM_TOKEN');
        $utopiaCache = new UtopiaCache(new NoneAdapter());
        $cache = new Cache($utopiaCache);

        $this->assertNotEmpty($username, 'NAMECOM_USERNAME environment variable must be set');
        $this->assertNotEmpty($token, 'NAMECOM_TOKEN environment variable must be set');

        $this->client = new NameCom(
            $username,
            $token,
            [
                'ns1.name.com',
                'ns2.name.com',
            ],
            'https://api.dev.name.com'
        );
        $this->clientWithCache = new NameCom(
            $username,
            $token,
            [
                'ns1.name.com',
                'ns2.name.com',
            ],
            'https://api.dev.name.com',
            $cache
        );
    }

    protected function getAdapter(): Registrar
    {
        return $this->client;
    }

    protected function getAdapterWithCache(): Registrar
    {
        return $this->clientWithCache;
    }

    protected function getTestDomain(): string
    {
        // For tests that need an existing domain, we'll purchase one on the fly
        // or return a domain we know exists
        $testDomain = $this->generateRandomString() . '.com';
        $this->client->purchase($testDomain, $this->getPurchaseContact(), 1);
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

    // NameCom-specific tests

    public function testPurchaseWithInvalidCredentials(): void
    {
        $client = new NameCom(
            'invalid-username',
            'invalid-token',
            [
                'ns1.name.com',
                'ns2.name.com',
            ],
            'https://api.dev.name.com'
        );

        $domain = $this->generateRandomString() . '.com';
        echo "\n[testPurchaseWithInvalidCredentials] Testing purchase with invalid credentials for: {$domain} (expecting exception)\n";

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage("Failed to purchase domain:");

        $client->purchase($domain, $this->getPurchaseContact(), 1);
    }

    public function testSuggestPremiumDomains(): void
    {
        echo "\n[testSuggestPremiumDomains] Getting premium suggestions for 'business'...\n";
        $result = $this->client->suggest(
            'business',
            ['com'],
            5,
            'premium',
            10000,
            100
        );

        $this->assertIsArray($result);
        echo "[testSuggestPremiumDomains] Received " . count($result) . " premium suggestions\n";

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
        echo "\n[testSuggestWithFilter] Getting suggestions for 'testdomain'...\n";
        $result = $this->client->suggest(
            'testdomain',
            ['com'],
            5,
            'suggestion'
        );

        $this->assertIsArray($result);
        echo "[testSuggestWithFilter] Received " . count($result) . " suggestions\n";

        foreach ($result as $domain => $data) {
            $this->assertEquals('suggestion', $data['type']);
        }
    }
}
