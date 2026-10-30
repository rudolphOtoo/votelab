<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\PaystackFeeCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A realistic campus awards night: a mix of paid, free and hybrid categories in every
 * lifecycle state, with ledger rows whose totals match each nominee's counter.
 */
final class DemoProgramSeeder extends Seeder
{
    /**
     * @var list<array{name: string, mode: VotingMode, status: CategoryStatus, price: int, nominees: list<string>}>
     */
    private const array CATEGORIES = [
        [
            'name' => 'Artiste of the Year',
            'mode' => VotingMode::Paid,
            'status' => CategoryStatus::Open,
            'price' => 100,
            'nominees' => ['Kwame Asante', 'Akosua Mensah', 'Yaw Boateng', 'Efua Owusu', 'Kofi Darko'],
        ],
        [
            'name' => 'Best Dressed',
            'mode' => VotingMode::Hybrid,
            'status' => CategoryStatus::Open,
            'price' => 50,
            'nominees' => ['Ama Serwaa', 'Kojo Antwi', 'Abena Ofori', 'Nana Yeboah'],
        ],
        [
            'name' => 'Most Influential Student',
            'mode' => VotingMode::Paid,
            'status' => CategoryStatus::Embargoed,
            'price' => 200,
            'nominees' => ['Adwoa Frimpong', 'Kwesi Appiah', 'Esi Quaye', 'Fiifi Ankrah'],
        ],
        [
            'name' => 'Freshman of the Year',
            'mode' => VotingMode::Free,
            'status' => CategoryStatus::Open,
            'price' => 100,
            'nominees' => ['Afia Badu', 'Kwabena Osei', 'Akua Donkor'],
        ],
        [
            'name' => 'Sports Personality',
            'mode' => VotingMode::Paid,
            'status' => CategoryStatus::Closed,
            'price' => 100,
            'nominees' => ['Yaa Asantewaa', 'Kwaku Manu', 'Maame Agyei', 'Selorm Tetteh'],
        ],
        [
            'name' => 'Entrepreneur of the Year',
            'mode' => VotingMode::Paid,
            'status' => CategoryStatus::Draft,
            'price' => 100,
            'nominees' => ['Edem Kpodo', 'Naa Adjeley', 'Kobby Sarpong'],
        ],
    ];

    public function run(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Accra Campus Creatives',
            'slug' => 'accra-campus-creatives',
            'paystack_subaccount_code' => 'ACCT_demotestonly',
        ]);

        $owner = User::factory()->create([
            'name' => 'Demo Organizer',
            'email' => 'organizer@votelab.test',
        ]);
        $organization->addMember($owner, OrganizationRole::Owner);

        $staff = User::factory()->create([
            'name' => 'Demo Staff',
            'email' => 'staff@votelab.test',
        ]);
        $organization->addMember($staff, OrganizationRole::Staff);

        $program = AwardProgram::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Campus Excellence Awards 2026',
            'slug' => 'campus-excellence-awards-2026',
            'tagline' => 'Celebrating the students who made this year unforgettable.',
            'description' => 'Vote for your favourites in every category. Paid votes support the SRC welfare fund.',
            'status' => ProgramStatus::Published,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(10),
        ]);

        foreach (self::CATEGORIES as $order => $definition) {
            $category = AwardCategory::query()->create([
                'organization_id' => $organization->id,
                'award_program_id' => $program->id,
                'name' => $definition['name'],
                'slug' => Str::slug($definition['name']),
                'description' => "Who deserves the {$definition['name']} crown this year?",
                'voting_mode' => $definition['mode'],
                'price_per_vote' => $definition['price'],
                'free_vote_limit' => 1,
                'status' => $definition['status'],
                'closed_at' => $definition['status'] === CategoryStatus::Closed ? now()->subHour() : null,
                'display_order' => $order,
            ]);

            foreach ($definition['nominees'] as $position => $name) {
                $nominee = Nominee::query()->create([
                    'organization_id' => $organization->id,
                    'award_program_id' => $program->id,
                    'award_category_id' => $category->id,
                    'name' => $name,
                    'nominee_code' => sprintf('C%02d-%02d', $order + 1, $position + 1),
                    'bio' => fake()->sentence(12),
                    'display_order' => $position,
                ]);

                if ($definition['status'] !== CategoryStatus::Draft) {
                    $this->seedVotes($category, $nominee);
                }
            }
        }

        $this->syncCounters($program);
    }

    private function seedVotes(AwardCategory $category, Nominee $nominee): void
    {
        if ($category->voting_mode->allowsPaidVotes()) {
            foreach (range(1, fake()->numberBetween(1, 6)) as $ignored) {
                $quote = PaystackFeeCalculator::fromConfig()->quote(quantity: fake()->randomElement([
                    1,
                    5,
                    10,
                    25,
                    50,
                ]), unitPrice: $category->price_per_vote);
                $transaction = VoteTransaction::factory()
                    ->for($nominee)
                    ->status(TransactionStatus::Successful)
                    ->create([
                        'quantity' => $quantity = $quote->quantity,
                        'unit_price' => $quote->unitPrice,
                        'net_amount' => $quote->subtotal,
                        'processing_fee_pesewas' => $quote->processingFee,
                        'gross_amount' => $quote->total,
                        'settlement_subaccount_code' => $nominee->organization?->paystack_subaccount_code,
                        'verified_at' => now(),
                        'credited_at' => now(),
                    ]);

                VoteRecord::factory()->for($nominee)->create([
                    'vote_transaction_id' => $transaction->id,
                    'is_paid' => true,
                    'quantity' => $quantity,
                    'voter_fingerprint' => null,
                ]);
            }

            VoteTransaction::factory()->for($nominee)->status(TransactionStatus::Failed)->create();
        }

        if ($category->voting_mode->allowsFreeVotes()) {
            VoteRecord::factory()->count(fake()->numberBetween(3, 15))->for($nominee)->create();
        }
    }

    /**
     * Set each nominee's derived counter from the ledger.
     */
    private function syncCounters(AwardProgram $program): void
    {
        $program
            ->nominees()
            ->each(static function (Nominee $nominee): void {
                $nominee->forceFill(['votes_count' => (int) $nominee->voteRecords()->sum('quantity')])->save();
            });
    }
}
