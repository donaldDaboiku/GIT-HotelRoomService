<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QrTokenStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\HotelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\Scenario;
use Tests\TestCase;

class HotelOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_in_and_log_out(): void
    {
        $world = Scenario::make();
        $login = $this->postJson('/api/v1/staff/login', [
            'email' => $world->admin->email,
            'password' => Scenario::PASSWORD,
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/staff/me')->assertOk()->assertJsonPath('data.email', $world->admin->email);
        $this->withToken($token)->postJson('/api/v1/staff/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/staff/me')->assertUnauthorized();
    }

    public function test_roles_and_hotels_are_isolated(): void
    {
        $aurora = Scenario::make('aurora-iso');
        $harbour = Scenario::make('harbour-iso');
        $guest = $this->guest($aurora);
        $this->withHeader('X-Guest-Token', $guest)
            ->postJson('/api/v1/guest/requests', [
                'type' => 'service',
                'service_id' => $aurora->towels->id,
                'quantity' => 1,
            ])->assertCreated();

        $requestId = HotelRequest::query()->firstOrFail()->id;
        $this->withToken($harbour->admin->createToken('t')->plainTextToken)
            ->getJson('/api/v1/staff/requests/'.$requestId)
            ->assertNotFound();
        $this->withToken($aurora->kitchen->createToken('t')->plainTextToken)
            ->getJson('/api/v1/staff/requests/'.$requestId)
            ->assertNotFound();
        $this->withToken($aurora->admin->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/rooms/'.$harbour->room->id.'/history')
            ->assertNotFound();

        $platform = User::query()->create([
            'name' => 'Platform',
            'email' => 'platform@iso.test',
            'password' => Scenario::PASSWORD,
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);
        $this->withToken($platform->createToken('t')->plainTextToken)
            ->getJson('/api/v1/staff/requests')
            ->assertForbidden();
        $this->withToken($platform->createToken('p')->plainTextToken)
            ->getJson('/api/v1/platform/hotels')
            ->assertOk()
            ->assertJsonMissing(['guest_note' => 'secret']);
    }

    public function test_revoked_and_invalid_qr_codes_cannot_open_a_session(): void
    {
        $world = Scenario::make('qr-test');
        $this->postJson('/api/v1/guest/sessions', [
            'hotel_code' => 'missing-hotel',
            'room_code' => $world->roomCode,
        ])->assertNotFound();

        $this->postJson('/api/v1/guest/sessions', [
            'hotel_code' => $world->hotel->public_code,
            'room_code' => 'notarealroomcodevalue',
        ])->assertNotFound();

        $world->room->activeQr->forceFill(['status' => QrTokenStatus::Revoked, 'revoked_at' => now()])->save();
        $this->postJson('/api/v1/guest/sessions', [
            'hotel_code' => $world->hotel->public_code,
            'room_code' => $world->roomCode,
        ])->assertNotFound()->assertJsonPath('message', 'This room link is no longer active.');
    }

    public function test_guest_request_is_validated_and_private(): void
    {
        $world = Scenario::make('guest-req');
        $token = $this->guest($world);
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', [
            'type' => 'service',
            'service_id' => $world->airConditioning->id,
        ])->assertUnprocessable();

        $created = $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', [
            'type' => 'service',
            'service_id' => $world->towels->id,
            'quantity' => 2,
            'guest_note' => 'Extra towels',
            'status' => 'completed',
        ]);
        $created->assertUnprocessable();

        $created = $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', [
            'type' => 'service',
            'service_id' => $world->towels->id,
            'quantity' => 2,
            'guest_note' => 'Extra towels',
        ])->assertCreated();

        $reference = $created->json('data.reference');
        $this->assertSame('submitted', HotelRequest::query()->where('reference', $reference)->firstOrFail()->status->value);

        $other = $this->guest($world);
        $this->withHeader('X-Guest-Token', $other)->getJson('/api/v1/guest/requests/'.$reference)->assertNotFound();
        $this->withHeader('X-Guest-Token', $token)->getJson('/api/v1/guest/requests/'.$reference)->assertOk();
    }

    public function test_order_totals_are_calculated_on_the_server(): void
    {
        $world = Scenario::make('orders');
        $token = $this->guest($world);
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/orders', [
            'payment_method' => 'cash_delivery',
            'total_minor' => 1,
            'items' => [['menu_item_id' => $world->jollof->id, 'quantity' => 2, 'price_minor' => 1]],
        ])->assertUnprocessable();

        $response = $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/orders', [
            'payment_method' => 'cash_delivery',
            'items' => [['menu_item_id' => $world->jollof->id, 'quantity' => 2, 'notes' => 'No pepper']],
        ])->assertCreated();

        $response->assertJsonPath('data.totals.subtotal_minor', 200000);
        $response->assertJsonPath('data.totals.service_charge_minor', 20000);
        $response->assertJsonPath('data.totals.tax_minor', 16500);
        $response->assertJsonPath('data.totals.total_minor', 236500);
        $response->assertJsonPath('data.payment_status', 'unpaid');
        $this->assertSame(100000, (int) HotelRequest::query()->firstOrFail()->items()->firstOrFail()->unit_price_minor);
    }

    public function test_status_transitions_follow_role_and_sequence(): void
    {
        $world = Scenario::make('flow');
        $token = $this->guest($world);
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', [
            'type' => 'service',
            'service_id' => $world->towels->id,
            'quantity' => 1,
        ])->assertCreated();
        $id = HotelRequest::query()->firstOrFail()->id;

        $admin = $world->admin->createToken('t')->plainTextToken;
        $this->withToken($admin)->patchJson('/api/v1/staff/requests/'.$id.'/status', [
            'status' => 'completed',
        ])->assertStatus(422);

        $this->withToken($world->reception->createToken('t')->plainTextToken)
            ->postJson('/api/v1/staff/requests/'.$id.'/assign', [
                'department_id' => $world->housekeeping->department_id,
                'user_id' => $world->housekeeping->id,
            ])->assertOk()->assertJsonPath('data.status', 'assigned');

        $this->withToken($world->kitchen->createToken('t')->plainTextToken)
            ->patchJson('/api/v1/staff/requests/'.$id.'/status', ['status' => 'in_progress'])
            ->assertNotFound();

        $house = $world->housekeeping->createToken('t')->plainTextToken;
        $this->withToken($house)->patchJson('/api/v1/staff/requests/'.$id.'/status', [
            'status' => 'in_progress',
        ])->assertOk();
        $this->withToken($house)->patchJson('/api/v1/staff/requests/'.$id.'/status', [
            'status' => 'completed',
            'note' => 'Towels delivered',
            'guest_visible' => true,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $id,
            'to_status' => 'completed',
            'changed_by_user_id' => $world->housekeeping->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request.status',
            'user_id' => $world->housekeeping->id,
        ]);
    }

    public function test_attachments_reject_non_images_and_store_photos_privately(): void
    {
        $world = Scenario::make('files');
        $token = $this->guest($world);
        $headers = ['X-Guest-Token' => $token, 'Accept' => 'application/json'];
        $this->withHeaders($headers)->post('/api/v1/guest/requests', [
            'type' => 'maintenance',
            'service_id' => $world->airConditioning->id,
            'guest_note' => 'Not cooling',
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertUnprocessable();

        $png = UploadedFile::fake()->image('vent.png', 8, 8);
        $this->withHeaders($headers)->post('/api/v1/guest/requests', [
            'type' => 'maintenance',
            'service_id' => $world->airConditioning->id,
            'guest_note' => 'Not cooling',
            'photo' => $png,
        ])->assertCreated();

        $path = \App\Models\Attachment::query()->firstOrFail()->path;
        $this->assertStringStartsWith('attachments/'.$world->hotel->id.'/', $path);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists($path));
    }

    public function test_duplicate_submissions_are_rejected(): void
    {
        $world = Scenario::make('dup');
        $token = $this->guest($world);
        $payload = [
            'type' => 'service',
            'service_id' => $world->towels->id,
            'quantity' => 1,
            'guest_note' => 'Same note',
        ];
        $first = $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', $payload)->assertCreated();
        $this->withHeader('X-Guest-Token', $token)
            ->postJson('/api/v1/guest/requests', $payload)
            ->assertStatus(409)
            ->assertJsonPath('data.reference', $first->json('data.reference'));
    }

    public function test_reports_separate_unpaid_orders_and_honor_dates(): void
    {
        $world = Scenario::make('reports');
        $token = $this->guest($world);
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/orders', [
            'payment_method' => 'pay_reception',
            'items' => [['menu_item_id' => $world->jollof->id, 'quantity' => 1]],
        ])->assertCreated();
        $order = HotelRequest::query()->firstOrFail();
        $admin = $world->admin->createToken('t')->plainTextToken;
        $this->withToken($admin)->postJson('/api/v1/staff/requests/'.$order->id.'/payment', [
            'payment_status' => 'paid',
        ])->assertOk();

        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/orders', [
            'payment_method' => 'cash_delivery',
            'guest_note' => 'second',
            'items' => [['menu_item_id' => $world->jollof->id, 'quantity' => 1]],
        ])->assertCreated();

        $report = $this->withToken($admin)->getJson('/api/v1/admin/reports/requests')->assertOk();
        $report->assertJsonPath('data.food_payments_received_minor', 118250);
        $report->assertJsonPath('data.food_unpaid_minor', 118250);
        $report->assertJsonPath('data.food_order_value_minor', 236500);

        HotelRequest::query()->where('payment_status', 'unpaid')->update(['submitted_at' => now()->subMonths(2)]);
        $today = now('Africa/Lagos')->toDateString();
        $filtered = $this->withToken($admin)->getJson('/api/v1/admin/reports/requests?from='.$today.'&to='.$today);
        $filtered->assertOk()->assertJsonPath('data.food_unpaid_minor', 0);
        $filtered->assertJsonPath('data.food_payments_received_minor', 118250);
    }

    public function test_feedback_is_allowed_once_after_completion(): void
    {
        $world = Scenario::make('feedback');
        $token = $this->guest($world);
        $reference = $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests', [
            'type' => 'service',
            'service_id' => $world->towels->id,
        ])->json('data.reference');

        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests/'.$reference.'/feedback', [
            'rating' => 5,
        ])->assertStatus(422);

        $id = HotelRequest::query()->where('reference', $reference)->firstOrFail()->id;
        $this->withToken($world->reception->createToken('t')->plainTextToken)->postJson('/api/v1/staff/requests/'.$id.'/assign', [
            'department_id' => $world->housekeeping->department_id,
            'user_id' => $world->housekeeping->id,
        ])->assertOk();
        $house = $world->housekeeping->createToken('t')->plainTextToken;
        $this->withToken($house)->patchJson('/api/v1/staff/requests/'.$id.'/status', ['status' => 'in_progress'])->assertOk();
        $this->withToken($house)->patchJson('/api/v1/staff/requests/'.$id.'/status', ['status' => 'completed'])->assertOk();

        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests/'.$reference.'/feedback', [
            'rating' => 4,
            'comment' => 'Prompt',
            'category' => 'Housekeeping',
        ])->assertOk();
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/requests/'.$reference.'/feedback', [
            'rating' => 3,
        ])->assertStatus(409);
    }

    public function test_validation_errors_use_a_json_body(): void
    {
        $world = Scenario::make('errors');
        $this->postJson('/api/v1/staff/login', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        $token = $this->guest($world);
        $this->withHeader('X-Guest-Token', $token)->postJson('/api/v1/guest/orders', [
            'items' => [],
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['items']]);
    }

    private function guest(Scenario $world): string
    {
        return $this->postJson('/api/v1/guest/sessions', [
            'hotel_code' => $world->hotel->public_code,
            'room_code' => $world->roomCode,
        ])->assertCreated()->json('data.token');
    }
}
