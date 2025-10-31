<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Cache;
use Utopia\Domains\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\InvalidContact;
use Utopia\Domains\Registrar\Exception\PriceNotFound;

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
     * Cache instance
     */
    protected ?Cache $cache = null;

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
     * @param Cache|null $cache Optional cache instance
     */
    public function __construct(
        array $takenDomains = [],
        array $supportedTlds = [],
        float $defaultPrice = 12.99,
        ?Cache $cache = null
    ) {
        if (!empty($takenDomains)) {
            $this->takenDomains = array_merge($this->takenDomains, $takenDomains);
        }

        if (!empty($supportedTlds)) {
            $this->supportedTlds = $supportedTlds;
        }

        $this->defaultPrice = $defaultPrice;
        $this->cache = $cache;
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
     * @param array|\Utopia\Domains\Contact $contacts
     * @param int $period
     * @param array $nameservers
     * @return array
     * @throws DomainTaken
     * @throws InvalidContact
     */
    public function purchase(string $domain, array|Contact $contacts, int $period = 1, array $nameservers = []): array
    {
        if (!$this->available($domain)) {
            throw new DomainTaken("Domain {$domain} is not available for registration", self::RESPONSE_CODE_DOMAIN_TAKEN);
        }

        $this->validateContacts($contacts);

        $this->purchasedDomains[] = $domain;

        return [
            'code' => (string) self::RESPONSE_CODE_SUCCESS,
            'id' => 'mock_' . md5($domain . time()),
            'domainId' => 'mock_domain_' . md5($domain),
            'period' => $period,
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
     * @return array
     * @throws DomainsException
     */
    public function getDomain(string $domain): array
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
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
     * @param int $ttl Time to live for the cache (if set) in seconds
     * @return array
     * @throws PriceNotFound
     */
    public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): array
    {
        if ($this->cache) {
            $cached = $this->cache->load($domain, $ttl);
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }
        }

        if (isset($this->premiumDomains[$domain])) {
            $result = [
                'price' => $this->premiumDomains[$domain] * $period,
                'is_registry_premium' => true,
                'registry_premium_group' => 'premium',
            ];

            if ($this->cache) {
                $this->cache->save($domain, $result);
            }

            return $result;
        }

        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            throw new PriceNotFound("Invalid domain format: {$domain}", self::RESPONSE_CODE_BAD_REQUEST);
        }

        $tld = end($parts);

        if (!in_array($tld, $this->supportedTlds)) {
            throw new PriceNotFound("TLD .{$tld} is not supported", self::RESPONSE_CODE_BAD_REQUEST);
        }

        $basePrice = $this->defaultPrice;
        $multiplier = match ($regType) {
            self::REG_TYPE_TRANSFER => 1.0,
            self::REG_TYPE_RENEWAL => 1.1,
            self::REG_TYPE_TRADE => 1.2,
            default => 1.0,
        };

        $result = [
            'price' => $basePrice * $period * $multiplier,
            'is_registry_premium' => false,
            'registry_premium_group' => null,
        ];

        if ($this->cache) {
            $this->cache->save($domain, $result);
        }

        return $result;
    }

    /**
     * Renew a domain
     *
     * @param string $domain
     * @param int $period
     * @return array
     * @throws DomainsException
     */
    public function renew(string $domain, int $period): array
    {
        if (!in_array($domain, $this->purchasedDomains)) {
            throw new DomainsException("Domain {$domain} not found in mock registry", self::RESPONSE_CODE_NOT_FOUND);
        }

        $domainInfo = $this->getDomain($domain);
        $currentExpiry = strtotime($domainInfo['registry_expiredate']);
        $newExpiry = strtotime("+{$period} years", $currentExpiry);

        return [
            'order_id' => 'mock_order_' . md5($domain . time()),
            'successful' => true,
            'new_expiration' => date('Y-m-d H:i:s', $newExpiry),
            'domain' => $domain,
        ];
    }

    /**
     * Update domain information
     *
     * @param string $domain
     * @param array|Contact|null $contacts
     * @param array $details
     * @return bool
     * @throws DomainsException
     * @throws InvalidContact
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
     * @param array $nameservers
     * @return array
     * @throws DomainTaken
     * @throws InvalidContact
     */
    public function transfer(string $domain, string $authCode, array|Contact $contacts, array $nameservers = []): array
    {
        if (in_array($domain, $this->purchasedDomains)) {
            throw new DomainTaken("Domain {$domain} is already in this account", self::RESPONSE_CODE_DOMAIN_TAKEN);
        }

        $this->validateContacts($contacts);

        $this->transferredDomains[] = $domain;
        $this->purchasedDomains[] = $domain;

        return [
            'code' => (string) self::RESPONSE_CODE_SUCCESS,
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
     * @return array
     */
    public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): array
    {
        $response = [
            'noservice' => 0,
        ];

        if (in_array($domain, $this->transferredDomains)) {
            $response['transferrable'] = 0;
            $response['reason'] = 'Transfer in progress';
            $response['status'] = 'pending_registry';
            $response['timestamp'] = date('D M d H:i:s Y');
            $response['unixtime'] = time();
        } elseif (in_array($domain, $this->purchasedDomains)) {
            $response['transferrable'] = 0;
            $response['reason'] = "Domain already exists in mock account";
            $response['reason_code'] = 'domain_already_belongs_to_current_reseller';
            $response['status'] = 'completed';
            $response['timestamp'] = date('D M d H:i:s Y');
            $response['unixtime'] = time();
        } else {
            $response['transferrable'] = 1;
            $response['type'] = 'reg2reg';
        }

        if ($getRequestAddress) {
            $response['request_address'] = 'mock@example.com';
        }

        return $response;
    }

    /**
     * Validate contacts
     *
     * @param array|Contact $contacts
     * @return void
     * @throws InvalidContact
     */
    private function validateContacts(array|Contact $contacts): void
    {
        $contactsArray = is_array($contacts) ? $contacts : [$contacts];

        foreach ($contactsArray as $contact) {
            if (!($contact instanceof Contact)) {
                throw new InvalidContact("Invalid contact: contact must be an instance of Contact", self::RESPONSE_CODE_INVALID_CONTACT);
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
                    throw new InvalidContact("Invalid contact: missing required field '{$field}'", self::RESPONSE_CODE_INVALID_CONTACT);
                }
            }

            if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidContact("Invalid contact: invalid email format", self::RESPONSE_CODE_INVALID_CONTACT);
            }
        }
    }
}
