<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;

/**
 * An organizer ability. One enum per area implements this; which roles hold each ability (and in which
 * category phases) lives in App\Support\PermissionMatrix, and anything not granted there is denied.
 */
interface Ability extends BackedEnum
{
    /**
     * What the ability covers, and any rule beyond role and phase (enforced in the policy).
     */
    public function description(): string;
}
