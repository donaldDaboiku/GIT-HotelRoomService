<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CashDelivery = 'cash_delivery';
    case PayReception = 'pay_reception';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::CashDelivery => 'Cash at delivery',
            self::PayReception => 'Pay at reception',
            self::Online => 'Online payment',
        };
    }
}
