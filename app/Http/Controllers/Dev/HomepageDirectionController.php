<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Support\Dev\HomepageDirections;
use App\Support\PublicSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * LOCAL ONLY (404 in every other environment): the three visual directions for the homepage, Step A of the
 * homepage brief: /dev/homepage/a, /b and /c, each the hero and the "Live now" section with real programs.
 * ?empty=1 shows the empty state; ?repeat=3 repeats the real cards to preview the desktop grid (labelled on the
 * page). Read only. Nothing here shows a vote count: only programs, organizations, status and closing times.
 */
final class HomepageDirectionController
{
    public function __invoke(Request $request, string $direction): Response
    {
        abort_unless(app()->environment('local'), 404);
        $design = HomepageDirections::find($direction);
        abort_if($design === null, 404);

        $live = $request->boolean('empty') ? [] : self::livePrograms();
        $repeat = max(1, min(4, $request->integer('repeat', 1)));
        $cards = $repeat > 1 ? array_merge(...array_fill(0, $repeat, $live)) : $live;

        return response()->view("dev.homepage.{$direction}", [
            'key' => $direction,
            'design' => $design,
            'pairs' => HomepageDirections::pairs($direction),
            'variables' => HomepageDirections::cssVariables($direction),
            'cards' => $cards,
            'repeated' => $repeat > 1 && $live !== [],
            'directions' => array_map(static fn(string $k): array => [
                'key' => $k,
                'name' => HomepageDirections::find($k)['name'] ?? $k,
            ], HomepageDirections::keys()),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Published programs with at least one category taking votes, soonest closing first.
     *
     * @return list<array{title: string, url: string, organization: string, organizationModel: \App\Models\Organization, banner: ?string, openCategories: int, closesAt: ?CarbonImmutable, closesIn: ?string, closesOn: ?string}>
     */
    private static function livePrograms(): array
    {
        $now = CarbonImmutable::now();

        return array_values(
            AwardProgram::query()
                ->where('status', ProgramStatus::Published)
                ->whereHas('categories', static fn($q) => $q->whereIn('status', CategoryStatus::votingAllowed()))
                ->withCount([
                    'categories as open_categories' => static fn($q) => $q->whereIn(
                        'status',
                        CategoryStatus::votingAllowed(),
                    ),
                ])
                ->with('organization')
                ->orderByRaw('ends_at is null')
                ->orderBy('ends_at')
                ->limit(8)
                ->get()
                ->map(static fn(AwardProgram $program): array => [
                    'title' => $program->title,
                    'url' => route('public.program', [$program->organization, $program]),
                    'organization' => $program->organization->name,
                    'organizationModel' => $program->organization,
                    'banner' => $program->banner_url,
                    'openCategories' => (int) $program->getAttribute('open_categories'),
                    'closesAt' => $program->ends_at,
                    'closesIn' => $program->ends_at !== null ? self::closesIn($program->ends_at, $now) : null,
                    'closesOn' => $program->ends_at !== null ? PublicSchedule::format($program->ends_at) : null,
                ])
                ->all(),
        );
    }

    /**
     * "Closes in 2d 4h", "Closes in 3h 12m", "Closes in 9m", "Closing now". resources/js/home.js keeps it current.
     */
    public static function closesIn(CarbonImmutable $closesAt, CarbonImmutable $now): string
    {
        $minutes = (int) floor(max(0, $closesAt->getTimestamp() - $now->getTimestamp()) / 60);

        return match (true) {
            $minutes < 1 => 'Closing now',
            $minutes < 60 => "Closes in {$minutes}m",
            $minutes < 1_440 => sprintf('Closes in %dh %dm', intdiv($minutes, 60), $minutes % 60),
            default => sprintf('Closes in %dd %dh', intdiv($minutes, 1_440), intdiv($minutes % 1_440, 60)),
        };
    }
}
