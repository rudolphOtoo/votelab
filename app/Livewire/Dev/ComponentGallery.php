<?php

declare(strict_types=1);

namespace App\Livewire\Dev;

use App\Enums\CategoryStatus;
use App\Support\Design\Branding;
use App\Support\Design\Contrast;
use App\Support\Design\Tokens;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * /dev/components: every design-system component in every state, the colour tokens with their contrast
 * ratios, and per-organization branding examples. Outside production only (404 there).
 */
#[Layout('layouts::app')]
final class ComponentGallery extends Component
{
    public int $quantity = 10;

    public string $email = '';

    public function mount(): void
    {
        abort_if(app()->isProduction(), 404);
    }

    public function render(): View
    {
        abort_if(app()->isProduction(), 404);

        return view('livewire.dev.component-gallery', [
            'colors' => Tokens::colors(),
            'pairs' => array_map(static fn(array $p): array => [
                ...$p,
                'ratio' => Contrast::ratio($p['foreground'], $p['background']),
            ], Tokens::pairs()),
            'statuses' => CategoryStatus::cases(),
            'brands' => [
                'VoteLab (default)' => Branding::votelab(),
                'Deep red #8B1E3F' => Branding::fromColor('#8b1e3f'),
                'Gold #F2B705 (dark text)' => Branding::fromColor('#f2b705'),
                'Mid grey #7A7A7A (fails → VoteLab)' => Branding::fromColor('#7a7a7a'),
            ],
            'paginator' => new LengthAwarePaginator([], 120, 10, 4, ['path' => url('/dev/components')]),
        ])->title('Components');
    }
}
