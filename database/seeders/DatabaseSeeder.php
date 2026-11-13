<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\NeverInProduction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use NeverInProduction;
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->refuseInProduction();

        $this->call([
            PlatformSettingSeeder::class,
            DemoProgramSeeder::class,
        ]);
    }
}
