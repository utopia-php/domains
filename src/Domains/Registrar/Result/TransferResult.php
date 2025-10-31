<?php

namespace Utopia\Domains\Registrar\Result;

final readonly class TransferResult
{
    public function __construct(
        public string $code,
        public string $id,
        public string $domainId,
        public bool $successful,
        public ?string $domain = null,
        public ?int $period = null,
        public ?array $nameservers = null,
    ) {
    }
}
