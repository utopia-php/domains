<?php

namespace Utopia\Domains\Registrar\Result;

use DateTime;

enum TransferStatus: string
{
    case Transferrable = 'transferrable';
    case NotTransferrable = 'not_transferrable';
    case PendingOwner = 'pending_owner';
    case PendingAdmin = 'pending_admin';
    case PendingRegistry = 'pending_registry';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case ServiceUnavailable = 'service_unavailable';
}

final readonly class TransferStatusResult
{
    public function __construct(
        public TransferStatus $status,
        public ?string $reason = null,
        public ?DateTime $timestamp = null,
    ) {
    }
}
