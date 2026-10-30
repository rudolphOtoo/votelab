<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Embargoed = 'embargoed'; // Voting is active, but live leaderboard is hidden
    case Closed = 'closed'; // Voting stopped; results locked
    case Revealed = 'revealed'; // Official winner announced on stage

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Embargoed => 'Embargoed',
            self::Closed => 'Closed',
            self::Revealed => 'Revealed',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Embargoed],
            self::Open => [self::Embargoed, self::Closed],
            self::Embargoed => [self::Open, self::Closed],
            self::Closed => [self::Revealed],
            self::Revealed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether new votes (free or a new paid checkout) may be started.
     */
    public function isVotingAllowed(): bool
    {
        return in_array($this, [self::Open, self::Embargoed], true);
    }

    public function isLeaderboardPublic(): bool
    {
        return in_array($this, [self::Open, self::Revealed], true);
    }

    /**
     * Whether a verified payment may still be credited. A closed category only
     * credits payments inside the close grace window; callers enforce the window.
     */
    public function canFulfillPayments(): bool
    {
        return in_array($this, [self::Open, self::Embargoed, self::Closed], true);
    }
}
