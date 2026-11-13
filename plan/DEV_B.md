# 👩‍💻 Dev B Workstream Plan: Public Voting Engine, Commerce & Security

> **Owner**: Dev B (You)  
> **Boundary Guarantee**: Dev B operates exclusively inside `app/Livewire/Public/`, `app/Actions/Voting/`, `app/Services/Payment/`, `app/Services/Security/`, and `app/Http/Controllers/Payment/`. Zero edits to admin management screens or stage projector displays.

---

## 🎯 Scope & Objectives

Dev B is responsible for the public voting experience, payment processing, and ballot security:
1. **Public Voter Portal**: Fluid category browsing and candidate cards with 4:5 portrait photos and status pills.
2. **The "Alive" Vote Drawer (`VoteDrawer.php`)**: An interactive bottom-sheet / modal featuring tactile feedback, quantity stepper, and live pricing calculations.
3. **Paystack Integration via PayZephyr**: Zero-net-loss fee calculation, payment initiation, and bulletproof webhook handling with idempotency locks.
4. **Anti-Sybil Free-Vote Defense**: Lightweight browser fingerprinting and cache-backed rate limiting to prevent botting on free votes without SMS OTP friction.

---

## 📁 File Manifest & Ownership

### 1. Actions (`app/Actions/Voting/`)
* **`CastFreeVoteAction.php`**:
  * Asserts category status allows voting (`Open` or `Embargoed`).
  * Asserts category supports `free` or `hybrid` mode.
  * Queries `VoteFingerprintService` to verify the voter hasn't exhausted their category allocation.
  * Atomically increments `nominees.votes_count` and records audit entry in `vote_records`.
* **`InitiatePaidVoteOrderAction.php`**:
  * Calculates total price (e.g. 50 votes $\times$ GHS 1.00 = GHS 50.00).
  * Runs `PaystackFeeCalculator` to compute gateway surcharge (zero net loss to organizer).
  * Creates `vote_transactions` in `pending` state.
  * Calls `PayZephyrService` to generate Paystack authorization URL and reference.
* **`FulfillPaidVoteOrderAction.php`**:
  * **Critical Idempotency Gate**: Wrapped in `DB::transaction()` with `VoteTransaction::where('payment_reference', $ref)->lockForUpdate()`.
  * If already `successful`, early-returns safely without double-incrementing.
  * Marks transaction `successful`.
  * Atomically increments `Nominee::where('id', $nomineeId)->increment('votes_count', $quantity)`.
  * Inserts batch record into `vote_records`.

### 2. Livewire Public Components (`app/Livewire/Public/`)
* **`ProgramLanding.php`**:
  * Hero banner displaying event title, countdown timer to voting deadline, and category grid.
* **`CategoryShow.php`**:
  * Nominee grid for a chosen category.
  * Respects `embargoed` status by suppressing vote counts from the view and network payload.
  * "Vote Now" button opens the interactive `VoteDrawer`.
* **`VoteDrawer.php`**:
  * Reactive bottom sheet (mobile) / center dialog (desktop).
  * Free vote claim tab (if permitted) vs. Paid vote pack tab.
  * Stepper component (`-` / `+`) and quick-select chips (`+10`, `+25`, `+50`, `+100`).
  * Dynamic total summary: e.g., *"50 Votes • GHS 50.00"*.
  * Primary CTA: *"Proceed to Secure Paystack Checkout"*.

### 3. Payment & Services (`app/Services/` & `app/Http/Controllers/`)
* **`PaystackPaymentGateway.php`**:
  * Adapter utilizing `kendenigerian/payzephyr` with custom fallback handling.
* **`PaystackFeeCalculator.php`**:
  * Ported from TicketLab: Computes Paystack standard fees (1.95% + GHS 0.00 / capped) so organizers receive 100% of the vote face value.
* **`PaystackWebhookController.php`**:
  * Route: `POST /api/webhooks/paystack` (CSRF-exempt).
  * Validates `X-Paystack-Signature` against `PAYSTACK_SECRET_KEY`.
  * Dispatches `FulfillPaidVoteOrderAction`.
* **`VoteFingerprintService.php`**:
  * Generates SHA256 signature from: `Request::ip() + User-Agent + Cookie UUID`.
  * Enforces `Cache::has("vote_limit:{$fingerprint}:{$categoryId}")` with a 24-hour expiration window.

---

## 🎨 Micro-Interaction & Tactile Physics (Emil Kowalski Philosophy)

1. **Vote Drawer Entrance**:
   ```css
   .vote-drawer {
     transform: translateY(100%);
     transition: transform 260ms cubic-bezier(0.32, 0.72, 0, 1), opacity 200ms ease-out;
   }
   .vote-drawer.active {
     transform: translateY(0);
   }
   ```
2. **Quantity Stepper Tactile Feel**:
   * Stepper buttons feature instant depress: `:active { transform: scale(0.92); }`.
   * Numerical display uses `font-variant-numeric: tabular-nums` to eliminate layout jiggle as values shift from 9 to 10 or 99 to 100.
3. **Preset Pill Selection**:
   * Selecting `+50` triggers a subtle spring ripple and auto-updates the checkout button label.
4. **Paystack Handoff Loading State**:
   * Checkout CTA transitions to a calming spinner with text: *"Securing ballot with Paystack..."* without freezing the browser thread.

---

## 🧪 Pest Verification Test Suite (`tests/Feature/Voting/`)

* `tests/Feature/Voting/FreeVoteRateLimitTest.php`:
  * Tests that a single voter fingerprint can cast 1 free vote, but a 2nd attempt within 24h fails with HTTP 429 / warning toast.
* `tests/Feature/Voting/PaidVoteInitiationTest.php`:
  * Validates gross, fee, and net calculations for 25 votes at GHS 1.00.
  * Confirms pending transaction is created with unique reference.
* `tests/Feature/Voting/WebhookFulfillmentIdempotencyTest.php`:
  * Simulates Paystack webhook hitting the endpoint twice in parallel.
  * Asserts `nominees.votes_count` increments **exactly once**.
* `tests/Feature/Voting/EmbargoDataLeakTest.php`:
  * Fetches `CategoryShow` when category is `embargoed`.
  * Asserts `votes_count` does NOT exist anywhere in the Livewire state, HTML response, or JSON dehydration payload.

