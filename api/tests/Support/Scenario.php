<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\DepartmentCode;
use App\Enums\ServiceKind;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\HotelProvisioner;
use App\Services\QrCodeService;

final class Scenario
{
    public Hotel $hotel;

    public User $admin;

    public User $reception;

    public User $housekeeping;

    public User $kitchen;

    public User $maintenance;

    public Room $room;

    public string $roomCode;

    public Service $towels;

    public Service $airConditioning;

    public MenuItem $jollof;

    public const PASSWORD = 'ChangeMe-local-only';

    public static function make(string $code = 'aurora-test'): self
    {
        $scenario = new self;
        $scenario->hotel = app(HotelProvisioner::class)->create([
            'name' => 'Test Hotel '.$code,
            'public_code' => $code,
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'service_charge_bps' => 1000,
            'tax_bps' => 750,
        ]);
        $scenario->hotel->settings->update([
            'service_charge_bps' => 1000,
            'tax_bps' => 750,
        ]);

        $department = fn (DepartmentCode $departmentCode) => Department::query()
            ->where('hotel_id', $scenario->hotel->id)
            ->where('code', $departmentCode->value)
            ->firstOrFail();

        $scenario->admin = $scenario->user('Admin', 'admin@'.$code.'.test', UserRole::HotelAdmin, null);
        $scenario->reception = $scenario->user('Reception', 'reception@'.$code.'.test', UserRole::Receptionist, $department(DepartmentCode::Reception)->id);
        $scenario->housekeeping = $scenario->user('Housekeeping', 'house@'.$code.'.test', UserRole::ServiceStaff, $department(DepartmentCode::Housekeeping)->id);
        $scenario->kitchen = $scenario->user('Kitchen', 'kitchen@'.$code.'.test', UserRole::KitchenStaff, $department(DepartmentCode::Kitchen)->id);
        $scenario->maintenance = $scenario->user('Maintenance', 'maint@'.$code.'.test', UserRole::ServiceStaff, $department(DepartmentCode::Maintenance)->id);

        $scenario->room = Room::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'number' => '101',
            'floor' => '1',
            'room_type' => 'Deluxe',
            'is_active' => true,
        ]);
        $token = app(QrCodeService::class)->issue($scenario->room, $scenario->admin);
        $scenario->roomCode = $token->public_code;

        $house = ServiceCategory::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'name' => 'Housekeeping',
            'kind' => ServiceKind::Housekeeping,
        ]);
        $faults = ServiceCategory::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'name' => 'Faults',
            'kind' => ServiceKind::Maintenance,
        ]);
        $scenario->towels = Service::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'service_category_id' => $house->id,
            'department_id' => $department(DepartmentCode::Housekeeping)->id,
            'name' => 'Fresh towels',
            'is_available' => true,
            'fee_minor' => 0,
        ]);
        $scenario->airConditioning = Service::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'service_category_id' => $faults->id,
            'department_id' => $department(DepartmentCode::Maintenance)->id,
            'name' => 'Air conditioning',
            'is_available' => true,
            'fee_minor' => 0,
        ]);

        $category = MenuCategory::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'name' => 'Mains',
            'is_available' => true,
        ]);
        $scenario->jollof = MenuItem::query()->create([
            'hotel_id' => $scenario->hotel->id,
            'menu_category_id' => $category->id,
            'name' => 'Jollof rice',
            'price_minor' => 100000,
            'is_available' => true,
            'is_sold_out' => false,
        ]);

        return $scenario;
    }

    public function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function user(string $name, string $email, UserRole $role, ?int $departmentId): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'hotel_id' => $this->hotel->id,
            'department_id' => $departmentId,
            'role' => $role,
            'status' => UserStatus::Active,
        ]);
    }
}
