<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Award programs. Grants live in App\Support\PermissionMatrix.
 */
enum ProgramAbility: string implements Ability
{
    case ProgramView = 'program.view';
    case ProgramCreate = 'program.create';
    case ProgramUpdate = 'program.update';
    case ProgramPublish = 'program.publish';
    case ProgramArchive = 'program.archive';
    case ProgramDelete = 'program.delete';

    public function description(): string
    {
        return match ($this) {
            self::ProgramView => 'See a program',
            self::ProgramCreate => 'Create a program',
            self::ProgramUpdate => 'Edit title, tagline, description, banner, dates',
            self::ProgramPublish => 'Publish (launch checklist) / unpublish (no category Live)',
            self::ProgramArchive => 'Archive a program',
            self::ProgramDelete => 'Delete a program with no vote records or transactions',
        };
    }
}
