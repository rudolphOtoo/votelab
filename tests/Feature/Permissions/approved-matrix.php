<?php

declare(strict_types=1);

/**
 * The approved matrix (2026-11-12), written out here independently of App\Support\PermissionMatrix so a
 * wrong grant in the code is caught. Per role: '*' = in every phase, '' = denied, otherwise the phase
 * initials it holds in (D = Draft, L = Live, C = Closed, R = Revealed).
 */

return [
    'organization.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'organization.updateProfile' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'organization.updateSlug' => ['owner' => '*', 'admin' => '', 'staff' => ''],
    'organization.updatePayoutSettings' => ['owner' => '*', 'admin' => '', 'staff' => ''],
    'members.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'members.manage' => ['owner' => '*', 'admin' => '', 'staff' => ''],
    'members.leave' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'program.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'program.create' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'program.update' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'program.publish' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'program.archive' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'program.delete' => ['owner' => '*', 'admin' => '', 'staff' => ''],
    'category.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'category.create' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.updateDetails' => ['owner' => 'DLC', 'admin' => 'DLC', 'staff' => ''],
    'category.updatePricing' => ['owner' => 'D', 'admin' => 'D', 'staff' => ''],
    'category.reorder' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.open' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.embargo' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.unembargo' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.close' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.reveal' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'category.delete' => ['owner' => 'D', 'admin' => 'D', 'staff' => ''],
    'nominee.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'nominee.create' => ['owner' => 'DL', 'admin' => 'DL', 'staff' => 'D'],
    'nominee.updateProfile' => ['owner' => 'DLC', 'admin' => 'DLC', 'staff' => 'DL'],
    'nominee.rename' => ['owner' => 'DLC', 'admin' => 'DLC', 'staff' => 'D'],
    'nominee.reorder' => ['owner' => '*', 'admin' => '*', 'staff' => 'D'],
    'nominee.changeCode' => ['owner' => 'D', 'admin' => 'D', 'staff' => 'D'],
    'nominee.changeCategory' => ['owner' => 'D', 'admin' => 'D', 'staff' => 'D'],
    'nominee.archive' => ['owner' => 'DLC', 'admin' => 'DLC', 'staff' => ''],
    'nominee.delete' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'results.view' => ['owner' => '*', 'admin' => '*', 'staff' => '*'],
    'results.viewHidden' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'results.export' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'results.audit' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'transactions.view' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'refunds.view' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'refunds.record' => ['owner' => '*', 'admin' => '', 'staff' => ''],
    'revenue.view' => ['owner' => '*', 'admin' => '*', 'staff' => ''],
    'ledger.write' => ['owner' => '', 'admin' => '', 'staff' => ''],
];
