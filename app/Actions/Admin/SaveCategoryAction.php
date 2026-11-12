<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryPhase;
use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates a category (Draft, last in order) or edits one. Name and description need
 * category.updateDetails (until revealed); slug, voting mode, price and free-vote limit need
 * category.updatePricing (Draft only), since voters' links and prices must not shift once voting opens.
 * Prices are integer pesewas. Every change is audited with before/after values and whether it happened
 * while the category was Live (D-84).
 */
final readonly class SaveCategoryAction
{
    private const array DETAILS = ['name', 'description'];

    private const array DRAFT_ONLY = ['slug', 'voting_mode', 'price_per_vote', 'free_vote_limit'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(AwardProgram $program, User $actor, array $input): AwardCategory
    {
        Gate::forUser($actor)->authorize('create', [AwardCategory::class, $program]);
        $data = self::validate($input, $program->id, null);

        $category = new AwardCategory();
        $category->forceFill([
            'voting_mode' => VotingMode::Paid,
            'price_per_vote' => 100,
            'free_vote_limit' => 1,
            ...$data,
            'organization_id' => $program->organization_id,
            'award_program_id' => $program->id,
            'status' => CategoryStatus::Draft,
            'display_order' =>
                (int) AwardCategory::query()->where('award_program_id', $program->id)->max('display_order') + 1,
        ])->save();

        Audit::record('category.created', $actor, $category, ['after' => $category->only([
            ...self::DETAILS,
            ...self::DRAFT_ONLY,
        ])]);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(AwardCategory $category, User $actor, array $input): AwardCategory
    {
        $category = AwardCategory::query()->findOrFail($category->id);
        $input = array_intersect_key($input, array_flip([...self::DETAILS, ...self::DRAFT_ONLY]));
        $data = self::validate($input, $category->award_program_id, $category->id);
        $before = $category->only(array_keys($data));
        $category->forceFill($data);
        $changed = $category->getDirty();

        if ($changed === []) {
            return $category;
        }

        $gate = Gate::forUser($actor);

        if (array_intersect_key($changed, array_flip(self::DETAILS)) !== []) {
            $gate->authorize('updateDetails', $category);
        }

        if (array_intersect_key($changed, array_flip(self::DRAFT_ONLY)) !== []) {
            $gate->authorize('updatePricing', $category);
        }

        $category->save();

        Audit::record(
            'category.updated',
            $actor,
            $category,
            [
                'before' => array_intersect_key($before, $changed),
                'after' => array_intersect_key($category->only(array_keys($changed)), $changed),
            ],
            ['while_live' => CategoryPhase::of($category->status) === CategoryPhase::Live],
        );

        return $category;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function validate(array $input, int $programId, ?int $categoryId): array
    {
        $required = $categoryId === null ? 'required' : 'sometimes';

        return Validator::make(
            $input,
            [
                'name' => [$required, 'string', 'max:120'],
                'slug' => [
                    $required,
                    'string',
                    'max:80',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique('award_categories', 'slug')
                        ->where('award_program_id', $programId)
                        ->ignore($categoryId),
                ],
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'voting_mode' => ['sometimes', Rule::enum(VotingMode::class)],
                'price_per_vote' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
                'free_vote_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
            [
                'slug.regex' => 'Use lower-case letters, numbers and single hyphens.',
                'slug.unique' => 'Another category in this program already uses this link.',
                'price_per_vote.integer' => 'Enter the price in pesewas (whole number).',
            ],
        )->validate();
    }
}
