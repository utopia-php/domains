<?php

namespace Utopia\Domains\Registrar;

use DateTime;

final readonly class Renewal
{
    public function __construct(
        public bool $successful,
        public ?string $orderId = null,
        public ?DateTime $expiresAt = null,
    ) {
    }
}
