<?php

namespace Utopia\Domains\Registrar\Adapter;

use DateTime;
use Exception;
use Utopia\Domains\Registrar\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTakenException;
use Utopia\Domains\Registrar\Exception\DomainNotTransferableException;
use Utopia\Domains\Registrar\Exception\InvalidContactException;
use Utopia\Domains\Registrar\Exception\AuthException;
use Utopia\Domains\Registrar\Exception\PriceNotFoundException;
use Utopia\Domains\Registrar\Exception\DomainNotAvailableException;
use Utopia\Domains\Registrar\Adapter;
use Utopia\Domains\Registrar\Renewal;
use Utopia\Domains\Registrar\TransferStatus;
use Utopia\Domains\Registrar\Domain;
use Utopia\Domains\Registrar\TransferStatusEnum;
use Utopia\Domains\Registrar\UpdateDetails;
use Utopia\Domains\Registrar;

class NameCom extends Adapter
{
    /**
     * Name.com API Error Messages
     */
    public const ERROR_MESSAGE_DOMAIN_TAKEN = 'Domain is not available';
    public const ERROR_MESSAGE_INVALID_CONTACT = 'invalid value for $country when calling';
    public const ERROR_MESSAGE_DOMAIN_NOT_TRANSFERABLE = 'we were unable to get authoritative domain information from the registry. this usually means that the domain name or auth code provided was not correct.';
    public const ERROR_MESSAGE_PRICE_NOT_FOUND = 'Not Found';

    /**
     * Contact Types
     */
    public const CONTACT_TYPE_REGISTRANT = 'registrant';
    public const CONTACT_TYPE_ADMIN = 'admin';
    public const CONTACT_TYPE_TECH = 'tech';
    public const CONTACT_TYPE_BILLING = 'billing';
    public const CONTACT_TYPE_OWNER = 'owner';

    protected string $username;
    protected string $token;

    /**
     * __construct
     * Instantiate a new adapter.
     *
     * @param  string  $username  Name.com API username
     * @param  string  $token  Name.com API token
     * @param  string  $endpoint  The endpoint to use for the API (use https://api.name.com for production)
     * @return void
     */
    public function __construct(
        string $username,
        string $token,
        protected string $endpoint = 'https://api.name.com'
    ) {
        $this->username = $username;
        $this->token = $token;

        if (str_starts_with($endpoint, 'http://')) {
            $this->endpoint = 'https://' . substr($endpoint, 7);
        } elseif (!str_starts_with($endpoint, 'https://')) {
            $this->endpoint = 'https://' . $endpoint;
        }

        $this->headers = [
            'Content-Type: application/json',
        ];
    }

    /**
     * Get the name of this adapter
     *
     * @return string
     */
    public function getName(): string
    {
        return 'namecom';
    }

    /**
     * Check if a domain is available
     *
     * @param string $domain The domain name to check
     * @return bool True if the domain is available, false otherwise
     */
    public function available(string $domain): bool
    {
        $result = $this->send('POST', '/core/v1/domains:checkAvailability', [
            'domainNames' => [$domain],
        ]);

        return $result['results'][0]['purchasable'] ?? false;
    }

    /**
     * Update nameservers for a domain
     *
     * @param string $domain The domain name
     * @param array $nameservers Array of nameserver hostnames
     * @return array Result with 'successful' boolean
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        try {
            $result = $this->send('POST', '/core/v1/domains/' . $domain . ':setNameservers', [
                'nameservers' => $nameservers,
            ]);

            return [
                'successful' => true,
                'nameservers' => $result['nameservers'] ?? $nameservers,
            ];
        } catch (Exception $e) {
            return [
                'successful' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Purchase a new domain
     *
     * @param string $domain The domain name to purchase
     * @param array|Contact $contacts Contact information
     * @param int $periodYears Registration period in years
     * @param array $nameservers Nameservers to use
     * @return string Order ID
     */
    public function purchase(string $domain, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string
    {
        try {
            $contacts = is_array($contacts) ? $contacts : [$contacts];
            $nameservers = empty($nameservers) ? $this->defaultNameservers : $nameservers;

            $contactData = $this->sanitizeContacts($contacts);

            $data = [
                'domain' => [
                    'domainName' => $domain,
                    'nameservers' => $nameservers,
                    'contacts' => $contactData,
                ],
                'years' => $periodYears,
            ];

            $result = $this->send('POST', '/core/v1/domains', $data);
            return (string) ($result['order'] ?? '');

        } catch (AuthException $e) {
            throw $e;

        } catch (Exception $e) {
            $message = 'Failed to purchase domain: ' . $e->getMessage();
            $code = $e->getCode();
            $errorLower = strtolower($e->getMessage());

            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_DOMAIN_TAKEN))) {
                throw new DomainTakenException($message, $e->getCode(), $e);
            }
            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_INVALID_CONTACT))) {
                throw new InvalidContactException($message, $e->getCode(), $e);
            }
            throw new DomainsException($message, $code, $e);
        }
    }

    /**
     * Transfer a domain to this registrar
     *
     * @param string $domain The domain name to transfer
     * @param string $authCode Authorization code for the transfer
     * @param array|Contact $contacts Contact information
     * @param int $periodYears Transfer period in years
     * @param array $nameservers Nameservers to use
     * @return string Order ID
     */
    public function transfer(string $domain, string $authCode, array|Contact $contacts, int $periodYears = 1, array $nameservers = []): string
    {
        try {
            $contacts = is_array($contacts) ? $contacts : [$contacts];
            $nameservers = empty($nameservers) ? $this->defaultNameservers : $nameservers;

            $contactData = $this->sanitizeContacts($contacts);

            $data = [
                'domainName' => $domain,
                'authCode' => $authCode,
                'years' => $periodYears,
                'contacts' => $contactData,
            ];

            if (!empty($nameservers)) {
                $data['nameservers'] = $nameservers;
            }

            $result = $this->send('POST', '/core/v1/transfers', $data);
            return (string) ($result['order'] ?? '');

        } catch (AuthException $e) {
            throw $e;

        } catch (Exception $e) {
            $message = 'Failed to transfer domain: ' . $e->getMessage();
            $code = $e->getCode();
            $errorLower = strtolower($e->getMessage());

            if (
                str_contains($errorLower, strtolower(self::ERROR_MESSAGE_DOMAIN_NOT_TRANSFERABLE)) ||
                $code === 409
            ) {
                throw new DomainNotTransferableException($message, $code, $e);
            }
            if (
                str_contains($errorLower, strtolower(self::ERROR_MESSAGE_INVALID_CONTACT)) ||
                $code === 422
            ) {
                throw new InvalidContactException($message, $e->getCode(), $e);
            }
            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_DOMAIN_TAKEN))) {
                throw new DomainTakenException($message, $e->getCode(), $e);
            }
            throw new DomainsException($message, $code, $e);
        }
    }

    /**
     * Cancel pending purchase orders (Name.com doesn't have a direct equivalent)
     *
     * @return bool Always returns true as Name.com handles this differently
     */
    public function cancelPurchase(): bool
    {
        // Name.com doesn't have a direct equivalent to OpenSRS's cancel pending orders
        // Transfers can be cancelled individually using the CancelTransfer endpoint
        return true;
    }

    /**
     * Suggest domain names based on search query
     *
     * @param array|string $query Search terms to generate suggestions from
     * @param array $tlds Top-level domains to search within
     * @param int|null $limit Maximum number of results to return
     * @param string|null $filterType Filter results by type (not fully supported by Name.com API)
     * @param int|null $priceMax Maximum price for premium domains
     * @param int|null $priceMin Minimum price for premium domains
     * @return array Domains with metadata
     */
    public function suggest(array|string $query, array $tlds = [], int|null $limit = null, string|null $filterType = null, int|null $priceMax = null, int|null $priceMin = null): array
    {
        $query = is_array($query) ? implode(' ', $query) : $query;

        $data = [
            'keyword' => $query,
        ];

        if (!empty($tlds)) {
            $data['tldFilter'] = array_map(fn ($tld) => ltrim($tld, '.'), $tlds);
        }

        if ($limit) {
            $data['limit'] = $limit;
        }

        $result = $this->send('POST', '/core/v1/domains:search', $data);

        $items = [];

        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $domainResult) {
                $domain = $domainResult['domainName'] ?? null;
                if (!$domain) {
                    continue;
                }

                $purchasable = $domainResult['purchasable'] ?? false;
                $price = isset($domainResult['purchasePrice']) ? (float) $domainResult['purchasePrice'] : null;
                $isPremium = isset($domainResult['premium']) && $domainResult['premium'] === true;

                // Apply price filters
                if ($price !== null) {
                    if ($priceMin !== null && $price < $priceMin) {
                        continue;
                    }
                    if ($priceMax !== null && $price > $priceMax) {
                        continue;
                    }
                }

                // Apply filter type
                if ($filterType === 'premium' && !$isPremium) {
                    continue;
                }
                if ($filterType === 'suggestion' && $isPremium) {
                    continue;
                }

                $items[$domain] = [
                    'available' => $purchasable,
                    'price' => $price,
                    'type' => $isPremium ? 'premium' : 'suggestion',
                ];

                if ($limit && count($items) >= $limit) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Get the registration price for a domain
     *
     * @param string $domain The domain name to get pricing for
     * @param int $periodYears Registration period in years
     * @param string $regType Type of registration
     * @param int $ttl Time to live for the cache
     * @return float The price of the domain
     */
    public function getPrice(string $domain, int $periodYears = 1, string $regType = Registrar::REG_TYPE_NEW, int $ttl = 3600): float
    {
        if ($this->cache) {
            $cacheKey = $domain . '_' . $periodYears;
            $cached = $this->cache->load($cacheKey, $ttl);
            if ($cached !== null && is_array($cached) && isset($cached[$regType])) {
                return (float) $cached[$regType];
            }
        }

        try {
            $isAvailable = $this->available($domain);
            if (!$isAvailable) {
                throw new DomainNotAvailableException('Domain is not available: ' . $domain, 400);
            }
        } catch (DomainNotAvailableException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new DomainsException('Failed to get price for domain: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $result = $this->send('GET', '/core/v1/domains/' . $domain . ':getPrice' . '?years=' . $periodYears);
            $purchasePrice = (float) ($result['purchasePrice'] ?? 0);
            $renewalPrice = (float) ($result['renewalPrice'] ?? 0);
            $transferPrice = (float) ($result['transferPrice'] ?? 0);

            if ($this->cache) {
                $cacheKey = $domain . '_' . $periodYears;
                $this->cache->save($cacheKey, [
                    Registrar::REG_TYPE_NEW => $purchasePrice,
                    Registrar::REG_TYPE_RENEWAL => $renewalPrice,
                    Registrar::REG_TYPE_TRANSFER => $transferPrice,
                ]);
            }

            switch ($regType) {
                case Registrar::REG_TYPE_NEW:
                    return $purchasePrice;
                case Registrar::REG_TYPE_RENEWAL:
                    return $renewalPrice;
                case Registrar::REG_TYPE_TRANSFER:
                    return $transferPrice;
            }

            throw new PriceNotFoundException('Price not found for domain: ' . $domain, 400);

        } catch (PriceNotFoundException $e) {
            throw $e;

        } catch (Exception $e) {
            $message = 'Failed to get price for domain: ' . $e->getMessage();
            $errorLower = strtolower($e->getMessage());

            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_PRICE_NOT_FOUND))) {
                throw new PriceNotFoundException($message, $e->getCode(), $e);
            }

            throw new DomainsException($message, $e->getCode(), $e);
        }
    }

    /**
     * Get list of available TLDs
     *
     * @return array List of TLD strings
     */
    public function tlds(): array
    {
        // Name.com supports too many TLDs to return efficiently
        return [];
    }

    /**
     * Get domain information
     *
     * @param string $domain The domain name
     * @return Domain Domain information
     */
    public function getDomain(string $domain): Domain
    {
        try {
            $result = $this->send('GET', '/core/v1/domains/' . $domain);

            $createdAt = isset($result['createDate']) ? new DateTime($result['createDate']) : null;
            $expiresAt = isset($result['expireDate']) ? new DateTime($result['expireDate']) : null;
            $autoRenew = isset($result['autorenewEnabled']) ? (bool) $result['autorenewEnabled'] : false;
            $nameservers = $result['nameservers'] ?? [];

            return new Domain(
                domain: $domain,
                createdAt: $createdAt,
                expiresAt: $expiresAt,
                autoRenew: $autoRenew,
                nameservers: $nameservers,
            );
        } catch (Exception $e) {
            throw new DomainsException('Failed to get domain information: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Update domain information
     *
     * Example request:
     * <code>
     * $details = new NameComUpdateDetails(
     *     autorenewEnabled: true,
     *     privacyEnabled: true,
     *     locked: false
     * );
     * $reg->updateDomain('example.com', $details);
     * </code>
     *
     * @see https://docs.name.com/docs/api-reference/domains/update-a-domain
     *
     * @param string $domain The domain name to update
     * @param UpdateDetails $details The details to update
     * @return bool True if successful
     */
    public function updateDomain(string $domain, UpdateDetails $details): bool
    {
        try {
            $data = $details->toArray();
            if (empty($data)) {
                throw new DomainsException(
                    'Details must contain at least one of: autorenewEnabled, privacyEnabled, locked',
                    400
                );
            }

            $this->send('PATCH', '/core/v1/domains/' . $domain, $data);
            return true;

        } catch (DomainsException $e) {
            throw $e;

        } catch (Exception $e) {
            throw new DomainsException('Failed to update domain: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Renew a domain
     *
     * @see https://docs.name.com/docs/api-reference/domains/renew-domain#renew-domain
     *
     * @param string $domain The domain name to renew
     * @param int $periodYears The number of years to renew
     * @return Renewal Renewal information
     */
    public function renew(string $domain, int $periodYears): Renewal
    {
        try {
            $data = [
                'years' => $periodYears,
            ];

            $result = $this->send('POST', '/core/v1/domains/' . $domain . ':renew', $data);

            $orderId = (string) ($result['order'] ?? '');
            $expiresAt = isset($result['domain']['expireDate']) ? new DateTime($result['domain']['expireDate']) : null;

            return new Renewal(
                orderId: $orderId,
                expiresAt: $expiresAt,
            );
        } catch (Exception $e) {
            throw new DomainsException('Failed to renew domain: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the authorization code for an EPP domain
     *
     * @see https://docs.name.com/docs/api-reference/domains/get-auth-code-for-domain#get-auth-code-for-domain
     *
     * @param string $domain The domain name
     * @return string The authorization code
     */
    public function getAuthCode(string $domain): string
    {
        try {
            $result = $this->send('GET', '/core/v1/domains/' . $domain . ':getAuthCode');

            if (isset($result['authCode'])) {
                return $result['authCode'];
            }

            throw new DomainsException('Auth code not found in response', 404);
        } catch (DomainsException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new DomainsException('Failed to get auth code: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Check transfer status for a domain
     *
     * @param string $domain The domain name
     * @return TransferStatus Transfer status information
     */
    public function checkTransferStatus(string $domain): TransferStatus
    {
        try {
            // Use efficient single-domain lookup endpoint
            $result = $this->send('GET', '/core/v1/transfers/' . $domain);

            $status = $this->mapTransferStatus($result['status'] ?? 'unknown');
            $reason = isset($result['statusDetails']) ? $result['statusDetails'] : null;

            return new TransferStatus(
                status: $status,
                reason: $reason,
                timestamp: isset($result['created']) ? new DateTime($result['created']) : null,
            );
        } catch (Exception $e) {
            // If transfer not found (404), domain is transferable (no transfer initiated)
            if ($e->getCode() === 404) {
                return new TransferStatus(
                    status: TransferStatusEnum::Transferrable,
                    reason: null,
                    timestamp: null,
                );
            }

            throw new DomainsException('Failed to check transfer status: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Map Name.com transfer status to TransferStatusEnum
     *
     * Name.com statuses: canceled, canceled_pending_refund, completed, failed,
     * pending, pending_insert, pending_new_auth_code, pending_transfer,
     * pending_unlock, rejected, submitting_transfer
     *
     * @see https://docs.name.com/docs/api-reference/transfers/get-transfer#get-transfer
     *
     * @param string $status Name.com status string
     * @return TransferStatusEnum
     */
    private function mapTransferStatus(string $status): TransferStatusEnum
    {
        return match (strtolower($status)) {
            'completed' => TransferStatusEnum::Completed,
            'canceled', 'canceled_pending_refund', 'rejected' => TransferStatusEnum::Cancelled,
            'pending', 'pending_transfer', 'submitting_transfer' => TransferStatusEnum::PendingRegistry,
            'pending_insert' => TransferStatusEnum::PendingAdmin,
            'pending_new_auth_code', 'pending_unlock' => TransferStatusEnum::PendingOwner,
            'failed' => TransferStatusEnum::NotTransferrable,
            default => TransferStatusEnum::NotTransferrable,
        };
    }

    /**
     * Send an API request to Name.com
     *
     * @param string $method HTTP method
     * @param string $path API endpoint path
     * @param array|null $data Request data
     * @return array Response data
     */
    private function send(string $method, string $path, ?array $data = null): array
    {
        $url = $this->endpoint . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->token);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                $jsonError = json_last_error_msg();
                curl_close($ch);
                throw new Exception('Failed to encode request data to JSON: ' . $jsonError);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Failed to send request to Name.com: ' . $error);
        }

        curl_close($ch);

        $response = json_decode($result, true);
        if ($response === null && $result !== 'null' && $result !== '') {
            throw new Exception('Failed to parse response from Name.com: Invalid JSON');
        }

        if ($httpCode >= 400) {
            $message = $response['message'] ?? $response['details'] ?? 'Unknown error';

            if ($httpCode === 401 && $message === 'Unauthorized') {
                throw new AuthException('Failed to send request to Name.com: ' . $message, $httpCode);
            }

            throw new Exception($message, $httpCode);
        }

        return $response ?? [];
    }

    /**
     * Sanitize contacts array to Name.com format
     *
     * @param Contact[] $contacts Array of Contact objects
     * @return array Sanitized contacts in Name.com format
     */
    private function sanitizeContacts(array $contacts): array
    {
        if (empty($contacts)) {
            throw new InvalidContactException('Contacts must be a non-empty array', 400);
        }

        // Validate all items are Contact instances
        foreach ($contacts as $key => $contact) {
            if (!$contact instanceof Contact) {
                $keyInfo = is_int($key) ? "index $key" : "key '$key'";
                throw new InvalidContactException("Contact at $keyInfo must be an instance of Contact", 400);
            }
        }

        // Use first contact as default fallback
        $defaultContact = reset($contacts);

        // Map contacts to required types using null coalescing
        // Checks associative keys first, then numeric indices, then falls back to default
        $mappings = [
            self::CONTACT_TYPE_REGISTRANT => $contacts[self::CONTACT_TYPE_REGISTRANT]
                ?? $contacts[self::CONTACT_TYPE_OWNER]
                ?? $contacts[0]
                ?? $defaultContact,
            self::CONTACT_TYPE_ADMIN => $contacts[self::CONTACT_TYPE_ADMIN]
                ?? $contacts[1]
                ?? $defaultContact,
            self::CONTACT_TYPE_TECH => $contacts[self::CONTACT_TYPE_TECH]
                ?? $contacts[2]
                ?? $defaultContact,
            self::CONTACT_TYPE_BILLING => $contacts[self::CONTACT_TYPE_BILLING]
                ?? $contacts[3]
                ?? $defaultContact,
        ];

        // Format all contacts
        $result = [];
        foreach ($mappings as $type => $contact) {
            $result[$type] = $this->formatContact($contact);
        }

        return $result;
    }

    /**
     * Format a Contact object to Name.com API format
     *
     * @param Contact $contact Contact object
     * @return array Formatted contact data
     */
    private function formatContact(Contact $contact): array
    {
        $data = $contact->toArray();

        return [
            'firstName' => $data['firstname'] ?? '',
            'lastName' => $data['lastname'] ?? '',
            'companyName' => $data['org'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address1' => $data['address1'] ?? '',
            'address2' => $data['address2'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'zip' => $data['postalcode'] ?? '',
            'country' => $data['country'] ?? '',
        ];
    }
}
