<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Events\FreeVoteCast;
use App\Exceptions\Voting\FreeVoteUnavailableException;
use App\Models\AwardCategory;
use App\Models\FreeVoteClaim;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Services\Security\VoterIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records one free vote. The category's daily allowance per voter is enforced by the database: each
 * vote claims slot n (1..free_vote_limit) under a unique (category, voter, day, slot) key, so two
 * racing requests can never both take the last slot.
 */
final readonly class CastFreeVoteAction
{
    /**
     * @throws FreeVoteUnavailableException
     */
    public function execute(Nominee $nominee, VoterIdentity $voter): VoteRecord
    {
        $category = $nominee->awardCategory()->firstOrFail();
        $program = $category->awardProgram()->firstOrFail();
        $context = [
            'award_program_id' => $nominee->award_program_id,
            'award_category_id' => $nominee->award_category_id,
            'nominee_id' => $nominee->id,
            'voter_key' => $voter->voterKey,
            'ip_hash' => $voter->ipHash,
        ];

        if (!$program->status->isPublic() || !$category->status->isVotingAllowed()) {
            throw FreeVoteUnavailableException::votingNotOpen();
        }

        if ($nominee->isArchived()) {
            throw FreeVoteUnavailableException::nomineeWithdrawn();
        }

        if (!$category->voting_mode->allowsFreeVotes()) {
            throw FreeVoteUnavailableException::freeVotesNotAccepted();
        }

        $limit = $category->free_vote_limit;
        $period = self::today();

        try {
            $record = DB::transaction(static function () use ($nominee, $voter, $limit, $period): ?VoteRecord {
                $used = self::claims($nominee->award_category_id, $voter, $period);

                if ($used >= $limit) {
                    return null;
                }

                $record = VoteRecord::query()->create([
                    'organization_id' => $nominee->organization_id,
                    'award_program_id' => $nominee->award_program_id,
                    'award_category_id' => $nominee->award_category_id,
                    'nominee_id' => $nominee->id,
                    'is_paid' => false,
                    'quantity' => 1,
                    'voter_fingerprint' => $voter->voterKey,
                    'ip_hash' => $voter->ipHash,
                    'cast_at' => now(),
                ]);

                FreeVoteClaim::query()->create([
                    'award_category_id' => $nominee->award_category_id,
                    'vote_record_id' => $record->id,
                    'voter_key' => $voter->voterKey,
                    'period' => $period,
                    'slot' => $used + 1,
                ]);

                Nominee::query()->whereKey($nominee->id)->increment('votes_count');

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            $record = null; // A concurrent request took the same slot.
        }

        if ($record === null) {
            Log::channel('voting')->warning(
                'free_vote.rejected',
                $context + ['reason' => 'limit_reached', 'limit' => $limit],
            );

            throw FreeVoteUnavailableException::limitReached($limit);
        }

        FreeVoteCast::dispatch($record);
        Log::channel('voting')->info('free_vote.cast', $context + ['vote_record_id' => $record->id]);

        return $record;
    }

    /**
     * Free votes this voter can still cast in the category today (for the vote drawer). Read-only; the
     * limit itself is enforced in execute(), by the database.
     */
    public static function remainingToday(AwardCategory $category, VoterIdentity $voter): int
    {
        return max(0, $category->free_vote_limit - self::claims($category->id, $voter, self::today()));
    }

    private static function today(): string
    {
        return now()->toDateString();
    }

    private static function claims(int $categoryId, VoterIdentity $voter, string $period): int
    {
        return FreeVoteClaim::query()
            ->where('award_category_id', $categoryId)
            ->where('voter_key', $voter->voterKey)
            ->where('period', $period)
            ->count();
    }
}
