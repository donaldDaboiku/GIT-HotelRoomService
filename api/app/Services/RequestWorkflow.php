<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepartmentCode;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\HotelRequest;
use App\Models\RequestAssignment;
use App\Models\RequestItem;
use App\Models\RequestStatusHistory;
use App\Models\User;
use App\Support\Access;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestWorkflow
{
    /**
     * @return list<RequestStatus>
     */
    public function allowedFor(User $user, HotelRequest $request): array
    {
        $allowed = [];
        foreach ($this->targets($request) as $status) {
            if ($this->roleAllows($user, $request, $status)) {
                $allowed[] = $status;
            }
        }

        return $allowed;
    }

    public function transition(
        User $actor,
        HotelRequest $request,
        RequestStatus $to,
        ?string $note = null,
        bool $guestVisible = false,
        ?string $ip = null,
    ): HotelRequest {
        if (! in_array($to, $this->targets($request), true)) {
            abort(422, 'That status change is not allowed from '.$request->status->value.'.');
        }
        if (! $this->roleAllows($actor, $request, $to)) {
            abort(403, 'You cannot make that status change.');
        }

        $note = $note !== null ? trim($note) : null;
        if (in_array($to, [RequestStatus::Rejected, RequestStatus::Cancelled], true) && ($note === null || strlen($note) < 3)) {
            throw ValidationException::withMessages([
                'note' => 'A reason of at least 3 characters is required.',
            ]);
        }

        $guestVisible = $guestVisible && (bool) $request->hotel->settings->guest_messages_enabled;

        return DB::transaction(function () use ($actor, $request, $to, $note, $guestVisible, $ip) {
            $request = HotelRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            abort_if($request === null, 404);
            if (! in_array($to, $this->targets($request), true)) {
                abort(422, 'That status change is not allowed from '.$request->status->value.'.');
            }
            if (! $this->roleAllows($actor, $request, $to)) {
                abort(403, 'You cannot make that status change.');
            }

            $from = $request->status;
            $request->status = $to;
            if ($request->acknowledged_at === null) {
                $request->acknowledged_at = now();
            }
            if ($to->isDone()) {
                $request->completed_at = now();
            }
            $request->save();

            $this->history($request, $from, $to, $actor, $note, $guestVisible);
            AuditLogger::record($actor, 'request.status', $request, [
                'from' => $from->value,
                'to' => $to->value,
            ], $ip, $request->hotel_id);
            NotificationDispatcher::closed($request, $to);

            return $request->refresh();
        });
    }

    public function assign(User $actor, HotelRequest $request, Department $department, ?User $assignee, ?string $ip = null): HotelRequest
    {
        abort_unless(Access::canView($actor, $request), 404);
        abort_unless((int) $department->hotel_id === (int) $request->hotel_id, 422, 'Choose a department from this hotel.');
        if ($request->status->isTerminal()) {
            abort(422, 'This request is already closed.');
        }
        if ($request->type === RequestType::Order && $department->code !== DepartmentCode::Kitchen) {
            abort(422, 'Food and drink orders stay with the kitchen.');
        }

        $manager = Access::canAssign($actor);
        $selfAccept = $actor->role === UserRole::ServiceStaff
            && $request->type !== RequestType::Order
            && $assignee !== null
            && (int) $assignee->id === (int) $actor->id
            && (int) $actor->department_id === (int) $department->id
            && (int) $request->department_id === (int) $department->id;

        abort_unless($manager || $selfAccept, 403, 'You cannot assign this request.');

        if ($assignee !== null) {
            abort_unless((int) $assignee->hotel_id === (int) $request->hotel_id, 422, 'Choose a staff member from this hotel.');
            abort_unless($assignee->status->value === 'active', 422, 'That staff account is not active.');
            if ($assignee->role !== UserRole::HotelAdmin && (int) $assignee->department_id !== (int) $department->id) {
                abort(422, 'That staff member is not in the selected department.');
            }
        }

        if ($selfAccept) {
            $current = $request->assignments()->latest('id')->first();
            if ($current && $current->assigned_to_user_id && (int) $current->assigned_to_user_id !== (int) $actor->id) {
                abort(403, 'This task is assigned to someone else.');
            }
        }

        return DB::transaction(function () use ($actor, $request, $department, $assignee, $ip, $selfAccept) {
            $locked = HotelRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            abort_if($locked === null, 404);
            if ($locked->status->isTerminal()) {
                abort(422, 'This request is already closed.');
            }
            if ($selfAccept) {
                $current = $locked->assignments()->latest('id')->first();
                if ($current && $current->assigned_to_user_id && (int) $current->assigned_to_user_id !== (int) $actor->id) {
                    abort(403, 'This task is assigned to someone else.');
                }
            }

            $from = $locked->status;
            $locked->department_id = $department->id;
            if ($locked->type === RequestType::Order) {
                if ($from === RequestStatus::Submitted) {
                    $locked->status = RequestStatus::Acknowledged;
                }
            } elseif (in_array($from, [RequestStatus::Submitted, RequestStatus::Acknowledged], true)) {
                $locked->status = RequestStatus::Assigned;
            }
            if ($locked->acknowledged_at === null && $locked->status !== RequestStatus::Submitted) {
                $locked->acknowledged_at = now();
            }
            $locked->save();

            RequestAssignment::query()->create([
                'request_id' => $locked->id,
                'hotel_id' => $locked->hotel_id,
                'department_id' => $department->id,
                'assigned_to_user_id' => $assignee?->id,
                'assigned_by_user_id' => $actor->id,
            ]);

            if ($from !== $locked->status) {
                $this->history($locked, $from, $locked->status, $actor, null, false);
            }

            AuditLogger::record($actor, 'request.assigned', $locked, [
                'department_id' => $department->id,
                'assigned_to_user_id' => $assignee?->id,
            ], $ip, $locked->hotel_id);

            if ($assignee !== null) {
                NotificationDispatcher::assigned($locked, $assignee);
            }

            return $locked->refresh();
        });
    }

    public function markUnavailable(User $actor, HotelRequest $request, RequestItem $item, ?string $note, ?string $ip = null): HotelRequest
    {
        abort_unless(Access::canView($actor, $request), 404);
        abort_unless((int) $item->request_id === (int) $request->id, 404);
        abort_unless($request->type === RequestType::Order, 422, 'Only menu items on an order can be marked unavailable.');
        abort_unless(in_array($actor->role, [UserRole::HotelAdmin, UserRole::KitchenStaff], true), 403);
        if (in_array($request->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
            abort(422, 'Record a refund before changing items on a paid order.');
        }

        return DB::transaction(function () use ($actor, $request, $item, $note, $ip) {
            $item->is_unavailable = true;
            $item->line_total_minor = 0;
            $item->save();

            $subtotal = (int) $request->items()->where('is_unavailable', false)->sum('line_total_minor');
            $bill = Money::quote($subtotal, (int) $request->service_charge_bps, (int) $request->tax_bps);
            $request->fill($bill)->save();

            $message = trim((string) $note) !== '' ? trim((string) $note) : $item->name.' is unavailable.';
            $visible = (bool) $request->hotel->settings->guest_messages_enabled;
            $request->notes()->create([
                'hotel_id' => $request->hotel_id,
                'user_id' => $actor->id,
                'body' => $message,
                'is_guest_visible' => $visible,
            ]);

            AuditLogger::record($actor, 'request.item_unavailable', $item, [
                'request_id' => $request->id,
                'total_minor' => $request->total_minor,
            ], $ip, $request->hotel_id);

            return $request->refresh();
        });
    }

    /**
     * @return list<RequestStatus>
     */
    private function targets(HotelRequest $request): array
    {
        if ($request->type === RequestType::Order) {
            return match ($request->status) {
                RequestStatus::Submitted => [RequestStatus::Acknowledged, RequestStatus::Accepted, RequestStatus::Rejected, RequestStatus::Cancelled],
                RequestStatus::Acknowledged => [RequestStatus::Accepted, RequestStatus::Rejected, RequestStatus::Cancelled],
                RequestStatus::Accepted => [RequestStatus::Preparing, RequestStatus::Rejected, RequestStatus::Cancelled],
                RequestStatus::Preparing => [RequestStatus::ReadyForDelivery, RequestStatus::Cancelled],
                RequestStatus::ReadyForDelivery => [RequestStatus::Delivered],
                default => [],
            };
        }

        return match ($request->status) {
            RequestStatus::Submitted => [RequestStatus::Acknowledged, RequestStatus::Assigned, RequestStatus::Rejected, RequestStatus::Cancelled],
            RequestStatus::Acknowledged => [RequestStatus::Assigned, RequestStatus::Rejected, RequestStatus::Cancelled],
            RequestStatus::Assigned => [RequestStatus::InProgress, RequestStatus::Rejected, RequestStatus::Cancelled],
            RequestStatus::InProgress => [RequestStatus::Completed, RequestStatus::Cancelled],
            default => [],
        };
    }

    private function roleAllows(User $user, HotelRequest $request, RequestStatus $to): bool
    {
        if (! Access::canView($user, $request)) {
            return false;
        }
        if ($user->role === UserRole::HotelAdmin) {
            return true;
        }

        if ($request->type === RequestType::Order) {
            if ($user->role === UserRole::Receptionist) {
                return in_array($to, [RequestStatus::Acknowledged, RequestStatus::Rejected, RequestStatus::Cancelled], true);
            }
            if ($user->role === UserRole::KitchenStaff) {
                return in_array($to, [
                    RequestStatus::Accepted,
                    RequestStatus::Preparing,
                    RequestStatus::ReadyForDelivery,
                    RequestStatus::Delivered,
                    RequestStatus::Rejected,
                ], true);
            }

            return false;
        }

        if ($user->role === UserRole::Receptionist) {
            return in_array($to, [RequestStatus::Acknowledged, RequestStatus::Assigned, RequestStatus::Rejected, RequestStatus::Cancelled], true);
        }

        if ($user->role === UserRole::ServiceStaff) {
            return in_array($to, [RequestStatus::InProgress, RequestStatus::Completed], true)
                && $this->assignedTo($request, $user);
        }

        return false;
    }

    private function assignedTo(HotelRequest $request, User $user): bool
    {
        $latest = $request->assignments()->latest('id')->first();

        return $latest !== null && (int) $latest->assigned_to_user_id === (int) $user->id;
    }

    private function history(
        HotelRequest $request,
        RequestStatus $from,
        RequestStatus $to,
        ?User $actor,
        ?string $note,
        bool $guestVisible,
    ): void {
        RequestStatusHistory::query()->create([
            'request_id' => $request->id,
            'hotel_id' => $request->hotel_id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by_user_id' => $actor?->id,
            'note' => $note,
            'guest_visible' => $guestVisible,
        ]);
    }
}
