<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    /**
     * Owners and admins may change programs, prices and category status.
     * Staff may manage nominees and view results.
     */
    public function canManageProgram(): bool
    {
        return $this !== self::Staff;
    }
}
