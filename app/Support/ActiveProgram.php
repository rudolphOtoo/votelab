<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AwardProgram;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The award program an organizer is currently working in, kept in the session.
 * Only programs belonging to one of the user's organizations can be selected.
 */
final readonly class ActiveProgram
{
    public const string SESSION_KEY = 'active_program_id';

    public function __construct(
        private Session $session,
    ) {}

    public function for(User $user): ?AwardProgram
    {
        $programs = $this->accessiblePrograms($user);
        $selectedId = $this->session->get(self::SESSION_KEY);

        if (is_int($selectedId)) {
            $selected = (clone $programs)->whereKey($selectedId)->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        return $programs->latest('id')->first();
    }

    public function set(User $user, AwardProgram $program): void
    {
        if (!$this->accessiblePrograms($user)->whereKey($program->id)->exists()) {
            throw new InvalidArgumentException('The user does not belong to this program\'s organization.');
        }

        $this->session->put(self::SESSION_KEY, $program->id);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return Builder<AwardProgram>
     */
    private function accessiblePrograms(User $user): Builder
    {
        return AwardProgram::query()->whereIn('organization_id', $user->organizations()->select('organizations.id'));
    }
}
