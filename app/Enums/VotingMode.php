<?php

declare(strict_types=1);

namespace App\Enums;

enum VotingMode: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Paid => 'Paid',
            self::Hybrid => 'Free + Paid',
        };
    }

    public function allowsFreeVotes(): bool
    {
        return $this !== self::Paid;
    }

    public function allowsPaidVotes(): bool
    {
        return $this !== self::Free;
    }
}
