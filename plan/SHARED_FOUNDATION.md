# 🏛️ VoteLab — Milestone 0: Shared Foundation Plan

> **Role & Purpose**: The universal starter foundation for VoteLab. This document defines the baseline database schema, domain models, enums, design tokens, packages, and architecture that **Dev A** and **Dev B** share before branching into independent workstreams.

---

## 📦 1. Curated Package Manifest (Senior Stable Delivery Triage)

We inspected `TicketLab`'s dependency tree and performed strict anti-bloat triage:

### PHP / Composer (`composer.json`)

| Package | Version | Status | Architectural Rationale |
| :--- | :--- | :--- | :--- |
| `php` | `^8.3` | **CORE** | Minimum requirement for typed properties, read-only classes, and performance. |
| `laravel/framework` | `^13.17` | **CORE** | Modern Laravel 13 framework baseline. |
| `livewire/livewire` | `^4.1` | **CORE** | Full-stack reactive UI with zero complex build tooling. |
| `kendenigerian/payzephyr` | `^5.1` | **CORE** | Unified payment abstraction layer configured with Paystack driver for paid votes. |
| `laravel/fortify` | `^1.37` | **CORE** | Headless authentication (passkeys, 2FA, session security) for admin/staff. |
| `spatie/simple-excel` | `*` | **CORE** | High-performance, streaming CSV/Excel export for vote tallying and audit reports. |
| `wireui/heroicons` | `^2.10` | **CORE** | Consistent SVG icons integrated with Blade components. |
| `sheaf/cli` | `^1.3` (dev) | **CORE** | Design component engine providing Sheaf UI components. |
| `carthage-software/mago` | `^1.58` (dev) | **CORE** | Zero-config, ultra-fast PHP linter, formatter, and static analysis. |
| `larastan/larastan` | `^3.9` (dev) | **CORE** | PHPStan Level 7 enforcement for strict type safety. |
| `pestphp/pest` | `^5.3` (dev) | **CORE** | Expressive testing suite for unit and feature testing. |
| `barryvdh/laravel-dompdf` | `*` | **REJECTED** | *TicketLab used this for PDF tickets; VoteLab is voting only—zero PDF generation needed.* |
| `chillerlan/php-qrcode` | `^6.0` | **REJECTED** | *TicketLab used this for door-scan QR tickets; VoteLab has no door scanning.* |

### JavaScript / Node (`package.json`)

| Package | Version | Status | Architectural Rationale |
| :--- | :--- | :--- | :--- |
| `tailwindcss` | `^4.0.7` | **CORE** | Utility-first CSS engine. |
| `@tailwindcss/vite` | `^4.1.11` | **CORE** | Native Vite plugin for Tailwind CSS 4. |
| `laravel-vite-plugin` | `^3.1` | **CORE** | Vite integration for Laravel asset bundling. |
| `canvas-confetti` | `^1.9.4` | **CORE** | Lightweight GPU-accelerated confetti burst for auditorium stage winner reveals. |
| `html5-qrcode` | `^2.3.8` | **REJECTED** | *Eliminated ticket scanner camera libraries.* |

---

## 🗄️ 2. Database Schema & Migration Blueprints

### Migration 1: `create_organizations_table`
*File*: `database/migrations/2026_10_30_000001_create_organizations_table.php`
```php
Schema::create('organizations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('logo_url')->nullable();
    $table->json('settings')->nullable();
    $table->timestamps();
});
```

### Migration 2: `create_platform_settings_table`
*File*: `database/migrations/2026_10_30_000002_create_platform_settings_table.php`
```php
Schema::create('platform_settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

### Migration 3: `create_award_programs_table`
*File*: `database/migrations/2026_10_30_000003_create_award_programs_table.php`
```php
Schema::create('award_programs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('slug');
    $table->string('tagline')->nullable();
    $table->text('description')->nullable();
    $table->string('banner_url')->nullable();
    $table->string('status')->default('draft'); // draft, published, archived
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->json('settings')->nullable();
    $table->timestamps();

    $table->unique(['organization_id', 'slug']);
    $table->index(['organization_id', 'status']);
});
```

### Migration 4: `create_award_categories_table`
*File*: `database/migrations/2026_10_30_000004_create_award_categories_table.php`
```php
Schema::create('award_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('award_program_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->string('voting_mode')->default('paid'); // free, paid, hybrid
    $table->unsignedInteger('price_per_vote')->default(100); // Pesewas / Cents (100 = GHS 1.00)
    $table->unsignedInteger('free_vote_limit')->default(1);
    $table->string('status')->default('draft'); // draft, open, embargoed, closed, revealed
    $table->integer('display_order')->default(0);
    $table->timestamps();

    $table->unique(['award_program_id', 'slug']);
    $table->index(['award_program_id', 'status', 'display_order']);
});
```

### Migration 5: `create_nominees_table`
*File*: `database/migrations/2026_10_30_000005_create_nominees_table.php`
```php
Schema::create('nominees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('stage_name')->nullable();
    $table->string('nominee_code', 32);
    $table->string('avatar_url')->nullable();
    $table->text('bio')->nullable();
    $table->unsignedBigInteger('votes_count')->default(0); // Atomic tally counter
    $table->integer('display_order')->default(0);
    $table->timestamps();

    $table->unique(['award_category_id', 'nominee_code']);
    $table->index(['award_category_id', 'votes_count']);
});
```

### Migration 6: `create_vote_transactions_table`
*File*: `database/migrations/2026_10_30_000006_create_vote_transactions_table.php`
```php
Schema::create('vote_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('award_category_id')->constrained()->restrictOnDelete();
    $table->foreignId('nominee_id')->constrained()->restrictOnDelete();
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('unit_price');
    $table->unsignedInteger('gross_amount');
    $table->unsignedInteger('fee_amount');
    $table->unsignedInteger('net_amount');
    $table->string('payment_provider')->default('paystack');
    $table->string('payment_reference')->unique();
    $table->string('status')->default('pending'); // pending, successful, failed, refunded
    $table->string('voter_phone')->nullable();
    $table->string('voter_email')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['organization_id', 'status']);
});
```

### Migration 7: `create_vote_records_table`
*File*: `database/migrations/2026_10_30_000007_create_vote_records_table.php`
```php
Schema::create('vote_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('award_category_id')->constrained()->restrictOnDelete();
    $table->foreignId('nominee_id')->constrained()->restrictOnDelete();
    $table->foreignId('vote_transaction_id')->nullable()->constrained()->nullOnDelete();
    $table->boolean('is_paid')->default(false);
    $table->unsignedInteger('quantity')->default(1);
    $table->string('voter_fingerprint', 128)->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->timestamp('cast_at')->useCurrent();

    $table->index(['voter_fingerprint', 'award_category_id']);
    $table->index(['nominee_id', 'cast_at']);
});
```

---

## 💎 3. Enums & Type Safety

### `App\Enums\CategoryStatus`
```php
namespace App\Enums;

enum CategoryStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Embargoed = 'embargoed'; // Voting is active, but live leaderboard is hidden
    case Closed = 'closed';       // Voting stopped; results locked
    case Revealed = 'revealed';   // Official winner announced on stage

    public function isVotingAllowed(): bool
    {
        return in_array($this, [self::Open, self::Embargoed], true);
    }

    public function isLeaderboardPublic(): bool
    {
        return in_array($this, [self::Open, self::Revealed], true);
    }
}
```

### `App\Enums\VotingMode`
```php
namespace App\Enums;

enum VotingMode: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Hybrid = 'hybrid';
}
```

### `App\Enums\TransactionStatus`
```php
namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
```

---

## 🏛️ 4. Shared Multi-Tenancy & Dedicated Mode Support

### `App\Support\DedicatedSite`
Resolves whether VoteLab operates as an open multi-tenant portal (`/org/{slug}`) or locks down to a single headline award gala (`/` serves the bespoke event).
* Reads `PlatformSetting::get('deployment_mode')` and `PlatformSetting::get('home_org_slug')`.
* Exposes `DedicatedSite::resolve(): ?self` with `public Organization $organization` and `public ?AwardProgram $program`.

### `App\Support\ActiveProgram`
Header-level program selector for administrative users to switch contexts across multiple active programs within an organization.

---

## 🎨 5. Design System: Sheaf UI & Serene Studio Tokens

Installed into `resources/css/theme.css`:
```css
:root {
  --bg-canvas: #FAF9F6;
  --bg-surface: #FFFFFF;
  --bg-subtle: #F3F1EC;
  --bg-hover: #EFECE6;
  --text-primary: #18181B;
  --text-secondary: #71717A;
  --text-tertiary: #A1A1AA;
  --border-hairline: #E4E2DC;
  --border-focused: #18181B;
  --accent-brand: #18181B;
  --accent-brand-fg: #FFFFFF;
  --status-success: #15803D;
  --status-warning: #B45309;
  --status-danger: #B91C1C;
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
}
```

Sheaf UI components mirrored into `resources/views/components/ui/`:
* `avatar`: Nominee portraits with fallback initials.
* `badge`: Status chips (`Open`, `Embargoed`, `Closed`).
* `button`: Primary, secondary, danger, and ghost buttons with `:active scale(0.98)` tactile response.
* `card`: Clean 1px soft-hairline containers.
* `input`: Form inputs with floating labels.
* `modal`: Accessible dialog sheets.
* `toast`: Transient feedback notifications.

