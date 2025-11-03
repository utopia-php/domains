<?php

namespace Utopia\Domains\Registrar;

use DateTime;

final readonly class Renew
{
    public function __construct(
        public bool $successful,
        public ?string $orderId = null,
        public ?DateTime $expiresAt = null,
    ) {
    }
}
