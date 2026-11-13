<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Admin\RevealResult;
use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\CategoryStatusTransition;
use App\Models\Nominee;

/**
 * What the stage screen shows, built fresh on every load and poll. Results data exists here only once the
 * category's stored status makes it public: live bars while Open (from LeaderboardCache, which reads its own
 * status gate), and winners + podium once Revealed. Embargoed and Closed scenes carry the category name and
 * nothing else — no names, codes, counts, order or podium — so nothing can leak into the HTML or the
 * Livewire payload before the reveal. Archived nominees never appear (D-102).
 */
final readonly class StageScene
{
    /**
     * @param  'empty'|'open'|'locked'|'closed'|'revealed'  $kind
     * @param  list<array{id: int, name: string, code: string, photo: ?string, votes: int, share: float}>  $bars
     * @param  list<array{id: int, name: string, code: string, photo: ?string, votes: int, winner: bool}>  $podium
     */
    private function __construct(
        public string $kind,
        public ?string $categoryName,
        public ?int $categoryId,
        public array $bars = [],
        public array $podium = [],
        public int $winners = 0,
    ) {}

    public static function for(AwardProgram $program, string $pinnedSlug = ''): self
    {
        $category = self::spotlight($program, $pinnedSlug);

        if ($category === null) {
            return new self('empty', null, null);
        }

        return match ($category->status) {
            CategoryStatus::Open => self::open($category),
            CategoryStatus::Revealed => self::revealed($category),
            CategoryStatus::Closed => new self('closed', $category->name, $category->id),
            default => new self('locked', $category->name, $category->id),
        };
    }

    /**
     * The pinned category (?category=slug) if it isn't Draft; otherwise the one whose status changed most
     * recently; otherwise the first non-draft category. Organizers drive the screen by changing statuses.
     */
    private static function spotlight(AwardProgram $program, string $pinnedSlug): ?AwardCategory
    {
        $visible = AwardCategory::query()
            ->where('award_program_id', $program->id)
            ->where('status', '!=', CategoryStatus::Draft);

        if ($pinnedSlug !== '' && ($pinned = (clone $visible)->where('slug', $pinnedSlug)->first()) !== null) {
            return $pinned;
        }

        $latest = CategoryStatusTransition::query()
            ->whereIn('award_category_id', (clone $visible)->select('id'))
            ->latest('id')
            ->value('award_category_id');

        return $latest !== null
            ? AwardCategory::query()->whereKey($latest)->first()
            : $visible->orderBy('display_order')->orderBy('id')->first();
    }

    private static function open(AwardCategory $category): self
    {
        $standings = LeaderboardCache::standings($category);

        if ($standings === null) {
            return new self('locked', $category->name, $category->id); // The status changed under us: fail closed.
        }

        $names = self::nominees(array_keys($standings));
        arsort($standings);
        $top = max(1, (int) max([1, ...array_values($standings)]));
        $bars = [];

        foreach (array_slice($standings, 0, 8, true) as $id => $votes) {
            if (!array_key_exists($id, $names)) {
                continue;
            }

            $bars[] = [...$names[$id], 'votes' => $votes, 'share' => round($votes / $top, 4)];
        }

        return new self('open', $category->name, $category->id, bars: $bars);
    }

    private static function revealed(AwardCategory $category): self
    {
        $result = RevealResult::forCategory($category, newlyRevealed: false);
        $names = self::nominees(array_column($result->podium, 'nominee_id'));
        $podium = [];

        foreach ($result->podium as $place) {
            $podium[] = [
                ...$names[$place['nominee_id']],
                'votes' => $place['votes'],
                'winner' => in_array($place['nominee_id'], $result->winnerIds, true),
            ];
        }

        return new self(
            'revealed',
            $category->name,
            $category->id,
            podium: $podium,
            winners: count($result->winnerIds),
        );
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{id: int, name: string, code: string, photo: ?string}>
     */
    private static function nominees(array $ids): array
    {
        $out = [];

        foreach (Nominee::query()
            ->whereKey($ids)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'stage_name', 'nominee_code', 'avatar_url']) as $nominee) {
            $out[$nominee->id] = [
                'id' => $nominee->id,
                'name' => $nominee->displayName(),
                'code' => $nominee->nominee_code,
                'photo' => $nominee->avatar_url,
            ];
        }

        return $out;
    }
}
