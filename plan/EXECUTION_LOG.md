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
| B.1 | `CastFreeVoteAction` | DEV_B §1 | todo |
| B.2 | `InitiatePaidVoteOrderAction` | DEV_B §1 | done |
| B.3 | `FulfillPaidVoteOrderAction` (idempotent crediting) | DEV_B §1 | done |
| B.4 | Livewire `ProgramLanding` | DEV_B §2 | todo |
| B.5 | Livewire `CategoryShow` (embargo-safe) | DEV_B §2 | todo |
| B.6 | Livewire `VoteDrawer` | DEV_B §2 | todo |
| B.7 | `PaymentGateway` contract + PayZephyr/Paystack adapter + fake | DEV_B §3 / prompt §6 | done |
| B.8 | `PaystackFeeCalculator` | DEV_B §3 | done |
| B.9 | `PaystackWebhookController` + callback controller + queued job | DEV_B §3 / prompt §5.4 | done |
| B.10 | `VoteFingerprintService` + rate limiters | DEV_B §3 | todo |
| B.11 | Tests `tests/Feature/Voting/*` + concurrency test | DEV_B §Tests | todo |
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

## 4. Open Questions (blockers)

_None open._

Resolved 2026-10-30 by the project owner:
1. **Spec:** build from the repo plan, applying §5 only where it maps (D-04…D-14). → D-16
2. **Scope:** build M0, Dev B, and Dev A in sequence, following the phase mapping above. → D-17
