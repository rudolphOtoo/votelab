<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates a program (as Draft) or updates one. The close grace window is 10 to VOTING_CLOSE_GRACE_MINUTES
 * minutes (D-94, default the maximum) and can't change while any category is closed but not yet revealed,
 * when a window may be running. Changes are audited with before/after values.
 */
final readonly class SaveProgramAction
{
    private const array FIELDS = [
        'title',
        'slug',
        'tagline',
        'description',
        'banner_url',
        'starts_at',
        'ends_at',
        'close_grace_minutes',
    ];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(Organization $organization, User $actor, array $input): AwardProgram
    {
        Gate::forUser($actor)->authorize('create', [AwardProgram::class, $organization]);
        $data = self::validate($input, $organization->id, null);

        $program = new AwardProgram();
        $program->forceFill([
            ...$data,
            'organization_id' => $organization->id,
            'status' => ProgramStatus::Draft,
            'close_grace_minutes' => $data['close_grace_minutes'] ?? self::maximum(),
        ])->save();

        Audit::record('program.created', $actor, $program, ['after' => $program->only(self::FIELDS)]);

        return $program;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(AwardProgram $program, User $actor, array $input): AwardProgram
    {
        $program = AwardProgram::query()->findOrFail($program->id);
        Gate::forUser($actor)->authorize('update', $program);
        $data = self::validate(
            array_intersect_key($input, array_flip(self::FIELDS)),
            $program->organization_id,
            $program->id,
        );

        if (
            array_key_exists('close_grace_minutes', $data)
            && (int) $data['close_grace_minutes'] !== $program->close_grace_minutes
            && $program->categories()->where('status', CategoryStatus::Closed)->exists()
        ) {
            throw ValidationException::withMessages([
                'close_grace_minutes' => 'The grace window can\'t change while a category is closed and not yet revealed.',
            ]);
        }

        $before = $program->only(array_keys($data));
        $program->forceFill($data);

        if (!$program->isDirty()) {
            return $program;
        }

        $changed = $program->getDirty();
        $program->save();

        Audit::record('program.updated', $actor, $program, [
            'before' => array_intersect_key($before, $changed),
            'after' => array_intersect_key($program->only(array_keys($changed)), $changed),
        ]);

        return $program;
    }

    public static function maximum(): int
    {
        return (int) config('voting.close_grace_minutes');
    }

    public static function minimum(): int
    {
        return min((int) config('voting.close_grace_minimum_minutes'), self::maximum());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function validate(array $input, int $organizationId, ?int $programId): array
    {
        $creating = $programId === null;
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make(
            $input,
            [
                'title' => [$required, 'string', 'max:160'],
                'slug' => [
                    $required,
                    'string',
                    'max:80',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique('award_programs', 'slug')
                        ->where('organization_id', $organizationId)
                        ->ignore($programId),
                ],
                'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
                'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'banner_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
                'starts_at' => ['sometimes', 'nullable', 'date'],
                'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
                'close_grace_minutes' => ['sometimes', 'integer', 'between:' . self::minimum() . ',' . self::maximum()],
            ],
            [
                'slug.regex' => 'Use lower-case letters, numbers and single hyphens.',
                'close_grace_minutes.between' => 'The grace window must be between :min and :max minutes.',
            ],
        )->validate();
    }
}
