<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelRequest;
use App\Services\ReportQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(private readonly ReportQuery $reports) {}

    public function requests(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $summary = $this->reports->summary($request->user()->hotel, $data['from'] ?? null, $data['to'] ?? null);

        return response()->json(['data' => $summary]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $hotel = $request->user()->hotel;
        [$start, $end] = $this->reports->range($hotel, $data['from'] ?? null, $data['to'] ?? null);
        $rows = HotelRequest::query()
            ->with(['room', 'feedback'])
            ->where('hotel_id', $hotel->id)
            ->whereBetween('submitted_at', [$start, $end])
            ->orderBy('submitted_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['note', 'order_value_minor is the calculated total. payment_received_minor is money recorded as paid. Unpaid orders are not collected revenue.']);
            fputcsv($out, [
                'reference', 'room', 'type', 'status', 'priority', 'submitted_at', 'acknowledged_at', 'completed_at',
                'currency', 'order_value_minor', 'payment_status', 'payment_received_minor', 'refund_minor', 'rating',
            ]);
            foreach ($rows as $row) {
                $value = (int) $row->total_minor;
                fputcsv($out, [
                    $row->reference,
                    $row->room->number,
                    $row->type->value,
                    $row->status->value,
                    $row->priority->value,
                    $row->submitted_at?->toIso8601String(),
                    $row->acknowledged_at?->toIso8601String(),
                    $row->completed_at?->toIso8601String(),
                    $row->currency,
                    $value,
                    $row->payment_status->value,
                    $row->payment_status->value === 'paid' ? $value : 0,
                    $row->payment_status->value === 'refunded' ? $value : 0,
                    $row->feedback?->rating,
                ]);
            }
            fclose($out);
        }, 'guest-requests.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
