# Platform homepage and public site pages: approved plan

Status: **approved 2026-11-13; Step A (directions) done; Step B (direction A, "Stage night") built 2026-11-14, D-148.** Build only after cleanup batches 4–7 are finished and committed
(owner). The full brief is the owner's "Platform homepage and public site pages" prompt; this file records the
approved plan and decisions so they survive until then.

## How `/` behaves today

Dedicated mode is one install-wide setting (`platform_settings.deployment_mode`), not a per-host lookup.

- **Multi-tenant** (default): `/` shows Laravel's welcome page.
- **Dedicated**, organization found: `/` shows its pinned or latest published program (`public/home.blade.php`
  → `ProgramLanding`). With no published program, or an unknown organization slug, it falls back to the welcome page.

## Approved plan

- **Routes** (all GET):
  - `/` (`home`): the homepage in multi-tenant mode; dedicated mode unchanged.
  - `/?q=`: nominee search without JavaScript.
  - `/results`, `/organizers`, `/organizers/request-access`, `/help`, `/contact`.
  - `/terms`, `/privacy`, `/refunds`: draft notice behind a `site.legal_draft` flag.
  - `/sitemap.xml`, and a generated `/robots.txt` that replaces `public/robots.txt`.
- **Reserved slugs added:** `results`, `organizers`, `contact`, `terms`, `privacy`, `refunds`, `sitemap`,
  `search`, `vote`.
- **Nominee code search across programs:** jump straight to the nominee only when exactly one published (listed)
  program has the code; otherwise list the matches with their program and category. Name search is
  case-insensitive and sorted by name, never by votes.
- **`noindex`** added to the stage, sign-in and invitation pages; the dashboard and payment pages already have it.
- **Welcome page:** removed once `/` is replaced.

## Decisions (owner, 2026-11-13)

1. **Platform fee wording:** dummy for now (`config/site.php`, `SITE_FEE_WORDING`; owner 2026-11-14).
2. **Organizer sign-up:** invite-only with a "Request access" form.
3. **Listing:**
   - All published programs are listed by default.
   - Each program gets a "Hide from the VoteLab homepage" switch on the program screen, stored in
     `award_programs.settings` and audited.
   - Hidden programs stay reachable by direct link but are left out of the homepage, `/results` and the sitemap.
4. **Support contact** (email, WhatsApp, phone): dummy for now (`SITE_SUPPORT_*`; owner 2026-11-14).
5. **Legal pages:**
   - Business: dummy `[VoteLab legal name]`, `[Registered address], Accra`, Ghana (`SITE_LEGAL_*`; owner 2026-11-14).
   - Keep `[placeholder]` markers for anything missing, plus the draft notice.
6. **Dedicated mode:** the new pages exist there too, scoped to that one organization.
7. **Dedicated mode with no published program:** a simple branded "No live program right now" page for that
   organization, never Laravel's welcome page.
8. **Request access:**
   - Submissions are kept 180 days, then deleted by a scheduled prune (tested).
   - The queued notification goes to the support email.
   - The privacy policy states this retention.
   - Phone and email are stored in plain text: a deliberate exception to D-86, which covers voters only.
