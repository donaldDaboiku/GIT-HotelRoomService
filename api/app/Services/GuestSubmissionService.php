<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\ServiceKind;
use App\Models\Attachment;
use App\Models\GuestAccessToken;
use App\Models\HotelRequest;
use App\Models\MenuItem;
use App\Models\RequestStatusHistory;
use App\Models\ResponseTarget;
use App\Models\Service;
use App\Support\Money;
use App\Support\ServiceHours;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GuestSubmissionService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function submitService(GuestAccessToken $token, array $input, ?UploadedFile $photo, ?string $ip = null): HotelRequest
    {
        $type = RequestType::from((string) $input['type']);
        if ($type === RequestType::Maintenance && $photo === null) {
            // Photo is optional for a maintenance report.
        }
        if ($type !== RequestType::Maintenance && $photo !== null) {
            throw ValidationException::withMessages([
                'photo' => 'Photos can only be attached to a maintenance report.',
            ]);
        }

        $service = Service::query()
            ->with(['category', 'department'])
            ->where('hotel_id', $token->hotel_id)
            ->whereKey((int) $input['service_id'])
            ->first();
        if ($service === null || ! $service->is_available) {
            throw ValidationException::withMessages([
                'service_id' => 'That service is not available.',
            ]);
        }

        $kind = $service->category->kind;
        $kindMatches = $type === RequestType::Maintenance
            ? $kind === ServiceKind::Maintenance
            : in_array($kind, [ServiceKind::Housekeeping, ServiceKind::Reception, ServiceKind::Other], true);
        if (! $kindMatches) {
            throw ValidationException::withMessages([
                'service_id' => 'That service does not match this request.',
            ]);
        }

        $quantity = $type === RequestType::Maintenance ? 1 : (int) ($input['quantity'] ?? 1);
        $note = $this->note($input['guest_note'] ?? null);
        $hash = hash('sha256', json_encode([
            'type' => $type->value,
            'service_id' => $service->id,
            'quantity' => $quantity,
            'note' => $note,
        ], JSON_THROW_ON_ERROR));

        return $this->withinDuplicateGuard($token, $hash, function () use ($token, $service, $type, $quantity, $note, $hash, $photo, $input, $ip) {
            $subtotal = (int) $service->fee_minor * $quantity;
            $settings = $token->hotel->settings;
            $bill = Money::quote($subtotal, (int) $settings->service_charge_bps, (int) $settings->tax_bps);
            $payment = $this->paymentMethod($settings, $bill['total_minor'], $input['payment_method'] ?? null);

            $request = $this->persist($token, $type, $service->department_id, $note, $hash, $bill, $payment, $ip, function (HotelRequest $request) use ($service, $quantity, $photo) {
                $request->items()->create([
                    'hotel_id' => $request->hotel_id,
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'unit_price_minor' => (int) $service->fee_minor,
                    'quantity' => $quantity,
                    'line_total_minor' => (int) $service->fee_minor * $quantity,
                ]);
                if ($photo !== null) {
                    $this->storePhoto($request, $photo);
                }
            });

            return $request;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{lines: list<array<string, mixed>>, totals: array<string, int>, currency: string, payment_method: ?string}
     */
    public function quoteOrder(GuestAccessToken $token, array $input): array
    {
        $built = $this->orderLines($token, $input, false);
        $settings = $token->hotel->settings;

        return [
            'lines' => $built['lines'],
            'totals' => $built['bill'],
            'currency' => $token->hotel->currency,
            'payment_method' => $built['payment']?->value,
            'service_charge_label' => Money::bpsLabel((int) $settings->service_charge_bps),
            'tax_label' => Money::bpsLabel((int) $settings->tax_bps),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function submitOrder(GuestAccessToken $token, array $input, ?string $ip = null): HotelRequest
    {
        $built = $this->orderLines($token, $input);
        $note = $this->note($input['guest_note'] ?? null);
        $hash = hash('sha256', json_encode([
            'type' => 'order',
            'payment_method' => $built['payment']?->value,
            'note' => $note,
            'items' => $built['identity'],
        ], JSON_THROW_ON_ERROR));

        $kitchen = $token->hotel->departments()->where('code', 'kitchen')->first();

        return $this->withinDuplicateGuard($token, $hash, function () use ($token, $built, $note, $hash, $kitchen, $ip) {
            return $this->persist(
                $token,
                RequestType::Order,
                $kitchen?->id,
                $note,
                $hash,
                $built['bill'],
                $built['payment'],
                $ip,
                function (HotelRequest $request) use ($built) {
                    foreach ($built['lines'] as $line) {
                        $request->items()->create([
                            'hotel_id' => $request->hotel_id,
                            'menu_item_id' => $line['menu_item_id'],
                            'name' => $line['name'],
                            'unit_price_minor' => $line['unit_price_minor'],
                            'quantity' => $line['quantity'],
                            'line_total_minor' => $line['line_total_minor'],
                            'notes' => $line['notes'],
                        ]);
                    }
                },
            );
        });
    }

    public function cancel(GuestAccessToken $token, HotelRequest $request, ?string $ip = null): HotelRequest
    {
        $this->owns($token, $request);
        if ($request->status !== RequestStatus::Submitted) {
            abort(422, 'Only a request that has not been acknowledged can be cancelled.');
        }

        $request->status = RequestStatus::Cancelled;
        $request->save();
        RequestStatusHistory::query()->create([
            'request_id' => $request->id,
            'hotel_id' => $request->hotel_id,
            'from_status' => RequestStatus::Submitted->value,
            'to_status' => RequestStatus::Cancelled->value,
            'note' => 'Cancelled by the guest.',
            'guest_visible' => true,
        ]);
        AuditLogger::record(null, 'request.cancelled_by_guest', $request, [], $ip, $request->hotel_id);

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function feedback(GuestAccessToken $token, HotelRequest $request, array $input, ?string $ip = null): HotelRequest
    {
        $this->owns($token, $request);
        if (! $request->status->isDone()) {
            abort(422, 'Feedback is available after the request is completed.');
        }

        $settings = $token->hotel->settings;
        $existing = $request->feedback;
        if ($existing !== null && ! $settings->feedback_revisions) {
            abort(409, 'Feedback has already been submitted for this request.');
        }

        $payload = [
            'hotel_id' => $request->hotel_id,
            'rating' => (int) $input['rating'],
            'comment' => $this->note($input['comment'] ?? null),
            'category' => isset($input['category']) ? trim((string) $input['category']) : null,
        ];

        if ($existing === null) {
            $request->feedback()->create($payload);
            AuditLogger::record(null, 'feedback.created', $request, ['rating' => $payload['rating']], $ip, $request->hotel_id);
        } else {
            $existing->fill($payload)->save();
            AuditLogger::record(null, 'feedback.revised', $request, ['rating' => $payload['rating']], $ip, $request->hotel_id);
        }

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{lines: list<array<string, mixed>>, identity: list<array<string, mixed>>, bill: array<string, int>, payment: ?PaymentMethod}
     */
    private function orderLines(GuestAccessToken $token, array $input, bool $requirePayment = true): array
    {
        $raw = collect($input['items'] ?? [])->map(function ($item) {
            return [
                'menu_item_id' => (int) ($item['menu_item_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'notes' => isset($item['notes']) ? trim((string) $item['notes']) : null,
            ];
        });

        $merged = [];
        foreach ($raw as $item) {
            $id = $item['menu_item_id'];
            if (! isset($merged[$id])) {
                $merged[$id] = $item;
                continue;
            }
            $merged[$id]['quantity'] += $item['quantity'];
            if ($item['notes']) {
                $merged[$id]['notes'] = trim(($merged[$id]['notes'] ? $merged[$id]['notes'].' ' : '').$item['notes']);
            }
        }

        $ids = array_keys($merged);
        $menu = MenuItem::query()
            ->where('hotel_id', $token->hotel_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($menu->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => 'One or more menu items are not available from this hotel.',
            ]);
        }

        $lines = [];
        $subtotal = 0;
        foreach ($merged as $id => $item) {
            /** @var MenuItem $menuItem */
            $menuItem = $menu->get($id);
            if (! $menuItem->is_available || $menuItem->is_sold_out) {
                throw ValidationException::withMessages([
                    'items' => $menuItem->name.' is not available.',
                ]);
            }
            if ($item['quantity'] < 1 || $item['quantity'] > 20) {
                throw ValidationException::withMessages([
                    'items' => 'Quantity for '.$menuItem->name.' must be between 1 and 20.',
                ]);
            }
            $lineTotal = (int) $menuItem->price_minor * $item['quantity'];
            $subtotal += $lineTotal;
            $lines[] = [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'unit_price_minor' => (int) $menuItem->price_minor,
                'quantity' => $item['quantity'],
                'line_total_minor' => $lineTotal,
                'notes' => $item['notes'] !== '' ? $item['notes'] : null,
            ];
        }

        $settings = $token->hotel->settings;
        $bill = Money::quote($subtotal, (int) $settings->service_charge_bps, (int) $settings->tax_bps);
        $payment = $this->paymentMethod($settings, $bill['total_minor'], $input['payment_method'] ?? null, $requirePayment);

        $identity = collect($lines)->map(fn (array $line) => [
            'menu_item_id' => $line['menu_item_id'],
            'quantity' => $line['quantity'],
            'notes' => $line['notes'],
        ])->sortBy('menu_item_id')->values()->all();

        return [
            'lines' => $lines,
            'identity' => $identity,
            'bill' => $bill,
            'payment' => $payment,
        ];
    }

    private function paymentMethod(object $settings, int $total, mixed $requested, bool $required = true): ?PaymentMethod
    {
        if ($total === 0) {
            return null;
        }
        if (! is_string($requested) || $requested === '') {
            if (! $required) {
                return null;
            }
            throw ValidationException::withMessages([
                'payment_method' => 'Choose how this request will be paid.',
            ]);
        }

        $method = PaymentMethod::tryFrom($requested);
        $enabled = match ($method) {
            PaymentMethod::CashDelivery => (bool) $settings->allow_cash,
            PaymentMethod::PayReception => (bool) $settings->allow_reception_payment,
            PaymentMethod::Online => (bool) $settings->allow_online_payment,
            default => false,
        };
        if (! $enabled) {
            throw ValidationException::withMessages([
                'payment_method' => 'That payment method is not available.',
            ]);
        }

        return $method;
    }

    /**
     * @param  array<string, int>  $bill
     * @param  callable(HotelRequest): void  $lines
     */
    private function persist(
        GuestAccessToken $token,
        RequestType $type,
        ?int $departmentId,
        ?string $note,
        string $hash,
        array $bill,
        ?PaymentMethod $payment,
        ?string $ip,
        callable $lines,
    ): HotelRequest {
        $hotel = $token->hotel;
        $settings = $hotel->settings;
        $open = ServiceHours::isOpen($settings->service_hours ?? [], $hotel->timezone);
        if (! $open && $settings->block_outside_hours) {
            abort(422, 'The hotel is not accepting requests outside service hours.');
        }

        $minutes = ResponseTarget::query()
            ->where('hotel_id', $hotel->id)
            ->where('request_type', $type->value)
            ->value('target_minutes');

        return DB::transaction(function () use ($token, $type, $departmentId, $note, $hash, $bill, $payment, $ip, $lines, $hotel, $open, $minutes, $settings) {
            $request = new HotelRequest([
                'hotel_id' => $hotel->id,
                'room_id' => $token->room_id,
                'department_id' => $departmentId,
                'guest_access_token_id' => $token->id,
                'type' => $type,
                'status' => RequestStatus::Submitted,
                'guest_note' => $note,
                'service_charge_bps' => (int) $settings->service_charge_bps,
                'tax_bps' => (int) $settings->tax_bps,
                'currency' => $hotel->currency,
                'payment_method' => $payment,
                'payment_status' => PaymentStatus::Unpaid,
                'payload_hash' => $hash,
                'submitted_at' => now(),
                'due_at' => $minutes ? now()->addMinutes((int) $minutes) : null,
                'outside_service_hours' => ! $open,
            ]);
            $request->fill($bill);

            $saved = false;
            $last = null;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                try {
                    $request->reference = HotelRequest::newReference();
                    $request->save();
                    $saved = true;
                    break;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                    $last = $exception;
                }
            }
            if (! $saved) {
                throw $last ?? new \RuntimeException('Could not allocate a request reference.');
            }

            $lines($request);
            RequestStatusHistory::query()->create([
                'request_id' => $request->id,
                'hotel_id' => $hotel->id,
                'from_status' => null,
                'to_status' => RequestStatus::Submitted->value,
                'note' => null,
                'guest_visible' => false,
            ]);
            NotificationDispatcher::requestSubmitted($request);
            AuditLogger::record(null, 'request.submitted', $request, ['reference' => $request->reference], $ip, $hotel->id);

            return $request->refresh();
        });
    }

    private function storePhoto(HotelRequest $request, UploadedFile $photo): void
    {
        $extension = ImageGuard::extension($photo);
        $name = (string) Str::uuid().'.'.$extension;
        $path = $photo->storeAs('attachments/'.$request->hotel_id, $name, 'local');
        if ($path === false) {
            abort(500, 'The photo could not be stored.');
        }

        Attachment::query()->create([
            'hotel_id' => $request->hotel_id,
            'request_id' => $request->id,
            'path' => $path,
            'original_name' => ImageGuard::safeOriginalName($photo).'.'.$extension,
            'mime' => (string) $photo->getMimeType(),
            'size' => (int) $photo->getSize(),
        ]);
    }

    /**
     * @param  callable(): HotelRequest  $create
     */
    private function withinDuplicateGuard(GuestAccessToken $token, string $hash, callable $create): HotelRequest
    {
        $existing = $this->recentDuplicate($token, $hash);
        if ($existing instanceof HotelRequest) {
            abort(response()->json([
                'message' => 'This request was just submitted.',
                'data' => ['reference' => $existing->reference],
            ], 409));
        }

        $lock = Cache::lock('guest-dup:'.$token->id.':'.$hash, 60);
        if (! $lock->get()) {
            $existing = $this->recentDuplicate($token, $hash);
            abort(response()->json([
                'message' => 'This request was just submitted.',
                'data' => ['reference' => $existing?->reference],
            ], 409));
        }

        try {
            return $create();
        } catch (\Throwable $exception) {
            $lock->release();
            throw $exception;
        }
    }

    private function recentDuplicate(GuestAccessToken $token, string $hash): ?HotelRequest
    {
        return HotelRequest::query()
            ->where('guest_access_token_id', $token->id)
            ->where('payload_hash', $hash)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();
    }

    private function owns(GuestAccessToken $token, HotelRequest $request): void
    {
        abort_unless((int) $request->guest_access_token_id === (int) $token->id, 404);
    }

    private function note(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $note = trim($value);

        return $note === '' ? null : $note;
    }
}
