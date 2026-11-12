<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryPhase;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\User;
use App\Support\Audit;
use App\Support\NomineeCode;
use App\Support\NomineePhoto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds a nominee to a category: validated, with a unique short code (given or generated, NomineeCode)
 * and an optional 4:5 WebP portrait (NomineePhoto). Starts at 0 votes, last in display order. A nominee
 * added while the category is Live (a late entry) is audited with its values (D-84).
 */
final readonly class CreateNomineeAction
{
    /**
     * @param  array{name?: mixed, stage_name?: mixed, bio?: mixed, nominee_code?: mixed, photo?: mixed, crop?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(AwardCategory $category, User $actor, array $input): Nominee
    {
        $category = AwardCategory::query()->findOrFail($category->id);
        Gate::forUser($actor)->authorize('create', [Nominee::class, $category]);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'stage_name' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'nominee_code' => ['nullable', 'string', 'max:16'],
            'photo' => ['nullable', 'file'],
            'crop' => ['nullable', 'array'],
        ])->validate();

        $nominee = new Nominee();
        $nominee->forceFill(self::attributes($category, $data))->save();

        if (CategoryPhase::of($category->status) === CategoryPhase::Live) {
            Audit::record('nominee.created_while_live', $actor, $nominee, [
                'before' => [],
                'after' => [
                    'name' => $nominee->name,
                    'stage_name' => $nominee->stage_name,
                    'nominee_code' => $nominee->nominee_code,
                ],
            ]);
        }

        return $nominee;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function attributes(AwardCategory $category, array $data): array
    {
        $requestedCode = $data['nominee_code'] ?? null;
        $crop = is_array($data['crop'] ?? null) ? $data['crop'] : null;
        $photo = ($data['photo'] ?? null) instanceof UploadedFile ? NomineePhoto::store($data['photo'], $crop) : [];

        return [
            'organization_id' => $category->organization_id,
            'award_program_id' => $category->award_program_id,
            'award_category_id' => $category->id,
            'name' => trim((string) $data['name']),
            'stage_name' => self::nullableText($data['stage_name'] ?? null),
            'bio' => self::nullableText($data['bio'] ?? null),
            'nominee_code' => self::code(
                $category,
                $requestedCode === null ? null : (string) $requestedCode,
                (string) $data['name'],
            ),
            'votes_count' => 0,
            'display_order' =>
                (int) Nominee::query()->where('award_category_id', $category->id)->max('display_order') + 1,
            ...$photo,
        ];
    }

    /**
     * @throws ValidationException
     */
    private static function code(AwardCategory $category, ?string $requested, string $name): string
    {
        return $requested === null || trim($requested) === ''
            ? NomineeCode::generate($category->award_program_id, $name)
            : NomineeCode::ensureAvailable($requested, $category->award_program_id);
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
