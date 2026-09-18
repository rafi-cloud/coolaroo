# 12. Task List (execution order)

Build checklist derived from this SDD, ordered so that nothing is built before what it depends on. Each task names the sections and IDs it implements: one task, one session, one branch.

**103 tasks · 130.5 effort points · project window 16–27 September 2026.**
Sizes are relative effort points, not calendar days: XS 0.25 · S 0.5 · M 1 · L 2.5 · XL 4.5.

**Rule of thumb:** migrations → enums → seeders → services → controllers → screens. A screen built before its service invents business logic that must be unpicked.

## 12.1 Milestones
| Milestone | Dates | Covers | Exit check |
|---|---|---|---|
| M1 Foundation | Wed 16 – Thu 17 Sep | Phase 1 | migrate:fresh --seed runs; both logins work; layouts render |
| M2 Core services and catalogue | Fri 18 – Sat 19 Sep | Phases 2–3 | Admin can build a full menu; stock and checkout services pass tests |
| M3 Ordering end to end | Sun 20 – Tue 22 Sep | Phases 4–5 | A QR order is paid, prepared, served and tracked live |
| M4 Public site, reservations and AI | Wed 23 – Thu 24 Sep | Phases 6–7 | Booking approved and seated; AI answers from the live menu |
| M5 Admin, reports and hardening | Fri 25 – Sat 26 Sep | Phase 8 | All 15 widgets show seeded data; scheduled jobs run |
| M6 Release | Sun 27 Sep | Undecided items, fixes | Demo-ready build |

## 12.2 Tasks in execution order

### Phase 1 — Foundation
*Project, database, models, access control, platform config · milestone M1 · 18 tasks · 17.0 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 1 | T001 | Laravel project scaffold, environment config, .env.example | S | 07.3, 10 |
| 2 | T002 | Customer and staff auth guards; remove default users migration | S | FR03, 06.1 |
| 3 | T003 | Install Reverb, Echo, Vite; queue, cache and session drivers | M | 07.2, NFR09 |
| 4 | T004 | Stripe and GitHub Models configuration (config/services.php) | S | 07.9 |
| 5 | T005 | Base Blade layouts: public, customer, staff, admin | M | 08.7 |
| 6 | T006 | Import style.css and dashboard.css; asset structure | S | 08.7, NFR18 |
| 7 | T010 | Migrations: role, staff, customer, setting | M | 06.4 |
| 8 | T011 | Migrations: restaurant_table, slot_capacity, visit, reservation | M | 06.4 |
| 9 | T012 | Migrations: menu, sizes, add-ons, allergens, dietary tags, junctions | M | 06.4 |
| 10 | T013 | Migrations: orders, order_item, history, payment, refund, feedback, audit_log, archive | L | 06.4 |
| 11 | T014 | 25 Eloquent models with relationships | L | 06.2 |
| 12 | T015 | 11 PHP enums with transition maps | M | 05 |
| 13 | T016 | Core seeders: roles, demo accounts, 37 settings, slots, tables | M | 06.5, 10.4 |
| 14 | T022 | Role middleware, policies, permissions matrix | M | FR04, 03.3 |
| 15 | T215 | Logging channels and exception handler mapping (409, 422, 403) | S | 07.11 |
| 16 | T216 | Rate limiting and throttle configuration (login, checkout, call waiter) | S | NFR05 |
| 17 | T220 | Localisation config: timezone, AUD currency helper, date formats | S | NFR13 |
| 18 | T221 | Mail configuration and queued mailable base class | S | NFR15 |

### Phase 2 — Accounts and catalogue
*Logins, menu administration, tables and QR, libraries, audit · milestone M2 · 15 tasks · 14.5 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 19 | T020 | Customer register, login, logout, verify email, reset password | M | FR01, FR03, FR05, FR06 |
| 20 | T021 | Staff login, role landing screen, session timeout | M | FR03, BR51 |
| 21 | T023 | Profile pages (customer and staff) | S | FR07 |
| 22 | T024 | Admin staff accounts CRUD; deactivation ends sessions | M | FR02, FR08, BR60 |
| 23 | T214 | PDF and QR library setup (dompdf, QR generator) | S | FR15, FR53 |
| 24 | T213 | File uploads: storage link, image validation, resizing for menu images | M | FR23, NFR08 |
| 25 | T040 | Categories, allergens and dietary tags CRUD | M | FR22, FR101 |
| 26 | T041 | Menu item list and form: image, tags, nutrition, featured, archive | L | FR23, FR24, FR25 |
| 27 | T042 | Sizes and sale price UI | M | FR26, FR27 |
| 28 | T043 | Add-on groups and options UI | M | FR28 |
| 29 | T107 | Time slots admin | S | FR100 |
| 30 | T050 | Admin tables CRUD, deactivate, status override | M | FR11, FR12, FR13, FR20 |
| 31 | T051 | Signed QR generation; PNG and PDF download | M | FR14, FR15 |
| 32 | T045 | SpecialsService | S | BR59 |
| 33 | T151 | AuditLogger and archive snapshots | M | FR89, BR62, NFR14 |

### Phase 3 — Core services
*The engine everything else calls · milestone M2 · 4 tasks · 10.0 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 34 | T053 | TableStatusService: transitions, visits, auto-clear, audit | L | FR19, FR21, BR01-BR07 |
| 35 | T044 | StockService: 5x buffer, atomic deduction, conflict flag, optional return | L | FR30, BR09-BR14, BR54 |
| 36 | T061 | CheckoutService: revalidation, buffer check, snapshots, idempotency | L | FR37 |
| 37 | T071 | PaymentService::markPaid transaction (stock, lines, visit, ETAs, broadcast) | L | FR55, 07.7 |

### Phase 4 — Ordering and payments
*QR scan to paid order · milestone M3 · 13 tasks · 17.0 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 38 | T052 | QR scan flow: validation, QR login page, reserved notice, unavailable page | L | FR31, BR56 |
| 39 | T060 | CartService (session cart bound to table), cart bar, cart page | L | FR35, FR36, BR57 |
| 40 | T062 | Order status timeline page and state endpoint | M | FR38 |
| 41 | T063 | Customer cancels unpaid order | S | FR41 |
| 42 | T064 | Receipt PDF | M | FR53 |
| 43 | T065 | Pause QR ordering middleware and messages | S | FR96, BR58 |
| 44 | T070 | StripeService: create session, verify on return, Check payment button | L | FR46, FR47, BR25 |
| 45 | T072 | Cash request and cash payment modal (rounding, adjustment) | M | FR48, FR49 |
| 46 | T073 | Staff Stripe QR modal with payment check | S | FR42 |
| 47 | T074 | Staff refund request modal | M | FR51 |
| 48 | T075 | Admin refund queue and RefundService (Stripe, cash, manual) | L | FR52 |
| 49 | T076 | Staff cancel of unpaid order; resolve stock conflict | M | FR93, FR94 |
| 50 | T054 | Call waiter endpoint with cooldown | S | FR40, BR50 |

### Phase 5 — Kitchen, floor and real-time
*Paid order to served order · milestone M3 · 12 tasks · 15.5 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 51 | T110 | Broadcast channel authorisation | M | NFR06, 07.8 |
| 52 | T111 | 11 broadcast events | M | FR73, 07.8 |
| 53 | T112 | Echo JS modules: order status, floor, KDS, dashboard, menu | L | NFR09 |
| 54 | T080 | Station queue page with filters | L | FR56, FR57 |
| 55 | T081 | Start and Ready actions; KitchenService status derivation | M | FR58, BR28 |
| 56 | T082 | EtaService and ETA adjustment | M | FR59, BR30 |
| 57 | T083 | Availability drawer (station-scoped toggles) | S | FR29 |
| 58 | T090 | Floor grid, alert lists, paused banner | L | FR16, FR73 |
| 59 | T091 | Table drawer: seat and clear, including groups | M | FR17, FR18 |
| 60 | T092 | Take-order-for-table page | M | FR42 |
| 61 | T093 | Mark served | S | FR60 |
| 62 | T144 | Admin order search and detail | M | FR102 |

### Phase 6 — Public site and reservations
*Everything a visitor sees, plus bookings · milestone M4 · 16 tasks · 23.5 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 63 | T030 | Homepage to Blade: header with hamburger, hero, tiles, about, footer from settings | L | S01, 08.6, FR91 |
| 64 | T031 | Homepage menu section: featured items, Specials offer block, category links | M | FR32, BR59 |
| 65 | T032 | Reviews section: averages and featured cards | S | FR80 |
| 66 | T033 | Full menu page: categories, Specials, allergen and dietary filters | L | FR32, FR33, FR34 |
| 67 | T034 | Item detail modal: sizes, add-ons, nutrition | M | FR34, FR35 |
| 68 | T100 | AvailabilityService: slots, capacity, closed days, lead time, pause | L | FR61, BR32-BR35 |
| 69 | T101 | ReservationService: request, approve, decline, cancel, update locks | L | FR62, FR66, FR67 |
| 70 | T102 | Reservations board, review panel, TrustService | L | FR63, FR68, FR09 |
| 71 | T103 | Phone bookings | S | FR64 |
| 72 | T104 | Assign, unassign and reassign tables | M | FR65, FR95 |
| 73 | T105 | Seat reservation and mark no-show | M | FR69, FR70 |
| 74 | T106 | Admin customer list; clear no-show | M | FR103, FR10 |
| 75 | T035 | Three-step reservation wizard | L | FR61, FR62 |
| 76 | T036 | My orders and My reservations pages | M | FR39, FR72 |
| 77 | T161 | 6 reservation email templates | M | FR74, FR75 |
| 78 | T130 | Feedback modal and submit | S | FR76 |

### Phase 7 — AI assistant
*Backend first, then interface · milestone M4 · 7 tasks · 10.0 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 79 | T120 | AiMenuService: GitHub Models client, config, busy and error handling | M | FR43, 07.9 |
| 80 | T121 | Menu and venue context builder (compact JSON under 8K tokens) | M | BR46 |
| 81 | T122 | Prompt design, structured output schema, allergen and off-topic guardrails | L | BR47, BR48 |
| 82 | T127 | AI chat API endpoint: validation, history, response contract, audit | M | FR43, BR49 |
| 83 | T128 | Meal builder API endpoint: validation, ID checks, server-side totals, cart payload | M | FR44, BR47 |
| 84 | T123 | Chat widget interface | M | FR43, S17 |
| 85 | T124 | Meal builder page and suggestion cards | L | FR44, S16 |

### Phase 8 — Admin, reports and hardening
*Dashboard, reports, settings, jobs, polish · milestone M5 · 18 tasks · 23.0 points*

| # | ID | Task | Size | Refs |
|---|---|---|---|---|
| 86 | T140 | ReportService: queries for the 15 dashboard widgets | L | FR81 |
| 87 | T141 | Admin dashboard page with charts | L | FR81, 08.5 |
| 88 | T142 | Report pages: sales, items, operations, reservations, feedback, staff | L | FR82, FR83, FR84, FR85, FR86, FR87 |
| 89 | T143 | PDF and CSV export | M | FR88 |
| 90 | T125 | AI on/off switch and AI usage report page | S | FR45, FR98 |
| 91 | T131 | Admin feedback moderation: reply, hide, feature | M | FR77, FR78, FR79 |
| 92 | T150 | SettingService and settings page (venue details, timers, switches) | M | FR91, FR96, FR97, FR98 |
| 93 | T152 | Audit log search and archive tab | M | FR90, FR99 |
| 94 | T160 | 8 scheduled commands | L | FR19, FR47, FR50, FR70, FR71, FR75 |
| 95 | T017 | Demo seeder at realistic volume (menu, orders, reservations, feedback) | L | 09.1 |
| 96 | T126 | AI evaluation set: test prompts, expected answers, accuracy findings | M | BR47, BR48 |
| 97 | T211 | Error pages: 403, 404, 419, 500 and paused states | S | NFR11 |
| 98 | T212 | Privacy policy and terms pages; attendance-history disclosure | S | NFR12 |
| 99 | T217 | Accessibility pass: labels, focus, contrast, touch targets | M | NFR11 |
| 100 | T218 | Security hardening: HTTPS enforcement, headers, CSRF and signed-route audit | M | NFR01, NFR02, NFR03, NFR04, NFR06 |
| 101 | T219 | Performance pass: eager loading, index verification, page budget | M | NFR08 |
| 102 | T222 | Queue and worker reliability: retries, backoff, backup script | S | NFR10 |
| 103 | T223 | Code conventions: Pint, service layer rules, BR references in docblocks | S | NFR19 |

### Undecided — resolve at M6
| ID | Epic | Task | Size | Refs |
|---|---|---|---|---|
| T190 | E19 | VPS provisioning: Nginx, PHP, MySQL, Redis, Supervisor, SSL | L | 10.2 |
| T191 | E19 | Deploy steps, cron, backups | M | 10.2 |
| T192 | E19 | XAMPP setup and screenshots for the report | S | 10.1 |

### Separate report part (not in this list)
| Epic | Scope | Refs |
|---|---|---|
| E17 | Testing: Selenium framework and suites, Laravel feature tests, browser, OS and responsiveness testing | 09 |
| E18 | Validation and performance: W3C HTML and CSS, php -l, HammerDB run | NFR18, guideline 3h |
| E20 | Documentation and report: Tango user manual, project management section, construction screenshots | guideline 1, 2, 4 |

## 12.3 Epics
| Epic | Name |
|---|---|
| E0 | Setup |
| E1 | Database and models |
| E2 | Auth and access |
| E3 | Public site (Blade) |
| E4 | Menu administration |
| E5 | Tables and QR |
| E6 | Cart, checkout, orders |
| E7 | Payments and refunds |
| E8 | Kitchen and bar display |
| E9 | Floor view and staff ordering |
| E10 | Reservations |
| E11 | Real-time |
| E12 | AI assistant |
| E13 | Feedback |
| E14 | Dashboard and reports |
| E15 | Settings and audit |
| E16 | Scheduled jobs and emails |
| E21 | Cross-cutting and hardening |

## 12.4 FR coverage
| FR | Requirement | Tasks | Note |
|---|---|---|---|
| FR01 | Customer Registration | T020 |  |
| FR02 | Create Staff Account | T024 |  |
| FR03 | Login and Logout | T002, T020, T021 |  |
| FR04 | Role-Based Access Control | T022 |  |
| FR05 | Reset Password | T020 |  |
| FR06 | Verify Email | T020 |  |
| FR07 | Update Profile | T023 |  |
| FR08 | Deactivate Staff Account | T024 |  |
| FR09 | View Customer Trust Profile | T102 |  |
| FR10 | Clear No-show Flag | T106 |  |
| FR11 | Create Table | T050 |  |
| FR12 | Edit Table | T050 |  |
| FR13 | Deactivate or Reactivate Table | T050 |  |
| FR14 | Generate Signed Table QR | T051 |  |
| FR15 | Download or Print QR | T051, T214 |  |
| FR16 | View Floor Status | T090 |  |
| FR17 | Seat Walk-in | T091 |  |
| FR18 | Clear Table | T091 |  |
| FR19 | Auto-clear Idle Table | T053, T160 |  |
| FR20 | Override Table Status | T050 |  |
| FR21 | Record Table Status Change | T053 |  |
| FR22 | Manage Categories | T040 |  |
| FR23 | Add Menu Item | T041, T213 |  |
| FR24 | Edit Menu Item | T041 |  |
| FR25 | Archive Menu Item | T041 |  |
| FR26 | Manage Sizes and Prices | T042 |  |
| FR27 | Set Sale Price | T042 |  |
| FR28 | Manage Add-on Groups | T043 |  |
| FR29 | Toggle Availability | T083 |  |
| FR30 | Set Daily Limit | T044 |  |
| FR31 | Scan Table QR | T052 |  |
| FR32 | Browse Menu | T031, T033 |  |
| FR33 | Filter by Allergen and Dietary Tag | T033 |  |
| FR34 | View Nutrition Information | T033, T034 |  |
| FR35 | Add Item to Cart | T034, T060 |  |
| FR36 | Edit Cart | T060 |  |
| FR37 | Checkout | T061 |  |
| FR38 | Track Order Status | T062 |  |
| FR39 | View Order History | T036 |  |
| FR40 | Call Waiter | T054 |  |
| FR41 | Cancel Unpaid Order | T063 |  |
| FR42 | Take Order for Table | T073, T092 |  |
| FR43 | AI Menu Chatbot | T120, T123, T127 |  |
| FR44 | AI Meal Builder | T124, T128 |  |
| FR45 | Monitor AI Usage | T125 |  |
| FR46 | Pay by Stripe Checkout | T070 |  |
| FR47 | Confirm Stripe Payment by Verification | T070, T160 |  |
| FR48 | Request Cash Payment | T072 |  |
| FR49 | Record Cash Payment | T072 |  |
| FR50 | Clean Up Unpaid Orders | T160 |  |
| FR51 | Request Refund | T074 |  |
| FR52 | Issue Refund | T075 |  |
| FR53 | Generate Receipt | T064, T214 |  |
| FR54 | Cash Reconciliation | — | Removed from scope |
| FR55 | Route Order Lines to Stations | T071 |  |
| FR56 | View Station Queue | T080 |  |
| FR57 | Filter Station Queue | T080 |  |
| FR58 | Update Line Status | T081 |  |
| FR59 | Adjust Station ETA | T082 |  |
| FR60 | Mark Served | T093 |  |
| FR61 | View Available Times | T035, T100 |  |
| FR62 | Submit Reservation Request | T035, T101 |  |
| FR63 | Review Reservation Request | T102 |  |
| FR64 | Create Staff Reservation | T103 |  |
| FR65 | Assign Tables to Reservation | T104 |  |
| FR66 | Update Reservation | T101 |  |
| FR67 | Cancel Reservation | T101 |  |
| FR68 | View Reservation List | T102 |  |
| FR69 | Seat Reservation | T105 |  |
| FR70 | Mark No-show | T105, T160 |  |
| FR71 | Reservation Floor Alerts | T160 |  |
| FR72 | View My Reservations | T036 |  |
| FR73 | Staff Real-time Alerts | T090, T111 |  |
| FR74 | Send Reservation Emails | T161 |  |
| FR75 | Send Reservation Reminder | T160, T161 |  |
| FR76 | Submit Feedback | T130 |  |
| FR77 | View and Reply to Feedback | T131 |  |
| FR78 | Hide Abusive Feedback | T131 |  |
| FR79 | Feature Review | T131 |  |
| FR80 | Public Ratings Section | T032 |  |
| FR81 | Admin Live Dashboard | T140, T141 |  |
| FR82 | Sales Report | T142 |  |
| FR83 | Item and Category Report | T142 |  |
| FR84 | Operations Report | T142 |  |
| FR85 | Reservation Report | T142 |  |
| FR86 | Feedback Report | T142 |  |
| FR87 | Staff Activity Report | T142 |  |
| FR88 | Export Report | T143 |  |
| FR89 | Record Audit Event | T151 |  |
| FR90 | Search Audit Log | T152 |  |
| FR91 | Manage Venue Settings | T030, T150 |  |
| FR92 | Manage Blackout Dates | — | Removed from scope |
| FR93 | Cancel Unpaid Order (Staff) | T076 |  |
| FR94 | Resolve Stock Conflict | T076 |  |
| FR95 | Unassign or Reassign Tables | T104 |  |
| FR96 | Pause QR Ordering | T065, T150 |  |
| FR97 | Pause Online Reservations | T150 |  |
| FR98 | Toggle AI Assistant | T125, T150 |  |
| FR99 | View Archived Records | T152 |  |
| FR100 | Manage Time Slots | T107 |  |
| FR101 | Manage Allergens and Dietary Tags | T040 |  |
| FR102 | Search and View Orders | T144 |  |
| FR103 | View Customer List | T106 |  |

## 12.5 NFR coverage
| NFR | Category | Tasks |
|---|---|---|
| NFR01 | Security | T218 |
| NFR02 | Security | T218 |
| NFR03 | Security | T218 |
| NFR04 | Security | T218 |
| NFR05 | Security | T216 |
| NFR06 | Security | T110, T218 |
| NFR07 | Payments | T070, T071 |
| NFR08 | Performance | T213, T219 |
| NFR09 | Real-time | T003, T112 |
| NFR10 | Reliability | T222 |
| NFR11 | Usability | T211, T217 |
| NFR12 | Privacy | T212 |
| NFR13 | Localisation | T220 |
| NFR14 | Auditability | T151 |
| NFR15 | Email | T221 |
| NFR16 | Testability | Separate report part (E17) |
| NFR17 | Licensing | Policy: no template code copied |
| NFR18 | Validation | T006 |
| NFR19 | Maintainability | T223 |

