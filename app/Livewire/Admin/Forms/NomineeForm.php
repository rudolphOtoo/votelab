<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Forms;

use App\Models\Nominee;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

/**
 * The nominee add/edit form, including the photo upload and its 4:5 crop. Builds the input for
 * CreateNomineeAction, or only the changed fields for UpdateNomineeAction (so a staff member editing a bio
 * isn't refused for "changing" a name they can't change).
 */
final class NomineeForm extends Form
{
    public string $name = '';

    public string $stage_name = '';

    public string $bio = '';

    public string $nominee_code = '';

    public ?TemporaryUploadedFile $photo = null;

    /** @var array{x: float, y: float, width: float}|null */
    public ?array $crop = null;

    public function fillFrom(Nominee $nominee): void
    {
        $this->reset();
        $this->name = $nominee->name;
        $this->stage_name = (string) $nominee->stage_name;
        $this->bio = (string) $nominee->bio;
        $this->nominee_code = $nominee->nominee_code;
        /** @var array{x: float, y: float, width: float}|null $crop */
        $crop = $nominee->photo_crop;
        $this->crop = $crop;
    }

    /**
     * @return array<string, mixed>
     */
    public function forCreate(): array
    {
        $this->validatePhoto();

        return [
            'name' => $this->name,
            'stage_name' => $this->stage_name,
            'bio' => $this->bio,
            'nominee_code' => $this->nominee_code === '' ? null : $this->nominee_code,
            'photo' => $this->photo,
            'crop' => $this->crop,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changesFrom(Nominee $nominee): array
    {
        $this->validatePhoto();
        $changes = [];

        foreach (['name', 'stage_name', 'bio', 'nominee_code'] as $field) {
            if ($this->{$field} !== (string) $nominee->getAttribute($field)) {
                $changes[$field] = $this->{$field};
            }
        }

        if ($this->photo !== null) {
            $changes['photo'] = $this->photo;
        }

        if ($this->photo !== null || json_encode($this->crop) !== json_encode($nominee->photo_crop)) {
            $changes['crop'] = $this->crop;
        }

        return $changes;
    }

    private function validatePhoto(): void
    {
        $this->validate(['photo' => [
            'nullable',
            'image',
            'max:' . (int) config('organizations.nominee_photos.max_kilobytes'),
        ]]);
    }
}
