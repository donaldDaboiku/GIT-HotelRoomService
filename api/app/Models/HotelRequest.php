<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelRequest extends Model
{
    use BelongsToHotel;

    protected $table = 'requests';

    protected $fillable = [
        'hotel_id',
        'room_id',
        'department_id',
        'guest_access_token_id',
        'type',
        'reference',
        'status',
        'priority',
        'guest_note',
        'subtotal_minor',
        'service_charge_minor',
        'tax_minor',
        'total_minor',
        'service_charge_bps',
        'tax_bps',
        'currency',
        'payment_method',
        'payment_status',
        'payload_hash',
        'submitted_at',
        'acknowledged_at',
        'completed_at',
        'due_at',
        'outside_service_hours',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'priority' => RequestPriority::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
            'outside_service_hours' => 'boolean',
        ];
    }

    public static function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $code = 'RS-';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function guestAccessToken(): BelongsTo
    {
        return $this->belongsTo(GuestAccessToken::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RequestAssignment::class, 'request_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(RequestNote::class, 'request_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(GuestFeedback::class, 'request_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'request_id');
    }
}
