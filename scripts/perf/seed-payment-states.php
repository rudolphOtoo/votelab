<?php

declare(strict_types=1);

// Local-only seed for scripts/perf/lighthouse.sh: one paid vote in each payment-result state, on the demo program.
// Run from the exported app's folder (the script's working directory), never the developer's own checkout:
//   php path/to/seed-payment-states.php
// The successful payment gets its ledger row and the counter moves with it, so the perf database has no drift.

use App\Enums\TransactionStatus;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Illuminate\Contracts\Console\Kernel;

require getcwd() . '/vendor/autoload.php';

$app = require getcwd() . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$nominee = Nominee::query()
    ->whereNull('archived_at')
    ->whereHas('awardCategory', static fn($query) => $query->where('status', 'open'))
    ->orderBy('id')
    ->firstOrFail();

foreach ([
    'VL_PERF_SUCCESS' => TransactionStatus::Successful,
    'VL_PERF_PENDING' => TransactionStatus::Pending,
    'VL_PERF_FAILED' => TransactionStatus::Failed,
] as $reference => $status) {
    $transaction = VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => $reference,
        'status' => $status,
        'quantity' => 10,
        'credited_at' => $status === TransactionStatus::Successful ? now() : null,
        'verified_at' => $status === TransactionStatus::Pending ? null : now(),
    ]);

    if ($status === TransactionStatus::Successful) {
        VoteRecord::factory()->for($nominee)->create([
            'vote_transaction_id' => $transaction->id,
            'is_paid' => true,
            'quantity' => $transaction->quantity,
            'voter_fingerprint' => null,
        ]);
        $nominee->increment('votes_count', $transaction->quantity);
    }
}
