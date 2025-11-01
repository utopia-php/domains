<?php

namespace Utopia\Domains\Registrar\Result;

use DateTime;

final readonly class RenewResult
{
    public function __construct(
        public bool $successful,
        public ?string $orderId = null,
        public ?DateTime $newExpiration = null,
    ) {
    }
}
