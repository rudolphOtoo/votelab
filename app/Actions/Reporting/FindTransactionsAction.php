<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Models\AwardProgram;
use App\Models\User;
use App\Models\VoteTransaction;
use App\Services\Security\PhoneHasher;
use App\Support\Audit;
use App\Support\ContactMask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Transaction lists and lookups for a program (transactions.view; owners and admins).
 *
 * - recent(): newest first, emails and phones masked (a***@gmail.com, *******567).
 * - lookup(): exact match only, by payment reference, email or phone (phone through its keyed hash; the
 *   number itself is never stored, D-86). Matches show the full email and the searched number. Every
 *   lookup is audited with its kind and match count, never the searched value.
 */
final readonly class FindTransactionsAction
{
    public function __construct(
        private PhoneHasher $phones,
    ) {}

    /**
     * @return list<TransactionRow>
     *
     * @throws AuthorizationException
     */
    public function recent(AwardProgram $program, User $actor, int $limit = 50): array
    {
        Gate::forUser($actor)->authorize('viewTransactions', $program);

        return $this->rows(
            VoteTransaction::query()->where('award_program_id', $program->id)->latest('id')->limit($limit),
            fullDetail: false,
        );
    }

    /**
     * @return list<TransactionRow>
     *
     * @throws AuthorizationException
     */
    public function lookup(AwardProgram $program, User $actor, string $term): array
    {
        Gate::forUser($actor)->authorize('viewTransactions', $program);

        $term = trim($term);
        $query = VoteTransaction::query()->where('award_program_id', $program->id);
        $searchedPhone = null;

        if (str_contains($term, '@')) {
            $kind = 'email';
            $query->where('voter_email', Str::lower($term));
        } elseif (
            ($hash = $this->phones->hashForSearch($term)) !== null
            && !str_contains(Str::upper($term), Str::upper((string) config('voting.reference_prefix')))
        ) {
            $kind = 'phone';
            $searchedPhone = PhoneHasher::normalize($term);
            $query->where('phone_hash', $hash);
        } else {
            $kind = 'reference';
            $query->where('payment_reference', $term);
        }

        $rows = $this->rows($query->latest('id')->limit(100), fullDetail: true, searchedPhone: $searchedPhone);

        Audit::record(
            'transactions.lookup',
            $actor,
            $program,
            [],
            [
                'kind' => $kind,
                'matches' => count($rows),
            ],
        );

        return $rows;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<VoteTransaction>  $query
     * @return list<TransactionRow>
     */
    private function rows($query, bool $fullDetail, ?string $searchedPhone = null): array
    {
        $rows = [];

        foreach ($query->with('nominee:id,name,stage_name')->get() as $transaction) {
            $rows[] = new TransactionRow(
                id: $transaction->id,
                reference: $transaction->payment_reference,
                status: $transaction->status->value,
                quantity: $transaction->quantity,
                grossAmount: $transaction->gross_amount,
                nomineeName: $transaction->nominee->displayName(),
                email: $fullDetail ? $transaction->voter_email : ContactMask::email($transaction->voter_email),
                phone: $fullDetail && $searchedPhone !== null
                    ? $searchedPhone
                    : ContactMask::phone($transaction->phone_last3),
                createdAt: (string) $transaction->created_at?->toIso8601String(),
            );
        }

        return $rows;
    }
}
