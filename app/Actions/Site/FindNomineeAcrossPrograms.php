<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\NomineeCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Find your nominee" on the VoteLab homepage (plan/SITE.md): searches every listed program, or in dedicated mode
 * that one organization's. A nominee code jumps straight to the nominee only when exactly one program has it (codes
 * are unique per program, not across programs); otherwise, and for names, it lists the matches with their program
 * and category, sorted by name and never by votes. Same scope as a program page's search: active nominees in public
 * (non-draft) categories of published programs not hidden from the homepage.
 */
final readonly class FindNomineeAcrossPrograms
{
    private const int MAX_MATCHES = 20;

    /**
     * @return array{redirect: ?string, matches: list<array{name: string, code: string, program: string, organization: string, category: string, url: string}>}
     */
    public function execute(string $query, ?Organization $scope = null): array
    {
        $term = trim($query);
        $code = NomineeCode::normalize($term);

        if (mb_strlen($term) < 2 && $code === '') {
            return ['redirect' => null, 'matches' => []];
        }

        $byCode = $code === ''
            ? collect()
            : $this->searchable($scope)->where('nominee_code', $code)->limit(self::MAX_MATCHES)->get();

        if ($byCode->count() === 1) {
            return ['redirect' => self::url($byCode->firstOrFail()), 'matches' => []];
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';
        $byName = mb_strlen($term) < 2
            ? collect()
            : $this
                ->searchable($scope)
                ->where(static fn(Builder $q) => $q->whereLike('name', $like, caseSensitive: false)->orWhereLike(
                    'stage_name',
                    $like,
                    caseSensitive: false,
                ))
                ->limit(self::MAX_MATCHES)
                ->get();

        $matches = array_values(
            $byCode
                ->merge($byName)
                ->unique('id')
                ->sortBy(static fn(Nominee $n): string => mb_strtolower($n->displayName()))
                ->take(self::MAX_MATCHES)
                ->map(static fn(Nominee $n): array => [
                    'name' => $n->displayName(),
                    'code' => $n->nominee_code,
                    'program' => $n->awardProgram->title,
                    'organization' => $n->awardProgram->organization->name,
                    'category' => $n->awardCategory->name,
                    'url' => self::url($n),
                ])
                ->all(),
        );

        return ['redirect' => null, 'matches' => $matches];
    }

    /**
     * @return Builder<Nominee>
     */
    private function searchable(?Organization $scope): Builder
    {
        return Nominee::query()
            ->active()
            ->whereHas('awardCategory', static fn(Builder $q) => $q->where('status', '!=', CategoryStatus::Draft))
            ->whereHas('awardProgram', static fn(Builder $q) => $q
                ->where('status', ProgramStatus::Published)
                ->listed()
                ->when($scope, static fn(Builder $q, Organization $o) => $q->where('organization_id', $o->id)))
            ->with(['awardProgram.organization', 'awardCategory:id,name'])
            ->select(['id', 'award_program_id', 'award_category_id', 'name', 'stage_name', 'nominee_code']);
    }

    private static function url(Nominee $nominee): string
    {
        return route('public.nominee', [
            $nominee->awardProgram->organization,
            $nominee->awardProgram,
            $nominee->nominee_code,
        ]);
    }
}
