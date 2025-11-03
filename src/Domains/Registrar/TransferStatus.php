<?php

namespace Utopia\Domains\Registrar;

use DateTime;

enum TransferStatusEnum: string
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

final readonly class TransferStatus
{
    public function __construct(
        public TransferStatusEnum $status,
        public ?string $reason = null,
        public ?DateTime $timestamp = null,
    ) {
    }
}
