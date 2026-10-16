<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Enums\PaymentMethod;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\HotelRequest;
use App\Services\GuestSubmissionService;
use App\Services\RequestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestRequestController extends Controller
{
    public function __construct(private readonly GuestSubmissionService $submissions) {}

    public function index(Request $request): JsonResponse
    {
        $token = $request->attributes->get('guestToken');
        $rows = HotelRequest::query()
            ->where('guest_access_token_id', $token->id)
            ->latest('submitted_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (HotelRequest $row) => RequestPresenter::guest($row))->values(),
        ]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        return response()->json(['data' => RequestPresenter::guest($this->owned($request, $reference))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([RequestType::Service->value, RequestType::Maintenance->value])],
            'service_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'guest_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'photo' => ['nullable', 'file', 'max:5120'],
            'status' => ['prohibited'],
            'total_minor' => ['prohibited'],
        ]);

        $created = $this->submissions->submitService(
            $request->attributes->get('guestToken'),
            $data,
            $request->file('photo'),
            $request->ip(),
        );

        return response()->json(['data' => RequestPresenter::guest($created)], 201);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->orderInput($request);

        return response()->json([
            'data' => $this->submissions->quoteOrder($request->attributes->get('guestToken'), $data),
        ]);
    }

    public function order(Request $request): JsonResponse
    {
        $data = $this->orderInput($request);
        $created = $this->submissions->submitOrder($request->attributes->get('guestToken'), $data, $request->ip());

        return response()->json(['data' => RequestPresenter::guest($created)], 201);
    }

    public function cancel(Request $request, string $reference): JsonResponse
    {
        $updated = $this->submissions->cancel(
            $request->attributes->get('guestToken'),
            $this->owned($request, $reference),
            $request->ip(),
        );

        return response()->json(['data' => RequestPresenter::guest($updated)]);
    }

    public function feedback(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);
        $updated = $this->submissions->feedback(
            $request->attributes->get('guestToken'),
            $this->owned($request, $reference),
            $data,
            $request->ip(),
        );

        return response()->json(['data' => RequestPresenter::guest($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderInput(Request $request): array
    {
        return $request->validate([
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'guest_note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:300'],
            'items.*.price_minor' => ['prohibited'],
            'items.*.unit_price_minor' => ['prohibited'],
            'total_minor' => ['prohibited'],
            'status' => ['prohibited'],
        ]);
    }

    private function owned(Request $request, string $reference): HotelRequest
    {
        $token = $request->attributes->get('guestToken');
        $row = HotelRequest::query()
            ->where('guest_access_token_id', $token->id)
            ->where('reference', strtoupper($reference))
            ->first();
        abort_if($row === null, 404);

        return $row;
    }
}
