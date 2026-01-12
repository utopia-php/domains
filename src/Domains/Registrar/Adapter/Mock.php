<?php

namespace Utopia\Domains\Registrar\Adapter;

use DateTime;
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\Domain;
use Utopia\Domains\Registrar\Registration;
use Utopia\Domains\Registrar\Renewal;
use Utopia\Domains\Registrar\TransferStatus;
use Utopia\Domains\Registrar\Adapter;
use Utopia\Domains\Registrar\TransferStatusEnum;
use Utopia\Domains\Registrar;

class Mock extends Adapter
{
    /**
     * Mock API Response Codes
     */
    private const RESPONSE_CODE_SUCCESS = 200;
    private const RESPONSE_CODE_BAD_REQUEST = 400;
    private const RESPONSE_CODE_NOT_FOUND = 404;
    private const RESPONSE_CODE_INVALID_CONTACT = 465;
    private const RESPONSE_CODE_DOMAIN_TAKEN = 485;

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
        if (in_array($domain, $this->takenDomains)) {
            return false;
        }

        if (in_array($domain, $this->purchasedDomains)) {
            return false;
        }

        return true;
    }

    /**
     * Purchase a domain
     *
     * @param string $domain
     * @param array|Contact $contacts
     * @param int $periodYears
     * @param array $nameservers
     * @return Registration
     * @throws DomainTakenException
     * @throws InvalidContactException
     */
    public function purchase(string $domain, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): Registration
    {
        if (!$this->available($domain)) {
            throw new DomainTakenException("Domain {$domain} is not available for registration", self::RESPONSE_CODE_DOMAIN_TAKEN);
        }

        $this->validateContacts($contacts);

        $this->purchasedDomains[] = $domain;

        return new Registration(
            code: (string) self::RESPONSE_CODE_SUCCESS,
            id: 'mock_' . md5($domain . time()),
            domainId: 'mock_domain_' . md5($domain),
            successful: true,
            domain: $domain,
            periodYears: $periodYears,
            nameservers: $nameservers,
        );
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

        if (($filterType === null || $filterType === 'premium') && $count < $limit) {
            foreach ($this->premiumDomains as $domain => $price) {
                if ($count >= $limit) {
                    break;
                }

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
     * @return Domain
     * @throws DomainsException
     */
    public function getDomain(string $domain): Domain
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
        }

        return new Domain(
            domain: $domain,
            createdAt: new DateTime(),
            expiresAt: new DateTime('+1 year'),
            autoRenew: false,
            nameservers: [
                'ns1.example.com',
                'ns2.example.com',
            ],
        );
    }

    /**
     * Get the price for a domain
     *
     * @param string $domain
     * @param int $periodYears
     * @param string $regType
     * @param int $ttl Time to live for the cache (if set) in seconds
     * @return float
     * @throws PriceNotFoundException
     */
    public function getPrice(string $domain, int $periodYears = 1, string $regType = Registrar::REG_TYPE_NEW, int $ttl = 3600): float
    {
        if ($this->cache) {
            $cached = $this->cache->load($domain, $ttl);
            if ($cached !== null && is_array($cached)) {
                return $cached['price'];
            }
        }

        if (isset($this->premiumDomains[$domain])) {
            $result = $this->premiumDomains[$domain] * $periodYears;
            if ($this->cache) {
                $this->cache->save($domain, [
                    'price' => $result,
                ]);
            }

            return $result;
        }

        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            throw new PriceNotFoundException("Invalid domain format: {$domain}", self::RESPONSE_CODE_BAD_REQUEST);
        }

        $tld = end($parts);

        if (!in_array($tld, $this->supportedTlds)) {
            throw new PriceNotFoundException("TLD .{$tld} is not supported", self::RESPONSE_CODE_BAD_REQUEST);
        }

        $basePrice = $this->defaultPrice;
        $multiplier = match ($regType) {
            Registrar::REG_TYPE_TRANSFER => 1.0,
            Registrar::REG_TYPE_RENEWAL => 1.1,
            Registrar::REG_TYPE_TRADE => 1.2,
            default => 1.0,
        };

        $result = $basePrice * $periodYears * $multiplier;
        if ($this->cache) {
            $this->cache->save($domain, [
                'price' => $result,
            ]);
        }

        return $result;
    }

    /**
     * Renewal a domain
     *
     * @param string $domain
     * @param int $periodYears
     * @return Renewal
     * @throws DomainsException
     */
    public function renew(string $domain, int $periodYears): Renewal
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
        }

        $domainInfo = $this->getDomain($domain);
        $currentExpiry = $domainInfo->expiresAt;
        $newExpiry = $currentExpiry ? (clone $currentExpiry)->modify("+{$periodYears} years") : new DateTime("+{$periodYears} years");

        return new Renewal(
            successful: true,
            orderId: 'mock_order_' . md5($domain . time()),
            expiresAt: $newExpiry,
        );
    }

    /**
     * Update domain information
     *
     * @param string $domain
     * @param array|Contact|null $contacts
     * @param array $details
     * @return bool
     * @throws DomainsException
     * @throws InvalidContactException
     */
    public function updateDomain(string $domain, array $details, array|Contact|null $contacts = null): bool
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
        }

        if ($contacts) {
            $this->validateContacts($contacts);
        }

        return true;
    }

    /**
     * Transfer a domain
     *
     * @param string $domain
     * @param string $authCode
     * @param array|Contact $contacts
     * @param int $periodYears
     * @param array $nameservers
     * @return Registration
     * @throws DomainTakenException
     * @throws InvalidContactException
     */
    public function transfer(string $domain, string $authCode, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): Registration
    {
        if (in_array($domain, $this->purchasedDomains)) {
            throw new DomainTakenException("Domain {$domain} is already in this account", self::RESPONSE_CODE_DOMAIN_TAKEN);
        }

        $this->validateContacts($contacts);

        $this->transferredDomains[] = $domain;
        $this->purchasedDomains[] = $domain;

        return new Registration(
            code: (string) self::RESPONSE_CODE_SUCCESS,
            id: 'mock_transfer_' . md5($domain . time()),
            domainId: 'mock_domain_' . md5($domain),
            successful: true,
            domain: $domain,
            periodYears: $periodYears,
            nameservers: $nameservers,
        );
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

    /**
     * Get the authorization code for an EPP domain
     *
     * @param string $domain
     * @return string
     * @throws DomainsException
     */
    public function getAuthCode(string $domain): string
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
        }

        return 'mock_' . substr(md5($domain), 0, 8);
    }

    /**
     * Check transfer status for a domain
     *
     * @param string $domain
     * @param bool $checkStatus
     * @param bool $getRequestAddress
     * @return TransferStatus
     */
    public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): TransferStatus
    {
        if (in_array($domain, $this->transferredDomains)) {
            return new TransferStatus(
                status: TransferStatusEnum::PendingRegistry,
                reason: 'Transfer in progress',
                timestamp: new DateTime(),
            );
        } elseif (in_array($domain, $this->purchasedDomains)) {
            return new TransferStatus(
                status: TransferStatusEnum::Completed,
                reason: "Domain already exists in mock account",
                timestamp: new DateTime(),
            );
        } else {
            return new TransferStatus(
                status: TransferStatusEnum::Transferrable,
                reason: null,
                timestamp: null,
            );
        }
    }

    /**
     * Update the nameservers for a domain
     *
     * @param string $domain
     * @param array $nameservers
     * @return array
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        return [
            'successful' => true,
            'nameservers' => $nameservers,
        ];
    }

    /**
     * Cancel pending purchase orders
     *
     * @return bool
     */
    public function cancelPurchase(): bool
    {
        return true;
    }

    /**
     * Validate contacts
     *
     * @param array|Contact $contacts
     * @return void
     * @throws InvalidContactException
     */
    private function validateContacts(array|Contact $contacts): void
    {
        $contactsArray = is_array($contacts) ? $contacts : [$contacts];

        foreach ($contactsArray as $contact) {
            if (!($contact instanceof Contact)) {
                throw new InvalidContactException("Invalid contact: contact must be an instance of Contact", self::RESPONSE_CODE_INVALID_CONTACT);
            }

            $contactData = $contact->toArray();
            $required = [
                'firstname',
                'lastname',
                'email',
                'phone',
                'address1',
                'city',
                'state',
                'postalcode',
                'country',
            ];

            foreach ($required as $field) {
                if (!isset($contactData[$field]) || empty($contactData[$field])) {
                    throw new InvalidContactException("Invalid contact: missing required field '{$field}'", self::RESPONSE_CODE_INVALID_CONTACT);
                }
            }

            if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidContactException("Invalid contact: invalid email format", self::RESPONSE_CODE_INVALID_CONTACT);
            }
        }
    }
}
