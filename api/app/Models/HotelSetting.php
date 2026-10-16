<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'welcome_message',
        'guest_info',
        'service_charge_bps',
        'tax_bps',
        'allow_cash',
        'allow_reception_payment',
        'allow_online_payment',
        'feedback_revisions',
        'guest_messages_enabled',
        'block_outside_hours',
        'service_hours',
        'notification_preferences',
    ];

    protected function casts(): array
    {
        return [
            'allow_cash' => 'boolean',
            'allow_reception_payment' => 'boolean',
            'allow_online_payment' => 'boolean',
            'feedback_revisions' => 'boolean',
            'guest_messages_enabled' => 'boolean',
            'block_outside_hours' => 'boolean',
            'service_hours' => 'array',
            'notification_preferences' => 'array',
        ];
    }
}
