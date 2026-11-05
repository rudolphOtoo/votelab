<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\QuotePaidVotesAction;
use App\Actions\Voting\StartVoteCheckoutAction;
use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Exceptions\Voting\FreeVoteUnavailableException;
use App\Models\Nominee;
use App\Services\Security\VoteFingerprintService;
use App\Support\Throttle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The vote bottom sheet (mobile) / dialog (desktop): free vote or paid vote pack.
 *
 * Shows the exact price breakdown Paystack will charge (L-8), via QuotePaidVotesAction. Every rule is
 * re-checked server-side by the actions; nothing the browser sends is trusted beyond quantity and email.
 */
final class VoteDrawer extends Component
{
    public const array QUICK_ADD = [10, 25, 50, 100];

    #[Locked]
    public int $nomineeId;

    public string $tab = 'paid';

    public int $quantity = 1;

    public string $email = '';

    public ?string $notice = null;

    public string $noticeTone = 'info';

    public function mount(int $nomineeId): void
    {
        $nominee = Nominee::query()->with('awardCategory')->findOrFail($nomineeId);
        $this->nomineeId = $nominee->id;

        if (!$nominee->awardCategory->voting_mode->allowsPaidVotes()) {
            $this->tab = 'free';
        }
    }

    public function showTab(string $tab): void
    {
        $this->tab = in_array($tab, ['free', 'paid'], true) ? $tab : 'paid';
        $this->notice = null;
    }

    /**
     * Step the quantity by one of the offered amounts: -1, +1 or a quick-add chip.
     */
    public function adjust(int $delta): void
    {
        if ($delta === 1 || $delta === -1 || in_array($delta, self::QUICK_ADD, true)) {
            $this->setQuantity($this->quantity + $delta);
        }
    }

    public function updatedQuantity(mixed $value): void
    {
        $this->setQuantity(is_numeric($value) ? (int) $value : 1);
    }

    public function checkout(StartVoteCheckoutAction $startCheckout, VoteFingerprintService $fingerprints): void
    {
        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . (int) config('voting.max_votes_per_order')],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ]);

        try {
            $this->redirect($startCheckout->execute(
                Nominee::query()->findOrFail($this->nomineeId),
                $this->quantity,
                $this->email,
                $fingerprints->identify(request()),
            ));
        } catch (CheckoutUnavailableException $e) {
            $this->flash($e->getMessage(), 'warning');
        }
    }

    public function castFreeVote(CastFreeVoteAction $castFreeVote, VoteFingerprintService $fingerprints): void
    {
        $voter = $fingerprints->identify(request());
        $retryAfter = Throttle::attempt('free-vote', $voter);

        if ($retryAfter !== null) {
            $this->flash("Too many attempts. Please try again in {$retryAfter} seconds.", 'warning');

            return;
        }

        try {
            $nominee = Nominee::query()->findOrFail($this->nomineeId);
            $castFreeVote->execute($nominee, $voter);
            $this->flash("Your free vote for {$nominee->displayName()} is in. Thank you!", 'success');
            $this->dispatch('votes-updated');
        } catch (FreeVoteUnavailableException $e) {
            $this->flash($e->getMessage(), 'warning');
        }
    }

    public function close(): void
    {
        $this->dispatch('vote-drawer-closed');
    }

    public function render(): View
    {
        $nominee = Nominee::query()->with(['awardCategory', 'organization'])->findOrFail($this->nomineeId);
        $mode = $nominee->awardCategory->voting_mode;

        [$quote, $quoteError] = [null, null];

        if ($mode->allowsPaidVotes()) {
            try {
                $quote = app(QuotePaidVotesAction::class)->execute($nominee, $this->quantity);
            } catch (CheckoutUnavailableException $e) {
                $quoteError = $e->getMessage();
            }
        }

        return view('livewire.public.vote-drawer', [
            'nominee' => $nominee,
            'category' => $nominee->awardCategory,
            'allowsFree' => $mode->allowsFreeVotes(),
            'allowsPaid' => $mode->allowsPaidVotes(),
            'freeVoteLimit' => $nominee->awardCategory->free_vote_limit,
            'quote' => $quote,
            'quoteError' => $quoteError,
            'maxQuantity' => (int) config('voting.max_votes_per_order'),
            'quickAdd' => self::QUICK_ADD,
        ]);
    }

    private function setQuantity(int $quantity): void
    {
        $this->quantity = max(1, min($quantity, (int) config('voting.max_votes_per_order')));
        $this->notice = null;
    }

    private function flash(string $message, string $tone): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
    }
}
