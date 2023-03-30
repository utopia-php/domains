<?php

namespace Utopia\Domains\Registrar;

use Exception;
use Utopia\Domains\Contact;

class OpenSRS extends Adapter
{
    protected array $defaultNameservers;

    protected array $user;

    /**
     * __construct
     * Instantiate a new adapter.
     *
     * @param  string  $apiKey
     * @param  string  $apiSecret
     * @param  string  $username
     * @param  string  $password
     * @param  array  $defaultNameservers
     * @param  bool  $production
     * @return void
     */
    public function __construct(string $apiKey, string $apiSecret, string $username, string $password, array $defaultNameservers, bool $production = false)
    {
        $this->endpoint =
          $production === false
          ? 'https://horizon.opensrs.net:55443'
          : 'https://rr-n1-tor.opensrs.net:55443';

        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->defaultNameservers = $defaultNameservers;

        $this->user = [
            'username' => $username,
            'password' => $password,
        ];

        $this->headers = [
            'Content-Type:text/xml',
            'X-Username:'.$this->apiSecret,
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

    public function suggest(array|string $query, array $tlds = [], $minLength = 1, $maxLength = 100): array
    {
        $query = is_array($query) ? $query : [$query];

        $message = [
            'object' => 'DOMAIN',
            'action' => 'name_suggest',
            'attributes' => [
                'services' => [
                    'suggestion',
                ],
                'searchstring' => implode(' ', $query),
                'tlds' => $tlds,
            ],
        ];

        $xpath = implode('/', [
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

        $result = $this->send($message);
        $result = $this->sanitizeResponse($result);
        $elements = $result->xpath($xpath);

        $items = [];

        foreach ($elements as $element) {
            $item = $element->xpath('dt_assoc/item');
            $domain = (string) $item[0];
            $available = (string) $item[1] === 'available';

            $items[$domain] = $available;
        }

        return $items;
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
            'attributes' => [
                'domain' => $domain,
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
                'domain' => $domain,
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

    private function buildEnvelop(string $object, string $action, array $attributes, string $domain = null): string
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
