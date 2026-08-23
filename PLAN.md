# PMS Sports Complex & Equipment Hub — Build Plan

Politeknik Muadzam Shah (PMS) — a 2-in-1 public information portal + smart equipment borrowing system managed exclusively by college staff and Jabatan Sukan, Kokurikulum dan Kebudayaan (JSKK).

## 1. Actors & Roles

Single `users` table with `role` enum.

| Role | Access |
|---|---|
| **Guest** | Public site only — facilities, live status, rules, gear catalog |
| **Student** | Reserve gear, view active loans/penalty/history, booking QR |
| **JSKK staff** | Counter checkout/checkin, ad-hoc loans, reservations queue, inventory, roster import, facility status |
| **Clinic/sports staff** | Incident & first-aid log |
| **Admin** (JSKK head) | Everything + penalty overrides, staff management |

## 2. Data Model

- `users` — `matric_no` (login), name, email, programme, phone, role, status; roster-imported with first-login password setup token
- `facilities` — name, sport, indoor/outdoor, description, photos, rules text; current `status` (open/maintenance/closed) + `facility_status_logs` for audit trail
- `site_settings` — global weather banner ("Outdoor areas closed — rain"), day-use hours text
- `equipment_types` — 8 gear categories (netballs, volleyballs, footballs, futsal balls, rugby balls, handballs, hockey sticks, badminton rackets), total qty, photo
- `accessory_suggestions` — pivot powering smart cross-prompts (racket→shuttlecocks, hockey stick→ball, etc.)
- `reservations` — student, status (pending/approved/rejected/cancelled/expired/fulfilled), pickup window, signed QR token; auto-expire if unclaimed
- `loans` + `loan_items` — student, staff who issued, card-deposit log (collected ✓ / returned ✓), checked_out_at, `due_at`, returned_at, condition notes
- `penalties` — student, loan, starts_at/ends_at (14 days), auto vs manual, lifted_at
- `incidents` — reporter, facility/loan refs, type (injury/first-aid/damage), severity, action taken, timestamps

Inventory stays **quantity-based per type** (balls aren't serialized); rackets/sticks can gain unit codes later if needed.

## 3. Core Business Rules

1. **Due time** = `min(checkout + 2h, 18:30)` — checkout at 17:00 means due at 18:30; nothing ever crosses night.
2. **Late return** → auto-create 14-day penalty; gate blocks all new reservations/checkouts while active. Repeat offence extends to latest late return + 14 days.
3. **Stock check** counts active loans + approved pending reservations against total qty.
4. **Card deposit** mandatory field before a loan can be marked handed over.
5. Scheduler command (every minute) marks overdue loans, imposes penalties, fires emails.

## 4. Screens

### Public
- Home — status board + weather banner + open/closed tiles for all facilities (4 badminton courts, convertible volleyball/netball court, 2 ping pong tables, gym with gender-specific schedules, football field, track, archery range, 3 volleyball courts, futsal, handball, 3 takraw, netball, 2 basketball, rugby, hockey, 2 petanque)
- Facility detail pages
- Gear catalog
- Borrowing rules / how-to

### Student Portal
- Fortify login (matric no)
- Dashboard with live countdown to due time
- Reservation flow with accessory cross-prompts
- History, penalty notices

### Staff Dashboard `/admin`
- Overview KPIs (active loans, due soon, overdue, stock out)
- **Checkout screen** — search/scan student ID → pick gear → accessory cross-prompt modal → log deposit → issue; generates loan QR
- **Return screen** — scan QR or matric lookup → confirm condition → close loan (or trigger penalty)
- Reservations queue (approve/reject)
- Inventory manager
- Roster CSV import with validation report
- Facility status toggler + global banner
- Incident log
- Penalties list
- Exports

## 5. Email Notifications (queued)

- ReservationApproved
- DueSoon (~30 min before)
- OverduePenaltyImposed
- ReturnConfirmed

## 6. Build Phases

Each phase lands tested.

1. **Foundations** — migrations, enums, factories/seeders, role middleware/gates
2. **Public site** — facilities, statuses, banner, rules pages
3. **Auth + roster** — Fortify login, CSV import, first-login activation
4. **Borrowing engine** — reservations, loans, due-time logic, penalties gate, QR issue/scan
5. **Admin dashboard** — counter screens, inventory, incidents, reports
6. **Notifications + polish** — scheduler, mails, demo seed data

## 7. Verification

Pest feature tests covering:
- Loan state machine
- Due-time edge cases (16:29 vs 16:31 checkouts, overnight cap at 18:30)
- Penalty blocking gate
- Stock availability races

## Notes

- SQLite is fine for development; switch to MySQL/MariaDB only if production demands it.
- Roster CSV must include an email column (or generate college-email pattern) since notifications are email-based.
