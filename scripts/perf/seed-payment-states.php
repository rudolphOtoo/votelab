// Local-only seed for scripts/perf/lighthouse.sh: one paid vote in each payment-result state, on the demo program.
$nominee = App\Models\Nominee::query()->whereNull('archived_at')->whereHas('awardCategory', fn ($q) => $q->where('status', 'open'))->orderBy('id')->firstOrFail();
foreach ([
    'VL_PERF_SUCCESS' => App\Enums\TransactionStatus::Successful,
    'VL_PERF_PENDING' => App\Enums\TransactionStatus::Pending,
    'VL_PERF_FAILED' => App\Enums\TransactionStatus::Failed,
] as $reference => $status) {
    App\Models\VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => $reference,
        'status' => $status,
        'quantity' => 10,
        'credited_at' => $status === App\Enums\TransactionStatus::Successful ? now() : null,
        'verified_at' => $status === App\Enums\TransactionStatus::Pending ? null : now(),
    ]);
}
