# Utopia Domains

[![Build Status](https://travis-ci.org/utopia-php/domains.svg?branch=master)](https://travis-ci.com/utopia-php/domains)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/domains.svg)
[![Discord](https://img.shields.io/discord/564160730845151244)](https://appwrite.io/discord)

Utopia Domains library is a simple and lite library for parsing domain names structure. This library is aiming to be as simple and easy to learn and use.  This library is maintained by the [Appwrite team](https://appwrite.io).

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project, it is completely **dependency-free** and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/domains
```

```php
<?php

require_once '../vendor/autoload.php';

use Utopia\Domains\Domain;

// demo.example.co.uk

$domain = new Domain('demo.example.co.uk');

$domain->get(); // demo.example.co.uk
$domain->getTLD(); // uk
$domain->getSuffix(); // co.uk
$domain->getRegisterable(); // example.co.uk
$domain->getName(); // example
$domain->getSub(); // demo
$domain->isKnown(); // true
$domain->isICANN(); // true
$domain->isPrivate(); // false
$domain->isTest(); // false

// demo.localhost

$domain = new Domain('demo.localhost');

$domain->get(); // demo.localhost
$domain->getTLD(); // localhost
$domain->getSuffix(); // ''
$domain->getRegisterable(); // ''
$domain->getName(); // demo
$domain->getSub(); // ''
$domain->isKnown(); // false
$domain->isICANN(); // false
$domain->isPrivate(); // false
$domain->isTest(); // true

```

Utopia Domains parser uses a public suffix PHP dataset auto-generated from the [publicsuffix.org](https://publicsuffix.org/). The dataset get periodically updates from us, but you can also manually update it by cloning this library and running the import script with the import command:

```bash
php ./data/import.php
```

## Library API

* **get()** - Return you full domain name.
* **getTLD()** - Return only the top-level-domain.
* **getSuffix()** - Return only the public suffix of your domain, for example: co.uk, ac.be, org.il, com, org.
* **getRegisterable()** - Return the registered or registrable domain, which is the public suffix plus one additional label.
* **getName()** - Returns only the registerable domain name. For example, blog.example.com will return 'example', and demo.co.uk will return 'demo'.
* **getSub()** - Returns the full sub domain path for you domain. For example, blog.example.com will return 'blog', and subdomain.demo.co.uk will return 'subdomain.demo'.
* **isKnown()** - Returns true if public suffix is know and false otherwise.
* **isICANN()** - Returns true if the public suffix is found in the ICANN DOMAINS section of the public suffix list.
* **isPrivate()** - Returns true if the public suffix is found in the PRIVATE DOMAINS section of the public suffix list.
* **isTest()** - Returns true if the domain TLD is 'locahost' or 'test' and false otherwise.

> If you want to parse ordinary web urls then use `$host = parse_url($return, PHP_URL_HOST); $domain = new Utopia\Domains\Domain($host);` to get the domain object. 


## Using the Registrar API
```php
<?php

use Utopia\Domains\Registrar;
use Utopia\Domains\Contact;
use Utopia\Domains\Registrar\OpenSRS;

$opensrs = new OpenSRS(
  'apikey', 
  'apisecret', 
  'username', 
  'password', 
  [
    'ns1.nameserver.com',
    'ns2.nameserver.com',
  ]
);


$reg = new Registrar($opensrs);

$contact = new Contact(
  'firstname',
  'lastname',
  'phone',
  'email',
  'address1',
  'address2',
  'address3',
  'city',
  'state',
  'country',
  'postalcode',
  'org',
  'owner',
);

$domain = 'yourname.com';

$available = $reg->available($domain);
$purchase = $reg->purchase($domain, 1, $contact); 
$suggest = $reg->suggest(['yourname', 'yourname1.com'], ['com', 'net', 'org'], 10, 10000, 100);
$domainDetails = $reg->getDomain($domain);
$renew = $reg->renew($domain, 1);
$transfer = $reg->transfer($domain, 'authcode', [$contact]);

```

## Library Registrar API
* **available(string $domain): bool** - Checks to see if a domain is available for registration.
* **purchase(string $domain, int $period, array $contacts, array $nameservers = []): array** - Purchase a domain name.
* **suggest(array $query, array $tlds = [], int|null $limit = null, int|null $priceMax = null, int|null $priceMin = null): array** - Suggest or search for domain names.
* **getDomain(string $domain): array** - Get domain details.
* **renew(string $domain, int $period): array** - Renew a domain name.
* **transfer(string $domain, string $authCode, array $contacts, array $nameservers = []): array** - Transfer a domain name.


## System Requirements

Utopia Framework requires PHP 8.0 or later. We recommend using the latest PHP version whenever possible.

## Authors

**Eldad Fux**

+ [https://twitter.com/eldadfux](https://twitter.com/eldadfux)
+ [https://github.com/eldadfux](https://github.com/eldadfux)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
