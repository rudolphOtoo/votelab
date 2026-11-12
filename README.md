# VoteLab

Paid and free voting for award programs (galas, campus awards, pageants) in Ghana.
Voters pay through Paystack Hosted Checkout via [PayZephyr](https://github.com/ken-de-nigerian/payzephyr).
Organizers run categories through a lifecycle (Draft → Open → Embargoed → Closed → Revealed)
and present the results on an auditorium stage screen.

- Plans: [`plan/`](plan/). Execution status and decisions: [`plan/EXECUTION_LOG.md`](plan/EXECUTION_LOG.md).
- Contributing: [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Stack

PHP 8.4+, Laravel 13, Livewire 4, Fortify, Tailwind CSS 4 with Sheaf UI, Pest 5, Larastan (level 8), Mago.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
composer dev
```

The demo seeder creates the **Campus Excellence Awards 2026** program for *Accra Campus Creatives*,
with categories in every lifecycle state, plus two users (password `password`):

| Email | Role |
| :--- | :--- |
| `organizer@votelab.test` | Owner |
| `staff@votelab.test` | Staff |

Use Paystack **test** keys (`sk_test_…`) everywhere except production. Never commit keys.

## Quality gates

```bash
composer lint         # Mago: format and auto-fix
composer lint:check   # Mago: check only (CI)
composer types:check  # Larastan level 8
composer test         # Pest, in parallel
composer check        # all of the above
```

## Money and vote integrity

- All money is stored as **integer pesewas**. Floats are never used for amounts.
- **The database is the ledger.** `vote_records` holds one row per free vote or credited paid transaction.
  `nominees.votes_count` is a derived counter that is only changed by atomic increments and can be
  rebuilt from the ledger.
- `vote_records.vote_transaction_id` is unique, so the database itself refuses to credit a payment twice.
- Pricing is snapshotted on the pending `vote_transactions` row at checkout. Fulfilment credits the stored
  quantity and never recalculates.

## Public voting

| URL | Page |
| :--- | :--- |
| `/{organization}/{program}` | Program landing: countdown and categories |
| `/{organization}/{program}/categories/{category}` | Nominees, with live standings while results are public |
| `/{organization}/{program}/{nominee-code}` | Deep link: the category with that nominee's vote drawer open |

- **Hidden results** (`embargoed`, `closed`): vote counts are never loaded into the page, its Livewire state or poll
  responses. Standings poll every 3 s (only while visible) from a short-lived cache that is cleared when votes land and rebuilt once, under a lock, by the next poll.
- **Free votes:** each category's `free_vote_limit` per voter per day (calendar day, Ghana time), enforced by a unique
  key in `free_vote_claims`. A voter is HMAC(IP | user agent | device cookie); only keyed hashes are stored.
  Rejections are logged to `storage/logs/voting-*.log` for organizer review.
- **Rate limits** (per minute, `VOTING_RATE_*`): checkout per voter/network/nominee, free votes per voter/network,
  public pages per network. Network limits are loose because campuses share one IP.
- Organization slugs must not clash with fixed paths (`api`, `payments`, `livewire-*`, `up`, Fortify routes);
  fixed routes always win.

## Organizers

Organizers sign in at `/login`; there is no public sign-up.

- **First owner:** `php artisan organizations:create "Accra Campus Creatives" accra-campus-creatives --owner=owner@example.com`
  creates the organization (idempotent: re-running never duplicates, renames or re-sends) and emails an
  owner invitation. The slug is checked against `config/organizations.php` `reserved_slugs`. In production
  add `--force`.
- **Invitations:** owners invite from **Dashboard → Members**. Links are signed, single-use and expire after
  `ORGANIZER_INVITATION_TTL_HOURS` (72). Re-inviting an email withdraws the older link; existing members
  can't be invited; sending is rate limited. Inviting or promoting an owner asks for the password again.
- **Owners:** an organization always keeps at least one owner (no demoting, removing or leaving as the last one).
- **Two-factor:** owners and admins must turn it on within `ORGANIZER_TWO_FACTOR_GRACE_DAYS` (7) of their
  first sign-in; after that only **Security** stays open until they do. Passkeys are supported too.
- **Sessions:** changing the password, resetting it, or turning on two-factor signs out every other session.
- Invitations and membership changes are logged to `storage/logs/audit-*.log` (`LOG_AUDIT_DAYS`, 365).

## Permissions

Roles: **Owner**, **Admin**, **Staff** (per organization). The table below is generated from
[`App\Support\PermissionMatrix`](app/Support/PermissionMatrix.php), the single source of truth; a test fails if
this copy drifts. Deny by default: a role without a ✓ is refused, and there is no override for anyone,
owners included. Phases are the category's: **Draft**; **Live** (voting open, results shown or hidden);
**Closed**; **Revealed**. A user from another organization is refused every ability.

| Ability | Owner | Admin | Staff | Notes |
| :--- | :---: | :---: | :---: | :--- |
| `organization.view` | ✓ | ✓ | ✓ | See the dashboard and programs |
| `organization.updateProfile` | ✓ | ✓ | ✗ | Change name and logo |
| `organization.updateSlug` | ✓ | ✗ | ✗ | Change the URL slug (reserved list; password confirm; owners emailed) |
| `organization.updatePayoutSettings` | ✓ | ✗ | ✗ | Change the Paystack subaccount (password confirm; owners emailed) |
| `members.view` | ✓ | ✓ | ✓ | See members and pending invitations |
| `members.manage` | ✓ | ✗ | ✗ | Invite, revoke, change roles, remove (owner invites need password confirm) |
| `members.leave` | ✓ | ✓ | ✓ | Leave the organization (never the last owner) |
| `program.view` | ✓ | ✓ | ✓ | See a program |
| `program.create` | ✓ | ✓ | ✗ | Create a program |
| `program.update` | ✓ | ✓ | ✗ | Edit title, tagline, description, banner, dates |
| `program.publish` | ✓ | ✓ | ✗ | Publish (launch checklist) / unpublish (no category Live) |
| `program.archive` | ✓ | ✓ | ✗ | Archive a program |
| `program.delete` | ✓ | ✗ | ✗ | Delete a program with no vote records or transactions |
| `category.view` | ✓ | ✓ | ✓ | See a category |
| `category.create` | ✓ | ✓ | ✗ | Create a category (program not archived) |
| `category.updateDetails` | ✓ Draft, Live, Closed | ✓ Draft, Live, Closed | ✗ | Edit name and description |
| `category.updatePricing` | ✓ Draft | ✓ Draft | ✗ | Edit voting mode, price per vote, free-vote limit |
| `category.reorder` | ✓ | ✓ | ✗ | Change display order |
| `category.open` | ✓ | ✓ | ✗ | Open voting (Draft → Open) |
| `category.embargo` | ✓ | ✓ | ✗ | Hide results (→ Embargoed) |
| `category.unembargo` | ✓ | ✓ | ✗ | Show results again (Embargoed → Open) |
| `category.close` | ✓ | ✓ | ✗ | Close voting (→ Closed) |
| `category.reveal` | ✓ | ✓ | ✗ | Reveal the winner (Closed → Revealed) |
| `category.delete` | ✓ Draft | ✓ Draft | ✗ | Delete a category |
| `nominee.view` | ✓ | ✓ | ✓ | See a nominee |
| `nominee.create` | ✓ Draft, Live | ✓ Draft, Live | ✓ Draft | Add a nominee |
| `nominee.updateProfile` | ✓ Draft, Live, Closed | ✓ Draft, Live, Closed | ✓ Draft, Live | Edit photo and bio |
| `nominee.rename` | ✓ Draft, Live, Closed | ✓ Draft, Live, Closed | ✓ Draft | Edit name and stage name |
| `nominee.reorder` | ✓ | ✓ | ✓ Draft | Change display order |
| `nominee.changeCode` | ✓ Draft | ✓ Draft | ✓ Draft | Change the nominee code |
| `nominee.changeCategory` | ✓ Draft | ✓ Draft | ✓ Draft | Move to another category (both Draft) |
| `nominee.archive` | ✓ Draft, Live, Closed | ✓ Draft, Live, Closed | ✗ | Archive (hidden; new votes refused; existing votes kept) |
| `nominee.delete` | ✓ | ✓ | ✗ | Delete a nominee with no vote records or transactions |
| `results.view` | ✓ | ✓ | ✓ | See counts in the admin area while results are public |
| `results.viewHidden` | ✓ | ✓ | ✗ | See live counts while results are hidden (admin area only) |
| `results.export` | ✓ | ✓ | ✗ | Export reconciled results (no voter personal data) |
| `results.audit` | ✓ | ✓ | ✗ | Check tallies against vote records |
| `transactions.view` | ✓ | ✓ | ✗ | Search and view transactions |
| `refunds.view` | ✓ | ✓ | ✗ | See the refund queue |
| `refunds.record` | ✓ | ✗ | ✗ | Record a refund as done (password confirm; no money moves) |
| `revenue.view` | ✓ | ✓ | ✗ | See revenue totals |
| `ledger.write` | ✗ | ✗ | ✗ | Create, change or delete vote records or transactions |

Policies (`app/Policies`) add the data rules named in the notes: a valid status change, no vote records or
transactions before deleting, the program not archived, results public (`results.view`) or hidden
(`results.viewHidden`). Vote records and transactions are written only by the voting system; corrections are
recorded refunds, which never change them.

## Operations

### Paystack

- **Webhook URL:** set the Paystack dashboard webhook to `https://<host>/api/webhooks/paystack`.
  PayZephyr's built-in `/payments/webhook/{provider}` route is deliberately disabled (it returns 404).
- **Settlement:** paid votes settle 100% to the organization's Paystack subaccount
  (`organizations.paystack_subaccount_code`). The subaccount bears Paystack's fee, which the voter pays on
  top of the vote price. Organizations without a subaccount cannot take paid votes.

### Queue worker (required)

Webhooks are acknowledged immediately and verified on the queue, so payments are only credited while a
worker is running. In production, keep one alive with Supervisor (or Laravel Horizon if you use Redis queues):

```ini
[program:votelab-worker]
command=php /var/www/votelab/artisan queue:work --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=90
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/votelab/storage/logs/worker.log
```

`FulfillPaidVoteOrderJob` sets its own `tries` (8), `backoff` (10 s up to 30 min) and `timeout` (60 s), and
logs `fulfilment.gave_up` to the `payments` log when it runs out of attempts. Alert on failed jobs
(`php artisan queue:failed`).

### Scheduler (required)

`payments:reconcile-pending` runs every five minutes. It re-verifies pending payments older than
10 minutes, credits any whose webhook never arrived, and expires checkouts still unpaid after 24 hours.
Add the Laravel scheduler to cron:

```cron
* * * * * cd /var/www/votelab && php artisan schedule:run >> /dev/null 2>&1
```

### Cache, locks and logs

- Payment fulfilment uses atomic cache locks. In production use Redis (`CACHE_STORE=redis`,
  `REDIS_CLIENT=predis`, or `phpredis` if the extension is installed) with AOF persistence and
  `maxmemory-policy noeviction`, so lock keys are never evicted.
- **Trusted proxies:** set `TRUSTED_PROXIES` to your load balancer's address or Cloudflare's published
  ranges so client IPs (used by voter fingerprints and rate limits) come from `X-Forwarded-For`. Leave it
  empty when the app is reached directly. See `.env.example` before using `*`.
- Payment decisions are logged to `storage/logs/payments-*.log` (kept 90 days), with ids, amounts and
  statuses only, never raw Paystack payloads or voter emails.
- **Close grace window:** `VOTING_CLOSE_GRACE_MINUTES` (default 5) is how long a closed category still
  credits payments that started before it closed. Later payments are recorded as `paid_uncredited` for refund.

All voting settings live in [`config/voting.php`](config/voting.php) and are listed in `.env.example`.

## Production checklist

Stage 3 (hardening) completes this list; the items below are already required.

- [ ] **Trusted proxies (`TRUSTED_PROXIES`).** Behind a load balancer, set its private address or subnet.
  Behind Cloudflare, set Cloudflare's published IP ranges, comma-separated:
  - Source: <https://www.cloudflare.com/ips/> (plain lists at <https://www.cloudflare.com/ips-v4> and
    <https://www.cloudflare.com/ips-v6>, or the API at `https://api.cloudflare.com/client/v4/ips`).
  - **Review them periodically:** Cloudflare adds and retires ranges. Check at least quarterly and before
    each event, and compare against the API's `etag` to spot changes. A missing range makes
    Cloudflare's own address look like the voter, so everyone behind it shares one rate limit.
  - Don't use `*` behind Cloudflare: it lets a voter forge their IP via `X-Forwarded-For`.
- [ ] **Paystack webhook** registered at `https://<host>/api/webhooks/paystack` (see [Paystack](#paystack)).
- [ ] **`APP_URL` is the exact public URL** (`https://` and the host voters and organizers use). Invitation
  and password-reset links are signed against it, and passkeys only work on that host (`relying_party_id`
  and `allowed_origins` derive from it). A mismatch behind a proxy makes every invitation link fail with 403.
- [ ] **Mail provider configured** (`MAIL_MAILER` and its credentials, `MAIL_FROM_ADDRESS` on a domain with
  SPF/DKIM). With the default `log` mailer, invitations and password resets are never delivered.
- [ ] **Queue worker** and **scheduler** running (see above). Invitation and password-reset emails are
  queued, so without a worker they are never sent.
- [ ] **Failed jobs are pruned:** the scheduler runs `queue:prune-failed --hours=168` daily. A failed
  invitation or reset email keeps its one-time link in `failed_jobs`; pruning removes it after 7 days.
  Retry failed emails (`php artisan queue:retry`) within that window, or send a new invitation.
- [ ] **Cache and locks on Redis** with `noeviction` (see [Cache, locks and logs](#cache-locks-and-logs)).
