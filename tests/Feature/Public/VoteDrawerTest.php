<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Livewire\Public\CategoryShow;
use App\Livewire\Public\VoteDrawer;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Security\VoteFingerprintService;
use App\Support\VoteKeys;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function drawerNominee(VotingMode $mode = VotingMode::Paid, ?Organization $organization = null): Nominee
{
    $program = AwardProgram::factory()->for($organization ?? Organization::factory())->create();
    $category = AwardCategory::factory()->for($program)->mode($mode)->create(['price_per_vote' => 100]);

    return Nominee::factory()->for($category)->create(['name' => 'Akosua Mensah']);
}

beforeEach(function (): void {
    config(['voting.paystack_fee_bps' => 195, 'voting.max_votes_per_order' => 500]);
    $this->gateway = fakeGateway();
});

it('shows the voter the price breakdown: subtotal + processing fee = total', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])
        ->set('quantity', 25)
        ->assertSee('25 votes × GHS 1.00')
        ->assertSee('GHS 25.00')
        ->assertSee('Processing fee (Paystack)')
        ->assertSee('GHS 0.50')
        ->assertSee('GHS 25.50')
        ->assertSee('Pay GHS 25.50 securely with Paystack');
});

it('steps, quick-adds and clamps the quantity', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])
        ->call('adjust', -1)
        ->assertSet('quantity', 1)
        ->call('adjust', 1)
        ->assertSet('quantity', 2)
        ->call('adjust', 50)
        ->assertSet('quantity', 52)
        ->call('adjust', 7)
        ->assertSet('quantity', 52) // only the offered steps
        ->call('adjust', -40)
        ->assertSet('quantity', 52)
        ->set('quantity', 0)
        ->assertSet('quantity', 1)
        ->set('quantity', 9_999)
        ->assertSet('quantity', 500)
        ->call('adjust', 100)
        ->assertSet('quantity', 500);
});

it('starts checkout with the quoted amount and redirects to Paystack', function (): void {
    $nominee = drawerNominee();

    Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->set('quantity', 25)
        ->call('checkout')
        ->assertRedirect('https://checkout.test/' . VoteTransaction::query()->sole()->payment_reference);

    $transaction = VoteTransaction::query()->sole();
    expect($transaction->status)
        ->toBe(TransactionStatus::Pending)
        ->and($transaction->gross_amount)
        ->toBe(2_550)
        ->and($this->gateway->initialized[0]->amount)
        ->toBe(2_550);
});

it('returns a double submit to the same checkout instead of starting another', function (): void {
    $component = Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])->set('quantity', 3);

    $component->call('checkout');
    $component
        ->call('checkout')
        ->assertRedirect('https://checkout.test/' . VoteTransaction::query()->sole()->payment_reference);

    expect(VoteTransaction::query()->count())->toBe(1)->and($this->gateway->initialized)->toHaveCount(1);
});

it('refuses a checkout while one is already starting for the same voter and nominee', function (): void {
    $nominee = drawerNominee();
    $component = Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id]);
    $voter = app(VoteFingerprintService::class)->identify(request());
    Cache::lock(VoteKeys::checkoutLock($voter->voterKey, $nominee->id), 15)->get();

    $component->call('checkout')->assertSee('already starting your checkout');

    expect(VoteTransaction::query()->count())->toBe(0);
});

it('rate-limits checkout attempts per voter', function (): void {
    config(['voting.rate_limits.checkout_per_voter' => 2]);
    $component = Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id]);

    $component->set('quantity', 1)->call('checkout');
    $component->set('quantity', 2)->call('checkout');
    $component->set('quantity', 3)->call('checkout')->assertSee('Too many checkout attempts');

    expect(VoteTransaction::query()->count())->toBe(2);
});

it('validates the optional receipt email', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])
        ->set('email', 'not-an-email')
        ->call('checkout')
        ->assertHasErrors(['email' => 'email']);

    expect(VoteTransaction::query()->count())->toBe(0);
});

it('tells the voter paid voting is not set up when the organizer has no subaccount', function (): void {
    $nominee = drawerNominee(organization: Organization::factory()->withoutPaystackSubaccount()->create());

    Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->assertSee('Paid voting is not set up for this event yet')
        ->assertDontSee('Processing fee')
        ->call('checkout')
        ->assertSee('Paid voting is not set up');

    expect(VoteTransaction::query()->count())->toBe(0);
});

it('opens on the free tab for free-only categories, without a payment form', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee(VotingMode::Free)->id])
        ->assertSet('tab', 'free')
        ->assertSee('Cast my free vote')
        ->assertDontSee('Processing fee')
        ->assertDontSee('Buy votes');
});

it('casts a free vote, refreshes the page, then refuses a second one today', function (): void {
    $nominee = drawerNominee(VotingMode::Hybrid);

    Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->call('showTab', 'free')
        ->call('castFreeVote')
        ->assertSee('Your free vote for Akosua Mensah is in')
        ->assertDispatched('votes-updated')
        ->call('castFreeVote')
        ->assertSee("already used today's free vote");

    expect(VoteRecord::query()->where('is_paid', false)->count())
        ->toBe(1)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(1);
});

it('treats a different device cookie as a different voter', function (): void {
    $nominee = drawerNominee(VotingMode::Free);

    Livewire::withCookie(config('voting.voter_cookie'), 'phone-a')
        ->test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->call('castFreeVote');
    Livewire::withCookie(config('voting.voter_cookie'), 'phone-b')
        ->test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->call('castFreeVote');

    expect($nominee->fresh()?->votes_count)->toBe(2);
});

it('rate-limits free vote attempts per network', function (): void {
    config(['voting.rate_limits.free_vote_per_ip' => 1]);
    $nominee = drawerNominee(VotingMode::Free);

    Livewire::withCookie(config('voting.voter_cookie'), 'phone-a')
        ->test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->call('castFreeVote');
    Livewire::withCookie(config('voting.voter_cookie'), 'phone-b')
        ->test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->call('castFreeVote')
        ->assertSee('Too many attempts');

    expect($nominee->fresh()?->votes_count)->toBe(1);
});

it('refuses free votes in paid-only categories', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])
        ->call('castFreeVote')
        ->assertSee('does not accept free votes');

    expect(VoteRecord::query()->count())->toBe(0);
});

it('does not let the browser retarget the drawer at another nominee', function (): void {
    Livewire::test(VoteDrawer::class, ['nomineeId' => drawerNominee()->id])->set('nomineeId', drawerNominee()->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('only selects nominees from the category being shown', function (): void {
    $nominee = drawerNominee();
    $category = $nominee->awardCategory;

    Livewire::test(CategoryShow::class, [
        'organization' => $category->awardProgram->organization,
        'awardProgram' => $category->awardProgram,
        'category' => $category,
    ])
        ->call('select', drawerNominee()->id)
        ->assertSet('selectedNomineeId', null)
        ->call('select', $nominee->id)
        ->assertSet('selectedNomineeId', $nominee->id)
        ->dispatch('vote-drawer-closed')
        ->assertSet('selectedNomineeId', null);
});
