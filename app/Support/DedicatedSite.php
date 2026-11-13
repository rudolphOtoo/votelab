<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\PlatformSetting;

/**
 * Resolves whether VoteLab runs as an open multi-tenant portal (/org/{slug})
 * or is locked to a single organization's headline program (/ serves it).
 */
final readonly class DedicatedSite
{
    public const string MODE_SETTING = 'deployment_mode';

    public const string ORG_SETTING = 'home_org_slug';

    public const string PROGRAM_SETTING = 'home_program_slug';

    public const string MODE_DEDICATED = 'dedicated';

    public const string MODE_MULTI_TENANT = 'multi_tenant';

    public function __construct(
        public Organization $organization,
        public ?AwardProgram $program,
    ) {}

    public static function resolve(): ?self
    {
        if (PlatformSetting::get(self::MODE_SETTING, self::MODE_MULTI_TENANT) !== self::MODE_DEDICATED) {
            return null;
        }

        $orgSlug = PlatformSetting::get(self::ORG_SETTING);

        if ($orgSlug === null) {
            return null;
        }

        $organization = Organization::query()->where('slug', $orgSlug)->first();

        if ($organization === null) {
            return null;
        }

        return new self($organization, self::resolveProgram($organization));
    }

    private static function resolveProgram(Organization $organization): ?AwardProgram
    {
        $programs = $organization->awardPrograms()->where('status', ProgramStatus::Published);
        $programSlug = PlatformSetting::get(self::PROGRAM_SETTING);

        if ($programSlug !== null) {
            return $programs->where('slug', $programSlug)->first();
        }

        return $programs->latest('starts_at')->latest('id')->first();
    }
}
