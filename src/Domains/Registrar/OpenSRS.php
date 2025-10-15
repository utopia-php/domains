<?php

namespace Utopia\Domains\Registrar;

use Exception;
use Utopia\Domains\Contact;
use Utopia\Domains\Exception as DomainsException;
use Utopia\Domains\Registrar\Exception\DomainTaken;
use Utopia\Domains\Registrar\Exception\PriceNotFound;

class OpenSRS extends Adapter
{
    protected array $defaultNameservers;

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
     * @param  bool  $production
     * @return void
     */
    public function __construct(string $apiKey, string $username, string $password, array $defaultNameservers, bool $production = false)
    {
        $this->endpoint =
          $production === false
          ? 'https://horizon.opensrs.net:55443'
          : 'https://rr-n1-tor.opensrs.net:55443';

        $this->apiKey = $apiKey;
        $this->defaultNameservers = $defaultNameservers;

        $this->user = [
            'username' => $username,
            'password' => $password,
        ];

        $this->headers = [
            'Content-Type:text/xml',
            'X-Username: ' . $username,
        ];
    }

    public function send(array $params = []): array|string
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

        return (string) $elements[0] === '210';
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

    public function updateNameservers(string $domain, array $nameservers): array
    {
        $message = [
            'object' => 'DOMAIN',
            'action' => 'advanced_update_nameservers',
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

    private function register(string $domain, string $regType, array $user, array $contacts, array $nameservers = []): string
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

            $regType = 'new';

            $result = $this->register($domain, $regType, $this->user, $contacts, $nameservers);

            $result = $this->response($result);

            return $result;
        } catch (Exception $e) {
            $message = 'Failed to purchase domain: ' . $e->getMessage();

            if (stripos($e->getMessage(), 'Domain taken') !== false) {
                throw new DomainTaken($message, $e->getCode(), $e);
            }

            throw new DomainsException($message, $e->getCode(), $e);
        }
    }

    public function transfer(string $domain, array|Contact $contacts, array $nameservers = []): array
    {
        $contacts = is_array($contacts) ? $contacts : [$contacts];

        $nameservers =
          empty($nameservers)
          ? $this->defaultNameservers
          : $nameservers;

        $contacts = $this->sanitizeContacts($contacts);

        $regType = 'transfer';

        $result = $this->register($domain, $regType, $this->user, $contacts, $nameservers);
        $result = $this->response($result);

        return $result;
    }

    public function cancelPurchase(): bool
    {
        $timestamp = date('Y-m-d\TH:i:s.000');
        $timestamp = strtotime($timestamp);

        $message = [
            'object' => 'ORDER',
            'action' => 'cancel_pending_orders',
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
        if ($priceMin !== null && $priceMax !== null && $priceMin >= $priceMax) {
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
            'action' => 'name_suggest',
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
     * @return array Contains 'price' (float), 'is_registry_premium' (bool), and 'registry_premium_group' (string|null)
     * @throws DomainsException When the domain does not exist or pricing cannot be fetched
     */
    public function getPrice(string $domain, int $period = 1, string $regType = 'new'): array
    {
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

            return [
                'price' => $price,
                'is_registry_premium' => $isRegistryPremium,
                'registry_premium_group' => $registryPremiumGroup,
            ];
        } catch (Exception $e) {
            $message = 'Failed to get price for domain: ' . $e->getMessage();

            if (stripos($e->getMessage(), 'not supported') !== false ||
                stripos($e->getMessage(), 'price') !== false ||
                stripos($e->getMessage(), 'not available') !== false) {
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
            'object' => 'domain',
            'action' => 'get',
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

    public function updateDomain(string $domain, array $contacts, array $details): bool
    {
        $contacts = $this->sanitizeContacts($contacts);

        $message = [
            'object' => 'domain',
            'action' => 'modify',
            'attributes' => [
                'domain' => $domain,
                'affect_domains' => 0,
                'data' => $details['data'],
                'contact_set' => $contacts,
            ],
        ];

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

    public function renew(string $domain, int $years): array
    {
        $message = [
            'object' => 'domain',
            'action' => 'renew',
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
