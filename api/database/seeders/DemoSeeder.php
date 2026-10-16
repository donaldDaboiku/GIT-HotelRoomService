<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Hotel::query()->where('public_code', 'aurora-lagos')->exists()) {
            $this->command?->info('Demo hotels are already present.');

            return;
        }

        $password = (string) config('hotel.seed_password');
        $provisioner = app(HotelProvisioner::class);
        $qr = app(QrCodeService::class);

        User::query()->create([
            'name' => 'GIT Platform Admin',
            'email' => 'platform@gitsystem.test',
            'password' => $password,
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);

        $aurora = $this->hotel($provisioner, 'Aurora Suites Lagos', 'aurora-lagos', 'Welcome to Aurora Suites. Your room team is ready when you are.');
        $aurora->settings->update([
            'service_charge_bps' => 1000,
            'tax_bps' => 750,
            'guest_info' => 'Dial reception from the room phone, or send a request here. Service hours are a target, not a guaranteed arrival time.',
        ]);
        $this->catalog($aurora);
        $this->staff($aurora, $password, 'aurora.test');
        $admin = User::query()->where('email', 'ada.admin@aurora.test')->firstOrFail();
        foreach ([['101', '1', 'Deluxe'], ['214', '2', 'Suite']] as [$number, $floor, $type]) {
            $room = Room::query()->create([
                'hotel_id' => $aurora->id,
                'number' => $number,
                'floor' => $floor,
                'room_type' => $type,
                'is_active' => true,
            ]);
            $qr->issue($room, $admin);
        }
        $this->logo($aurora, 'Aurora');

        $harbour = $this->hotel($provisioner, 'Harbour Inn', 'harbour-inn', 'Welcome to Harbour Inn.');
        $this->staff($harbour, $password, 'harbour.test');
        $harbourAdmin = User::query()->where('email', 'admin@harbour.test')->firstOrFail();
        $room = Room::query()->create([
            'hotel_id' => $harbour->id,
            'number' => '12',
            'floor' => '1',
            'room_type' => 'Standard',
            'is_active' => true,
        ]);
        $qr->issue($room, $harbourAdmin);

        $this->command?->info('Demo accounts use the SEED_PASSWORD from .env.');
        $this->command?->call('hotel:links');
    }

    private function hotel(HotelProvisioner $provisioner, string $name, string $code, string $welcome): Hotel
    {
        return $provisioner->create([
            'name' => $name,
            'public_code' => $code,
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'welcome_message' => $welcome,
            'contact_phone' => '+234 1 700 0000',
            'address' => 'Lagos, Nigeria',
        ]);
    }

    private function staff(Hotel $hotel, string $password, string $domain): void
    {
        $department = fn (DepartmentCode $code) => Department::query()
            ->where('hotel_id', $hotel->id)
            ->where('code', $code->value)
            ->firstOrFail();

        $rows = $domain === 'aurora.test'
            ? [
                ['Ada Okonkwo', 'ada.admin@aurora.test', UserRole::HotelAdmin, null],
                ['Tunde Bakare', 'tunde.reception@aurora.test', UserRole::Receptionist, $department(DepartmentCode::Reception)->id],
                ['Chioma Eze', 'chioma.housekeeping@aurora.test', UserRole::ServiceStaff, $department(DepartmentCode::Housekeeping)->id],
                ['Ibrahim Musa', 'ibrahim.kitchen@aurora.test', UserRole::KitchenStaff, $department(DepartmentCode::Kitchen)->id],
                ['Ngozi Adeyemi', 'ngozi.maintenance@aurora.test', UserRole::ServiceStaff, $department(DepartmentCode::Maintenance)->id],
            ]
            : [
                ['Harbour Admin', 'admin@harbour.test', UserRole::HotelAdmin, null],
            ];

        foreach ($rows as [$name, $email, $role, $departmentId]) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'hotel_id' => $hotel->id,
                'department_id' => $departmentId,
                'role' => $role,
                'status' => UserStatus::Active,
            ]);
        }
    }

    private function catalog(Hotel $hotel): void
    {
        $department = fn (DepartmentCode $code) => Department::query()
            ->where('hotel_id', $hotel->id)
            ->where('code', $code->value)
            ->firstOrFail();

        $house = ServiceCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Housekeeping', 'kind' => ServiceKind::Housekeeping, 'sort_order' => 1]);
        $care = ServiceCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Room care', 'kind' => ServiceKind::Other, 'sort_order' => 2]);
        $faults = ServiceCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Room faults', 'kind' => ServiceKind::Maintenance, 'sort_order' => 3]);
        $desk = ServiceCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Reception', 'kind' => ServiceKind::Reception, 'sort_order' => 4]);

        $services = [
            [$house, $department(DepartmentCode::Housekeeping), 'Fresh towels', 'A set of bath and hand towels.', 0],
            [$house, $department(DepartmentCode::Housekeeping), 'Toiletries', 'Soap, shampoo, and dental kit.', 0],
            [$house, $department(DepartmentCode::Housekeeping), 'Room cleaning', 'A tidy of the room during service hours.', 0],
            [$house, $department(DepartmentCode::Housekeeping), 'Bed linen replacement', 'Fresh sheets and pillowcases.', 0],
            [$care, $department(DepartmentCode::Laundry), 'Laundry pickup', 'We collect clothes from the room.', 250000],
            [$desk, $department(DepartmentCode::Reception), 'Speak with reception', 'A note for the front desk.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Air conditioning', 'Cooling, heating, or airflow problems.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Electrical fault', 'Lights, sockets, or power issues.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Plumbing or water', 'Leaks, drains, or water pressure.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Television or remote', 'Picture, sound, or remote-control issues.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Wi-Fi', 'Connection trouble in the room.', 0],
            [$faults, $department(DepartmentCode::Maintenance), 'Other room fault', 'Anything else that needs a technician.', 0],
        ];
        foreach ($services as $index => [$category, $dept, $name, $description, $fee]) {
            Service::query()->create([
                'hotel_id' => $hotel->id,
                'service_category_id' => $category->id,
                'department_id' => $dept->id,
                'name' => $name,
                'description' => $description,
                'is_available' => true,
                'fee_minor' => $fee,
                'sort_order' => $index + 1,
            ]);
        }

        $mains = MenuCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Mains', 'sort_order' => 1, 'is_available' => true]);
        $drinks = MenuCategory::query()->create(['hotel_id' => $hotel->id, 'name' => 'Drinks', 'sort_order' => 2, 'is_available' => true]);
        $items = [
            [$mains, 'Jollof rice', 'Smoky party jollof with grilled chicken.', 450000, 'Contains gluten', 'jollof'],
            [$mains, 'Grilled croaker', 'Charcoal croaker, pepper sauce, and plantain.', 850000, 'Fish', 'croaker'],
            [$mains, 'Club sandwich', 'Chicken, egg, and vegetables on toasted bread.', 380000, null, 'sandwich'],
            [$drinks, 'Chapman', 'House fruit punch.', 250000, null, 'chapman'],
            [$drinks, 'Bottled water', 'Still water, 75cl.', 80000, null, 'water'],
            [$drinks, 'Coffee', 'Freshly brewed coffee.', 150000, null, 'coffee'],
        ];
        foreach ($items as $index => [$category, $name, $description, $price, $diet, $art]) {
            $path = 'menu/'.$art.'.svg';
            Storage::disk('public')->put($path, $this->svg($name));
            MenuItem::query()->create([
                'hotel_id' => $hotel->id,
                'menu_category_id' => $category->id,
                'name' => $name,
                'description' => $description,
                'price_minor' => $price,
                'image_path' => $path,
                'is_available' => true,
                'is_sold_out' => false,
                'dietary_info' => $diet,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function logo(Hotel $hotel, string $mark): void
    {
        $path = 'logos/'.$hotel->public_code.'.svg';
        Storage::disk('public')->put($path, $this->svg($mark));
        $hotel->forceFill(['logo_path' => $path])->save();
    }

    private function svg(string $label): string
    {
        $safe = htmlspecialchars($label, ENT_QUOTES);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480">
          <rect width="640" height="480" fill="#1F4D3A"/>
          <circle cx="320" cy="190" r="72" fill="#F6F3EE"/>
          <text x="320" y="320" text-anchor="middle" fill="#F6F3EE" font-family="Georgia" font-size="42">{$safe}</text>
        </svg>
        SVG;
    }
}
