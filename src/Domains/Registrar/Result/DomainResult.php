<?php

namespace Utopia\Domains\Registrar\Result;

use DateTime;

final readonly class DomainResult
{
    public function __construct(
        public string $domain,
        public ?DateTime $registryCreateDate = null,
        public ?DateTime $registryExpireDate = null,
        public ?bool $autoRenew = null,
        public ?bool $letExpire = null,
        public ?array $nameserverList = null,
        public ?array $additionalData = null,
    ) {
    }
}
