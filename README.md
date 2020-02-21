# Utopia Domains

** Work in progress!**

[![Build Status](https://travis-ci.org/utopia-php/domains.svg?branch=master)](https://travis-ci.org/utopia-php/domains)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/domains.svg)
[![Chat With Us](https://img.shields.io/gitter/room/utopia-php/community.svg)](https://gitter.im/utopia-php/community?utm_source=share-link&utm_medium=link&utm_campaign=share-link)

Utopia Domains library is simple and lite library for parsing domain name structure. This library is aiming to be as simple and easy to learn and use.

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project it is **dependency free** and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/domains
```

```php
<?php

require_once '../vendor/autoload.php';

use Utopia\Domains\Domain;

$domain = new Domain('demo.example.co.uk');

$domain->get(); // demo.example.co.uk
$domain->getTLD(); // .uk
$domain->getSuffix(); // co.uk
$domain->getName(); // example
$domain->getSub(); // demo
$domain->isKnown(); // true
$domain->isICANN(); // true
$domain->isPrivate(); // false
$domain->isTest(); // false

```

## System Requirements

Utopia Framework requires PHP 7.1 or later. We recommend using the latest PHP version whenever possible.

## Authors

**Eldad Fux**

+ [https://twitter.com/eldadfux](https://twitter.com/eldadfux)
+ [https://github.com/eldadfux](https://github.com/eldadfux)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)