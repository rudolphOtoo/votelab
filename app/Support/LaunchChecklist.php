<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VotingMode;
use App\Models\AwardProgram;
use App\Models\Nominee;

/**
 * What must be true before a program goes live. PublishProgramAction refuses while any required item fails;
 * the program page shows every item with its reason.
 */
final readonly class LaunchChecklist
{
    /**
     * @param  list<array{key: string, label: string, passed: bool, detail: string}>  $items
     */
    private function __construct(
        public array $items,
    ) {}

    public static function for(AwardProgram $program): self
    {
        $program->loadMissing('organization');
        $categories = $program->categories()->get();
        $nominees = Nominee::query()
            ->active()
            ->where('award_program_id', $program->id)
            ->get(['id', 'award_category_id', 'avatar_url', 'nominee_code']);
        $paid = $categories->filter(static fn($c): bool => $c->voting_mode !== VotingMode::Free);

        $emptyCategories = $categories->reject(static fn($c): bool => $nominees->contains('award_category_id', $c->id));
        $withoutPhoto = $nominees->filter(
            static fn(Nominee $n): bool => $n->avatar_url === null || $n->avatar_url === '',
        );
        $withoutCode = $nominees->filter(static fn(Nominee $n): bool => trim($n->nominee_code) === '');
        $unpriced = $paid->filter(static fn($c): bool => $c->price_per_vote < 1);
        $datesOk =
            $program->starts_at !== null
            && $program->ends_at !== null
            && $program->starts_at->lessThan($program->ends_at);

        return new self([
            self::item('categories', 'At least one category', $categories->isNotEmpty(), 'Add a category.'),
            self::item(
                'nominees',
                'Every category has a nominee',
                $categories->isNotEmpty() && $emptyCategories->isEmpty(),
                self::names('Empty', $emptyCategories->pluck('name')->all()),
            ),
            self::item(
                'photos',
                'Every nominee has a photo',
                $withoutPhoto->isEmpty(),
                "{$withoutPhoto->count()} nominee(s) without a photo.",
            ),
            self::item(
                'codes',
                'Every nominee has a code',
                $withoutCode->isEmpty(),
                "{$withoutCode->count()} nominee(s) without a code.",
            ),
            self::item(
                'prices',
                'Paid categories have a price',
                $unpriced->isEmpty(),
                self::names('No price', $unpriced->pluck('name')->all()),
            ),
            self::item(
                'payout',
                'Payout account set for paid voting',
                $paid->isEmpty() || $program->organization->canTakePaidVotes(),
                'Add the Paystack subaccount code in organization settings.',
            ),
            self::item(
                'paystack_mode',
                'Paystack keys match this environment',
                $paid->isEmpty() || PaystackKeyMode::matchesEnvironment(),
                PaystackKeyMode::explanation(),
            ),
            self::item('dates', 'Start and end dates set', $datesOk, 'Set a start date before the end date.'),
            self::item(
                'share_link',
                'Public link ready',
                $program->slug !== '' && $program->organization->slug !== '',
                url("/{$program->organization->slug}/{$program->slug}"),
            ),
        ]);
    }

    public function passes(): bool
    {
        foreach ($this->items as $item) {
            if (!$item['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function failing(): array
    {
        $failing = [];

        foreach ($this->items as $item) {
            if (!$item['passed']) {
                $failing[] = $item['key'];
            }
        }

        return $failing;
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string}
     */
    private static function item(string $key, string $label, bool $passed, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $passed && $key !== 'share_link' ? '' : $detail,
        ];
    }

    /**
     * @param  array<int, mixed>  $names
     */
    private static function names(string $prefix, array $names): string
    {
        return $names === [] ? '' : $prefix . ': ' . implode(', ', array_map('strval', $names)) . '.';
    }
}
