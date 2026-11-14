# VoteLab architecture

A map of how VoteLab works, for someone new to the code. It names the classes to read and the decisions behind
them (`D-xx`, in [`plan/EXECUTION_LOG.md`](../plan/EXECUTION_LOG.md)); the code and its tests are the detail.
Setup, operations and the production checklist are in the [README](../README.md).

## The shape of the app

- **Laravel 13, Livewire 4, Fortify, PostgreSQL 17** in production (SQLite works for development), Redis for
  cache, locks and sessions, and the queue in PostgreSQL (D-137, D-141).
- **Actions hold the business rules** (`app/Actions/*`): one class per operation, each checking its own policy,
  so Livewire components, controllers and console commands stay thin and call the same code.
- **Three audiences, three surfaces:**

  | Audience | Surface | Code |
  |---|---|---|
  | Voters | `/{organization}/{program}` and its categories and nominee deep links; no login | `Livewire/Public` |
  | Organizers | `/dashboard`, behind Fortify sign-in, two-factor for owners and admins | `Livewire/Admin` |
  | The venue | `/stage/{organization}/{program}`, the projector screen; no login | `Livewire/Stage` |

- **Money is integer pesewas** everywhere (`App\Support\Money` formats it). One payment is at most
  2,147,483,647 pesewas (D-139).

## The vote and money path

```text
Voter ─ VoteDrawer ─► StartVoteCheckoutAction ─► InitiatePaidVoteOrderAction ─► Paystack Hosted Checkout
                        (rate limits, one lock     (quote, pending vote_transactions
                         per voter and nominee)     row with every amount snapshotted)

Paystack ─► /payments/callback ────────────────┐
Paystack ─► /api/webhooks/paystack ─► queue ───┼─► FulfillPaidVoteOrderAction ─► vote_records row + votes_count += n
Scheduler ─► payments:reconcile-pending ───────┘      (verify with Paystack, DecidePaymentSettlement)
```

1. **Checkout.** `QuotePaidVotesAction` prices the order: the voter pays Paystack's fee on top, so the
   organizer receives exactly the vote price (`PaystackFeeCalculator`). `InitiatePaidVoteOrderAction` stores a
   `pending` `vote_transactions` row with the quantity, every amount and the organization's Paystack subaccount,
   then starts Hosted Checkout. Fulfilment only ever credits these stored values.
2. **Three triggers, one action.** The voter's return (`/payments/callback`), Paystack's webhook (signature
   checked against the raw body, then queued on the `payments` queue) and the five-minute sweeper all call
   `FulfillPaidVoteOrderAction`. It ignores what the trigger claims and asks Paystack for the payment's real state.
3. **Exactly once**, three ways: a cache lock per reference; a conditional
   `UPDATE … SET status = 'successful' WHERE status IN ('pending', 'expired')` that must affect one row; and a unique
   `vote_records.vote_transaction_id`. Race tests on PostgreSQL prove the database guards on their own
   (`tests/Concurrency`, D-140).
4. **Deciding.** `DecidePaymentSettlement` is pure (no I/O): credit only for the exact amount, currency and
   reference, and only while the category takes payments. A payment confirmed after the close grace window,
   or after the reveal, becomes `paid_uncredited` and goes to the refund queue (D-85, D-98).
5. **The ledger.** `vote_records` has one row per credited payment or free vote, and is the record of every
   vote. `nominees.votes_count` is a derived counter, changed only by atomic increments after the ledger row.
   `RecountCategoryTallies` rebuilds it from the ledger under row locks: before every reveal, and nightly as a
   dry run that alerts on any drift (D-05).
6. **The sweeper** (`ReconcilePendingPaymentsAction`) re-verifies payments still pending after 10 minutes and
   expires those unpaid after 24 hours. One failing reference never blocks the others; one that fails three
   runs in a row raises an alert (D-140).

**Free votes** (`CastFreeVoteAction`) skip Paystack. Each voter has a daily allowance per category, enforced by
the database: every vote claims slot *n* in `free_vote_claims` under a unique (category, voter, day, slot) key.
The voter is a keyed hash of IP, browser and a device cookie (`VoteFingerprintService`); no IP or cookie is
stored in plain form.

**Refunds** happen outside VoteLab. An owner records one (`RecordRefundAction`) for a `paid_uncredited`
payment; votes are final once counted (D-98, D-134).

## Category lifecycle and hidden results

```text
Draft ──► Open ⇄ Embargoed ──► Closed ──► Revealed
```

| Status | Takes votes | Results public |
|---|---|---|
| Draft | no | no (not public at all) |
| Open | yes | yes |
| Embargoed | yes | **no** |
| Closed | no (payments started before the close still credit within the grace window) | **no** |
| Revealed | no | yes, with the podium |

- `TransitionCategoryStatusAction` is the only way to change status; `RevealWinnerAction` is the only way to
  reveal. Both lock the row, re-check the move, record the transition and the audit entry, and clear the
  leaderboard cache after commit.
- **Hidden means hidden.** While results are hidden, no page, poll, share image, export or Livewire payload
  may carry a count, an order or a podium. `LeaderboardCache` fails closed:
  it reads the category's current status from the database on every call and returns nothing unless results
  are public. `CategoryShow` checks again as a second layer. `EmbargoDataLeakTest` uses distinctive vote counts
  so any leak is detectable.
- **Standings are cached** (`LeaderboardCache`, stale-while-revalidate): polls read the counter through the
  cache, never the ledger. A new vote only marks the copy stale, and the next poll rebuilds it once under a lock.

## Organizers and permissions

- **Organizations** have members with one role each: owner, admin or staff. Invitations are signed one-time
  links (`InviteMemberAction`, `AcceptInvitationAction`). The last owner can't be removed or demoted;
  membership changes lock the organization row (D-140 race test).
- **`App\Support\PermissionMatrix` is the single source of truth**: ability × role, optionally limited to
  category phases. It denies by default, has no `Gate::before` bypass, and the policies combine it with data
  rules (a valid status change, no ledger rows, …). `PermissionLookups` remembers a user's role for one
  request only, and forgets it on every membership change (D-138).
- **Sensitive actions** (payout account, refunds, reveal anyway) need a recently confirmed password, notify
  owners where it matters, and are written to the append-only audit log (`App\Support\Audit`, D-90).

## The stage screen

`StageLeaderboard` polls every 5 s through `LeaderboardCache`. It follows the same hidden-results rules, and
plays the reveal once per category per screen, with motion reduced when the viewer prefers it (D-104, D-133).

## Front end

- Blade components in `resources/views/components/ui` make up the design system (gallery at `/dev/components`
  outside production).
- One JS bundle per audience, each with a gzipped budget checked in CI (`scripts/perf/bundle-budget.mjs`,
  D-124, D-144):

  | Bundle | Used by | Contents |
  |---|---|---|
  | `public` | Voters | Livewire and Alpine |
  | `result` | Payment result page | Plain JavaScript, 0.5 KB |
  | `auth` | Sign-in | Adds the passkeys client |
  | `admin` | Dashboard | Loads passkeys on demand, adds the photo cropper |
  | `stage` | Projector | Adds the reveal and confetti |

## Operations

- **Queues** (`App\Support\Queues`): `payments` first, then `mail` and `default` on one worker program; `images`
  (portrait variants) on its own. Every job's timeout stays under `retry_after` (D-141).
- **Scheduler:** the sweeper every 5 minutes, failed-job pruning daily, the nightly tally audit at 03:00, and a
  heartbeat each minute for `/health`.
- **Observability:** `/health` for uptime monitors (D-109). `App\Support\Observability\Alert` raises alerts to
  the `alerts` log and Sentry: failed jobs, fulfilment that gave up, a stuck sweeper reference, tally drift.
  Sentry never receives phone numbers or emails (`PiiScrubber`, D-107).
- **Privacy:** payer phone numbers are stored only as a keyed hash plus the last three digits (D-86); voter
  fingerprints and IPs only as keyed hashes. The keys (`VOTING_PHONE_HASH_KEY`, `VOTING_FINGERPRINT_KEY`) are
  required in production, and rotating either one has consequences (README checklist).

## Where to start reading

1. `app/Actions/Voting/FulfillPaidVoteOrderAction.php` and `DecidePaymentSettlement.php`: the money path.
2. `app/Support/LeaderboardCache.php` and `app/Enums/CategoryStatus.php`: hidden results.
3. `app/Support/PermissionMatrix.php`: who may do what.
4. `tests/Feature/Voting`, `tests/Feature/Security`, `tests/Concurrency`: the rules, as executable examples.
