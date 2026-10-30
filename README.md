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
- Payment decisions are logged to `storage/logs/payments-*.log` (kept 90 days), with ids, amounts and
  statuses only, never raw Paystack payloads or voter emails.
- **Close grace window:** `VOTING_CLOSE_GRACE_MINUTES` (default 5) is how long a closed category still
  credits payments that started before it closed. Later payments are recorded as `paid_uncredited` for refund.

All voting settings live in [`config/voting.php`](config/voting.php) and are listed in `.env.example`.
