<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestStatus: string
{
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Preparing = 'preparing';
    case ReadyForDelivery = 'ready_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Delivered, self::Cancelled, self::Rejected], true);
    }

    public function isDone(): bool
    {
        return $this === self::Completed || $this === self::Delivered;
    }

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Assigned => 'Assigned',
            self::Accepted => 'Accepted',
            self::InProgress => 'In progress',
            self::Preparing => 'Preparing',
            self::ReadyForDelivery => 'Ready for delivery',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
        };
    }
}
