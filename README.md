# VoteLab

Paid and free voting for award programs (galas, campus awards, pageants) in Ghana.
Voters pay through Paystack Hosted Checkout via [PayZephyr](https://github.com/ken-de-nigerian/payzephyr).
Organizers run categories through a lifecycle (Draft → Open → Embargoed → Closed → Revealed)
and present the results on an auditorium stage screen.

- Plans: [`plan/`](plan/). Execution status and decisions: [`plan/EXECUTION_LOG.md`](plan/EXECUTION_LOG.md).
- How it works (money path, hidden results, permissions): [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).
- Contributing: [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Stack

PHP 8.4+, Laravel 13, Livewire 4, Fortify, Tailwind CSS 4 with the VoteLab design system (`resources/views/components/ui`, gallery at `/dev/components` outside production), Pest 5, Larastan (level 8), Mago.

## Local setup

```bash
cp .env.example .env    # first: Composer's package discovery boots the app, which needs APP_ENV=local
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
composer dev
```

SQLite is the default for local development. Production runs PostgreSQL 17; to develop or test on it locally,
see [Database](#database-postgresql-17).

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
composer audit        # known advisories in PHP dependencies (CI fails on any)
npm audit             # known advisories in JS dependencies (CI fails on any)
```

## Money and vote integrity

- All money is stored as **integer pesewas**. Floats are never used for amounts.
- **One payment is at most GHS 21,474,836.47** (2,147,483,647 pesewas, what the amount columns hold on
  PostgreSQL). A larger order is refused before checkout with "This order is too large for one payment"
  (D-139).
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
- **Audit trail:** every organizer action that changes something (invitations, memberships, two-factor,
  category status, reveal, nominees, settings) and every transaction lookup is written to the append-only
  `audit_events` table (who, what, which record, before/after, IP) and copied to `storage/logs/audit-*.log`
  (`LOG_AUDIT_DAYS`, 365). The app never updates or deletes audit rows.
- **Tally drift:** if a nominee's counter ever differs from the vote ledger (checked at every reveal), it is
  corrected from the ledger, logged at error level and audited per nominee.

## Organizer screens

From **Dashboard → a program** (all work on a phone: single column, 44 px targets, sticky Save, bottom-sheet
confirmations):

- **Overview:** details, dates, the late-payment window (10–30 min), the **launch checklist** (publish stays
  disabled until it passes, including Paystack keys matching the environment), publish/unpublish/archive.
- **Categories & nominees:** add/edit (pricing only in Draft; price entered in cedis, stored in pesewas),
  reorder with ↑/↓, open / hide results / show results / close / **reveal** (shows payments still pending;
  "reveal anyway" needs a tick and your password). Nominees: add/edit with a **4:5 crop editor** (drag, zoom,
  arrow keys; re-encoded from the private original), archive, and the **share kit** (link, code, WhatsApp
  share, 1080×1920 Status and 1080×1080 images; never vote counts).
- **Results & money:** per-category totals checked against the ledger (drift flagged), audited transaction
  lookups, the refund queue (owners record refunds with an optional Paystack reference), revenue, the audit
  history, and CSV export (audited; no voter personal data).

Share images use Instrument Sans (`resources/fonts`, SIL Open Font License, `resources/fonts/OFL.txt`).

## Stage screen

Open `https://<host>/stage/<organization>/<program>` on the projector (no login; 16:9, large type, dark theme).
It follows the category whose status changed most recently (or pin one with `?category=<slug>`): live bars while
open, a padlock while results are hidden or voting is closed, and the winner reveal (gold halo, confetti; joint
winners on a tie) once revealed. Nothing about results is sent to the screen before they are public. It polls
every 5 seconds; a failed poll keeps the last screen. The screen asks the browser to stay awake (Wake Lock),
and confetti and bar animation are off when the device prefers reduced motion. Archived nominees never appear.

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

### Deploying

**Server:**
- PHP 8.4 or 8.5 with `pdo_pgsql`, `gd` (WebP and FreeType), `intl`, `bcmath` and `mbstring`.
- PostgreSQL 17 (see [Database](#database-postgresql-17)) and Redis (see [Cache, locks and logs](#cache-locks-and-logs)).
- Supervisor for the [queue workers](#queue-workers-required) and cron for the [scheduler](#scheduler-required).
- Node 22, only to build assets: on the server, or in CI before copying `public/build` over.

**First deploy**, once:

```bash
cp .env.example .env              # then set production values: the checklist below names each one
php artisan key:generate --force  # once; changing APP_KEY later logs everyone out and breaks encrypted values
php artisan storage:link          # nominee photos and logos are served from storage/app/public
php artisan migrate --force
php artisan organizations:create "Accra Campus Creatives" accra-campus-creatives --owner=owner@example.com --force
```

**Never run `db:seed` or `migrate --seed` in production.** The default seeder creates the demo program and two
demo users whose password is `password`. In production it refuses to run, even with `--force` (D-143).

**Every deploy:**

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build           # or copy public/build from CI
php artisan migrate --force
php artisan optimize              # caches config, routes, views and events
php artisan queue:restart         # workers finish their current job, then reload the new code
```

- **Release tag:** set `SENTRY_RELEASE` (for example to the commit hash, `git rev-parse --short HEAD`) before
  `php artisan optimize`, so Sentry tells releases apart.
- **After caching, `.env` is no longer read:** after changing it, run `php artisan optimize` again.
- **Afterwards:** check `/health` with the `X-Health-Token` header. If a deploy changes the Sentry setup, also
  run `php artisan ops:sentry-check` from staging.

**Zero-downtime deploys (recommended):** build each release in its own directory, then switch a `current`
symlink to it in one atomic step, as Laravel Forge, Envoyer and Deployer do. The web server and Supervisor
point at `current`. `storage/` and `.env` are shared between releases, so uploads, logs and settings survive.
The site never goes down, which matters most while voting is open:

```bash
# in the new release directory, with storage/ and .env linked to the shared copies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force           # additive migrations only while voting is open
php artisan optimize
ln -sfn /var/www/votelab/releases/<new> /var/www/votelab/current.tmp && mv -Tf /var/www/votelab/current.tmp /var/www/votelab/current
php artisan queue:restart             # then reload PHP-FPM so it drops cached file paths (OPcache)
```

**If a deploy really needs maintenance mode** (`php artisan down`), payments still confirm (D-143):
- `/api/webhooks/paystack` and `/payments/callback` stay open: everything else returns 503.
- A voter returning from Paystack has their votes credited at once.
- A webhook is acknowledged and queued; the workers pause until `php artisan up`, then credit it.
- Voters can't start new votes until the site is back. Keep the downtime short.
- While voting is open, use only additive migrations, and build new indexes `CONCURRENTLY` (see
  [Database](#database-postgresql-17)).

**Rollback:** switch `current` back to the previous release (or check out its code), run `php artisan optimize`
and `php artisan queue:restart`. Migrations only move forward in production; a migration that has to be undone
gets a new migration, written and tested like any other.

### Paystack

- **Webhook URL:** set the Paystack dashboard webhook to `https://<host>/api/webhooks/paystack`.
  PayZephyr's built-in `/payments/webhook/{provider}` route is deliberately disabled (it returns 404), and so
  is its `/payments/health`: it would call Paystack on every request and list the providers. Use `/health`.
- **Settlement:** paid votes settle 100% to the organization's Paystack subaccount
  (`organizations.paystack_subaccount_code`). The subaccount bears Paystack's fee, which the voter pays on
  top of the vote price. Organizations without a subaccount cannot take paid votes.

### Database (PostgreSQL 17)

Production runs **PostgreSQL 17** (16 if your host only offers it), D-137. SQLite stays fine for local
development; CI runs the full suite on both, and the race tests in `tests/Concurrency` only on PostgreSQL.

Create the database with UTF-8 encoding and a UTF-8 locale, so nominee search (`ILIKE`) ignores case beyond
plain ASCII letters:

```sql
CREATE ROLE votelab LOGIN PASSWORD '…';
CREATE DATABASE votelab OWNER votelab ENCODING 'UTF8'
    LC_COLLATE 'en_US.UTF-8' LC_CTYPE 'en_US.UTF-8' TEMPLATE template0;
```

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=votelab
DB_USERNAME=votelab
DB_PASSWORD=…
```

**Locally on PostgreSQL** (optional): install [Postgres.app](https://postgresapp.com) or
[DBngin](https://dbngin.com) on macOS and start a PostgreSQL 17 server on port 5432. Run `createdb votelab`,
set the lines above in `.env` (Postgres.app's default user is your macOS user, with no password), then
`php artisan migrate:fresh --seed`.

**Tests on PostgreSQL:** create an empty `votelab_test` database, then:

```bash
DB_CONNECTION=pgsql DB_DATABASE=votelab_test DB_USERNAME=votelab DB_PASSWORD= php artisan test --parallel
```

`--parallel` creates one database per process (`votelab_test_test_1`, …), so the role needs `CREATEDB`.
`tests/Concurrency` runs real races: each side in its own PHP process and database connection, against
committed data. Each test proves in `pg_stat_activity` that the database really made one side wait.

**Index migrations after launch** must not lock a busy table. Build indexes with `CONCURRENTLY`, which
PostgreSQL refuses inside a transaction, so the migration opts out of Laravel's migration transaction:

```php
return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS vote_records_example_index ON vote_records (example)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS vote_records_example_index');
    }
};
```

If a concurrent build fails it leaves an `INVALID` index: drop it and run the migration again. Changes that
rewrite a table (a column type change, a new column with a volatile default) lock it while they run, so
schedule them outside voting windows.

### Queue workers (required)

Webhooks are acknowledged immediately and verified on the queue, so payments are only credited while a
worker is running. The queue is in PostgreSQL (`QUEUE_CONNECTION=database`). Jobs are split into named
queues (`App\Support\Queues`), so a slow job never delays a payment:

| Queue | Jobs | Timeout |
| :--- | :--- | :--- |
| `payments` | `FulfillPaidVoteOrderJob` (webhook fulfilment): 8 tries, backoff 10 s up to 30 min | 60 s |
| `mail` | Invitations, owners' security notices, password resets | default |
| `default` | Anything not named above | default |
| `images` | `GenerateNomineePhotoVariantsJob` (portrait copies, 20 nominees per job) | 75 s |

Run two Supervisor programs: one takes `payments` first, then `mail`, then `default`; the other runs
`images` alone, so CPU-heavy photo work runs on its own worker:

```ini
[program:votelab-worker]
command=php /var/www/votelab/artisan queue:work --queue=payments,mail,default --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=90
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/votelab/storage/logs/worker.log

[program:votelab-images]
command=php /var/www/votelab/artisan queue:work --queue=images --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=90
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/votelab/storage/logs/worker-images.log
```

Every job's timeout stays below the queue's `retry_after` (90 s, `DB_QUEUE_RETRY_AFTER`); otherwise a job still
running would be handed to a second worker. `stopwaitsecs=90` lets a running job finish when Supervisor stops
a worker. A test keeps the timeouts and these commands in step (`QueueTimeoutsTest`). Run
`php artisan queue:restart` after each deploy. Fulfilment logs `fulfilment.gave_up` to the `payments` log when
it runs out of attempts. Alert on failed jobs (`php artisan queue:failed`).

### Scheduler (required)

`payments:reconcile-pending` runs every five minutes. It re-verifies pending payments older than
10 minutes, credits any whose webhook never arrived, and expires checkouts still unpaid after 24 hours.
Add the Laravel scheduler to cron:

```cron
* * * * * cd /var/www/votelab && php artisan schedule:run >> /dev/null 2>&1
```

### Cache, locks and logs

- **Cache, locks and sessions on Redis** in production: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
  `REDIS_CLIENT=predis` (or `phpredis` if the extension is installed). Payment fulfilment and the leaderboard
  use atomic cache locks, so run Redis with **persistence on** (AOF, `appendfsync everysec`) and
  **`maxmemory-policy noeviction`**: lock keys and sessions are never evicted to make room. Size `maxmemory`
  with headroom and alert on memory use, because a full Redis with `noeviction` refuses writes. This keeps
  lock and cache traffic off PostgreSQL's connection budget. The queue stays on PostgreSQL. (The `database`
  cache store also works: its locks use `INSERT … ON CONFLICT DO NOTHING`, which is safe on PostgreSQL.)
- **Trusted proxies:** set `TRUSTED_PROXIES` to your load balancer's address or Cloudflare's published
  ranges so client IPs (used by voter fingerprints and rate limits) come from `X-Forwarded-For`. Leave it
  empty when the app is reached directly. See `.env.example` before using `*`.
- Payment decisions are logged to `storage/logs/payments-*.log` (kept 90 days), with ids, amounts and
  statuses only, never raw Paystack payloads or voter emails.
- **Close grace window:** a payment started before a category closed is still credited if Paystack confirms it
  within `VOTING_CLOSE_GRACE_MINUTES` (default 30) of the close **and** before the reveal; otherwise it is
  recorded as `paid_uncredited` for refund. Revealing refuses while such payments are still pending, unless
  the organizer explicitly reveals anyway (those payments are then refunded, not counted).
- **Withdrawn nominees:** archiving a nominee hides it and refuses new votes; payments started before the
  archive are still credited, and its votes stay in the results.

All voting settings live in [`config/voting.php`](config/voting.php) and are listed in `.env.example`.

### Monitoring and recovery

- **`GET /health`** (JSON, for uptime monitors): `ok`, `degraded` (queue backing up, jobs failed in the last
  24 h, scheduler stopped) or `down` with HTTP 503 (database or the shared lock store failing). The checks
  themselves are shown only with a valid `X-Health-Token` header (`HEALTH_CHECK_TOKEN`). `/up` only says the
  process is alive.
- **Alerts** (`storage/logs/alerts-*.log`, and Sentry when `SENTRY_LARAVEL_DSN` is set): every failed job,
  `fulfilment.gave_up` (a paid vote may be uncredited: verify it by hand), and tally drift.
- **Sentry** never receives phone numbers or email addresses: `send_default_pii` is off, SQL bindings are
  never sent, and every event and breadcrumb is scrubbed (`App\Support\Observability\PiiScrubber`). To
  confirm it in your Sentry project, run `php artisan ops:sentry-check` from a non-production environment
  with the same DSN: the event's fake numbers and email must show as `[phone]` / `[email]`.
- **Tallies:** `php artisan votes:rebuild-tallies org-slug/program-slug --dry-run` compares every nominee's
  counter with the vote ledger; without `--dry-run` it fixes counters from the ledger (`--force` in
  production). The scheduler runs `--all --dry-run` at 03:00 and alerts on any drift.

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
- [ ] **Queue workers** and **scheduler** running (see above). Invitation and password-reset emails are
  queued, so without a worker they are never sent.
- [ ] **`VOTING_PHONE_HASH_KEY` set** (a random 32-byte value) before launch, and kept. Payer phone numbers
  are never stored, only an HMAC of them for exact search; **rotating the key breaks phone search on every
  existing transaction** (old hashes stop matching). Production refuses to boot without it; it never falls back
  to `APP_KEY`.
- [ ] **`VOTING_FINGERPRINT_KEY` set** (a random 32-byte value) before launch, and kept. It keys the voter
  fingerprints behind free-vote limits and the hashed IPs on vote records; **rotating it resets every voter's
  daily free votes**. Production refuses to boot without it; it never falls back to `APP_KEY` (D-126).
- [ ] **Portrait copies backfilled** once after deploying D-127: `php artisan nominees:photo-variants --dry-run`,
  then without `--dry-run` (queued; needs the worker). Safe to run again.
- [ ] **`NOMINEE_PHOTO_ORIGINAL_DISK` is private** (default `local`): uploaded originals are kept there for
  re-cropping and must never be web-readable.
- [ ] **Paystack live keys in production** (`sk_live_…`); the launch checklist refuses to publish a paid
  program otherwise, and refuses live keys anywhere else.
- [ ] **`NOMINEE_PHOTO_DISK` is publicly readable** (`public` with `php artisan storage:link`, or a public
  S3 bucket): nominee photos are served from it.
- [ ] **The boot guard passes.** In production the app refuses to start (web and CLI) unless: the cache store
  holds atomic locks shared by all servers (not `array` or `file`), `APP_DEBUG=false`, `PAYSTACK_SECRET_KEY`
  is a live key, `VOTING_PHONE_HASH_KEY` and `VOTING_FINGERPRINT_KEY` are set, `PAYMENTS_LOGGING_ENABLED` is not `true` (D-122),
  and neither pcov nor xdebug is loaded (D-146). The error lists every problem.
- [ ] **Sentry DSN set** (`SENTRY_LARAVEL_DSN`) and alert rules created for `queue.job_failed`,
  `fulfilment.gave_up`, `tally.drift` and `tally.drift_nightly`.
- [ ] **An uptime monitor watches `/health`** (alert on 503 and on `"status":"degraded"`). Set
  `HEALTH_CHECK_TOKEN` to a long random value and send it as the `X-Health-Token` header from the monitor to
  see which check failed; without it the response is only the status word.
- [ ] **Rate limits fit your venue** (`VOTING_RATE_*`, per minute): voter limits are per device with high
  per-network backstops for carrier-grade NAT and venue Wi-Fi; raise `*_PER_IP` for very large venues.
- [ ] **Failed jobs are pruned:** the scheduler runs `queue:prune-failed --hours=168` daily. A failed
  invitation or reset email keeps its one-time link in `failed_jobs`; pruning removes it after 7 days.
  Retry failed emails (`php artisan queue:retry`) within that window, or send a new invitation.
- [ ] **Cache, locks and sessions on Redis** with persistence on and `noeviction` (see
  [Cache, locks and logs](#cache-locks-and-logs)). The queue stays on PostgreSQL.
- [ ] **PostgreSQL 17** (16 if that's all the host offers), UTF-8 encoding and locale (see
  [Database](#database-postgresql-17)).
- [ ] **Connection limits fit.** Every PHP-FPM child, queue worker and scheduler run holds one connection:
  `max_connections` (default 100, minus `superuser_reserved_connections`) must exceed PHP-FPM
  `pm.max_children` × app servers + 3 queue workers + 2 for the scheduler, with headroom for deploys and a
  console. Beyond that, put **PgBouncer** in front. For transaction pooling, use **PgBouncer 1.21 or later
  with `max_prepared_statements` set** (e.g. 200): PDO uses server-side prepared statements, which older
  PgBouncers break in transaction mode. VoteLab keeps no session state on its connections (no `SET`,
  advisory locks, `LISTEN` or temporary tables; only `search_path`, set at connect to the default `public`).
- [ ] **Point-in-time-recovery backups**: continuous WAL archiving plus base backups (the host's PITR, or
  pgBackRest / WAL-G), kept at least 14 days and off the database server. **Restore one to a scratch server
  before launch and before each season**, and check that `votes:rebuild-tallies --all --dry-run` reports no
  drift on it. The vote ledger (`vote_records`, `vote_transactions`) is the record of every vote and payment.
- [ ] **The app timezone stays UTC** (`config/app.php`): timestamps are stored without a zone and read as UTC,
  and public times convert to Africa/Accra for display. Don't change it, and don't add an `APP_TIMEZONE` override.
- [ ] **Index migrations after launch use `CREATE INDEX CONCURRENTLY`** outside a transaction (see
  [Database](#database-postgresql-17)).
- [ ] **No pcov or xdebug on production servers** (`php -m` lists neither, for PHP-FPM and the CLI). pcov
  measures test coverage for mutation testing, locally and in CI only (D-146); never `pecl install` it on a
  server. The boot guard refuses to start if either is loaded.
- [ ] **Both queue worker programs running** (`payments,mail,default` and `images`; see
  [Queue workers](#queue-workers-required)).
