<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\PaymentStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\HotelRequest;
use App\Models\RequestItem;
use App\Services\AuditLogger;
use App\Services\NotificationDispatcher;
use App\Services\RequestPresenter;
use App\Services\RequestWorkflow;
use App\Support\Access;
use App\Support\StaffRequestFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffRequestController extends Controller
{
    public function __construct(private readonly RequestWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'type' => ['nullable', Rule::enum(RequestType::class)],
            'department_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'priority', 'overdue'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Access::scopeVisible(HotelRequest::query()->with(['room', 'department']), $user);
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (! empty($data['department_id'])) {
            $query->where('department_id', $data['department_id']);
        }
        if (! empty($data['priority'])) {
            $query->where('priority', $data['priority']);
        }
        $timezone = $user->hotel->timezone;
        if (! empty($data['from'])) {
            $query->where('submitted_at', '>=', \Illuminate\Support\Carbon::parse($data['from'], $timezone)->startOfDay()->utc());
        }
        if (! empty($data['to'])) {
            $query->where('submitted_at', '<=', \Illuminate\Support\Carbon::parse($data['to'], $timezone)->endOfDay()->utc());
        }
        if (! empty($data['search'])) {
            $search = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], trim($data['search']));
            $query->where(function ($inner) use ($search) {
                $inner->where('reference', 'ilike', '%'.$search.'%')
                    ->orWhereHas('room', fn ($room) => $room->where('number', 'ilike', '%'.$search.'%'));
            });
        }

        match ($data['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('submitted_at'),
            'priority' => $query->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 else 2 end")->orderBy('submitted_at'),
            'overdue' => $query->orderByRaw('due_at asc nulls last'),
            default => $query->orderByDesc('submitted_at'),
        };

        $page = $query->paginate(20);
        $page->getCollection()->transform(fn (HotelRequest $row) => [
            'id' => $row->id,
            'reference' => $row->reference,
            'type' => $row->type->value,
            'status' => $row->status->value,
            'status_label' => $row->status->label(),
            'priority' => $row->priority->value,
            'room_number' => $row->room->number,
            'department' => $row->department?->name,
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'due_at' => $row->due_at?->toIso8601String(),
            'overdue' => $row->due_at !== null && ! $row->status->isTerminal() && $row->due_at->isPast(),
            'total_minor' => (int) $row->total_minor,
            'payment_status' => $row->payment_status->value,
        ]);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = StaffRequestFinder::find($request->user(), $id);

        return response()->json(['data' => RequestPresenter::staff($row, $request->user())]);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(RequestStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'guest_visible' => ['nullable', 'boolean'],
        ]);
        $row = StaffRequestFinder::find($request->user(), $id);
        $updated = $this->workflow->transition(
            $request->user(),
            $row,
            RequestStatus::from($data['status']),
            $data['note'] ?? null,
            (bool) ($data['guest_visible'] ?? false),
            $request->ip(),
        );

        return response()->json(['data' => RequestPresenter::staff($updated, $request->user())]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ]);
        $row = StaffRequestFinder::find($request->user(), $id);
        $department = $request->user()->hotel->departments()->whereKey($data['department_id'])->first();
        abort_if($department === null, 422, 'Choose a department from this hotel.');
        $assignee = null;
        if (! empty($data['user_id'])) {
            $assignee = $request->user()->hotel->users()->whereKey($data['user_id'])->first();
            abort_if($assignee === null, 422, 'Choose a staff member from this hotel.');
        }
        $updated = $this->workflow->assign($request->user(), $row, $department, $assignee, $request->ip());

        return response()->json(['data' => RequestPresenter::staff($updated, $request->user())]);
    }

    public function note(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'guest_visible' => ['nullable', 'boolean'],
        ]);
        $row = StaffRequestFinder::find($request->user(), $id);
        $visible = (bool) ($data['guest_visible'] ?? false) && (bool) $row->hotel->settings->guest_messages_enabled;
        $row->notes()->create([
            'hotel_id' => $row->hotel_id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'is_guest_visible' => $visible,
        ]);
        AuditLogger::record($request->user(), 'request.note', $row, ['guest_visible' => $visible], $request->ip(), $row->hotel_id);

        return response()->json(['data' => RequestPresenter::staff($row->refresh(), $request->user())]);
    }

    public function priority(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'priority' => ['required', Rule::enum(RequestPriority::class)],
        ]);
        $row = StaffRequestFinder::find($request->user(), $id);
        abort_unless(Access::canSetPriority($request->user(), $row), 403);
        $row->priority = RequestPriority::from($data['priority']);
        $row->save();
        AuditLogger::record($request->user(), 'request.priority', $row, ['priority' => $row->priority->value], $request->ip(), $row->hotel_id);

        return response()->json(['data' => RequestPresenter::staff($row->refresh(), $request->user())]);
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:1000']]);
        abort_unless(Access::canAssign($request->user()), 403);
        $row = StaffRequestFinder::find($request->user(), $id);
        $row->priority = RequestPriority::Urgent;
        $row->save();
        $row->notes()->create([
            'hotel_id' => $row->hotel_id,
            'user_id' => $request->user()->id,
            'body' => trim($data['note']),
            'is_guest_visible' => false,
        ]);
        AuditLogger::record($request->user(), 'request.escalated', $row, [], $request->ip(), $row->hotel_id);
        NotificationDispatcher::escalated($row);

        return response()->json(['data' => RequestPresenter::staff($row->refresh(), $request->user())]);
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'paid', 'refunded'])],
        ]);
        $row = StaffRequestFinder::find($request->user(), $id);
        abort_unless(Access::canRecordPayment($request->user(), $row), 403);
        if (in_array($row->status, [RequestStatus::Cancelled, RequestStatus::Rejected], true) && $data['payment_status'] === 'paid') {
            abort(422, 'A cancelled or rejected request cannot be marked as paid.');
        }
        $row->payment_status = PaymentStatus::from($data['payment_status']);
        $row->save();
        AuditLogger::record($request->user(), 'request.payment', $row, [
            'payment_status' => $row->payment_status->value,
            'total_minor' => (int) $row->total_minor,
        ], $request->ip(), $row->hotel_id);

        return response()->json(['data' => RequestPresenter::staff($row->refresh(), $request->user())]);
    }

    public function unavailable(Request $request, int $id, int $item): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $row = StaffRequestFinder::find($request->user(), $id);
        $line = RequestItem::query()->where('request_id', $row->id)->whereKey($item)->first();
        abort_if($line === null, 404);
        $updated = $this->workflow->markUnavailable($request->user(), $row, $line, $data['note'] ?? null, $request->ip());

        return response()->json(['data' => RequestPresenter::staff($updated, $request->user())]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $rows = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(40)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (AppNotification $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'channel' => $row->channel,
                'delivery_status' => $row->delivery_status,
                'request_id' => $row->request_id,
                'read_at' => $row->read_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function readNotification(Request $request, int $notification): JsonResponse
    {
        $row = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($notification)
            ->first();
        abort_if($row === null, 404);
        if ($row->read_at === null) {
            $row->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => ['id' => $row->id, 'read_at' => $row->read_at?->toIso8601String()]]);
    }
}
