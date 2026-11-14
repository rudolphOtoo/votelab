<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\LeaderboardCache;
use App\Support\NomineePortrait;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What the public site lists (plan/SITE.md): programs open for voting, recently revealed winners, the /results page
 * and the sitemap. Only published programs that aren't hidden from the VoteLab homepage, and in dedicated mode only
 * that one organization's.
 *
 * Hidden results stay hidden: winners come only from revealed categories, through LeaderboardCache::standings(),
 * which reads the category's current status and returns nothing unless its results are public. Nothing here carries
 * a vote count. Lists are cached for site.listing_cache_seconds as plain arrays; any save to a program, category,
 * nominee or organization bumps a version number that is part of every key (forget()).
 */
final class SiteDirectory
{
    private const string VERSION_KEY = 'site:directory:version';

    /**
     * Programs with at least one category taking votes, soonest closing first.
     *
     * @return list<array{title: string, url: string, organization: string, logo: ?string, banner: ?string, openCategories: int, closesAt: ?string}>
     */
    public static function live(?Organization $scope = null, int $limit = 12): array
    {
        return self::remember("live:{$limit}", $scope, static fn(): array => array_values(
            self::listed($scope)
                ->whereHas('categories', static fn(Builder $q) => $q->whereIn(
                    'status',
                    CategoryStatus::votingAllowed(),
                ))
                ->withCount([
                    'categories as open_categories' => static fn(Builder $q) => $q->whereIn(
                        'status',
                        CategoryStatus::votingAllowed(),
                    ),
                ])
                ->with('organization')
                ->orderByRaw('ends_at is null')
                ->orderBy('ends_at')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(static fn(AwardProgram $program): array => [
                    'title' => $program->title,
                    'url' => route('public.program', [$program->organization, $program]),
                    'organization' => $program->organization->name,
                    'logo' => $program->organization->logo_url,
                    'banner' => $program->banner_url,
                    'openCategories' => (int) $program->getAttribute('open_categories'),
                    'closesAt' => $program->ends_at?->toIso8601String(),
                ])
                ->all(),
        ));
    }

    /**
     * Revealed categories with their winners (a tie lists every nominee on the top count), most recent reveal first.
     *
     * @return list<array{category: string, url: string, program: string, programUrl: string, organization: string, winners: list<array{name: string, code: string, photo: ?string, srcset: ?string, url: string}>}>
     */
    public static function winners(?Organization $scope = null, int $limit = 6): array
    {
        return self::remember("winners:{$limit}", $scope, static function () use ($scope, $limit): array {
            $categories = AwardCategory::query()
                ->where('status', CategoryStatus::Revealed)
                ->whereIn('award_program_id', self::listed($scope)->select('id'))
                ->with('awardProgram.organization')
                ->addSelect([
                    'revealed_at' => DB::table('category_status_transitions')
                        ->selectRaw('max(created_at)')
                        ->whereColumn('award_category_id', 'award_categories.id')
                        ->where('to_status', CategoryStatus::Revealed->value),
                ])
                ->orderByDesc('revealed_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $winnerIds = [];
            foreach ($categories as $category) {
                // Fails closed: null unless this category's results are public right now.
                $standings = LeaderboardCache::standings($category) ?? [];
                $top = max([0, ...$standings]);
                $winnerIds[$category->id] = $top > 0 ? array_keys($standings, $top, true) : [];
            }

            $nominees = Nominee::query()
                ->whereKey(array_merge([], ...array_values($winnerIds)))
                ->get()
                ->keyBy('id');

            return array_values(
                $categories->map(static function (AwardCategory $category) use ($winnerIds, $nominees): array {
                    $program = $category->awardProgram;
                    $organization = $program->organization;

                    return [
                        'category' => $category->name,
                        'url' => route('public.category', [$organization, $program, $category]),
                        'program' => $program->title,
                        'programUrl' => route('public.program', [$organization, $program]),
                        'organization' => $organization->name,
                        'winners' => array_values(array_filter(array_map(static function (int $id) use (
                            $nominees,
                            $organization,
                            $program,
                        ): ?array {
                            $nominee = $nominees->get($id);

                            return (
                                $nominee instanceof Nominee
                                    ? [
                                        'name' => $nominee->displayName(),
                                        'code' => $nominee->nominee_code,
                                        'photo' => $nominee->avatar_url,
                                        'srcset' => NomineePortrait::srcset($nominee),
                                        'url' => route('public.nominee', [
                                            $organization,
                                            $program,
                                            $nominee->nominee_code,
                                        ]),
                                    ]
                                    : null
                            );
                        }, $winnerIds[$category->id] ?? []))),
                    ];
                })->all(),
            );
        });
    }

    /**
     * Listed programs and their public (non-draft) categories, for sitemap.xml. URLs and dates only.
     *
     * @return list<array{url: string, updated: string}>
     */
    public static function sitemap(?Organization $scope = null): array
    {
        return self::remember('sitemap', $scope, static function () use ($scope): array {
            $entries = [];
            $programs = self::listed($scope)
                ->with([
                    'organization',
                    'categories' => static fn($q) => $q->where('status', '!=', CategoryStatus::Draft),
                ])
                ->orderBy('id')
                ->get();

            foreach ($programs as $program) {
                $entries[] = [
                    'url' => route('public.program', [$program->organization, $program]),
                    'updated' => $program->updated_at?->toDateString() ?? CarbonImmutable::now()->toDateString(),
                ];

                foreach ($program->categories as $category) {
                    $entries[] = [
                        'url' => route('public.category', [$program->organization, $program, $category]),
                        'updated' => $category->updated_at?->toDateString() ?? CarbonImmutable::now()->toDateString(),
                    ];
                }
            }

            return $entries;
        });
    }

    /**
     * Retire every cached list at once: called whenever a program, category, nominee or organization is saved.
     */
    public static function forget(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * Published programs not hidden from the VoteLab homepage, within the scope.
     *
     * @return Builder<AwardProgram>
     */
    private static function listed(?Organization $scope): Builder
    {
        return AwardProgram::query()
            ->where('status', ProgramStatus::Published)
            ->listed()
            ->when($scope, static fn(Builder $q, Organization $organization) => $q->where(
                'organization_id',
                $organization->id,
            ));
    }

    /**
     * @template T of array
     *
     * @param  Closure(): T  $build
     * @return T
     */
    private static function remember(string $what, ?Organization $scope, Closure $build): array
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $key = sprintf('site:directory:%d:%s:%s', $version, $scope === null ? 'all' : $scope->id, $what);

        return Cache::remember($key, (int) config('site.listing_cache_seconds'), $build);
    }
}
