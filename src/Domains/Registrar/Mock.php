<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\PriceNotFound;

class Mock extends Adapter
{
    /**
     * Domains that are considered unavailable/taken
     */
    protected array $takenDomains = [
        'google.com',
        'facebook.com',
        'amazon.com',
    ];

    /**
     * Domains that have been purchased in this mock session
     */
    protected array $purchasedDomains = [];

    /**
     * Domains that have been transferred in this mock session
     */
    protected array $transferredDomains = [];

    /**
     * Supported TLDs
     */
    protected array $supportedTlds = [
        'com',
        'net',
        'org',
        'io',
        'dev',
        'app',
    ];

    /**
     * Default price per year for non-premium domains
     */
    protected float $defaultPrice = 12.99;

    /**
     * Premium domains with their prices
     */
    protected array $premiumDomains = [
        'premium.com' => 5000.00,
        'business.com' => 10000.00,
        'shop.net' => 2500.00,
    ];

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mock';
    }

    /**
     * Constructor
     *
     * @param array $takenDomains Optional list of domains to mark as taken
     * @param array $supportedTlds Optional list of supported TLDs
     * @param float $defaultPrice Optional default price for domains
     */
    public function __construct(
        array $takenDomains = [],
        array $supportedTlds = [],
        float $defaultPrice = 12.99
    ) {
        if (!empty($takenDomains)) {
            $this->takenDomains = array_merge($this->takenDomains, $takenDomains);
        }

        if (!empty($supportedTlds)) {
            $this->supportedTlds = $supportedTlds;
        }

        $this->defaultPrice = $defaultPrice;
    }

    /**
     * Check if a domain is available for registration
     *
     * @param string $domain
     * @return bool
     */
    public function available(string $domain): bool
    {
        // Taken domains are not available
        if (in_array($domain, $this->takenDomains)) {
            return false;
        }

        // Already purchased domains are not available
        if (in_array($domain, $this->purchasedDomains)) {
            return false;
        }

        return true;
    }

    /**
     * Purchase a domain
     *
     * @param string $domain
     * @param array<\Utopia\Domains\Contact> $contacts
     * @param array $nameservers
     * @return array
     * @throws DomainTaken
     */
    public function purchase(string $domain, array|Contact $contacts, array $nameservers = []): array
    {
        if (!$this->available($domain)) {
            throw new DomainTaken("Domain {$domain} is not available for registration", 485);
        }

        // Add to purchased domains
        $this->purchasedDomains[] = $domain;

        return [
            'code' => '200',
            'id' => 'mock_' . md5($domain . time()),
            'domainId' => 'mock_domain_' . md5($domain),
            'successful' => true,
            'domain' => $domain,
            'nameservers' => $nameservers,
        ];
    }

    /**
     * Suggest domain names
     *
     * @param array|string $query
     * @param array $tlds
     * @param int|null $limit
     * @param string|null $filterType
     * @param int|null $priceMax
     * @param int|null $priceMin
     * @return array
     */
    public function suggest(
        array|string $query,
        array $tlds = [],
        int|null $limit = null,
        string|null $filterType = null,
        int|null $priceMax = null,
        int|null $priceMin = null
    ): array {
        $query = is_array($query) ? implode('-', $query) : $query;
        $tlds = !empty($tlds) ? $tlds : $this->supportedTlds;
        $limit = $limit ?? 10;

        $suggestions = [];
        $count = 0;

        // Generate suggestions
        if ($filterType === null || $filterType === 'suggestion') {
            foreach ($tlds as $tld) {
                if ($count >= $limit) {
                    break;
                }

                $domain = $query . '.' . ltrim($tld, '.');
                $suggestions[$domain] = [
                    'available' => $this->available($domain),
                    'price' => null,
                    'type' => 'suggestion',
                ];
                $count++;
            }
        }

        // Add premium suggestions if requested
        if (($filterType === null || $filterType === 'premium') && $count < $limit) {
            foreach ($this->premiumDomains as $domain => $price) {
                if ($count >= $limit) {
                    break;
                }

                // Apply price filters
                if ($priceMin !== null && $price < $priceMin) {
                    continue;
                }
                if ($priceMax !== null && $price > $priceMax) {
                    continue;
                }

                $suggestions[$domain] = [
                    'available' => $this->available($domain),
                    'price' => $price,
                    'type' => 'premium',
                ];
                $count++;
            }
        }

        return $suggestions;
    }

    /**
     * Get list of supported TLDs
     *
     * @return array
     */
    public function tlds(): array
    {
        return $this->supportedTlds;
    }

    /**
     * Get domain information
     *
     * @param string $domain
     * @return array
     * @throws DomainsException
     */
    public function getDomain(string $domain): array
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", 404);
        }

        return [
            'domain' => $domain,
            'registry_createdate' => date('Y-m-d H:i:s'),
            'registry_expiredate' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'auto_renew' => '0',
            'let_expire' => '0',
            'nameserver_list' => [
                'ns1.mock.com',
                'ns2.mock.com',
            ],
        ];
    }

    /**
     * Get the price for a domain
     *
     * @param string $domain
     * @param int $period
     * @param string $regType
     * @return array
     * @throws PriceNotFound
     */
    public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW): array
    {
        // Check if it's a premium domain
        if (isset($this->premiumDomains[$domain])) {
            return [
                'price' => $this->premiumDomains[$domain] * $period,
                'is_registry_premium' => true,
                'registry_premium_group' => 'premium',
            ];
        }

        // Extract TLD
        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            throw new PriceNotFound("Invalid domain format: {$domain}", 400);
        }

        $tld = end($parts);

        // Check if TLD is supported
        if (!in_array($tld, $this->supportedTlds)) {
            throw new PriceNotFound("TLD .{$tld} is not supported", 400);
        }

        // Calculate price based on registration type
        $basePrice = $this->defaultPrice;
        $multiplier = match ($regType) {
            self::REG_TYPE_TRANSFER => 1.0,
            self::REG_TYPE_RENEWAL => 1.1,
            self::REG_TYPE_TRADE => 1.2,
            default => 1.0,
        };

        return [
            'price' => $basePrice * $period * $multiplier,
            'is_registry_premium' => false,
            'registry_premium_group' => null,
        ];
    }

    /**
     * Renew a domain
     *
     * @param string $domain
     * @param int $years
     * @return array
     * @throws DomainsException
     */
    public function renew(string $domain, int $years): array
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", 404);
        }

        $currentExpiry = strtotime('+1 year');
        $newExpiry = strtotime("+{$years} years", $currentExpiry);

        return [
            'order_id' => 'mock_order_' . md5($domain . time()),
            'successful' => true,
            'new_expiration' => date('Y-m-d H:i:s', $newExpiry),
            'domain' => $domain,
        ];
    }

    /**
     * Transfer a domain
     *
     * @param string $domain
     * @param array<\Utopia\Domains\Contact> $contacts
     * @param array $nameservers
     * @return array
     * @throws DomainTaken
     */
    public function transfer(string $domain, array|Contact $contacts, array $nameservers = []): array
    {
        // In mock, we simulate that the domain must exist somewhere to transfer
        if (in_array($domain, $this->purchasedDomains)) {
            throw new DomainTaken("Domain {$domain} is already in this account", 485);
        }

        // Add to transferred domains
        $this->transferredDomains[] = $domain;
        $this->purchasedDomains[] = $domain;

        return [
            'code' => '200',
            'id' => 'mock_transfer_' . md5($domain . time()),
            'domainId' => 'mock_domain_' . md5($domain),
            'successful' => true,
            'domain' => $domain,
            'nameservers' => $nameservers,
        ];
    }

    /**
     * Get list of purchased domains (for testing purposes)
     *
     * @return array
     */
    public function getPurchasedDomains(): array
    {
        return $this->purchasedDomains;
    }

    /**
     * Get list of transferred domains (for testing purposes)
     *
     * @return array
     */
    public function getTransferredDomains(): array
    {
        return $this->transferredDomains;
    }

    /**
     * Reset the mock state (for testing purposes)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->purchasedDomains = [];
        $this->transferredDomains = [];
    }

    /**
     * Add a domain to the taken list (for testing purposes)
     *
     * @param string $domain
     * @return void
     */
    public function addTakenDomain(string $domain): void
    {
        if (!in_array($domain, $this->takenDomains)) {
            $this->takenDomains[] = $domain;
        }
    }

    /**
     * Add a premium domain (for testing purposes)
     *
     * @param string $domain
     * @param float $price
     * @return void
     */
    public function addPremiumDomain(string $domain, float $price): void
    {
        $this->premiumDomains[$domain] = $price;
    }
}
