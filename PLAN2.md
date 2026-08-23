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

Each phase lands tested. Status as of 2026-08-23 — full suite green (162 tests / 495 assertions).

### Phase 1 — Foundations
- [x] Migrations for all tables (`facilities`, `facility_status_logs`, `site_settings`, `equipment_types`, `accessory_suggestions`, `reservations` + items, `loans` + items, `penalties`, `incidents`)
- [x] Roster fields + `role` enum on `users`
- [x] Enums: `UserRole`, `FacilityStatus`, `FacilityCategory`, `IncidentType`, `IncidentSeverity`, `ReservationStatus`
- [x] Factories for every model
- [x] Seeders: admin/staff/clinic/student accounts, facilities, gear types
- [x] Role middleware (`EnsureRole`, `role:` alias)

### Phase 2 — Public site
- [x] Home status board with weather banner + open/closed tiles for every facility
- [x] Facility detail pages (`/facilities/{slug}`)
- [x] Gear catalog (`/gear`) with live stock counts
- [x] Borrowing rules / how-to page (`/rules`)

### Phase 3 — Auth + roster
- [x] Fortify login by matric no (password reset, verification, 2FA, passkeys from starter kit)
- [x] CSV roster import with validation report (`/admin/roster`)
- [x] First-login activation via invitation token (`/activate`)

### Phase 4 — Borrowing engine
- [x] Student reservation flow (`/reservations`) with pickup deadline auto-expiry
- [x] Stock availability check (active loans + approved reservations vs total qty)
- [x] Due-time rule: `min(checkout + max_loan_minutes, closing time)` — nothing crosses night
- [x] Penalty gate blocks reservations AND checkouts while a penalty is active
- [x] Repeat-offence penalty extension on later late returns
- [x] Counter checkout with card-deposit log (`/counter/checkout`)
- [x] Return screen with matric/pickup-code lookup + condition confirmation (`/counter/returns`)
- [x] **QR codes** — pickup QR on approved reservations, return QR issued at checkout; counter pages prefill from scanned URLs (`?code=` / `?loan=`) and offer in-browser camera scanning (BarcodeDetector)

### Phase 5 — Admin dashboard
- [x] Counter overview KPIs (active loans, due soon, overdue, stock out)
- [x] Reservations queue with approve/reject + decision notes
- [x] Inventory manager (`/admin/inventory`)
- [x] Facility status toggler + global weather banner (`/admin/facilities`)
- [x] Incident log (JSKK + clinic staff)
- [x] Penalties list with manual impose/lift (`/counter/penalties`)
- [x] CSV exports: loans, reservations, incidents
- [x] **Accessory cross-prompts at checkout** — suggestion panels in both the reservation-pickup and walk-in flows; extras merge into the loan with live stock checks (`accessory_suggestions` pivot now wired end-to-end)

### Phase 6 — Notifications + polish
- [x] Every-minute scheduler (`borrowing:process`: stale-reservation expiry, due-soon alerts, overdue penalties)
- [x] Queued mails: ReservationApproved, DueSoon (~30 min before), OverduePenalty, ReturnConfirmed (+ RosterInvitation)
- [x] Demo seed data for prototype walkthroughs
- [x] Final polish pass (empty states audited across admin screens, responsive search/filter bars, production env checklist in `.env.example`, `npm run build` verified)

## 7. Verification

Pest feature tests covering:
- Loan state machine
- Due-time edge cases (16:29 vs 16:31 checkouts, overnight cap at 18:30)
- Penalty blocking gate
- Stock availability races

## Notes

- SQLite is fine for development; switch to MySQL/MariaDB only if production demands it.
- Roster CSV must include an email column (or generate college-email pattern) since notifications are email-based.
