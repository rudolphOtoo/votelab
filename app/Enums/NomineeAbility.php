<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nominees. Phase-limited grants use the phase of the nominee category. Grants live in App\Support\PermissionMatrix.
 */
enum NomineeAbility: string implements Ability
{
    case NomineeView = 'nominee.view';
    case NomineeCreate = 'nominee.create';
    case NomineeUpdateProfile = 'nominee.updateProfile';
    case NomineeRename = 'nominee.rename';
    case NomineeReorder = 'nominee.reorder';
    case NomineeChangeCode = 'nominee.changeCode';
    case NomineeChangeCategory = 'nominee.changeCategory';
    case NomineeArchive = 'nominee.archive';
    case NomineeDelete = 'nominee.delete';

    public function description(): string
    {
        return match ($this) {
            self::NomineeView => 'See a nominee',
            self::NomineeCreate => 'Add a nominee',
            self::NomineeUpdateProfile => 'Edit photo and bio',
            self::NomineeRename => 'Edit name and stage name',
            self::NomineeReorder => 'Change display order',
            self::NomineeChangeCode => 'Change the nominee code',
            self::NomineeChangeCategory => 'Move to another category (both Draft)',
            self::NomineeArchive => 'Archive (hidden; new votes refused; existing votes kept)',
            self::NomineeDelete => 'Delete a nominee with no vote records or transactions',
        };
    }
}
