<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HotelRequest;
use App\Models\User;
use App\Support\Access;
use App\Support\Money;

final class RequestPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function guest(HotelRequest $request): array
    {
        $request->loadMissing(['room', 'items', 'statusHistories', 'notes', 'feedback', 'attachments', 'hotel.settings']);
        $settings = $request->hotel->settings;
        $done = $request->status->isDone();
        $existing = $request->feedback;

        return [
            'reference' => $request->reference,
            'type' => $request->type->value,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'room_number' => $request->room->number,
            'guest_note' => $request->guest_note,
            'items' => $request->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'line_total_minor' => (int) $item->line_total_minor,
                'unit_price_display' => Money::format((int) $item->unit_price_minor, $request->currency),
                'line_total_display' => Money::format((int) $item->line_total_minor, $request->currency),
                'notes' => $item->notes,
                'is_unavailable' => (bool) $item->is_unavailable,
            ])->values(),
            'totals' => self::totals($request),
            'payment_method' => $request->payment_method?->value,
            'payment_status' => $request->payment_status->value,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'response_target_at' => $request->due_at?->toIso8601String(),
            'outside_service_hours' => (bool) $request->outside_service_hours,
            'history' => $request->statusHistories->map(fn ($row) => [
                'from' => $row->from_status,
                'to' => $row->to_status,
                'at' => $row->created_at?->toIso8601String(),
                'message' => $row->guest_visible ? $row->note : null,
            ])->values(),
            'messages' => $request->notes->where('is_guest_visible', true)->map(fn ($note) => [
                'body' => $note->body,
                'at' => $note->created_at?->toIso8601String(),
            ])->values(),
            'attachments' => $request->attachments->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime' => $file->mime,
            ])->values(),
            'feedback' => [
                'can_submit' => $done && ($existing === null || (bool) $settings->feedback_revisions),
                'existing' => $existing === null ? null : [
                    'rating' => (int) $existing->rating,
                    'comment' => $existing->comment,
                    'category' => $existing->category,
                ],
            ],
            'can_cancel' => $request->status->value === 'submitted',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function staff(HotelRequest $request, User $viewer): array
    {
        $request->loadMissing([
            'room',
            'department',
            'items',
            'assignments.assignee',
            'assignments.assigner',
            'assignments.department',
            'statusHistories.user',
            'notes.author',
            'feedback',
            'attachments',
        ]);

        $workflow = app(RequestWorkflow::class);

        return [
            'id' => $request->id,
            'reference' => $request->reference,
            'type' => $request->type->value,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'priority' => $request->priority->value,
            'room' => [
                'id' => $request->room->id,
                'number' => $request->room->number,
                'floor' => $request->room->floor,
                'room_type' => $request->room->room_type,
            ],
            'department' => $request->department === null ? null : [
                'id' => $request->department->id,
                'name' => $request->department->name,
                'code' => $request->department->code->value,
            ],
            'guest_note' => $request->guest_note,
            'items' => $request->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'line_total_minor' => (int) $item->line_total_minor,
                'notes' => $item->notes,
                'is_unavailable' => (bool) $item->is_unavailable,
            ])->values(),
            'totals' => self::totals($request),
            'payment_method' => $request->payment_method?->value,
            'payment_status' => $request->payment_status->value,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'acknowledged_at' => $request->acknowledged_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'due_at' => $request->due_at?->toIso8601String(),
            'overdue' => $request->due_at !== null && ! $request->status->isTerminal() && $request->due_at->isPast(),
            'outside_service_hours' => (bool) $request->outside_service_hours,
            'allowed_transitions' => array_map(
                fn ($status) => $status->value,
                $workflow->allowedFor($viewer, $request),
            ),
            'can_assign' => Access::canAssign($viewer) || $viewer->role->value === 'service_staff',
            'can_set_priority' => Access::canSetPriority($viewer, $request),
            'can_record_payment' => Access::canRecordPayment($viewer, $request),
            'history' => $request->statusHistories->map(fn ($row) => [
                'from' => $row->from_status,
                'to' => $row->to_status,
                'at' => $row->created_at?->toIso8601String(),
                'note' => $row->note,
                'guest_visible' => (bool) $row->guest_visible,
                'by' => $row->user?->name,
            ])->values(),
            'notes' => $request->notes->map(fn ($note) => [
                'id' => $note->id,
                'body' => $note->body,
                'guest_visible' => (bool) $note->is_guest_visible,
                'at' => $note->created_at?->toIso8601String(),
                'by' => $note->author?->name,
            ])->values(),
            'assignments' => $request->assignments->map(fn ($assignment) => [
                'department' => $assignment->department?->name,
                'assignee' => $assignment->assignee?->name,
                'by' => $assignment->assigner?->name,
                'at' => $assignment->created_at?->toIso8601String(),
            ])->values(),
            'attachments' => $request->attachments->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime' => $file->mime,
                'size' => (int) $file->size,
            ])->values(),
            'feedback' => $request->feedback === null ? null : [
                'rating' => (int) $request->feedback->rating,
                'comment' => $request->feedback->comment,
                'category' => $request->feedback->category,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function totals(HotelRequest $request): array
    {
        return [
            'subtotal_minor' => (int) $request->subtotal_minor,
            'service_charge_minor' => (int) $request->service_charge_minor,
            'tax_minor' => (int) $request->tax_minor,
            'total_minor' => (int) $request->total_minor,
            'currency' => $request->currency,
            'subtotal_display' => Money::format((int) $request->subtotal_minor, $request->currency),
            'service_charge_display' => Money::format((int) $request->service_charge_minor, $request->currency),
            'tax_display' => Money::format((int) $request->tax_minor, $request->currency),
            'total_display' => Money::format((int) $request->total_minor, $request->currency),
            'service_charge_label' => Money::bpsLabel((int) $request->service_charge_bps),
            'tax_label' => Money::bpsLabel((int) $request->tax_bps),
        ];
    }
}
