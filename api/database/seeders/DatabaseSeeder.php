<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $password = (string) config('hotel.seed_password');
        if (strlen($password) < 10) {
            $this->command?->error('Set SEED_PASSWORD in .env to at least 10 characters before seeding.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
