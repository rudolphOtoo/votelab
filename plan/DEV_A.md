# 👨‍💻 Dev A Workstream Plan: Back-Office Studio & Stage Presentation

> **Owner**: Dev A (Teammate)  
> **Boundary Guarantee**: Dev A operates exclusively inside `app/Livewire/Admin/`, `app/Livewire/Stage/`, `app/Actions/Admin/`, and `app/Policies/Admin/`. Zero edits to voter drawer, payment processing, or webhook handling.

---

## 🎯 Scope & Objectives

Dev A is responsible for the organizer's command center and the live event experience:
1. **Category & Nominee Management**: Full CRUD for award titles, descriptions, voting rules, and candidate rosters.
2. **Lifecycle State Machine**: Instant toggling between `Draft`, `Open`, `Embargoed` (blind voting), `Closed`, and `Revealed`.
3. **Auditorium Stage Screen (`/stage/{program:slug}`)**: High-impact, dark-mode projector screen for the event hall with live standing updates and winner reveal sequences.
4. **Audit & Results Export**: One-click streaming Excel/CSV exports of official vote tallies.

---

## 📁 File Manifest & Ownership

### 1. Actions (`app/Actions/Admin/`)
* **`CreateNomineeAction.php`**:
  * Validates and creates nominee record.
  * Generates unique shortcode (e.g. `CAT-01`) if omitted.
  * Handles portrait photo storage via Laravel Storage disk.
* **`UpdateCategoryStatusAction.php`**:
  * Enforces state machine transitions (`Draft` $\to$ `Open` $\to$ `Embargoed` $\to$ `Closed` $\to$ `Revealed`).
  * Emits event `CategoryStatusChanged` to notify live screens.
* **`RevealWinnerAction.php`**:
  * Locks category state to `Revealed`.
  * Computes final winner(s) and prepares podium payload for the auditorium stage view.

### 2. Livewire Admin Components (`app/Livewire/Admin/`)
* **`ProgramManager.php`**:
  * View/edit program title, dates, banner, and dedicated mode settings.
* **`CategoryManager.php`**:
  * List, create, and sort categories.
  * Configure voting mode (`Free`, `Paid`, `Hybrid`) and unit price (e.g., GHS 1.00 / 100 pesewas).
  * Quick-toggle action buttons for `Embargo` and `Close`.
* **`NomineeManager.php`**:
  * Livewire interface for candidate roster within a selected category.
  * Image upload preview with crop guidance (4:5 aspect ratio).
* **`ResultsAuditor.php`**:
  * Real-time admin tally overview with percentage distributions.
  * Streaming export to CSV/Excel using `spatie/simple-excel`.

### 3. Stage & Presentation Components (`app/Livewire/Stage/`)
* **`StageLeaderboard.php`**:
  * Fullscreen projector interface mounted at `/stage/{program:slug}`.
  * Deep basalt theme (`--bg-canvas: #121316`) optimized for high-contrast auditorium projectors.
  * Auto-refreshing poll (`wire:poll.5s`) that smoothly interpolates vote bar widths (`transform: scaleX(...)`).
  * Big-screen winner reveal overlay triggered when category transitions to `Revealed`, initiating `canvas-confetti` fireworks.

### 4. Policies (`app/Policies/`)
* **`AwardProgramPolicy.php`**: Ensures only organization admins can modify program configurations.
* **`AwardCategoryPolicy.php`**: Restricts status changes and price configurations to authorized staff.
* **`NomineePolicy.php`**: Governs candidate additions and edits.

---

## 🎭 Stage Screen Animation & Interaction Rules (Emil Kowalski Philosophy)

1. **Leaderboard Bar Transitions**:
   * **Rule**: Never animate CSS `width`.
   * **Code**: Animate `transform: scaleX(progress)` with `transform-origin: left; transition: transform 250ms cubic-bezier(0.16, 1, 0.3, 1);`.
2. **Winner Reveal Sequence**:
   * Suspense state: Drumroll pulse on top 3 candidate cards (`scale(0.98)` $\to$ `scale(1.02)` gentle loop).
   * Reveal trigger: Winner card expands with spring physics (`scale(1.08)`) with a gold halo border (`#FBBF24`), followed by a 3-burst `canvas-confetti` explosion.
3. **Embargo Visual Cue**:
   * When category is marked `Embargoed`, stage screen displays an elegant animated padlock icon with text: *"Official Blind Voting Phase Active — Scores Hidden Until Finale"*.

---

## 🧪 Pest Verification Test Suite (`tests/Feature/Admin/`)

* `tests/Feature/Admin/CategoryManagementTest.php`:
  * Validates that slug collisions within the same program are rejected.
  * Confirms price per vote defaults to integer pesewas.
* `tests/Feature/Admin/NomineeRosterTest.php`:
  * Tests candidate creation with avatar upload.
  * Asserts initial `votes_count` is exactly `0`.
* `tests/Feature/Admin/CategoryLifecycleTest.php`:
  * Asserts category cannot transition directly from `Draft` to `Revealed`.
  * Confirms `Embargoed` status prevents vote count retrieval on unauthenticated endpoints.
* `tests/Feature/Admin/ResultsExportTest.php`:
  * Verifies streaming Excel export outputs valid header columns (`Category`, `Nominee Code`, `Candidate Name`, `Total Votes`).

