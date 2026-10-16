<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelRequest;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminRoomController extends Controller
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function index(Request $request): JsonResponse
    {
        $rooms = Room::query()
            ->with('activeQr')
            ->where('hotel_id', $request->user()->hotel_id)
            ->orderBy('number')
            ->get();

        return response()->json(['data' => $rooms->map(fn (Room $room) => $this->payload($room, $request))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $room = Room::query()->create([
            ...$data,
            'hotel_id' => $request->user()->hotel_id,
        ]);
        $this->qr->issue($room, $request->user(), $request->ip());
        AuditLogger::record($request->user(), 'room.created', $room, ['number' => $room->number], $request->ip(), $room->hotel_id);

        return response()->json(['data' => $this->payload($room->load('activeQr'), $request)], 201);
    }

    public function update(Request $request, int $room): JsonResponse
    {
        $model = $this->room($request, $room);
        $model->fill($this->validated($request, $model->id))->save();
        AuditLogger::record($request->user(), 'room.updated', $model, [], $request->ip(), $model->hotel_id);

        return response()->json(['data' => $this->payload($model->load('activeQr'), $request)]);
    }

    public function history(Request $request, int $room): JsonResponse
    {
        $model = $this->room($request, $room);
        $rows = HotelRequest::query()
            ->where('hotel_id', $model->hotel_id)
            ->where('room_id', $model->id)
            ->latest('submitted_at')
            ->limit(50)
            ->get(['id', 'reference', 'type', 'status', 'submitted_at', 'completed_at']);

        return response()->json([
            'data' => $rows->map(fn (HotelRequest $row) => [
                'id' => $row->id,
                'reference' => $row->reference,
                'type' => $row->type->value,
                'status' => $row->status->value,
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'completed_at' => $row->completed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function qrAction(Request $request, int $room): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['issue', 'revoke', 'reissue'])]]);
        $model = $this->room($request, $room);
        match ($data['action']) {
            'revoke' => $this->qr->revoke($model, $request->user(), $request->ip()),
            'reissue' => $this->qr->reissue($model, $request->user(), $request->ip()),
            default => $this->qr->issue($model, $request->user(), $request->ip()),
        };

        return response()->json(['data' => $this->payload($model->refresh()->load('activeQr'), $request)]);
    }

    public function svg(Request $request, int $room): Response
    {
        $model = $this->room($request, $room)->load('activeQr', 'hotel');
        abort_if($model->activeQr === null, 404, 'This room has no active QR code.');
        $svg = $this->qr->svg($this->qr->url($model->hotel, $model->activeQr));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function png(Request $request, int $room): Response
    {
        $model = $this->room($request, $room)->load('activeQr', 'hotel');
        abort_if($model->activeQr === null, 404, 'This room has no active QR code.');
        $png = $this->qr->png($this->qr->url($model->hotel, $model->activeQr));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="room-'.$model->number.'.png"',
        ]);
    }

    private function room(Request $request, int $id): Room
    {
        $room = Room::query()->where('hotel_id', $request->user()->hotel_id)->whereKey($id)->first();
        abort_if($room === null, 404);

        return $room;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $hotelId = $request->user()->hotel_id;

        return $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rooms', 'number')->where('hotel_id', $hotelId)->ignore($ignoreId),
            ],
            'floor' => ['nullable', 'string', 'max:20'],
            'room_type' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Room $room, Request $request): array
    {
        $token = $room->activeQr;
        $hotel = $request->user()->hotel;

        return [
            'id' => $room->id,
            'number' => $room->number,
            'floor' => $room->floor,
            'room_type' => $room->room_type,
            'is_active' => (bool) $room->is_active,
            'qr' => $token === null ? null : [
                'public_code' => $token->public_code,
                'url' => $this->qr->url($hotel, $token),
            ],
        ];
    }
}
