<?php

namespace Utopia\Domains\Registrar;

final readonly class Registeration
{
    public function __construct(
        public string $code,
        public string $id,
        public string $domainId,
        public bool $successful,
        public ?string $domain = null,
        public ?int $periodYears = null,
        public ?array $nameservers = null,
    ) {
    }
}
