<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Actions\Admin\UpdateOrganizationSettingsAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Support\LeaderboardCache;
use Database\Seeders\Showcase\ShowcaseArt;
use Database\Seeders\Showcase\ShowcaseLedger;
use Database\Seeders\Showcase\ShowcasePayments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * LOCAL ONLY: a demo that reaches every screen, for clicking through the app (plan/PREVIEW.md).
 *
 *   php artisan migrate:fresh --seed --seeder=LocalShowcaseSeeder
 *
 * Refuses to run unless APP_ENV=local: it creates accounts with a known password. Two organizations (one with
 * an uploaded logo and brand colour), an owner, admin and staff inside the two-factor grace period, and a
 * published program with a category in every state, including a revealed tie. Portraits are generated
 * silhouettes put through the real upload pipeline, so every srcset variant exists. Payments cover every
 * result page (successful, pending, failed, expired, flagged, paid-but-not-counted, refunded), each credited
 * payment has its ledger rows, and every counter is set from the ledger, so there is no drift. Category
 * changes, reveals, the refund and the branding go through the real actions, so the history is genuine.
 */
final class LocalShowcaseSeeder extends Seeder
{
    // @mago-expect lint:no-literal-password
    public const string PASSWORD = 'password';

    /** @var array<string, array{name: string, role: OrganizationRole}> */
    public const array ACCOUNTS = [
        'owner@votelab.test' => ['name' => 'Demo Owner', 'role' => OrganizationRole::Owner],
        'admin@votelab.test' => ['name' => 'Demo Admin', 'role' => OrganizationRole::Admin],
        'staff@votelab.test' => ['name' => 'Demo Staff', 'role' => OrganizationRole::Staff],
    ];

    public const string ORGANIZATION = 'accra-campus-creatives';

    public const string PROGRAM = 'campus-excellence-awards-2026';

    /**
     * The status each category ends in, and its nominees: name => [code, votes]. Votes are exact, so the
     * podium (and the tie in Best Dressed) is the same on every run.
     *
     * @var list<array{name: string, mode: VotingMode, price: int, status: CategoryStatus, nominees: array<string, array{string, int}>}>
     */
    public const array CATEGORIES = [
        [
            'name' => 'Artiste of the Year',
            'mode' => VotingMode::Paid,
            'price' => 100,
            'status' => CategoryStatus::Open,
            'nominees' => [
                'Kwame Asante' => ['KA27', 412],
                'Oheneba Kwame Agyemang-Prempeh Asantewaa-Boakye' => ['KB38', 388],
                'Akosua Mensah' => ['AM42', 251],
                'Yaw Boateng' => ['YB53', 97],
                'Efua Ansah' => ['EA64', 64],
                'Kofi Darko' => ['KD75', 20],
            ],
        ],
        [
            'name' => 'Most Popular Student',
            'mode' => VotingMode::Hybrid,
            'price' => 50,
            'status' => CategoryStatus::Open,
            'nominees' => [
                'Ama Serwaa' => ['AS23', 180],
                'Kojo Antwi' => ['KJ34', 142],
                'Abena Ofori' => ['AB45', 131],
                'Nana Yeboah' => ['NY56', 76],
                'Esi Quaye' => ['EQ67', 39],
            ],
        ],
        [
            'name' => 'Freshman of the Year',
            'mode' => VotingMode::Free,
            'price' => 100,
            'status' => CategoryStatus::Open,
            'nominees' => [
                'Afia Badu' => ['FB28', 46],
                'Kwabena Mensa' => ['KM39', 33],
                'Akua Donkor' => ['AD48', 21],
                'Selasi Kpogo' => ['SK59', 12],
            ],
        ],
        [
            'name' => 'Best Dancer',
            'mode' => VotingMode::Paid,
            'price' => 200,
            'status' => CategoryStatus::Embargoed,
            'nominees' => [
                'Adwoa Frimpong' => ['AF22', 305],
                'Kwesi Appiah' => ['KW33', 284],
                'Dzifa Amegah' => ['DZ44', 150],
                'Fiifi Ankrah' => ['FA55', 92],
            ],
        ],
        [
            'name' => 'Sports Personality',
            'mode' => VotingMode::Paid,
            'price' => 100,
            'status' => CategoryStatus::Closed,
            'nominees' => [
                'Yaa Asantewaa' => ['YA26', 230],
                'Kwaku Manu' => ['KU37', 198],
                'Maame Agyei' => ['MA48', 160],
                'Selorm Tetteh' => ['ST59', 75],
                'Edinam Kuma' => ['EK62', 40],
            ],
        ],
        [
            'name' => 'Song of the Year',
            'mode' => VotingMode::Paid,
            'price' => 100,
            'status' => CategoryStatus::Revealed,
            'nominees' => [
                'Kweku Bentil' => ['KX24', 520],
                'Araba Essel' => ['AR35', 361],
                'Ekow Mensah' => ['EW46', 298],
                'Naa Adjeley' => ['NA57', 120],
                'Kobby Sarpong' => ['KS68', 64],
            ],
        ],
        [
            'name' => 'Best Dressed',
            'mode' => VotingMode::Hybrid,
            'price' => 50,
            'status' => CategoryStatus::Revealed,
            'nominees' => [
                'Abla Dzifa' => ['AZ27', 640],
                'Kofi Nyarko' => ['KN38', 640],
                'Ewurabena Koomson' => ['EB49', 410],
                'Jojo Quansah' => ['JQ52', 150],
            ],
        ],
        [
            'name' => 'Entrepreneur of the Year',
            'mode' => VotingMode::Paid,
            'price' => 100,
            'status' => CategoryStatus::Draft,
            'nominees' => [
                'Edem Kpodo' => ['ED23', 0],
                'Akos Wiredu' => ['AW34', 0],
                'Kekeli Amedor' => ['KE45', 0],
                'Nii Lamptey' => ['NL56', 0],
            ],
        ],
    ];

    private int $portraitSeed = 0;

    private ShowcaseLedger $ledger;

    public function __construct()
    {
        $this->ledger = new ShowcaseLedger();
    }

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new RuntimeException(
                'LocalShowcaseSeeder only runs with APP_ENV=local: it creates accounts with a known password.',
            );
        }

        $this->call(PlatformSettingSeeder::class);

        $users = $this->users();
        $organization = $this->organization($users);
        $this->secondOrganization($users['owner@votelab.test']);
        $program = $this->program($organization);

        $payments = new ShowcasePayments($this->ledger);
        $categories = [];

        foreach (self::CATEGORIES as $order => $definition) {
            $categories[$definition['name']] = $this->category($program, $definition, $order);
        }

        $payments->inFlight($categories);
        ShowcaseLedger::syncCounters($program);
        $this->moveToFinalStates($categories, $users['owner@votelab.test']);
        $payments->afterTheReveal($categories['Song of the Year'], $users['owner@votelab.test']);

        // Nothing cached before the final states may be served.
        array_map(static fn(AwardCategory $category) => LeaderboardCache::purge($category->id), $categories);

        $this->printLogins();
    }

    /**
     * @return array<string, User>
     */
    private function users(): array
    {
        $users = [];

        foreach (self::ACCOUNTS as $email => $account) {
            $users[$email] = User::factory()->create([
                'name' => $account['name'],
                'email' => $email,
                'password' => self::PASSWORD,
            ]);
            // Inside the two-factor grace period (organizations.two_factor_grace_days), so nobody is locked out.
            $users[$email]->forceFill(['first_signed_in_at' => now()])->save();
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     */
    private function organization(array $users): Organization
    {
        $organization = Organization::query()->create([
            'name' => 'Accra Campus Creatives',
            'slug' => self::ORGANIZATION,
            'paystack_subaccount_code' => 'ACCT_showcasetestonly',
        ]);

        foreach (self::ACCOUNTS as $email => $account) {
            $organization->addMember($users[$email], $account['role']);
        }

        // Logo upload and brand colour through Settings' own action: re-encoded logo, audited change.
        app(UpdateOrganizationSettingsAction::class)->execute(
            $organization,
            $users['owner@votelab.test'],
            ['logo' => ShowcaseArt::logo(345), 'brand_color' => '#8B1E3F'],
            passwordConfirmed: false,
        );

        return $organization->refresh();
    }

    /**
     * A second organization for the owner, so the dashboard shows the organization switcher. No logo: it shows
     * the fallback mark.
     */
    private function secondOrganization(User $owner): void
    {
        $organization = Organization::query()->create([
            'name' => 'Kumasi Arts Collective',
            'slug' => 'kumasi-arts-collective',
            'settings' => ['brand_color' => '#1F4E8C'],
        ]);
        $organization->addMember($owner, OrganizationRole::Owner);

        $program = AwardProgram::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Kumasi Arts Awards 2027',
            'slug' => 'kumasi-arts-awards-2027',
            'tagline' => 'Still being set up.',
            'status' => ProgramStatus::Draft,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonths(2),
        ]);

        AwardCategory::query()->create([
            'organization_id' => $organization->id,
            'award_program_id' => $program->id,
            'name' => 'Best Visual Artist',
            'slug' => 'best-visual-artist',
            'voting_mode' => VotingMode::Paid,
            'price_per_vote' => 100,
            'status' => CategoryStatus::Draft,
            'display_order' => 0,
        ]);
    }

    private function program(Organization $organization): AwardProgram
    {
        return AwardProgram::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Campus Excellence Awards 2026',
            'slug' => self::PROGRAM,
            'tagline' => 'Celebrating the students who made this year unforgettable.',
            'description' => 'Vote for your favourites in every category. Paid votes support the SRC welfare fund.',
            'status' => ProgramStatus::Published,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(10),
        ]);
    }

    /**
     * Created open (draft stays draft) with its votes; moveToFinalStates() takes it to its final state.
     *
     * @param  array{name: string, mode: VotingMode, price: int, status: CategoryStatus, nominees: array<string, array{string, int}>}  $definition
     */
    private function category(AwardProgram $program, array $definition, int $order): AwardCategory
    {
        $draft = $definition['status'] === CategoryStatus::Draft;
        $category = AwardCategory::query()->create([
            'organization_id' => $program->organization_id,
            'award_program_id' => $program->id,
            'name' => $definition['name'],
            'slug' => Str::slug($definition['name']),
            'description' => "Who deserves the {$definition['name']} crown this year?",
            'voting_mode' => $definition['mode'],
            'price_per_vote' => $definition['price'],
            'free_vote_limit' => 1,
            'status' => $draft ? CategoryStatus::Draft : CategoryStatus::Open,
            'display_order' => $order,
        ]);

        $position = 0;

        foreach ($definition['nominees'] as $name => [$code, $votes]) {
            $nominee = $this->nominee($category, $name, $code, $position++);
            $this->ledger->votes($nominee, $votes);
        }

        return $category;
    }

    private function nominee(AwardCategory $category, string $name, string $code, int $position): Nominee
    {
        $nominee = Nominee::query()->create([
            'organization_id' => $category->organization_id,
            'award_program_id' => $category->award_program_id,
            'award_category_id' => $category->id,
            'name' => $name,
            'nominee_code' => $code,
            'bio' => fake()->sentence(14),
            'display_order' => $position,
        ]);

        ShowcaseArt::attachPortrait($nominee, ++$this->portraitSeed);

        return $nominee;
    }

    /**
     * @param  array<string, AwardCategory>  $categories
     */
    private function moveToFinalStates(array $categories, User $owner): void
    {
        $transition = app(TransitionCategoryStatusAction::class);
        $reveal = app(RevealWinnerAction::class);

        foreach (self::CATEGORIES as $definition) {
            $category = $categories[$definition['name']];

            match ($definition['status']) {
                CategoryStatus::Embargoed => $transition->execute($category, CategoryStatus::Embargoed, $owner),
                CategoryStatus::Closed => $transition->execute($category, CategoryStatus::Closed, $owner),
                CategoryStatus::Revealed => $reveal->execute(
                    $transition->execute($category, CategoryStatus::Closed, $owner),
                    $owner,
                ),
                default => null,
            };
        }
    }

    private function printLogins(): void
    {
        $rows = array_map(
            static fn(string $email, array $account): array => [$account['role']->label(), $email, self::PASSWORD],
            array_keys(self::ACCOUNTS),
            self::ACCOUNTS,
        );

        $this->command?->getOutput()->newLine();
        $this->command?->info('Local showcase logins (two-factor not required for 7 days):');
        $this->command?->table(['Role', 'Email', 'Password'], $rows);
    }
}
