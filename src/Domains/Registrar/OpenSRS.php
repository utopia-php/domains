<?php

namespace Utopia\Domains\Registrar;

use Exception;
use Utopia\Domains\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\DomainNotTransferable;
use Utopia\Domains\Registrar\Exception\InvalidContact;
use Utopia\Domains\Registrar\Exception\PriceNotFound;
use Utopia\Domains\Cache;

class OpenSRS extends Adapter
{
    /**
     * OpenSRS API Response Codes - https://domains.opensrs.guide/docs/codes
     */
    public const RESPONSE_CODE_DOMAIN_AVAILABLE = 210;
    public const RESPONSE_CODE_DOMAIN_PRICE_NOT_FOUND = 400;
    public const RESPONSE_CODE_INVALID_CONTACT = 465;
    public const RESPONSE_CODE_DOMAIN_TAKEN = 485;
    public const RESPONSE_CODE_DOMAIN_NOT_TRANSFERABLE = 487;

    protected array $user;

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'opensrs';
    }

    /**
     * __construct
     * Instantiate a new adapter.
     *
     * @param  string  $apiKey
     * @param  string  $username
     * @param  string  $password
     * @param  array  $defaultNameservers
     * @param  string  $endpoint - The endpoint to use for the API (use rr-n1-tor.opensrs.net:55443 for production)
     * @return void
     */
    public function __construct(
        protected string $apiKey,
        string $username,
        string $password,
        protected array $defaultNameservers = [],
        protected string $endpoint = 'https://horizon.opensrs.net:55443',
        protected ?Cache $cache = null
    ) {
        if (str_starts_with($endpoint, 'http://')) {
            $this->endpoint = 'https://' . substr($endpoint, 7);
        } elseif (!str_starts_with($endpoint, 'https://')) {
            $this->endpoint = 'https://' . $endpoint;
        }

        $this->user = [
            'username' => $username,
            'password' => $password,
        ];

        $this->headers = [
            'Content-Type:text/xml',
            'X-Username: ' . $username,
        ];
    }

    /**
     * Check if a domain is available
     *
     * @param string $domain The domain name to check
     * @return bool True if the domain is available, false otherwise
     */
    public function available(string $domain): bool
    {
        $result = $this->send([
            'object' => 'DOMAIN',
            'action' => 'LOOKUP',
            'attributes' => [
                'domain' => $domain,
            ],
        ]);


        $result = $this->sanitizeResponse($result);
        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_code"]');

        return (int) $elements[0] === self::RESPONSE_CODE_DOMAIN_AVAILABLE;
    }

    public function updateNameservers(string $domain, array $nameservers): array
    {
        $message = [
            'object' => 'DOMAIN',
            'action' => 'ADVANCED_UPDATE_NAMESERVERS',
            'domain' => $domain,
            'attributes' => [
                'add_ns' => $nameservers,
                'op_type' => 'add_remove',
            ],
        ];

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);

        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="is_success"]');
        $successful = "{$elements[0]}" === '1' ? true : false;

        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_text"]');
        $text = "{$elements[0]}";

        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_code"]');
        $code = "{$elements[0]}";

        return [
            'code' => $code,
            'text' => $text,
            'successful' => $successful,
        ];
    }

    private function register(string $domain, string $regType, array $user, array $contacts, array $nameservers = [], ?string $authCode = null): string
    {
        $hasNameservers = empty($nameservers) ? 0 : 1;

        $message = [
            'object' => 'DOMAIN',
            'action' => 'SW_REGISTER',
            'attributes' => [
                'domain' => $domain,
                'period' => 1,
                'contact_set' => $contacts,
                'custom_tech_contact' => 0,
                'custom_nameservers' => $hasNameservers,
                'reg_username' => $user['username'],
                'reg_password' => $user['password'],
                'reg_type' => $regType,
                'handle' => 'process',
                'f_whois_privacy' => 1,
                'auto_renew' => 0,
            ],
        ];

        if ($authCode) {
            $message['attributes']['auth_info'] = $authCode;
        }

        if ($hasNameservers) {
            $message['attributes']['nameserver_list'] = $nameservers;
        }

        $result = $this->send($message);

        return $result;
    }

    public function purchase(string $domain, array|Contact $contacts, array $nameservers = []): array
    {
        try {
            $contacts = is_array($contacts) ? $contacts : [$contacts];

            $nameservers =
            empty($nameservers)
            ? $this->defaultNameservers
            : $nameservers;

            $contacts = $this->sanitizeContacts($contacts);

            $regType = self::REG_TYPE_NEW;

            $result = $this->register($domain, $regType, $this->user, $contacts, $nameservers);

            $result = $this->response($result);

            return $result;
        } catch (Exception $e) {
            $message = 'Failed to purchase domain: ' . $e->getMessage();

            if ($e->getCode() === self::RESPONSE_CODE_DOMAIN_TAKEN) {
                throw new DomainTaken($message, $e->getCode(), $e);
            }
            if ($e->getCode() === self::RESPONSE_CODE_INVALID_CONTACT) {
                throw new InvalidContact($message, $e->getCode(), $e);
            }
            throw new DomainsException($message, $e->getCode(), $e);
        }
    }

    public function transfer(string $domain, string $authCode, array|Contact $contacts, array $nameservers = []): array
    {
        $contacts = is_array($contacts) ? $contacts : [$contacts];

        $nameservers =
          empty($nameservers)
          ? $this->defaultNameservers
          : $nameservers;

        $contacts = $this->sanitizeContacts($contacts);

        $regType = self::REG_TYPE_TRANSFER;

        try {
            $result = $this->register($domain, $regType, $this->user, $contacts, $nameservers, $authCode);
            $result = $this->response($result);

            return $result;
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code === self::RESPONSE_CODE_DOMAIN_NOT_TRANSFERABLE) {
                throw new DomainNotTransferable('Domain is not transferable', $e->getCode(), $e);
            }
            if ($code === self::RESPONSE_CODE_INVALID_CONTACT) {
                throw new InvalidContact('Failed to transfer domain: ' . $e->getMessage(), $code, $e);
            }
            if ($code === self::RESPONSE_CODE_DOMAIN_TAKEN) {
                throw new DomainTaken('Domain is already in this account', $code, $e);
            }
            throw new DomainsException('Failed to transfer domain: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function cancelPurchase(): bool
    {
        $timestamp = date('Y-m-d\TH:i:s.000');
        $timestamp = strtotime($timestamp);

        $message = [
            'object' => 'ORDER',
            'action' => 'CANCEL_PENDING_ORDERS',
            'attributes' => [
                'to_date' => $timestamp,
                'status' => [
                    'declined',
                    'pending',
                ],
            ],
        ];

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);

        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="is_success"]');
        $successful = "{$elements[0]}" === '1' ? true : false;

        return $successful;
    }

    /**
     * Suggest domain names based on search query
     *
     * @param array|string $query Search terms to generate suggestions from
     * @param array $tlds Top-level domains to search within (e.g., ['com', 'net', 'org'])
     * @param int|null $limit Maximum number of results to return
     * @param string|null $filterType Filter results by type: 'premium', 'suggestion', or null for both
     * @param int|null $priceMax Maximum price for premium domains
     * @param int|null $priceMin Minimum price for premium domains
     * @return array Domains with metadata: `available` (bool), `price` (float|null), `type` (string)
     */
    public function suggest(array|string $query, array $tlds = [], int|null $limit = null, string|null $filterType = null, int|null $priceMax = null, int|null $priceMin = null): array
    {
        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            throw new Exception("Invalid price range: priceMin ($priceMin) must be less than priceMax ($priceMax).");
        }

        if ($filterType !== null && !in_array($filterType, ['premium', 'suggestion'])) {
            throw new Exception("Invalid filter type: filterType ($filterType) must be 'premium' or 'suggestion'.");
        }

        if ($filterType !== null && $filterType === 'suggestion' && ($priceMin !== null || $priceMax !== null)) {
            throw new Exception("Invalid price range: priceMin ($priceMin) and priceMax ($priceMax) cannot be set when filterType is 'suggestion'.");
        }

        $query = is_array($query) ? $query : [$query];
        $message = [
            'object' => 'DOMAIN',
            'action' => 'NAME_SUGGEST',
            'attributes' => [
                'services' => ['suggestion', 'premium', 'lookup'],
                'searchstring' => implode(' ', $query),
                'skip_registry_lookup' => 1,
            ],
        ];
        $tlds = !empty($tlds) ? array_map(fn ($tld) => '.' . ltrim($tld, '.'), $tlds) : [];

        if (!empty($tlds)) {
            $message['attributes']['tlds'] = $tlds;
            if ($filterType === 'premium' || $filterType === null) {
                $message['attributes']['service_override']['premium']['tlds'] = $tlds;
            }
            if ($filterType === 'suggestion' || $filterType === null) {
                $message['attributes']['service_override']['suggestion']['tlds'] = $tlds;
            }
            $message['attributes']['service_override']['lookup']['tlds'] = $tlds;
        }
        if ($limit) {
            if ($filterType === 'premium' || $filterType === null) {
                $message['attributes']['service_override']['premium']['maximum'] = $limit;
            }
            if ($filterType === 'suggestion' || $filterType === null) {
                $message['attributes']['service_override']['suggestion']['maximum'] = $limit;
            }
        }
        if ($priceMin !== null) {
            $message['attributes']['service_override']['premium']['price_min'] = $priceMin;
        }
        if ($priceMax !== null) {
            $message['attributes']['service_override']['premium']['price_max'] = $priceMax;
        }

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);

        $items = [];

        // Process suggestion domains
        if ($filterType === 'suggestion' || $filterType === null) {
            $suggestionXpath = implode('/', [
                '//body',
                'data_block',
                'dt_assoc',
                'item[@key="attributes"]',
                'dt_assoc',
                'item[@key="suggestion"]',
                'dt_assoc',
                'item[@key="items"]',
                'dt_array',
                'item',
            ]);
            $suggestionElements = $result->xpath($suggestionXpath);

            $processedCount = 0;
            $suggestionLimit = $limit;

            foreach ($suggestionElements as $element) {
                if ($suggestionLimit !== null && $processedCount >= $suggestionLimit) {
                    break;
                }

                $domainNode = $element->xpath('dt_assoc/item[@key="domain"]');
                $statusNode = $element->xpath('dt_assoc/item[@key="status"] | dt_assoc/item[@key="availability"]');
                $domain = isset($domainNode[0]) ? (string) $domainNode[0] : null;
                $status = isset($statusNode[0]) ? strtolower((string) $statusNode[0]) : '';
                $available = in_array($status, ['available', 'true', '1'], true);

                if ($domain) {
                    $items[$domain] = [
                        'available' => $available,
                        'price' => null,
                        'type' => 'suggestion'
                    ];

                    $processedCount++;
                }
            }

            if ($filterType === 'suggestion') {
                return $items;
            }

            if ($limit && count($items) >= $limit) {
                return array_slice($items, 0, $limit, true);
            }
        }

        // Process premium domains
        if (
            ($filterType === 'premium' || $filterType === null) &&
            !($limit && count($items) >= $limit)
        ) {
            $premiumXpath = implode('/', [
                '//body',
                'data_block',
                'dt_assoc',
                'item[@key="attributes"]',
                'dt_assoc',
                'item[@key="premium"]',
                'dt_assoc',
                'item[@key="items"]',
                'dt_array',
                'item',
            ]);
            $premiumElements = $result->xpath($premiumXpath);

            $remainingLimit = $limit ? ($limit - count($items)) : null;
            $processedCount = 0;

            foreach ($premiumElements as $element) {
                if ($remainingLimit !== null && $processedCount >= $remainingLimit) {
                    break;
                }

                $item = $element->xpath('dt_assoc/item');

                $domain = null;
                $available = false;
                $price = null;

                foreach ($item as $field) {
                    $key = (string) $field['key'];
                    $value = (string) $field;

                    switch ($key) {
                        case 'domain':
                            $domain = $value;
                            break;
                        case 'status':
                            $available = $value === 'available';
                            break;
                        case 'price':
                            $price = is_numeric($value) ? floatval($value) : null;
                            break;
                    }
                }

                if ($domain) {
                    $items[$domain] = [
                        'available' => $available,
                        'price' => $price,
                        'type' => 'premium'
                    ];

                    $processedCount++;
                }
            }
        }

        return $items;
    }

    /**
     * Get the registration price for a domain
     *
     * @param string $domain The domain name to get pricing for
     * @param int $period Registration period in years (default 1)
     * @param string $regType Type of registration: 'new', 'renewal', 'transfer', or 'trade'
     * @param int $ttl Time to live for the cache (if set) in seconds (default 3600 seconds = 1 hour)
     * @return array Contains 'price' (float), 'is_registry_premium' (bool), and 'registry_premium_group' (string|null)
     * @throws PriceNotFound When pricing information is not found or unavailable for the domain
     * @throws DomainsException When other errors occur during price retrieval
     */
    public function getPrice(string $domain, int $period = 1, string $regType = self::REG_TYPE_NEW, int $ttl = 3600): array
    {
        if ($this->cache) {
            $cached = $this->cache->load($domain, $ttl);
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }
        }

        try {
            $message = [
                'object' => 'DOMAIN',
                'action' => 'GET_PRICE',
                'attributes' => [
                    'domain' => $domain,
                    'period' => $period,
                    'reg_type' => $regType,
                ],
            ];

            $result = $this->send($message);
            $result = $this->sanitizeResponse($result);

            $priceXpath = '//body/data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="price"]';
            $priceElements = $result->xpath($priceXpath);
            $price = isset($priceElements[0]) ? floatval((string) $priceElements[0]) : null;

            $isPremiumXpath = '//body/data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="is_registry_premium"]';
            $isPremiumElements = $result->xpath($isPremiumXpath);
            $isRegistryPremium = isset($isPremiumElements[0]) ? ((string) $isPremiumElements[0] === '1') : false;

            $premiumGroupXpath = '//body/data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="registry_premium_group"]';
            $premiumGroupElements = $result->xpath($premiumGroupXpath);
            $registryPremiumGroup = isset($premiumGroupElements[0]) ? (string) $premiumGroupElements[0] : null;

            $result = [
                'price' => $price,
                'is_registry_premium' => $isRegistryPremium,
                'registry_premium_group' => $registryPremiumGroup,
            ];

            if ($this->cache) {
                $this->cache->save($domain, $result);
            }

            return $result;
        } catch (Exception $e) {
            $message = 'Failed to get price for domain: ' . $e->getMessage();

            if ($e->getCode() === self::RESPONSE_CODE_DOMAIN_PRICE_NOT_FOUND) {
                throw new PriceNotFound($message, $e->getCode(), $e);
            }
            throw new DomainsException($message, $e->getCode(), $e);
        }
    }

    public function tlds(): array
    {
        // OpenSRS offers no endpoint for this
        return [];
    }

    public function getDomain(string $domain): array
    {
        $message = [
            'object' => 'DOMAIN',
            'action' => 'GET',
            'domain' => $domain,
            'attributes' => [
                'type' => 'all_info',
                'clean_ca_subset' => 1,
            ],
        ];

        $xpath = implode('/', [
            '//body',
            'data_block',
            'dt_assoc',
            'item[@key="attributes"]',
            'dt_assoc',
            'item',
        ]);

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);
        $elements = $result->xpath($xpath);

        $results = [];

        foreach ($elements as $element) {
            $key = "{$element['key']}";
            $value = "{$element}";

            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Update the domain information
     *
     * Example request 1:
     * <code>
     * $reg->updateDomain('example.com', [
     *     'data' => 'contact_info',
     * ], [
     *     new Contact('John Doe', 'john.doe@example.com', '+1234567890'),
     * ]);
     * </code>
     *
     * Example request 2:
     * <code>
     * $reg->updateDomain('example.com', [
     *     'data' => 'ca_whois_display_setting',
     *     'display' => 'FULL',
     * ]);
     * </code>
     *
     * @param string $domain The domain name to update
     * @param array $details The details to update the domain with
     * @param array|Contact|null $contacts The contacts to update the domain with (optional)
     * @return bool True if the domain was updated successfully, false otherwise
     */
    public function updateDomain(string $domain, array $details, array|Contact|null $contacts = null): bool
    {
        $message = [
            'object' => 'DOMAIN',
            'action' => 'MODIFY',
            'domain' => $domain,
            'attributes' => $details,
        ];

        if ($contacts) {
            if ($details['data'] !== 'contact_info') {
                throw new Exception("Invalid data: data must be 'contact_info' in order to update contacts");
            }
            $contacts = is_array($contacts) ? $contacts : [$contacts];
            $contacts = $this->sanitizeContacts($contacts);
            $message['attributes']['contact_set'] = $contacts;
        }

        $xpath = implode('/', [
            '//body',
            'data_block',
            'dt_assoc',
            'item[@key="attributes"]',
            'dt_assoc',
            'item[@key="details"]',
            'dt_assoc',
            'item[@key="'.$domain.'"]',
            'dt_assoc',
            'item[@key="is_success"]',
        ]);

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);
        $elements = $result->xpath($xpath);

        return (string) $elements[0] === '1';
    }

    /**
     * Renew a domain
     *
     * @param string $domain The domain name to renew
     * @param int $years The number of years to renew the domain for
     * @return array Contains the renewal information
     */
    public function renew(string $domain, int $years): array
    {
        $message = [
            'object' => 'DOMAIN',
            'action' => 'RENEW',
            'attributes' => [
                'domain' => $domain,
                'auto_renew' => 0,
                'currentexpirationyear' => '2022',
                'period' => $years,
                'handle' => 'process',
            ],
        ];

        $xpath = implode('/', [
            '//body',
            'data_block',
            'dt_assoc',
            'item[@key="attributes"]',
            'dt_assoc',
            'item',
        ]);

        $result = $this->send($message);
        $result = simplexml_load_string($result);
        $elements = $result->xpath($xpath);

        $results = [];

        foreach ($elements as $item) {
            $key = "{$item['key']}";

            if ($key === 'registration expiration date') {
                $result['new_expiration'] = "{$item}";

                continue;
            }

            $value = "{$item}";

            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Get the authorization code for an EPP domain
     *
     * @param string $domain The EPP domain name for which to retrieve the auth code
     * @return string The authorization code
     * @throws DomainsException When the domain does not use EPP protocol or other errors occur
     */
    public function getAuthCode(string $domain): string
    {
        try {
            $message = [
                'object' => 'DOMAIN',
                'action' => 'GET',
                'domain' => $domain,
                'attributes' => [
                    'type' => 'domain_auth_info'
                ],
            ];

            $result = $this->send($message);
            $result = $this->sanitizeResponse($result);

            $xpath = '//body/data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="domain_auth_info"]';
            $elements = $result->xpath($xpath);

            if (empty($elements)) {
                throw new DomainsException('Auth code not found in response', 404);
            }

            return (string) $elements[0];
        } catch (Exception $e) {
            throw new DomainsException('Failed to get auth code: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Check transfer status for a domain
     *
     * @param string $domain The fully qualified domain name
     * @param bool $checkStatus Flag to request the status of a transfer request
     * @param bool $getRequestAddress Flag to request the registrant's contact email address
     * @return array Contains transfer status information including 'transferable', 'status', 'reason', etc.
     * @throws DomainsException When errors occur during the check
     */
    public function checkTransferStatus(string $domain, bool $checkStatus = true, bool $getRequestAddress = false): array
    {
        try {
            $message = [
                'object' => 'DOMAIN',
                'action' => 'CHECK_TRANSFER',
                'attributes' => [
                    'domain' => $domain,
                    'check_status' => $checkStatus ? 1 : 0,
                    'get_request_address' => $getRequestAddress ? 1 : 0,
                ],
            ];

            $result = $this->send($message);
            $result = $this->sanitizeResponse($result);

            $xpath = '//body/data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item';
            $elements = $result->xpath($xpath);

            $response = [];

            foreach ($elements as $element) {
                $key = (string) $element['key'];
                $value = (string) $element;

                switch ($key) {
                    case 'transferrable':
                    case 'noservice':
                        $response[$key] = (int) $value;
                        break;
                    case 'unixtime':
                        $response[$key] = (int) $value;
                        break;
                    default:
                        $response[$key] = $value;
                }
            }

            return $response;
        } catch (Exception $e) {
            throw new DomainsException('Failed to check transfer status: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function send(array $params = []): array|string
    {
        $object = $params['object'];
        $action = $params['action'];
        $domain = $params['domain'] ?? null;
        $attributes = $params['attributes'];

        $xml = $this->buildEnvelop($object, $action, $attributes, $domain);

        $headers = array_merge($this->headers, [
            'X-Signature:'.md5(md5($xml.$this->apiKey).$this->apiKey),
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        return curl_exec($ch);
    }

    private function sanitizeResponse(string $response)
    {
        $result = simplexml_load_string($response);
        $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_code"]');
        $code = (int) "{$elements[0]}";

        if ($code > 299) {
            $elements = $result->xpath('//body/data_block/dt_assoc/item[@key="response_text"]');
            $text = "{$elements[0]}";

            throw new Exception($text, $code);
        }

        return $result;
    }

    private function response(string $xml): array
    {
        $doc = $this->sanitizeResponse($xml);
        $elements = $doc->xpath('//data_block/dt_assoc/item[@key="response_code"]');
        $responseCode = "{$elements[0]}";

        $elements = $doc->xpath('//data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="id"]');
        $responseId = count($elements) > 0 ? "{$elements[0]}" : '';

        $elements = $doc->xpath('//data_block/dt_assoc/item[@key="attributes"]/dt_assoc/item[@key="domain_id"]');
        $responseDomainId = count($elements) > 0 ? "{$elements[0]}" : '';

        $elements = $doc->xpath('//data_block/dt_assoc/item[@key="is_success"]');
        $responseSuccessful = "{$elements[0]}" === '1' ? true : false;

        return [
            'code' => $responseCode,
            'id' => $responseId,
            'domainId' => $responseDomainId,
            'successful' => $responseSuccessful,
        ];
    }

    private function createArray(string $key, array $ary): string
    {
        $result = [
            '<item key="'.$key.'">',
            '<dt_array>',
        ];

        foreach ($ary as $key => $value) {
            $result[] = $this->createEnvelopItem($key, $value);
        }

        $result[] = '</dt_array>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function createAssoc(string $key, array $assoc): string
    {
        $result = [
            '<item key="'.$key.'">',
            '<dt_assoc>',
        ];

        foreach ($assoc as $itemKey => $itemValue) {
            if (is_array($itemValue)) {
                if (array_keys($itemValue) === range(0, count($itemValue) - 1)) {
                    $result[] = $this->createArray($itemKey, $itemValue);
                } else {
                    $result[] = $this->createAssoc($itemKey, $itemValue);
                }
            } else {
                $result[] = $this->createEnvelopItem($itemKey, $itemValue);
            }
        }

        $result[] = '</dt_assoc>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function createServiceOverride(array $overrides): string
    {
        $result = [
            '<item key="service_override">',
            '<dt_assoc>',
        ];

        foreach ($overrides as $serviceName => $serviceConfig) {
            $result[] = $this->createAssoc($serviceName, $serviceConfig);
        }

        $result[] = '</dt_assoc>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function createEnvelopItem(string $key, string|int|array $value): string
    {
        if (is_array($value)) {
            return $this->createArray($key, $value);
        }

        return "<item key='{$key}'>{$value}</item>";
    }

    private function validateContact(array $contact): array
    {
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
            'owner',
            'org',
        ];

        $filter = [
            'firstname' => 'first_name',
            'lastname' => 'last_name',
            'org' => 'org_name',
            'postalcode' => 'postal_code',
        ];

        $result = [];

        foreach ($required as $key) {
            $key = strtolower($key);

            if (! isset($contact[$key])) {
                throw new Exception("Contact is missing required field: {$key}");
            }

            $filtered_key = $filter[$key] ?? $key;

            $result[$filtered_key] = $contact[$key];
        }

        return $result;
    }

    private function createContact(string $type, array $contact): string
    {
        $contact = $this->validateContact($contact);
        $result = [
            "<item key='{$type}'>",
            '<dt_assoc>',
        ];

        foreach ($contact as $key => $value) {
            $result[] = $this->createEnvelopItem($key, $value);
        }

        $result[] = '</dt_assoc>';
        $result[] = '</item>';

        $xml = implode(PHP_EOL, $result);

        return $xml;
    }

    private function createContactSet(array $contacts): string
    {
        $result = [
            '<item key="contact_set">',
            '<dt_assoc>',
        ];

        foreach ($contacts as $type => $contact) {
            $result[] = $this->createContact($type, $contact);
        }

        $result[] = '</dt_assoc>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function createNameserver(string $name, int $sortOrder): string
    {
        return implode(PHP_EOL, [
            '<dt_assoc>',
            $this->createEnvelopItem('name', $name),
            $this->createEnvelopItem('sortorder', $sortOrder),
            '</dt_assoc>',
        ]);
    }

    private function createNameserverList(array $nameservers): string
    {
        $result = [
            '<item key="nameserver_list">',
            '<dt_array>',
        ];

        for ($index = 0; $index < count($nameservers); $index++) {
            $result[] = $this->createNameserver($nameservers[$index], $index);
        }

        $result[] = '</dt_array>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function createNamespaceAssign(array $nameservers): string
    {
        $result = [
            '<item key="add_ns">',
            '<dt_array>',
        ];

        for ($index = 0; $index < count($nameservers); $index++) {
            $result[] = $this->createEnvelopItem($index, $nameservers[$index]);
        }

        $result[] = '</dt_array>';
        $result[] = '</item>';

        return implode(PHP_EOL, $result);
    }

    private function buildEnvelop(string $object, string $action, array $attributes, ?string $domain = null): string
    {
        $result = [
            '<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
            "<!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>",
            '<OPS_envelope>',
            '<header>',
            '<version>0.9</version>',
            '</header>',
            '<body>',
            '<data_block>',
            '<dt_assoc>',
            $this->createEnvelopItem('protocol', 'XCP'),
            $this->createEnvelopItem('object', $object),
            $this->createEnvelopItem('action', $action),
            (
                is_null($domain)
                ? ''
                : $this->createEnvelopItem('domain', $domain)
            ),
            '<item key="attributes">',
            '<dt_assoc>',
        ];

        foreach ($attributes as $key => $value) {
            switch($key) {
                case 'contact_set':
                    $result[] = $this->createContactSet($value);
                    break;
                case 'nameserver_list':
                    $result[] = $this->createNameserverList($value);
                    break;
                case 'assign_ns':
                case 'add_ns':
                case 'remove_ns':
                    $result[] = $this->createNamespaceAssign($value);
                    break;
                case 'service_override':
                    $result[] = $this->createServiceOverride($value);
                    break;
                default:
                    $result[] =
                      is_array($value)
                      ? $this->createArray($key, $value)
                      : $this->createEnvelopItem($key, $value);
            }
        }

        $closing = [
            '</dt_assoc>',
            '</item>',
            '</dt_assoc>',
            '</data_block>',
            '</body>',
            '</OPS_envelope>',
        ];

        foreach ($closing as $line) {
            $result[] = $line;
        }

        $xml = implode(PHP_EOL, $result);

        return $xml;
    }

    /**
     * Sanitize the contacts
     *
     * @param Contact[] $contacts Array of Contact objects to sanitize
     * @return array The sanitized contacts
     */
    private function sanitizeContacts(array $contacts): array
    {
        if (count(array_keys($contacts)) == 1) {
            return [
                'owner' => $contacts[0]->toArray(),
                'admin' => $contacts[0]->toArray(),
                'tech' => $contacts[0]->toArray(),
                'billing' => $contacts[0]->toArray(),
            ];
        }

        $result = [];
        foreach ($contacts as $key => $val) {
            $result[$key] = $val->toArray();
        }

        return $result;
    }
}
