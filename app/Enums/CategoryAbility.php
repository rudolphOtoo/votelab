<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categories, including status changes (each move is its own ability). Grants live in App\Support\PermissionMatrix.
 */
enum CategoryAbility: string implements Ability
{
    case CategoryView = 'category.view';
    case CategoryCreate = 'category.create';
    case CategoryUpdateDetails = 'category.updateDetails';
    case CategoryUpdatePricing = 'category.updatePricing';
    case CategoryReorder = 'category.reorder';
    case CategoryOpen = 'category.open';
    case CategoryEmbargo = 'category.embargo';
    case CategoryUnembargo = 'category.unembargo';
    case CategoryClose = 'category.close';
    case CategoryReveal = 'category.reveal';
    case CategoryDelete = 'category.delete';

    public function description(): string
    {
        return match ($this) {
            self::CategoryView => 'See a category',
            self::CategoryCreate => 'Create a category (program not archived)',
            self::CategoryUpdateDetails => 'Edit name and description',
            self::CategoryUpdatePricing => 'Edit voting mode, price per vote, free-vote limit',
            self::CategoryReorder => 'Change display order',
            self::CategoryOpen => 'Open voting (Draft → Open)',
            self::CategoryEmbargo => 'Hide results (→ Embargoed)',
            self::CategoryUnembargo => 'Show results again (Embargoed → Open)',
            self::CategoryClose => 'Close voting (→ Closed)',
            self::CategoryReveal => 'Reveal the winner (Closed → Revealed)',
            self::CategoryDelete => 'Delete a category',
        };
    }

    /**
     * The ability a status change needs (unembargo is Embargoed → Open), or null for a move no
     * ability covers. Whether CategoryStatus allows the move at all is checked separately.
     */
    public static function forMove(CategoryStatus $from, CategoryStatus $to): ?self
    {
        return match (true) {
            $from === CategoryStatus::Draft && $to === CategoryStatus::Open => self::CategoryOpen,
            $to === CategoryStatus::Embargoed => self::CategoryEmbargo,
            $from === CategoryStatus::Embargoed && $to === CategoryStatus::Open => self::CategoryUnembargo,
            $to === CategoryStatus::Closed => self::CategoryClose,
            $to === CategoryStatus::Revealed => self::CategoryReveal,
            default => null,
        };
    }
}
