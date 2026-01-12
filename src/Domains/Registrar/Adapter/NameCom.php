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
use Utopia\Domains\Cache;
use Utopia\Domains\Registrar\Adapter;
use Utopia\Domains\Registrar\Registration;
use Utopia\Domains\Registrar\Renewal;
use Utopia\Domains\Registrar\TransferStatus;
use Utopia\Domains\Registrar\Domain;
use Utopia\Domains\Registrar\TransferStatusEnum;
use Utopia\Domains\Registrar;

class NameCom extends Adapter
{
    /**
     * Name.com API Error Messages
     */
    public const ERROR_MESSAGE_DOMAIN_TAKEN = 'Domain is not available';
    public const ERROR_MESSAGE_INVALID_CONTACT = 'invalid value for $country when calling';
    public const ERROR_MESSAGE_DOMAIN_NOT_TRANSFERABLE = 'we were unable to get authoritative domain information from the registry. this usually means that the domain name or auth code provided was not correct.';
    public const ERROR_MESSAGE_PRICE_NOT_FOUND = 'none of the submitted domains are valid';

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
            return $result['order'];

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
            return $result['order'];

        } catch (AuthException $e) {
            throw $e;

        } catch (Exception $e) {
            $message = 'Failed to transfer domain: ' . $e->getMessage();
            $code = $e->getCode();
            $errorLower = strtolower($e->getMessage());

            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_DOMAIN_NOT_TRANSFERABLE))) {
                throw new DomainNotTransferableException($message, $code, $e);
            }
            if (str_contains($errorLower, strtolower(self::ERROR_MESSAGE_INVALID_CONTACT))) {
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
            $cacheKey = $domain . '_' . $regType . '_' . $periodYears;
            $cached = $this->cache->load($cacheKey, $ttl);
            if ($cached !== null && is_array($cached)) {
                return $cached['price'];
            }
        }

        try {
            // Use checkAvailability to get price information
            $result = $this->send('POST', '/core/v1/domains:checkAvailability', [
                'domainNames' => [$domain],
            ]);

            if (isset($result['results']) && is_array($result['results']) && count($result['results']) > 0) {
                $domainResult = $result['results'][0];
                $price = isset($domainResult['purchasePrice']) ? (float) $domainResult['purchasePrice'] : null;

                if ($price === null) {
                    throw new PriceNotFoundException('Price not found for domain: ' . $domain, 400);
                }

                if ($this->cache) {
                    $cacheKey = $domain . '_' . $regType . '_' . $periodYears;
                    $this->cache->save($cacheKey, ['price' => $price]);
                }

                return $price;
            }

            throw new PriceNotFoundException('Price not found for domain: ' . $domain, 400);
        } catch (PriceNotFoundException $e) {
            throw $e;
        } catch (AuthException $e) {
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
     * @param string $domain The domain name to update
     * @param array $details The details to update
     * @param array|Contact|null $contacts The contacts to update
     * @return bool True if successful
     */
    public function updateDomain(string $domain, array $details, array|Contact|null $contacts = null): bool
    {
        try {
            // Name.com allows combining multiple updates in a single PATCH request
            $data = [];

            // Add contacts if provided
            if ($contacts !== null) {
                $contacts = is_array($contacts) ? $contacts : [$contacts];
                $contactData = $this->sanitizeContacts($contacts);
                $data['contacts'] = $contactData;
            }

            // Add autorenew if provided
            if (isset($details['autorenew'])) {
                $data['autorenewEnabled'] = (bool) $details['autorenew'];
            }

            // Only send request if there's something to update
            if (!empty($data)) {
                $this->send('PATCH', '/core/v1/domains/' . $domain, $data);
            }

            return true;
        } catch (Exception $e) {
            throw new DomainsException('Failed to update domain: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Renew a domain
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
     * @param bool $checkStatus Flag to check status
     * @param bool $getRequestAddress Flag to get request address
     * @return TransferStatus Transfer status information
     */
    public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): TransferStatus
    {
        try {
            // List all transfers and find the one for this domain
            $result = $this->send('GET', '/core/v1/transfers');

            if (isset($result['transfers']) && is_array($result['transfers'])) {
                foreach ($result['transfers'] as $transfer) {
                    if (isset($transfer['domainName']) && $transfer['domainName'] === $domain) {
                        $status = $this->mapTransferStatus($transfer['status'] ?? 'unknown');
                        $reason = null;

                        if ($status === TransferStatusEnum::NotTransferrable) {
                            $reason = $transfer['statusDetails'] ?? 'Domain is not transferable';
                        }

                        return new TransferStatus(
                            status: $status,
                            reason: $reason,
                            timestamp: isset($transfer['created']) ? new DateTime($transfer['created']) : null,
                        );
                    }
                }
            }

            // If no transfer found, domain is transferable (or no transfer initiated)
            return new TransferStatus(
                status: TransferStatusEnum::Transferrable,
                reason: null,
                timestamp: null,
            );
        } catch (Exception $e) {
            throw new DomainsException('Failed to check transfer status: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Map Name.com transfer status to TransferStatusEnum
     *
     * @param string $status Name.com status string
     * @return TransferStatusEnum
     */
    private function mapTransferStatus(string $status): TransferStatusEnum
    {
        return match (strtolower($status)) {
            'pending' => TransferStatusEnum::PendingRegistry,
            'approved', 'complete', 'completed' => TransferStatusEnum::Completed,
            'cancelled', 'rejected' => TransferStatusEnum::Cancelled,
            'pending_owner' => TransferStatusEnum::PendingOwner,
            'pending_admin' => TransferStatusEnum::PendingAdmin,
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

        $result = [];

        // Name.com expects specific contact types
        $types = ['registrant', 'admin', 'tech', 'billing'];

        if (count($contacts) === 1) {
            // Use the same contact for all types
            $contact = $contacts[0];
            foreach ($types as $type) {
                $result[$type] = $this->formatContact($contact);
            }
        } elseif (array_keys($contacts) === range(0, count($contacts) - 1)) {
            // Numerically-indexed array: map by position to types
            // 0→registrant, 1→admin, 2→tech, 3→billing
            $firstContact = $contacts[0];
            foreach ($types as $index => $type) {
                // Use contact at position if exists, otherwise fall back to first contact
                $contact = $contacts[$index] ?? $firstContact;
                $result[$type] = $this->formatContact($contact);
            }
        } else {
            // Associative array: map provided contacts to Name.com types
            foreach ($contacts as $key => $contact) {
                if (in_array($key, $types)) {
                    $result[$key] = $this->formatContact($contact);
                } elseif ($key === 'owner') {
                    $result['registrant'] = $this->formatContact($contact);
                }
            }
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
