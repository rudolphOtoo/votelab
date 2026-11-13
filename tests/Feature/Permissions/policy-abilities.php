<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;

/**
 * Every policy ability: [Gate ability, matrix ability (or 'move' for status changes), arguments built from the world, category statuses
 * in which the policy's data rules are satisfied (others are expected to be refused for every role)].
 */

$all = CategoryStatus::cases();
$org = static fn(array $w): array => [$w['organization']];
$program = static fn(array $w): array => [$w['program']];
$category = static fn(array $w): array => [$w['category']];
$nominee = static fn(array $w): array => [$w['nominee']];
return [
    'organization view' => ['view', 'organization.view', $org, $all],
    'organization updateProfile' => ['updateProfile', 'organization.updateProfile', $org, $all],
    'organization updateSlug' => ['updateSlug', 'organization.updateSlug', $org, $all],
    'organization updatePayoutSettings' => [
        'updatePayoutSettings',
        'organization.updatePayoutSettings',
        $org,
        $all,
    ],
    'members viewMembers' => ['viewMembers', 'members.view', $org, $all],
    'members manageMembers' => ['manageMembers', 'members.manage', $org, $all],
    'members leave' => ['leave', 'members.leave', $org, $all],
    'program view' => ['view', 'program.view', $program, $all],
    'program create' => [
        'create',
        'program.create',
        static fn(array $w): array => [AwardProgram::class, $w['organization']],
        $all,
    ],
    'program update' => ['update', 'program.update', $program, $all],
    'program archive' => ['archive', 'program.archive', $program, $all],
    'program exportResults' => ['exportResults', 'results.export', $program, $all],
    'program auditResults' => ['auditResults', 'results.audit', $program, $all],
    'program viewTransactions' => ['viewTransactions', 'transactions.view', $program, $all],
    'program viewRefunds' => ['viewRefunds', 'refunds.view', $program, $all],
    'program recordRefunds' => ['recordRefunds', 'refunds.record', $program, $all],
    'program viewRevenue' => ['viewRevenue', 'revenue.view', $program, $all],
    'category view' => ['view', 'category.view', $category, $all],
    'category create' => [
        'create',
        'category.create',
        static fn(array $w): array => [AwardCategory::class, $w['program']],
        $all,
    ],
    'category updateDetails' => ['updateDetails', 'category.updateDetails', $category, $all],
    'category updatePricing' => ['updatePricing', 'category.updatePricing', $category, $all],
    'category reorder' => ['reorder', 'category.reorder', $category, $all],
    'category → open' => [
        'changeStatus',
        'move',
        static fn(array $w): array => [$w['category'], CategoryStatus::Open],
        [CategoryStatus::Draft, CategoryStatus::Embargoed],
    ],
    'category → embargoed' => [
        'changeStatus',
        'move',
        static fn(array $w): array => [$w['category'], CategoryStatus::Embargoed],
        [CategoryStatus::Draft, CategoryStatus::Open],
    ],
    'category → closed' => [
        'changeStatus',
        'move',
        static fn(array $w): array => [$w['category'], CategoryStatus::Closed],
        [CategoryStatus::Open, CategoryStatus::Embargoed],
    ],
    'category → revealed' => [
        'changeStatus',
        'move',
        static fn(array $w): array => [$w['category'], CategoryStatus::Revealed],
        [CategoryStatus::Closed],
    ],
    'category delete' => ['delete', 'category.delete', $category, $all],
    'category viewResults' => [
        'viewResults',
        'results.view',
        $category,
        [CategoryStatus::Open, CategoryStatus::Revealed],
    ],
    'category viewHiddenResults' => [
        'viewHiddenResults',
        'results.viewHidden',
        $category,
        [CategoryStatus::Draft, CategoryStatus::Embargoed, CategoryStatus::Closed],
    ],
    'nominee view' => ['view', 'nominee.view', $nominee, $all],
    'nominee create' => [
        'create',
        'nominee.create',
        static fn(array $w): array => [Nominee::class, $w['category']],
        $all,
    ],
    'nominee updateProfile' => ['updateProfile', 'nominee.updateProfile', $nominee, $all],
    'nominee rename' => ['rename', 'nominee.rename', $nominee, $all],
    'nominee reorder' => ['reorder', 'nominee.reorder', $nominee, $all],
    'nominee changeCode' => ['changeCode', 'nominee.changeCode', $nominee, $all],
    'nominee changeCategory' => [
        'changeCategory',
        'nominee.changeCategory',
        static function (array $w): array {
            $target = AwardCategory::factory()->for($w['program'])->status($w['category']->status)->create();
            return [$w['nominee'], $target];
        },
        $all,
    ],
    'nominee archive' => ['archive', 'nominee.archive', $nominee, $all],
    'nominee delete' => ['delete', 'nominee.delete', $nominee, $all],
];
