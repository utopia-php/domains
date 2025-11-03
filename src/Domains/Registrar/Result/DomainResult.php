<?php

namespace Utopia\Domains\Registrar\Result;

use DateTime;

final readonly class DomainResult
{
    public function __construct(
        public string $domain,
        public ?DateTime $createdAt = null,
        public ?DateTime $expiresAt = null,
        public ?bool $autoRenew = null,
        public ?array $nameservers = null
    ) {
    }
}
