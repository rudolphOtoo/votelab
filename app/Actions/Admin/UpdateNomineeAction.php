<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryPhase;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\User;
use App\Support\Audit;
use App\Support\NomineeCode;
use App\Support\NomineeEdits;
use App\Support\NomineePhotoEdit;
use App\Support\ShareImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Edits a nominee. Each kind of change needs its own ability (App\Support\PermissionMatrix): photo, crop and
 * bio need nominee.updateProfile; name and stage name nominee.rename; the code nominee.changeCode; the
 * category nominee.changeCategory. A new photo or crop is always re-encoded from the stored original.
 * Every change is audited with before/after values and whether it happened while Live (D-84), and the
 * cached share images are cleared.
 */
final readonly class UpdateNomineeAction
{
    private const array ABILITY_FOR = [
        'bio' => 'updateProfile',
        'photo' => 'updateProfile',
        'crop' => 'updateProfile',
        'name' => 'rename',
        'stage_name' => 'rename',
        'nominee_code' => 'changeCode',
    ];

    /**
     * @param  array<string, mixed>  $input  name, stage_name, bio, nominee_code, photo (upload), crop, award_category_id
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(Nominee $nominee, User $actor, array $input): Nominee
    {
        $nominee = Nominee::query()->findOrFail($nominee->id);
        $gate = Gate::forUser($actor);
        $data = self::validate($input);
        $changes = NomineeEdits::textChanges($nominee, $data);

        foreach (array_keys($changes) as $field) {
            $gate->authorize(self::ABILITY_FOR[$field], $nominee);
        }

        if (array_key_exists('nominee_code', $changes)) {
            $changes['nominee_code'] = NomineeCode::ensureAvailable(
                (string) $changes['nominee_code'],
                $nominee->award_program_id,
                $nominee->id,
            );
        }

        $target = null;

        $targetId = $data['award_category_id'] ?? null;

        if ($targetId !== null && (int) $targetId !== $nominee->award_category_id) {
            $target = AwardCategory::query()->findOrFail((int) $targetId);
            $gate->authorize('changeCategory', [$nominee, $target]);
            $changes['award_category_id'] = $target->id;
        }

        $photo = NomineePhotoEdit::apply($nominee, $data, $gate);
        $before = $nominee->only([...array_keys($changes), ...($photo === [] ? [] : ['photo_crop'])]);

        if ($changes === [] && $photo === []) {
            return $nominee;
        }

        $nominee->forceFill([...$changes, ...$photo])->save();
        ShareImages::forget($nominee->id);

        Audit::record(
            'nominee.updated',
            $actor,
            $nominee,
            ['before' => $before, 'after' => $nominee->only(array_keys($before))],
            [
                'while_live' => CategoryPhase::of(($target ?? $nominee->awardCategory)->status) === CategoryPhase::Live,
                'photo' => NomineePhotoEdit::describe($photo, $data),
            ],
        );

        return $nominee;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function validate(array $input): array
    {
        return Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'stage_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'nominee_code' => ['sometimes', 'required', 'string', 'max:16'],
            'photo' => ['sometimes', 'nullable', 'file'],
            'crop' => ['sometimes', 'nullable', 'array'],
            'award_category_id' => ['sometimes', 'integer'],
        ])->validate();
    }
}
