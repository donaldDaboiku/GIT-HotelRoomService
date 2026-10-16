<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\GuestFeedback;
use App\Models\Hotel;
use App\Models\HotelRequest;
use App\Models\RequestItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReportQuery
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(Hotel $hotel, ?string $from, ?string $to): array
    {
        $start = $from
            ? Carbon::parse($from, $hotel->timezone)->startOfDay()
            : now($hotel->timezone)->startOfMonth();
        $end = $to
            ? Carbon::parse($to, $hotel->timezone)->endOfDay()
            : now($hotel->timezone)->endOfDay();

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Hotel $hotel, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->range($hotel, $from, $to);
        $base = HotelRequest::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('submitted_at', [$start, $end]);

        $orders = (clone $base)->where('type', RequestType::Order->value);
        $openOrders = (clone $orders)->whereNotIn('status', [
            RequestStatus::Cancelled->value,
            RequestStatus::Rejected->value,
        ]);

        $orderValue = (int) (clone $openOrders)->sum('total_minor');
        $received = (int) (clone $openOrders)->where('payment_status', PaymentStatus::Paid->value)->sum('total_minor');
        $refunds = (int) (clone $orders)->where('payment_status', PaymentStatus::Refunded->value)->sum('total_minor');
        $unpaid = (int) (clone $openOrders)->where('payment_status', PaymentStatus::Unpaid->value)->sum('total_minor');

        $serviceFees = (clone $base)
            ->where('type', RequestType::Service->value)
            ->whereNotIn('status', [RequestStatus::Cancelled->value, RequestStatus::Rejected->value]);
        $serviceValue = (int) (clone $serviceFees)->sum('total_minor');
        $serviceReceived = (int) (clone $serviceFees)->where('payment_status', PaymentStatus::Paid->value)->sum('total_minor');

        $total = (clone $base)->count();
        $considered = (clone $base)->whereNotIn('status', [RequestStatus::Cancelled->value])->count();
        $overdue = (clone $base)
            ->whereNotIn('status', [RequestStatus::Cancelled->value])
            ->whereNotNull('due_at')
            ->where(function (Builder $query) {
                $query->where(function (Builder $open) {
                    $open->whereNull('completed_at')->where('due_at', '<', now());
                })->orWhereColumn('completed_at', '>', 'due_at');
            })
            ->count();

        $ack = (clone $base)->whereNotNull('acknowledged_at')->selectRaw(
            'avg(extract(epoch from (acknowledged_at - submitted_at))) as seconds',
        )->value('seconds');
        $done = (clone $base)->whereNotNull('completed_at')->selectRaw(
            'avg(extract(epoch from (completed_at - submitted_at))) as seconds',
        )->value('seconds');

        $volume = (clone $base)
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $top = RequestItem::query()
            ->select('name', DB::raw('sum(quantity) as times'))
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('service_id')
            ->whereHas('request', function (Builder $query) use ($start, $end) {
                $query->whereBetween('submitted_at', [$start, $end]);
            })
            ->groupBy('name')
            ->orderByDesc('times')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'times' => (int) $row->times])
            ->values();

        $ratings = GuestFeedback::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$start, $end]);
        $averageRating = (clone $ratings)->avg('rating');
        $ratingCount = (clone $ratings)->count();

        return [
            'from' => $start->toIso8601String(),
            'to' => $end->toIso8601String(),
            'request_volume' => [
                'service' => (int) ($volume[RequestType::Service->value] ?? 0),
                'order' => (int) ($volume[RequestType::Order->value] ?? 0),
                'maintenance' => (int) ($volume[RequestType::Maintenance->value] ?? 0),
                'total' => $total,
            ],
            'average_acknowledgement_seconds' => $ack === null ? null : (int) round((float) $ack),
            'average_completion_seconds' => $done === null ? null : (int) round((float) $done),
            'overdue_rate' => $considered === 0 ? 0 : round($overdue / $considered, 4),
            'overdue_count' => $overdue,
            'most_requested_services' => $top,
            'food_order_value_minor' => $orderValue,
            'food_payments_received_minor' => $received,
            'food_refunds_minor' => $refunds,
            'food_unpaid_minor' => $unpaid,
            'service_fee_value_minor' => $serviceValue,
            'service_fees_received_minor' => $serviceReceived,
            'guest_satisfaction' => [
                'average_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
                'responses' => $ratingCount,
            ],
            'currency' => $hotel->currency,
            'note' => 'food_payments_received_minor counts orders recorded as paid. Unpaid order value is not collected revenue.',
        ];
    }
}
