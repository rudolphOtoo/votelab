# Local preview: every screen, on the laptop and on a phone

Seeded data that reaches every screen we built, served on the LAN so a phone on the same Wi-Fi can open it. Local only: the seeder and the `/dev/payments/*` pages refuse to run outside `APP_ENV=local`.

**Base URL (laptop and phone):** <http://192.168.1.129:8001>

All links below use it. If your Mac's address changes (another Wi-Fi network, a new DHCP lease), see [Reset](#reset-and-restore).

## Start and stop

```bash
npm run build                                   # the preview uses a production build; don't leave `npm run dev` running (it creates public/hot)
php artisan storage:link                        # once
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8001 --no-reload
php artisan queue:work --tries=3 --sleep=2      # second terminal: photo variants, emails (to storage/logs/laravel.log)
```

Stop both with Ctrl-C. `--host=0.0.0.0` lets the phone connect; a plain `php artisan serve` listens on localhost only.

**Don't run** `php artisan schedule:work` or `payments:reconcile-pending` against this demo: they ask Paystack about the seeded references, which don't exist there, and would settle the demo's pending payments.

## Logins

Password for all three: `password`. Two-factor isn't required for 7 days after seeding (the dashboard shows the deadline). Sign in at <http://192.168.1.129:8001/login>; use a private window for a second role at the same time.

| Role | Email | Should see | Should **not** see |
|---|---|---|---|
| Owner | `owner@votelab.test` | Everything: hidden counts, revenue, Transactions, Refunds (and can record one), History, Members (can invite), Settings (name, logo, colour, slug, payout account). Two organizations in the switcher. | — |
| Admin | `admin@votelab.test` | Hidden counts, revenue, Transactions, Refunds queue, History; can open/hide/close/reveal; Settings: name, logo, colour. | Recording a refund, slug and payout fields, inviting or removing members. Only one organization. |
| Staff | `staff@votelab.test` | Programs, categories, nominees; can edit nominee photos and bios while voting is live; public results only. | Counts while results are hidden, revenue, the Transactions, Refunds, Revenue and History tabs, category status buttons, Settings edits. |

## What's seeded

**Accra Campus Creatives**: uploaded logo, brand colour `#8B1E3F`. **Campus Excellence Awards 2026** (published):

| Category | Voting | State | Notes |
|---|---|---|---|
| Artiste of the Year | Paid, GHS 1.00 | Open, results shown | Very long name (KB38); a pending, a failed and an expired payment |
| Most Popular Student | Free + paid, GHS 0.50 | Open, results shown | 1 free vote a day |
| Freshman of the Year | Free | Open, results shown | |
| Best Dancer | Paid, GHS 2.00 | Open, **results hidden** | A flagged payment |
| Sports Personality | Paid | **Closed** | One payment still being confirmed (blocks the reveal for 30 minutes after seeding) |
| Song of the Year | Paid | **Revealed** | Clear winner; two payments confirmed after the reveal: one in the Refunds queue, one refunded |
| Best Dressed | Free + paid | **Revealed, tie** | Two winners on 640 votes |
| Entrepreneur of the Year | Paid | **Draft** | Not public |

**Kumasi Arts Collective**: second organization (owner only), no logo (fallback mark), a draft program. Every nominee has a generated silhouette portrait (no real people) with all photo variants. The ledger matches every counter (`votes:rebuild-tallies --all --dry-run` reports no drift). History has the branding change, the status changes, both reveals and the refund.

## Public pages (voters, no login)

| Page | Link | Look at |
|---|---|---|
| Program landing | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026> | Logo and colour, category cards per state, no counts anywhere on this page |
| Search by code | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026?q=ka-27> | Goes straight to the nominee (dash and case ignored) |
| Search by name | type `en` in the landing page search | Matching names, alphabetical, never by votes |
| Open, results shown (paid) | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/artiste-of-the-year> | Bars and counts; long name wraps cleanly at 360 px |
| Open, free + paid | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/most-popular-student> | Free/paid tabs in the drawer; "free votes left today" |
| Open, free only | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/freshman-of-the-year> | Free vote confirm with "Votes are final once counted." |
| Open, results hidden | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/best-dancer> | Padlock, voting still works, **no counts or order** (also after refresh and Back) |
| Closed | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/sports-personality> | "Results coming up", no voting, no counts |
| Revealed | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/song-of-the-year> | Winner and podium |
| Revealed tie | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/best-dressed> | Both joint winners shown equally |
| Draft (404) | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/categories/entrepreneur-of-the-year> | Not found, as intended |
| Nominee deep link, **drawer open** | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/KB38> | Vote drawer open on the long name: bundles, total before paying, "Votes are final once counted and can't be refunded.", receipt-email hint |
| Nominee in hidden category | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/AF22> | Drawer works, no counts |
| Share image (WhatsApp preview) | <http://192.168.1.129:8001/accra-campus-creatives/campus-excellence-awards-2026/KA27/share/og> | Portrait, name, code, brand colour, never counts (also `/square` and `/story`) |

**Paying from the drawer** opens real Paystack **test** checkout, which needs internet. The demo's payout account `ACCT_showcasetestonly` doesn't exist at Paystack, so checkout is likely to be refused; to try a real test payment, sign in as the owner and set a real test subaccount in Settings. The result pages below don't need Paystack.

### Payment result pages

Read only: they show the latest seeded payment in each state, never contact Paystack and never change anything.

| State | Link | Expect |
|---|---|---|
| Successful | <http://192.168.1.129:8001/dev/payments/successful> | "Your votes are in", share and vote-again buttons |
| Pending | <http://192.168.1.129:8001/dev/payments/pending> | "Confirming your payment", 10 s countdown with pause (it re-renders the same state) |
| Failed | <http://192.168.1.129:8001/dev/payments/failed> | "Payment not completed", Try again |
| Expired | <http://192.168.1.129:8001/dev/payments/expired> | "Checkout expired" |
| Flagged | <http://192.168.1.129:8001/dev/payments/flagged> | "We could not confirm this payment" |
| Paid, not counted | <http://192.168.1.129:8001/dev/payments/paid_uncredited> | "Voting closed before your payment was confirmed", refund wording |

On every one: reference with Copy, amount as `GHS 0.00`, no receipt promise.

## Dashboard (sign in first)

| Page | Link | Look at |
|---|---|---|
| Dashboard and switcher | <http://192.168.1.129:8001/dashboard> | Owner: switch to Kumasi Arts Collective and back; two-factor deadline banner |
| Program overview | <http://192.168.1.129:8001/dashboard/programs/2> | Per-category state, revenue (owner/admin only), launch checklist |
| Categories | <http://192.168.1.129:8001/dashboard/programs/2/categories> | Status buttons per state; **Reveal** on Sports Personality is refused with "1 payment still being confirmed" (for 30 minutes after seeding), then goes through; "Reveal anyway" asks for your password |
| Nominees (open category) | <http://192.168.1.129:8001/dashboard/programs/2/categories/2/nominees> | Photo upload and re-crop; staff can edit while live but not rename or change codes |
| Nominees (draft category) | <http://192.168.1.129:8001/dashboard/programs/2/categories/9/nominees> | Everything editable, including codes |
| Results | <http://192.168.1.129:8001/dashboard/programs/2/results> | Hidden category: counts for owner/admin, padlock for staff |
| Transactions | <http://192.168.1.129:8001/dashboard/programs/2/results?tab=transactions> | Every status; look up by phone `0551234987` or by reference `VL_SHOWCASE_SUCCESS` |
| Refunds | <http://192.168.1.129:8001/dashboard/programs/2/results?tab=refunds> | One item (`VL_SHOWCASE_UNCREDITED`); owner can record it (password asked) |
| Revenue | <http://192.168.1.129:8001/dashboard/programs/2/results?tab=revenue> | Totals in GHS |
| History | <http://192.168.1.129:8001/dashboard/programs/2/results?tab=history> | Branding change, status changes, reveals, the refund |
| Members and Settings | <http://192.168.1.129:8001/dashboard/organizations/accra-campus-creatives/members> | Members list; Settings with logo, colour picker and live preview (owner/admin) |
| Account security | <http://192.168.1.129:8001/dashboard/security> | Turn on two-factor or passkeys (asks for your password) |

Database ids above are fixed for a fresh seed. Program 1 and category 1 belong to the Kumasi organization.

## Stage screen (projector; no login)

Open on the laptop, full screen (F11 or ⌃⌘F), ideally on a TV over HDMI.

| Scene | Link | Expect |
|---|---|---|
| Default | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026> | Picks the scene itself: here the latest reveal (the Best Dressed tie). Brand colour, logo, and the "Vote on your phone" link |
| Live | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026?category=artiste-of-the-year> | Bars update as votes come in (cast a free vote in Freshman of the Year from the phone and switch to that category) |
| Results hidden | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026?category=best-dancer> | Padlock, no counts |
| Closed | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026?category=sports-personality> | "Results coming up" |
| Reveal | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026?category=song-of-the-year> | "And the winner is…", then the podium and confetti (once; a reload replays it) |
| Reveal, tie | <http://192.168.1.129:8001/stage/accra-campus-creatives/campus-excellence-awards-2026?category=best-dressed> | Joint winners |

With "reduce motion" on, the podium appears at once with no confetti. To watch a reveal happen live, keep the Sports Personality stage open and reveal it from Categories once the pending payment no longer blocks.

## Component gallery

<http://192.168.1.129:8001/dev/components>: every UI component, its states, colour contrast checks and the branding examples (404 in production).

## Phone checklist

- Same Wi-Fi as the Mac; open the base URL in Chrome/Safari, then once **inside WhatsApp** (send yourself the nominee link).
- Turn the phone sideways; try the largest text size; try TalkBack/VoiceOver on the drawer.
- Every image should load. If portraits are broken, `APP_URL` doesn't match the address you opened; see below.

## Reset and restore

**Reset the demo** (wipes the local database; refuses unless `APP_ENV=local`):

```bash
php artisan migrate:fresh --seed --seeder=LocalShowcaseSeeder
```

About 20 seconds. Logins are printed at the end. Old portrait files stay in `storage/app/public/nominees`; they're harmless.

**If the Mac's IP address changes:** find it with `ipconfig getifaddr en0`, set `APP_URL=http://<new-ip>:8001` in `.env`, then run `php artisan config:clear` and reset as above. Portrait and logo URLs are stored at seed time, so a reseed is needed.

**Restore the database from before this preview:**

```bash
cp storage/app/private/db-backups/database-20261113-100823.sqlite database/database.sqlite
php artisan config:clear
```

**Go back to the normal demo data:** `php artisan migrate:fresh --seed` (the original `DemoProgramSeeder`).
