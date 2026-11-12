# VoteLab — Execution Log

> Working log for executing the plan in `plan/`. Updated at every phase checkpoint.
> Sources: `plan/SHARED_FOUNDATION.md` (M0), `plan/DEV_A.md` (A), `plan/DEV_B.md` (B), `CONTRIBUTING.md`, and the Agent Execution Prompt (incl. its §5 "Blueprint Corrections").

---

## 0. Recon Snapshot (2026-10-30)

| Item | Finding |
| :--- | :--- |
| Branch | `dev`, clean, up to date with `origin/dev` |
| Application code | **None.** Repo contains only `plan/*.md`, `CONTRIBUTING.md`, `.gitignore`. No `composer.json`, `package.json`, migrations, models, tests, or CI. |
| Local toolchain | PHP 8.5.11, Composer 2.9.4, Node 22.15.1 / npm 10.9.2, Laravel installer present. MySQL 26.7, PostgreSQL 18.6, SQLite available. Redis server running; **`phpredis` extension not installed** (would need `predis/predis` or `pecl install redis`). |
| Package availability (Packagist, latest) | `laravel/framework` 13.39.0 · `livewire/livewire` 4.4.8 · `kendenigerian/payzephyr` 5.1.1 · `laravel/fortify` 1.41.0 · `pestphp/pest` 5.3.4 · `larastan/larastan` 3.13.2 · `carthage-software/mago` 1.59.0 · `sheaf/cli` 1.3.5 · `wireui/heroicons` 2.10.0. All satisfy the plan's constraints. |
| Plan shape | Milestone 0 (shared foundation) + two parallel workstreams (Dev A: admin/stage, Dev B: public voting/payments). **No numbered task IDs and no Phases 1–5.** |

### Plan ↔ Execution Prompt mismatch

The execution prompt's §5 corrections target a different blueprint than the one in this repo. Concepts the prompt assumes that **do not exist** in `plan/`:

- `EventStatus` with `Tallying` / `Settled` states and property hooks (plan has `CategoryStatus`: draft/open/embargoed/closed/revealed, already using methods).
- Redis `HINCRBY` live tallies + 60s DB sync (plan uses an atomic DB counter `nominees.votes_count`; no Redis).
- Promo codes / `discounts` table, flash multipliers, bonus votes, bundles.
- Supporter push, `voter_display_name`, privacy modes / strict-anonymous mode, canvas flyer, theme accent contrast check.
- Subaccounts / platform 10% cut.
- Task IDs 1.1 … 5.3; tables `transactions`, `nominees.short_code`, `cached_votes_count`.

Things the plan has that the prompt doesn't mention: multi-tenant organizations, dedicated-site mode, free + hybrid voting, browser-fingerprint free-vote limiting, Fortify auth, Sheaf UI, stage projector screen.

---

## 1. Status Table

Task IDs are derived here because the plan has none: `M0.x` = Shared Foundation, `A.x` = Dev A, `B.x` = Dev B.

| ID | Task | Source | Status |
| :--- | :--- | :--- | :--- |
| M0.1 | Scaffold Laravel 13 app + Livewire 4, Fortify, Pest, Larastan, Mago, Vite/Tailwind 4 | SHARED §1 | done |
| M0.2 | Migrations 1–7 (organizations … vote_records) with corrections | SHARED §2 | done |
| M0.3 | Models, factories, seeders (+ demo seeder) | SHARED §2 / prompt §6 | done |
| M0.4 | Enums `CategoryStatus`, `VotingMode`, `TransactionStatus` (+ transitions) | SHARED §3 | done |
| M0.5 | `DedicatedSite`, `ActiveProgram`, `PlatformSetting` | SHARED §4 | done |
| M0.6 | Design tokens `theme.css` + Sheaf UI base components | SHARED §5 | done |
| M0.7 | `config/voting.php`, `.env.example`, quality gates, CI workflow | prompt §4/§6 | done |
| A.1 | `CreateNomineeAction` (shortcode gen, avatar storage) | DEV_A §1 | todo |
| A.2 | `UpdateCategoryStatusAction` + `CategoryStatusChanged` event | DEV_A §1 | todo |
| A.3 | `RevealWinnerAction` | DEV_A §1 | todo |
| A.4 | Livewire `ProgramManager` | DEV_A §2 | todo |
| A.5 | Livewire `CategoryManager` | DEV_A §2 | todo |
| A.6 | Livewire `NomineeManager` (4:5 upload) | DEV_A §2 | todo |
| A.7 | Livewire `ResultsAuditor` + streaming export | DEV_A §2 | todo |
| A.8 | `StageLeaderboard` (`/stage/{program:slug}`), reveal + confetti | DEV_A §3 | todo |
| A.9 | Policies: AwardProgram, AwardCategory, Nominee | DEV_A §4 | todo |
| A.10 | Tests `tests/Feature/Admin/*` | DEV_A §Tests | todo |
| B.1 | `CastFreeVoteAction` | DEV_B §1 | done |
| B.2 | `InitiatePaidVoteOrderAction` | DEV_B §1 | done |
| B.3 | `FulfillPaidVoteOrderAction` (idempotent crediting) | DEV_B §1 | done |
| B.4 | Livewire `ProgramLanding` | DEV_B §2 | done |
| B.5 | Livewire `CategoryShow` (embargo-safe) | DEV_B §2 | done |
| B.6 | Livewire `VoteDrawer` | DEV_B §2 | done |
| B.7 | `PaymentGateway` contract + PayZephyr/Paystack adapter + fake | DEV_B §3 / prompt §6 | done |
| B.8 | `PaystackFeeCalculator` | DEV_B §3 | done |
| B.9 | `PaystackWebhookController` + callback controller + queued job | DEV_B §3 / prompt §5.4 | done |
| B.10 | `VoteFingerprintService` + rate limiters | DEV_B §3 | done |
| B.11 | Tests `tests/Feature/Voting/*` + concurrency test | DEV_B §Tests | in-progress |
| B.12 | K6 load scripts `tests/load/` + README | prompt §6 | todo |

Phase mapping (approved, D-17):
Phase 1 = M0 · Phase 2 = B.7–B.9, B.2–B.3 (money path) · Phase 3 = B.1, B.4–B.6, B.10 (public portal) · Phase 4 = A.1–A.10 (admin + stage) · Phase 5 = hardening, B.12, reconciliation command.

---

## 2. Decisions Log

| ID | Decision | Reason | Plan section affected |
| :--- | :--- | :--- | :--- |
| D-01 | Keep this log in `plan/` (not `docs/plan/`). | Repo already has one plan directory; avoid splitting. | — |
| D-02 | Follow `CONTRIBUTING.md` for tooling: **Mago** for lint/format (not Pint), Larastan for analysis. Raise PHPStan to **level 8** (CONTRIBUTING says 7; prompt says ≥ 8). | Repo convention wins on tooling; stricter level satisfies both. | CONTRIBUTING, SHARED §1 |
| D-03 | Branch names follow `CONTRIBUTING.md` (`feature/…`), not `feat/phase-N-…`. Commits stay Conventional with task IDs. | Repo convention. | CONTRIBUTING |
| D-04 | (§5.1) `CategoryStatus` gains `allowedTransitions()`, `canTransitionTo()`, `canInitiateCheckout()`, `canFulfillPayment()`. One `TransitionCategoryStatusAction` enforces transitions and audits who/when. Plan's `UpdateCategoryStatusAction` becomes this action. | Single enforcement point; plan already uses methods (no illegal enum properties). | SHARED §3, DEV_A §1 |
| D-05 | (§5.2) DB is the ledger. `nominees.votes_count` is a derived counter; `votes:rebuild-tallies {program}` recomputes it from `vote_records`/successful transactions and reports drift. **No Redis tally layer initially**; leaderboard polls read the counter through a short cache. | Plan has no Redis; the counter is cheap to read. Redis can be added later as a read model without changing the ledger. | SHARED §2, DEV_B §1 |
| D-06 | (§5.3) Fulfilment = `Cache::lock(VoteKeys::fulfilment($ref))->block(5)` + conditional `UPDATE … SET status='successful' WHERE payment_reference=? AND status='pending'`; credit only if 1 row affected, inside a DB transaction. Replaces plan's `lockForUpdate`-only approach. | Survives lock expiry and sequential acquisition. | DEV_B §1 (B.3) |
| D-07 | (§5.4) Webhook verifies HMAC-SHA512 on raw body first, returns 200, processes in a queued job. Both callback and webhook re-verify via gateway and assert status=success, amount = stored `gross_amount`, currency = GHS, reference match; mismatch → `flagged`, no credit. | Never trust payloads. | DEV_B §3 (B.9) |
| D-08 | (§5.5/§5.6) All money in pesewas (int). Quantity, unit price, fee, gross, net are snapshotted on the pending transaction; fulfilment credits stored `quantity` only. Max quantity per order in `config('voting.max_votes_per_order')`. | No recalculation at credit time; no floats. | DEV_B §1 (B.2) |
| D-09 | (§5.8) Add `TransactionStatus::PaidUncredited` and `Flagged`. Successful payment for a category that no longer accepts votes → `paid_uncredited`, surfaced in `ResultsAuditor` and the export. | Never silently drop a paid vote. | SHARED §3, DEV_A A.7 |
| D-10 | (§5.9 analog) Closing a category waits out `config('voting.close_grace_minutes', 5)` for in-flight pending payments: `Open/Embargoed → Closed` is allowed, but payments initiated before close and verified within the grace window are still credited; after it they become `paid_uncredited`. Reveal runs the rebuild synchronously before locking. | Plan has no Tallying state; this preserves its state set while honouring in-flight payments. | SHARED §3, DEV_A A.2/A.3 |
| D-11 | (§5.10) Embargoed categories: public components never load `votes_count` into properties or HTML; counts only via policy-gated admin/stage components. Test asserts absence in HTML and Livewire snapshot. (Plan already requires this.) | Server-side enforcement. | DEV_B B.5, DEV_A A.8 |
| D-12 | (§5.11) **Drop `vote_transactions.voter_phone`.** `voter_email` is optional; when absent, use proxy `anon-{Str::random(24)}@{config('voting.proxy_email_domain')}` for Paystack. `vote_records.ip_address` is replaced by `ip_hash` (HMAC of the IP); the raw address is never stored. Redact customer data from logged Paystack payloads. | Hard rule: never store MSISDNs. Minimise PII. | SHARED §2 (Migrations 6/7) |
| D-13 | (§5.12) Fee model per plan: voter bears Paystack's fee via gross-up so the organizer nets face value (`bearer=account`). No subaccounts or platform cut yet; `config('voting.platform_fee_bps', 0)` reserved. Fee constants verified against Paystack docs at B.8. | Plan specifies zero-net-loss to organizer, no platform cut. | DEV_B B.8 |
| D-14 | §5.7 (promo codes), §5.2 Redis specifics, flash multipliers, supporter push, strict-anonymous display names, canvas flyer, theme accent contrast: **not applicable** — features absent from the plan. Not built unless the plan is extended. | Avoid scope invented outside the plan. | — |
| D-16 | The repo plan is the governing spec; §5 is applied only where it maps. | Owner decision, 2026-10-30. | All |
| D-17 | Build all workstreams: Phase 1 M0 → Phases 2–3 Dev B → Phase 4 Dev A → Phase 5 hardening. | Owner decision, 2026-10-30. | DEV_A, DEV_B |
| D-18 | PHP floor raised from `^8.3` to **`^8.4`**. | The plan's own `pestphp/pest ^5.3` (and PHPUnit 13, openspout 5) require PHP 8.4. CI runs 8.4 and 8.5. | SHARED §1 |
| D-19 | Organizer access uses an `organization_user` pivot with `OrganizationRole` (owner, admin, staff). Owners and admins manage programs, prices and status; staff manage nominees and view results. | The plan names "organization admins" and "authorized staff" but defines no user ↔ organization link. | SHARED §2, DEV_A §4 |
| D-20 | Mago runs **lint and format only**; type analysis is Larastan's job. Larastan has `parseModelCastsMethod: true`, so enum and date casts are typed from `casts()` bodies. | Mago's analyzer duplicates PHPStan and doesn't resolve Laravel magic. Without the flag, Larastan typed every cast column as `string`. | CONTRIBUTING |
| D-21 | `nominees.nominee_code` is unique per **program** (`award_program_id, nominee_code`), not per category. `nominees` gains `award_program_id`. | A voter can quote a code without naming the category, and the constraint is strictly stronger than the plan's. | SHARED §2 (Migration 5) |
| D-22 | `award_categories.closed_at` starts the close grace window (D-10). New `category_status_transitions` table audits every status change (from, to, user, time). | §5.1/§5.9: transitions must be auditable and the grace window enforceable. | SHARED §2 (Migration 4) |
| D-23 | `vote_records.vote_transaction_id` is **unique**, so the database rejects a second credit of the same payment as a last line of defence under D-06. | Defence in depth for exactly-once crediting. | SHARED §2 (Migration 7) |
| D-24 | Sheaf components (`button, badge, card, input, modal, toast, avatar`) installed via `sheaf:init --with-livewire`, which uses Livewire's bundled Alpine. Plan tokens live in `resources/css/theme.css`, mapped to Tailwind and Sheaf variables, plus a `[data-theme=stage]` basalt palette. The skeleton's `CLAUDE.md`/`AGENTS.md` (Laravel Boost boilerplate) were not added. | Avoids a duplicate Alpine instance. Keeps one token source. | SHARED §5 |
| D-25 | `predis/predis` added so Redis works without the `phpredis` extension (not installed locally). | Production cache and locks on Redis (README). | — |
| D-26 | **Git is manual from Phase 2 onward.** The agent creates the phase branch locally and leaves its work uncommitted. It never commits, pushes, amends, rebases, resets, stashes, merges, or runs `gh pr`. Each phase ends with a Commit Plan (ordered commits, file lists, copy-paste commands, PR title and body) and the agent waits for a go-ahead before the next phase. If a phase starts before its parent merges, it stacks on the parent branch. | Revised execution prompt, 2026-10-30. Phase 1 was already committed and pushed under the earlier prompt (`30f9c8e` on `origin/feature/m0-shared-foundation`); it was left as is, because undoing a pushed commit would need a reset and a force-push. | Prompt §4 |
| D-27 | PayZephyr's DTOs carry amounts as **floats in cedis**. `PaystackPaymentGateway` is the only place pesewas ↔ cedis conversion happens: outbound amounts are checked to round-trip exactly (`LogicException` otherwise), and verified amounts become `(int) round(amount * 100)`. The domain only sees integer pesewas. | §5.6, Hard-never "no floats for money". Tested from 1 to 4,294,967,295 pesewas. | DEV_B B.7 |
| D-28 | The gateway goes through `PaymentManager::chargeWithFallback($request, ['paystack'])` and `verify($ref, 'paystack')`, so it's pinned to Paystack with no fallback, and we keep PayZephyr's in-flight double-submit guard, tracing, and ambiguous-outcome protection. PayZephyr's own transaction log is off (`PAYMENTS_LOGGING_ENABLED=false`) and its migrations aren't installed, because `vote_transactions` is the ledger. Its `/payments/webhook/{provider}` route can't be turned off; it's registered but unused. Paystack's dashboard must point at `/api/webhooks/paystack`. | One ledger. Avoids tables nobody reads. | DEV_B B.7, B.9 |
| D-29 | (§5.12, verified) The Paystack Ghana fee is **1.95% flat, with no cap and no fixed fee**, on local cards, mobile money and bank transfer (Paystack support, "What are Paystack's transaction charges", 2026-10-30). Ghana merchants can't switch on automatic pass-through, so `PaystackFeeCalculator` grosses up: the voter pays the smallest whole-pesewa amount such that `gross - ceil(gross x 1.95%) >= face value`. No subaccount or split is sent, so the main account bears Paystack's fee (covered by the gross-up) and `bearer` doesn't apply. The rate, flat fee and cap are config (`voting.paystack_fee.*`). | Organizer nets face value, per plan. | DEV_B B.8 |
| D-30 | Verified status mapping: only Paystack's literal `success` is a success. `failed` is a failure. Everything else, including `abandoned`, `ongoing`, `reversed` and unknown states, stays **pending** and is never credited. An abandoned checkout can still be completed by the voter. | §5.4. | DEV_B B.3 |
| D-31 | Fulfilment (`FulfillPaidVoteOrderAction`) is shared by callback and webhook. It takes `Cache::lock(VoteKeys::fulfilmentLock($ref))->block(wait)`, skips already-settled transactions without re-verifying, verifies, then checks reference, amount (= stored `gross_amount`) and currency. Mismatch → `flagged`; failed → `failed`; category not accepting → `paid_uncredited` with reason `category_{status}`. Otherwise one DB transaction runs a conditional `pending → successful` UPDATE, and only if it affected one row: atomic `votes_count` increment plus a `vote_records` insert. `VotesCredited` dispatches after commit. | §5.3, §5.4, §5.8, D-06, D-23. | DEV_B B.3 |
| D-32 | A closed category credits a payment only if its checkout was created at or before `closed_at` and it is verified before `closed_at + close_grace_minutes`. | Concrete form of D-10. | DEV_B B.3 |
| D-33 | The webhook controller checks the signature on the raw body first (401 if bad), returns 200, and queues `FulfillPaidVoteOrderJob` only for `charge.success` with a reference. The payload is never trusted for amounts. The job releases with backoff (8 tries, up to 30 min) while Paystack is unreachable, the lock is held, or the payment is still pending, and logs `fulfilment.gave_up` on final failure. Webhooks live in `routes/api.php` (stateless, no CSRF). | §5.4, prompt §6 Security. | DEV_B B.9 |
| D-34 | The callback (`GET /payments/callback?reference=` or `trxref=`) runs the same action synchronously and renders `payments.result` for every status. If Paystack is unreachable or the lock is busy it shows the transaction as it stands (pending). It never shows vote totals. Throttled to 30/min. | §5.4 "callback works even if the webhook already credited". | DEV_B B.9 |
| D-35 | PII: voters may optionally give an email (trimmed, lower-cased, validated, stored). Otherwise Paystack gets `anon-{Str::random(24)}@{voting.proxy_email_domain}` and nothing is stored. Payment logs go to the `payments` channel (90 days) with ids, amounts and statuses only, never raw payloads or emails. | §5.11. | DEV_B B.2 |
| D-36 | Concurrency is tested deterministically rather than with forked processes: a racing worker settles the row mid-verification (no credit), a pre-existing ledger row makes the credit roll back entirely, and a held lock times out without crediting, then succeeds once released. Real parallel load arrives with the K6 scripts (B.12, Phase 5). | Reliable in CI; covers each layer of D-31 separately. | prompt §6 Testing |
| D-37 | Mago's `excessive-parameter-list` allows constructors up to 10 parameters (methods stay at 5), so readonly DTOs can be built with named arguments. | DTOs mirror provider payloads. | — |
| D-38 | **Checkout initialization calls Paystack's `/transaction/initialize` directly** (Laravel `Http`) instead of through PayZephyr. PayZephyr's `PaystackDriver` is `final` and its charge payload drops `split`, so it cannot send `subaccount`, `transaction_charge` or `bearer`, which L-5 requires. Verification and webhook signatures still go through PayZephyr. The amount is sent as integer pesewas, so D-27's float conversion now only applies to verified amounts. Partly supersedes D-28. | L-5 cannot be done otherwise without forking the driver. | DEV_B B.7 |
| D-39 | **Stuck-pending sweeper:** `payments:reconcile-pending` runs every 5 minutes (`withoutOverlapping`, `onOneServer`). It re-verifies `pending` transactions older than 10 minutes through `FulfillPaidVoteOrderAction` (trigger `sweeper`), 500 per run. If Paystack still says "not paid" after 24 hours, the status becomes `expired` (`unpaid_timeout`). It never expires after a failed verification (outage). `expired` stays open to a late success: credited if the category accepts it, otherwise `paid_uncredited`. A Paystack `failed` on an expired order leaves it expired. | Prompt §4 item 1. | DEV_B B.3 |
| D-40 | PayZephyr's `POST /payments/webhook/{provider}` is **shadowed by an app route that returns 404** (`bootstrap/app.php`, `then:`); app routes load after package routes, so ours replaces it. Tests prove a correctly signed request there changes no rows, queues nothing and fires no events, and that no listener exists for any PayZephyr event. Supersedes D-28's "registered but unused". | Prompt §4 item 2. | DEV_B B.9 |
| D-41 | **L-3 pricing:** `total = ceil(subtotal / (1 − rate))`, integer arithmetic, with the rate in `voting.paystack_fee_bps` (195). This replaces D-29's rule (smallest total such that `total − ceil(fee) ≥ subtotal`); flat-fee and cap settings are removed. The two agree on almost every amount. **Caveat:** L-3 guarantees the organizer is never short if Paystack rounds its fee to the nearest pesewa (tested for 1 to 20,000 pesewas). If Paystack rounds its fee *up*, a few totals could leave the organizer 1 pesewa short. To be confirmed in Paystack test mode before launch. | Locked decision L-3. | DEV_B B.8 |
| D-42 | **L-5 settlement:** `organizations.paystack_subaccount_code` (per organization; every event of the organizer settles there). Checkout sends `subaccount`, `transaction_charge: 0` (main account takes nothing) and `bearer: subaccount` (configurable, validated to `account`/`subaccount`). The code is snapshotted on `vote_transactions.settlement_subaccount_code`. Without a code, paid checkout is blocked with "Paid voting is not set up for this event yet. Organizers: add your Paystack subaccount code…". **Open verification:** Paystack's split-payments guide documents `subaccount`, `transaction_charge` and `bearer: "subaccount"`, but not a 100%-to-subaccount setup explicitly. Confirm in test mode that `transaction_charge: 0` leaves the main account nothing and that the subaccount bears the fee. | Locked decision L-5. | DEV_B B.2, B.7 |
| D-43 | **L-4:** `voting.platform_fee_bps` and `VOTING_PLATFORM_FEE_BPS` removed; no commission or split logic anywhere. Supersedes D-13's reserved key. | Locked decision L-4. | — |
| D-44 | **L-3/L-8 naming:** `vote_transactions.fee_amount` renamed to `processing_fee_pesewas` (new migration; Phase 1 migrations untouched). `VotePriceQuote` exposes `subtotal`, `processingFee`, `total`. `QuotePaidVotesAction` holds every paid-vote eligibility rule and the price; the drawer (Phase 3) and `InitiatePaidVoteOrderAction` both use it, so the breakdown shown equals what is stored and sent to Paystack (tested). | Locked decisions L-3, L-8. | DEV_B B.2, B.6 |
| D-45 | **Invoicing totals:** `SummarizeProgramRevenueAction` runs one grouped query per program and returns credited totals (transactions, votes, vote price, processing fees, gross), `paid_uncredited` totals (refund liability) and a count per status. Tested to reconcile with the ledger and to exclude other programs. | Prompt §4 item 5. | DEV_A A.7 (export later) |
| D-46 | The verified-payment decision moved into a pure `DecidePaymentSettlement` returning a `SettlementDecision` (credit / wait / settle-as-status). `FulfillPaidVoteOrderAction` only locks, verifies, decides and applies. | Keeps each class under Mago's complexity limit, and the rules are readable in one place. | DEV_B B.3 |
| D-47 | `Tests\TestCase` calls `withoutVite()`, so view tests don't need `npm run build`. Found by checking each commit in a clean export. | Tests mustn't depend on build artifacts. | — |
| D-48 | Locked decisions from the continuation prompt are recorded as: L-1 = D-26; L-2 = stacking rule (Phase 3 branches from `feature/phase-2-payments` while m0 and Phase 2 are unmerged); L-3 = D-41/D-44; L-4 = D-43; L-5 = D-42; L-6 = D-27/D-38; L-7 = D-05/D-23; L-8 = D-44. | Continuation prompt §3. | All |
| D-49 | **Public routes:** `/{organization:slug}/{awardProgram:slug}`, `/…/categories/{category:slug}` and `/…/{nominee:nominee_code}`, all with `scopeBindings()`. Parameter names match the relations Laravel resolves (`awardPrograms`, `categories`, `nominees`). Categories sit under a literal `categories/` segment so they can't be confused with nominee codes. Slugs and codes are regex-constrained. The group is registered last, so fixed paths always win. Components also re-check organization → program → category ownership in `mount()`. The nominee deep link reuses `CategoryShow` with that nominee's drawer open, so there is no separate nominee page. | Prompt; DEV_B §2. | DEV_B B.4–B.6 |
| D-50 | `/` renders the home program's landing page in dedicated mode (`DedicatedSite`), and the default welcome page otherwise. The plan defines no multi-tenant portal index. | SHARED §4. | SHARED §4 |
| D-51 | **Free-vote limits are enforced in the database:** `free_vote_claims` has a unique key on (category, voter_key, period, slot), with slots running from 1 to `free_vote_limit`. Two racing requests can't both take the last slot; the loser is rejected. Deviation: the plan's rolling 24 h `Cache::has` window becomes a **calendar day** (app timezone, UTC = Ghana time), because a rolling window can't be expressed as a unique key. The plan's `free_vote_limit` (default 1) is honoured. | Continuation §6 "enforce in the database". | DEV_B B.1, B.10 |
| D-52 | **Fingerprint:** `voter_key = HMAC-SHA256(ip | user agent | vl_voter cookie)`, and `ip_hash = HMAC-SHA256(ip)`, keyed with `VOTING_FINGERPRINT_KEY` or else `APP_KEY`. The plan's plain SHA256 is keyed so IPs can't be brute-forced back. `vl_voter` is a random UUID (1 year, httpOnly, SameSite=Lax) set by `EnsureVoterCookie`; without it the key drops the cookie part, which is stricter. Known limit: clearing cookies gives a new voter key. That is bounded by the per-network rate limit. A bot challenge (Turnstile) isn't in the plan, so it's a follow-up. | DEV_B §3; Never-list (no raw IPs). | DEV_B B.10 |
| D-53 | **Rate limits:** named limiters in `AppServiceProvider`, with values in `voting.rate_limits`. `vote-checkout` is per voter (5), network (30) and nominee (300) per minute; `free-vote` is per voter (10) and network (60); `public-pages` is per network (120) as route middleware. Livewire actions apply limiters through `App\Support\Throttle`, because they don't pass through route middleware; it checks every limit before counting any. Network limits are loose because campuses share one IP. | Prompt §3. | DEV_B B.10 |
| D-54 | **Idempotent checkout start (`StartVoteCheckoutAction`):** resubmitting the identical order (voter, nominee, quantity, email) within 10 min returns the authorization URL already issued. Otherwise rate limits apply, and one 15 s lock per voter and nominee stops a concurrent double start. Logic lives in an action, keeping the component thin. | Continuation §6 double-submit. | DEV_B B.6 |
| D-55 | **Hidden results:** counts are read only from `LeaderboardCache`, and only while `isLeaderboardPublic()` (open or revealed). Embargoed *and* closed categories show none. Counts are never public properties; tests check HTML, attributes, the Livewire snapshot and a poll response, including a category that goes blind mid-session. Polling is `wire:poll.3s.visible` while results are public and 15 s otherwise (to notice status changes). | §5.10, D-11. | DEV_B B.5 |
| D-56 | **Leaderboard cache:** `[nominee_id => votes]` from the derived `nominees.votes_count` (one indexed read, no aggregates), cached for `VOTING_LEADERBOARD_CACHE_SECONDS`. `RefreshLeaderboardCache` (auto-discovered) refreshes it on `VotesCredited` and on the new after-commit `FreeVoteCast`. | Prompt §3. | DEV_B B.5 |
| D-57 | `Model::preventLazyLoading()` outside production. Required `BelongsTo` relations are declared as non-null `@property-read` on the models, because Larastan otherwise types them nullable (their FKs are NOT NULL). | Continuation §7 (no N+1). | — |
| D-58 | No supporter names and no anonymous/name toggle: the repo plan has no supporter feature (the continuation checklist item doesn't apply). The drawer offers only an optional receipt email (D-35). | Plan scope. | DEV_B B.6 |
| D-59 | The drawer and cards are built from token-based Tailwind markup rather than Sheaf's `modal`/`card`, which hardcode `white`/`neutral` colours instead of the theme tokens. They follow the plan's motion rules: bottom-sheet `translateY` with 260 ms `cubic-bezier(0.32,0.72,0,1)`, `scale(0.92)` steppers, `tabular-nums`, `scaleX` bars, reduced-motion respected. The stepper is one `adjust(int)` action that only accepts −1, +1 and the chips. | SHARED §5, DEV_B UX rules. | DEV_B B.6 |
| D-60 | **D-26 superseded for Phases 3–5.** The agent now commits locally, one plan task (or logical change) per commit, after tests, PHPStan and Mago pass. It still never pushes, merges, rebases or force-pushes without the owner's go-ahead. | Owner's "bring the platform to life" prompt, 2026-11-05 (Stage 0 and rule 9). | Prompt §4 |
| D-61 | **Git is the owner's alone; supersedes D-60.** The agent runs only read-only git (`status`, `diff`, `log`, `branch`, `show`, `merge-base`). It never commits, pushes, merges, rebases, resets, stashes or creates/switches branches. Each stage ends with the branch name, an ordered commit plan with explicit file lists and Conventional Commit messages, and a stop for the owner's go-ahead. | Owner's "55% to production-ready" prompt, 2026-11-12, rule 1. | All |
| D-62 | **Trusted proxies:** `config/trustedproxy.php` parses `TRUSTED_PROXIES` (empty = none, `*` = calling peer, else comma list of IPs/CIDRs) for Laravel's global `TrustProxies`. Only `X-Forwarded-For/-Proto/-Port` are trusted; `X-Forwarded-Host` never is, so a forged host can't poison reset or signed links. `.env.example` recommends Cloudflare's published ranges over `*`, which lets a voter prepend a fake address. | Client IPs feed fingerprints (D-52) and rate limits (D-53). | DEV_B B.10 |
| D-63 | `FulfillPaidVoteOrderAction` writes `votes_count` **last** in its transaction, after the `vote_records` insert, matching `CastFreeVoteAction`. The three exactly-once guards (D-31) are unchanged. | The ledger is the source of truth and the counter is derived; the nominee row lock is held for the shortest time. | DEV_B B.3 |
| D-64 | **Leaderboard invalidation; supersedes D-56's refresh (fallback amended by D-66).** `RefreshLeaderboardCache` now only forgets the category's entry. `LeaderboardCache::standings()` rebuilds a miss under a per-category lock (`VoteKeys::leaderboardRebuildLock`, 5 s), re-checking the cache once the lock is held, so concurrent misses run one query. If the lock can't be had within 2 s the request reads the counter directly without caching, so a stuck rebuild never breaks a page. | Rebuilding per vote is one query per vote; invalidation plus a single locked rebuild bounds it to one per miss. | DEV_B B.5, DEV_A A.8 |
| D-65 | `Features::registration()` is removed from Fortify; `GET`/`POST /register` are 404. Organizers will join by invitation (Phase 4, Stage 2a). `CreateNewUser` stays registered until invitations replace it. | Owner's prompt, Stage 1. | DEV_A §4 |
| D-66 | **Amends D-64: stale-while-revalidate leaderboard.** Two keys per category: the standings copy (`VoteKeys::leaderboard`, kept 10 min) and a freshness marker (`VoteKeys::leaderboardFresh`, kept `VOTING_LEADERBOARD_CACHE_SECONDS`). Votes delete only the marker. Fresh copy → served. Stale copy → one request takes the rebuild lock (non-blocking) and rebuilds; every other request gets the stale copy immediately, never waiting and never reading the DB. No copy at all → wait up to 2 s on the lock and reuse the rebuilt copy; only then read the counter directly (uncached). `Cache::flexible` was not used: it can only age a copy by time (a vote can't mark it stale without writing its internal `illuminate:cache:flexible:created:*` key) and defers the refresh to after the response. Embargo is enforced before the cache is touched (`CategoryShow` calls `standings()` only when `isLeaderboardPublic()`), so every path, including the fallbacks, sits behind it; a test proves hidden categories read no leaderboard key with a stuck lock and a stale or missing copy. `LeaderboardCache::refresh()` is `@deprecated` (tests only) and is removed in Stage 3. Known bound: a rebuild that read the counter just before a vote committed can mark its copy fresh for up to one cache period. | Owner review of Stage 1, 2026-11-12: serve the last good value, read the DB only when no copy exists. | DEV_B B.5, DEV_A A.8 |
| D-15 | Add `award_program_id` to `vote_transactions` and `vote_records`, index `vote_transactions(award_category_id, status)`, and use `scopeBindings()` for program → category → nominee routes. | Hot queries and event-scoped binding (prompt §6). | SHARED §2 |

---

## 3. Phase Notes

### Phase 1 — M0 Shared Foundation (`feature/m0-shared-foundation`) — done
- Git: committed as `30f9c8e` and pushed before D-26. The PR into `dev` is still to be opened by the human: https://github.com/rudolphOtoo/votelab/compare/dev...feature/m0-shared-foundation?expand=1
- Laravel 13.39, Livewire 4.4.8, Fortify 1.41, PayZephyr 5.1.1, simple-excel 3.11, heroicons 2.10, Pest 5.3.4, Larastan 3.13.2, Mago 1.59, Sheaf CLI 1.3.5, canvas-confetti 1.9.
- Gates: `composer lint:check`, `composer types:check` (level 8, 0 errors), `php artisan test --parallel` (37 tests), `migrate:fresh --seed` all pass. CI workflow added.
- Deferred: Fortify views and `User` 2FA/passkey wiring to Phase 4 (admin login). The `welcome` route stays until Phase 3 (B.4).
- PayZephyr findings for Phase 2: config defaults to `NGN` and a `stripe` fallback (overridden by `.env.example` to GHS and no fallback). It ships its own webhook route (`/payments/webhook`) and `payment_transactions` tables via `payzephyr:install`. Its fluent `amount()` takes major units, so check the pesewas path in `vendor/` before writing B.7.

### Phase 2 — Money path (`feature/phase-2-payments`, stacked on `feature/m0-shared-foundation`) — done, awaiting commit
- **Base:** `feature/m0-shared-foundation` (at `e43a59f`, not yet merged to `dev`). No files owned by Phase 1 were changed except shared config and wiring: `.env.example`, `config/voting.php`, `config/logging.php`, `bootstrap/app.php`, `routes/web.php`, `phpunit.xml`, `tests/Pest.php`, `mago.toml`, `AppServiceProvider`.
- **Built:** B.7 gateway contract + PayZephyr adapter + test fake; B.8 fee calculator; B.2 checkout initiation; B.3 idempotent fulfilment; B.9 webhook endpoint, queued job and callback page.
- **Gates:** `composer lint:check`, `composer types:check` (level 8, 0 errors), `php artisan test --parallel` (117 tests, 394 assertions), `migrate:fresh --seed`, `npm run build` all pass. No test touches the network.
- **Not in this phase:** rate limiting of checkout initiation is applied where it is called (VoteDrawer, Phase 3, B.6/B.10). Leaderboard cache invalidation will listen to `VotesCredited` (Phase 3). Tally rebuild command and drift report: Phase 5.
- **Ops:** set the Paystack dashboard webhook URL to `https://<host>/api/webhooks/paystack`. Run a queue worker.

#### Commit Plan (Phase 2)

Run from the repo root on `feature/phase-2-payments`:

```bash
# 1. Config (no code depends on it yet)
git add .env.example config/voting.php config/logging.php phpunit.xml mago.toml
git commit -m "chore(payments): add Paystack fee, reference and lock config and payments log channel [B.7][B.8]"

# 2. Gateway contract, PayZephyr adapter and fee calculator
git add app/Contracts/PaymentGateway.php app/Exceptions/PaymentGatewayException.php app/Services/Payment/ app/Providers/AppServiceProvider.php app/Support/Money.php tests/Fakes/FakePaymentGateway.php tests/Pest.php tests/Feature/Payment/PaystackPaymentGatewayTest.php tests/Unit/Payment/PaystackFeeCalculatorTest.php
git commit -m "feat(payments): add PaymentGateway contract, PayZephyr Paystack adapter and fee calculator [B.7][B.8]"

# 3. Checkout initiation, idempotent fulfilment, webhook, callback (routes ship with their controllers)
git add app/Actions/Voting/ app/Enums/PaymentTrigger.php app/Events/VotesCredited.php app/Exceptions/Voting/CheckoutUnavailableException.php app/Support/VoteKeys.php app/Http/Controllers/Payment/ app/Jobs/FulfillPaidVoteOrderJob.php resources/views/payments/result.blade.php routes/api.php routes/web.php bootstrap/app.php tests/Feature/Voting/
git commit -m "feat(voting): initiate paid orders and credit verified payments exactly once via webhook and callback [B.2][B.3][B.9]"

# 4. Execution log
git add plan/EXECUTION_LOG.md
git commit -m "docs(plan): log Phase 2 decisions and commit plan"

git push -u origin feature/phase-2-payments
```

#### PR (Phase 2)

**Title:** `feat(payments): Paystack checkout, verification and exactly-once vote crediting [B.2][B.3][B.7][B.8][B.9]`

**Body:**

```
Base: `feature/m0-shared-foundation` until it merges, then retarget to `dev`.

Implements the paid-vote money path from plan/DEV_B.md:
- PaymentGateway contract + PayZephyr Paystack adapter (pinned to Paystack, no fallback) + in-memory fake for tests
- PaystackFeeCalculator: Ghana 1.95%, no cap; voter covers the fee so the organizer nets face value (integer pesewas)
- InitiatePaidVoteOrderAction: validates program/category/mode/quantity, snapshots all amounts on a pending transaction, starts Hosted Checkout
- FulfillPaidVoteOrderAction: lock + conditional pending→successful UPDATE + unique ledger row; verifies status, amount, currency, reference; flagged / failed / paid_uncredited paths
- POST /api/webhooks/paystack (HMAC-SHA512 on raw body, 200 fast, queued job with backoff) and GET /payments/callback (idempotent result page)

Decisions: D-27 to D-37 in plan/EXECUTION_LOG.md (float boundary in PayZephyr, fee gross-up, status mapping, grace window, PII).

How to test:
composer check
php artisan test --filter=Voting
# Manual: set PAYSTACK test keys, run `php artisan queue:work`, point the Paystack test webhook at /api/webhooks/paystack

Ops: Paystack dashboard webhook URL must be /api/webhooks/paystack (PayZephyr's /payments/webhook/{provider} is unused). A queue worker is required.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
```

Checked before handover: `git diff` and the untracked files contain no `.env`, keys or build output. `.env.example` has blank Paystack keys, and `phpunit.xml` only carries the dummy `sk_test_votelab_suite`. `public/build` and `storage/` are git-ignored.

### Phase 2 hardening (continuation prompt §4) — done, awaiting commit
- **Branch:** `feature/phase-2-payments`, on top of your commits `fc404d5…32826d3`. Base is still `feature/m0-shared-foundation` (not in `dev`).
- **Built:**
  - Stuck-pending sweeper and `expired` status (D-39).
  - PayZephyr route neutralised and proven inert (D-40).
  - L-3/L-5/L-8: fee rate in config, `processing_fee_pesewas`, subaccount settlement with the no-subaccount block, and a quote action shared by drawer and checkout (D-41–D-44).
  - Job timeout plus a README section on Supervisor/Horizon and cron (item 4).
  - Revenue totals for invoicing (D-45).
- **Gates:** `composer lint:check`, `composer types:check` (0 errors), `php artisan test --parallel` (157 tests), `migrate:fresh --seed`, `npm run build`. Each planned commit was also checked on its own in a clean `git archive` export (tests and PHPStan pass after each).
- **Before launch, in Paystack test mode:** (1) the 100%-to-subaccount settlement and `bearer: subaccount` behave as intended (D-42); (2) how Paystack rounds its 1.95% fee (D-41 caveat).

#### Commit Plan (Phase 2 hardening)

```bash
# 1. Settlement and pricing (L-3, L-5, L-8)
git add database/migrations/2026_10_30_200001_add_paystack_settlement_to_organizations_and_vote_transactions.php app/Models/Organization.php app/Models/VoteTransaction.php database/factories/OrganizationFactory.php database/factories/VoteTransactionFactory.php database/seeders/DemoProgramSeeder.php app/Services/Payment/PaystackFeeCalculator.php app/Services/Payment/Data/VotePriceQuote.php app/Services/Payment/Data/CheckoutRequest.php app/Services/Payment/PaystackPaymentGateway.php app/Actions/Voting/QuotePaidVotesAction.php app/Actions/Voting/InitiatePaidVoteOrderAction.php app/Exceptions/Voting/CheckoutUnavailableException.php config/voting.php .env.example tests/TestCase.php tests/Unit/Payment/PaystackFeeCalculatorTest.php tests/Feature/Payment/PaystackPaymentGatewayTest.php tests/Feature/Voting/PaidVoteInitiationTest.php tests/Feature/Voting/QuotePaidVotesTest.php tests/Feature/Foundation/SchemaIntegrityTest.php tests/Feature/Voting/FulfillPaidVoteOrderTest.php
git commit -m "feat(payments): settle 100% to the organizer subaccount with a voter-paid processing fee [B.2][B.7][B.8]"

# 2. Stuck-pending sweeper and expired status
git add app/Enums/TransactionStatus.php app/Enums/PaymentTrigger.php app/Actions/Voting/FulfillPaidVoteOrderAction.php app/Actions/Voting/DecidePaymentSettlement.php app/Actions/Voting/SettlementDecision.php app/Actions/Voting/ReconcilePendingPaymentsAction.php app/Actions/Voting/ReconciliationSummary.php app/Console/Commands/ReconcilePendingPaymentsCommand.php routes/console.php resources/views/payments/result.blade.php app/Jobs/FulfillPaidVoteOrderJob.php tests/Unit/Enums/VotingEnumsTest.php tests/Feature/Voting/ReconcilePendingPaymentsTest.php
git commit -m "feat(payments): re-verify stale pending payments and expire unpaid checkouts [B.3]"

# 3. Neutralise PayZephyr's built-in webhook route
git add bootstrap/app.php tests/Feature/Payment/PayZephyrBuiltInWebhookTest.php
git commit -m "fix(payments): disable PayZephyr's built-in webhook route so only /api/webhooks/paystack is accepted [B.9]"

# 4. Revenue totals for invoicing
git add app/Actions/Voting/RevenueTotals.php app/Actions/Voting/ProgramRevenueSummary.php app/Actions/Voting/SummarizeProgramRevenueAction.php tests/Feature/Voting/ProgramRevenueSummaryTest.php
git commit -m "feat(payments): add per-program revenue totals for invoicing [B.3]"

# 5. Operations docs and execution log
git add README.md plan/EXECUTION_LOG.md
git commit -m "docs: document queue worker, scheduler and Paystack settlement; log Phase 2 hardening"

git push
```

Secrets check: no `.env`, no keys and no build output in the diff. `.env.example` keeps blank Paystack keys; the only key-like strings are the dummy `sk_test_votelab_suite` in tests/`phpunit.xml` and `ACCT_demotestonly` in the demo seeder.

#### PR body addendum (Phase 2)

Append to the Phase 2 PR description:

```
Hardening (continuation prompt §4):
- Settlement: 100% to the organization's Paystack subaccount (`subaccount`, `transaction_charge: 0`, `bearer: subaccount`); paid checkout blocked without a subaccount code. Initialize now calls Paystack directly because PayZephyr cannot send these fields (D-38, D-42).
- Pricing: total = ceil(vote price / (1 − 1.95%)); `processing_fee_pesewas` stored; one quote action feeds the drawer and checkout (D-41, D-44).
- Sweeper: `payments:reconcile-pending` every 5 min; credits missed webhooks, expires unpaid checkouts after 24 h; late successes still credit or become paid_uncredited (D-39).
- PayZephyr's /payments/webhook/{provider} now returns 404 and is proven inert (D-40).
- Revenue totals per program for invoicing (D-45). README: Supervisor/Horizon + cron.

Verify in Paystack test mode before launch: 100%-to-subaccount settlement and fee rounding (D-41, D-42).
```

### Phase 3 — Public portal & free votes (`feature/phase-3-voting-portal`, stacked on `feature/phase-2-payments`) — done, committed locally 2026-11-05 (not pushed, D-60)
- **Base:** `feature/phase-2-payments` @ `829a917`; neither it nor m0 is in `dev` yet (L-2).
- **Built:** B.4 `ProgramLanding`, B.5 `CategoryShow` (hidden-results safe, cached polling), B.6 `VoteDrawer` (L-8 breakdown, stepper and chips, idempotent checkout, free vote tab), B.1 `CastFreeVoteAction`, B.10 `VoteFingerprintService` + `EnsureVoterCookie` + rate limiters, the leaderboard cache and listener, and deep links with scoped bindings.
- **Gates:** `composer lint:check`, `composer types:check` (0 errors), `php artisan test --parallel` (215 tests), `migrate:fresh --seed`, `npm run build`. Each planned commit was also checked alone in a clean `git archive` export (tests, PHPStan and Mago pass).
- **Not verified:** the pages have not been viewed in a real browser or on a phone. Tests check rendered HTML and behaviour only.
- **Follow-ups:**
  - (1) Bot challenge (Turnstile) for free votes, which needs a plan decision.
  - (2) Reserved organization slugs (`api`, `payments`, `up`, `livewire-*`, Fortify paths), validated when organizations are created (Phase 4).
  - (3) Livewire update requests (`/livewire-*/update`, including polls) are not route-throttled; they are cheap because they read the cache.
  - (4) Visual and device QA on slow 3G.

#### Commit Plan (Phase 3)

```bash
git checkout feature/phase-3-voting-portal

# 1. Free votes: DB-enforced daily limits, keyed fingerprints, device cookie
git add database/migrations/2026_11_05_100001_create_free_vote_claims_table.php app/Models/FreeVoteClaim.php app/Models/AwardCategory.php app/Models/AwardProgram.php app/Models/Nominee.php app/Models/VoteRecord.php app/Models/VoteTransaction.php database/seeders/DemoProgramSeeder.php app/Services/Security/VoterIdentity.php app/Services/Security/VoteFingerprintService.php app/Http/Middleware/EnsureVoterCookie.php app/Actions/Voting/CastFreeVoteAction.php app/Exceptions/Voting/FreeVoteUnavailableException.php app/Events/FreeVoteCast.php config/voting.php config/logging.php .env.example tests/Feature/Voting/FreeVoteRateLimitTest.php tests/Feature/Security/VoteFingerprintServiceTest.php
git commit -m "feat(voting): cast free votes with database-enforced daily limits and keyed voter fingerprints [B.1][B.10]"

# 2. Rate limiting, idempotent checkout start, leaderboard cache
git add app/Providers/AppServiceProvider.php app/Support/Throttle.php app/Support/VoteKeys.php app/Support/LeaderboardCache.php app/Listeners/RefreshLeaderboardCache.php app/Actions/Voting/StartVoteCheckoutAction.php app/Exceptions/Voting/CheckoutUnavailableException.php tests/Feature/Voting/LeaderboardCacheTest.php
git commit -m "feat(voting): add vote rate limiters, idempotent checkout start and a refreshed leaderboard cache [B.6][B.10]"

# 3. Public portal: landing, category, vote drawer, deep links
git add app/Livewire/Public/ resources/views/livewire/public/ resources/views/components/public/status-pill.blade.php resources/views/public/home.blade.php routes/web.php tests/Feature/Public/ tests/Feature/Voting/EmbargoDataLeakTest.php
git commit -m "feat(portal): add program landing, category page and vote drawer with scoped deep links [B.4][B.5][B.6]"

# 4. Docs
git add README.md plan/EXECUTION_LOG.md
git commit -m "docs: document the public portal, free-vote policy and Phase 3 decisions"

git push -u origin feature/phase-3-voting-portal
```

Secrets check: no `.env`, keys or build output in the diff. `.env.example` adds a blank `VOTING_FINGERPRINT_KEY` and rate-limit numbers only.

#### PR (Phase 3)

**Title:** `feat(portal): public voting portal, vote drawer and free votes [B.1][B.4][B.5][B.6][B.10]`

**Body:**

```
Base: `feature/phase-2-payments` until it merges, then retarget to `dev`.

Implements Phase 3 (plan/DEV_B.md public portal + free votes):
- Deep links with scopeBindings(): /{org}/{program}, /{org}/{program}/categories/{category}, /{org}/{program}/{nominee-code} (drawer open). Unpublished programs and draft categories are 404s.
- VoteDrawer: L-8 breakdown (subtotal + Paystack fee = total, from QuotePaidVotesAction), stepper and +10/25/50/100 chips, optional receipt email, idempotent checkout start (StartVoteCheckoutAction), free-vote tab.
- CastFreeVoteAction: daily per-category allowance enforced by a unique (category, voter, day, slot) key in free_vote_claims; keyed HMAC fingerprints (no raw IPs/cookies stored).
- Rate limiters (checkout, free vote, public pages) in AppServiceProvider; leaderboard cache refreshed on VotesCredited / FreeVoteCast.
- Hidden results enforced server-side: no counts in HTML, Livewire snapshot or polls for embargoed/closed categories (tested).

Decisions: D-49 to D-59 in plan/EXECUTION_LOG.md. Deviation: free-vote window is a calendar day, not rolling 24 h (D-51).

How to test:
composer check
php artisan migrate:fresh --seed && composer dev
# open /accra-campus-creatives/campus-excellence-awards-2026 and /accra-campus-creatives/campus-excellence-awards-2026/C01-02

Follow-ups: Turnstile for free votes (needs a plan decision), reserved org slugs (Phase 4), device/3G QA.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
```

### Stage 1 — Production safety (`fix/production-safety`, stacked on `feature/phase-3-voting-portal`) — done, awaiting owner's commits (D-61)
- **Built:** D-61 recorded; trusted proxies from `TRUSTED_PROXIES` (D-62); counter written last in paid fulfilment (D-63); leaderboard invalidation with a locked single rebuild (D-64), made stale-while-revalidate (D-66); public registration closed (D-65); README production checklist started (Cloudflare ranges and their review).
- **Tests added:** `tests/Feature/Security/TrustedProxyTest.php`, `tests/Feature/Auth/RegistrationClosedTest.php`, two fulfilment tests (ledger-before-counter query order; failed ledger insert leaves counter and status untouched), five leaderboard tests (vote marks stale and keeps the copy; interleaved cold misses rebuild once; concurrent stale reads get the stale copy while one rebuilds; stuck lock serves the stale copy with no query or wait; stuck lock with no copy reads directly), and an embargo test over every fallback path (embargoed/closed × stale/no copy, stuck lock): no counts, rank order or `scaleX` bars, and no leaderboard key read. Existing tests unchanged.
- **Known, not in this stage:** Fortify login views are still missing (Stage 2a); `/home` redirect target doesn't exist yet (Stage 2a).

## 4. Open Questions (blockers)

_None open._

Resolved 2026-10-30 by the project owner:
1. **Spec:** build from the repo plan, applying §5 only where it maps (D-04…D-14). → D-16
2. **Scope:** build M0, Dev B, and Dev A in sequence, following the phase mapping above. → D-17
