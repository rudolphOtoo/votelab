<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Support\DedicatedSite;
use Illuminate\Database\Seeder;

final class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_MULTI_TENANT);
    }
}
