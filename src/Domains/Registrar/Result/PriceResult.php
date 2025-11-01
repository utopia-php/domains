<?php

namespace Utopia\Domains\Registrar\Result;

final readonly class PriceResult
{
    public function __construct(
        public ?float $price,
        public bool $isRegistryPremium,
        public ?string $registryPremiumGroup = null,
    ) {
    }
}
