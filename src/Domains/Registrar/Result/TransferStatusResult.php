<?php

namespace Utopia\Domains\Registrar\Result;

use DateTime;

final readonly class TransferStatusResult
{
    public function __construct(
        public int $transferrable,
        public int $noservice,
        public ?string $reason = null,
        public ?string $reasonCode = null,
        public ?string $status = null,
        public ?DateTime $timestamp = null,
        public ?string $type = null,
        public ?string $requestAddress = null,
    ) {
    }
}
