# 3. Requirements

Priority: **Must** required for release · **Should** planned · **Could** if time allows. *(changed)* / *(new)* mark differences from Functional Requirements v2.0.

## 3.1 Actors and roles
| Role | Description |
|---|---|
| Admin | Manager on duty. Everything staff can do, plus menu, tables, staff, refunds, settings, reports, audit. |
| Waitstaff | Floor view, seat/clear tables, take orders, cash, serve, reservations, refund requests, cancel unpaid orders. |
| Kitchen | Kitchen station display, kitchen availability, refund requests, resolve conflicts. |
| Bar | Bar station display, bar availability, refund requests, resolve conflicts. |
| Customer | Logged-in account: QR ordering, payment, tracking, reservations, feedback, AI. |
| Visitor | Not logged in: website, menu, reviews, AI, call waiter from a table QR. |

## 3.2 Staff role behaviour
Admin passes every staff role check. Kitchen and Bar only see their station. Roles are fixed seed data.

## 3.3 Permissions matrix
| Capability | Admin | Waitstaff | Kitchen | Bar | Customer | Visitor |
|---|---|---|---|---|---|---|
| Manage menu, sizes, add-ons, sale prices, limits, featured | Y | - | - | - | - | - |
| Toggle item/add-on availability | Y | - | Own station | Own station | - | - |
| Manage tables, QR, slots, allergens, tags, settings, switches | Y | - | - | - | - | - |
| View floor, seat and clear tables | Y | Y | - | - | - | - |
| Override table status | Y | - | - | - | - | - |
| Browse menu, reviews, use AI | Y | Y | Y | Y | Y | Y |
| Call waiter from table QR | - | - | - | - | Y | Y |
| Place QR orders and pay | - | - | - | - | Y | - |
| Take order for a table | Y | Y | - | - | - | - |
| Record cash payment and adjustment | Y | Y | - | - | - | - |
| Cancel unpaid order | Y | Y | - | - | Own order | - |
| Request refund | Y | Y | Y | Y | - | - |
| Issue refund | Y | - | - | - | - | - |
| View and update station lines, adjust ETA | Y | - | Own station | Own station | - | - |
| Resolve stock conflict | Y | - | Own station | Own station | - | - |
| Mark served | Y | Y | - | - | - | - |
| Review, create, assign, unassign, seat reservations; mark no-show | Y | Y | - | - | - | - |
| Request, update, cancel own reservations; submit feedback | - | - | - | - | Y | - |
| Clear no-show flag, moderate feedback | Y | - | - | - | - | - |
| Dashboard, reports, audit log, archive, customers, staff accounts | Y | - | - | - | - | - |

## 3.4 Functional requirements

### User and Access
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR01 | Customer Registration | Visitor | C | Must | Register with name, email, password and mobile (unique, BR52). Account can order immediately; verification needed only for reservations (BR42). |
| FR02 | Create Staff Account | Admin | C | Must | Admin creates staff with name, email, temporary password and role. No staff self-registration. |
| FR03 | Login and Logout | All users | R | Must | Separate customer and staff logins. Login rate-limited (NFR05). Staff sessions time out (BR51). |
| FR04 | Role-Based Access Control | System | R | Must | Every route, action and private channel authorised per the permissions matrix (3.3). |
| FR05 | Reset Password | All users | U | Must | Emailed reset link valid 60 minutes (framework password_reset_tokens). |
| FR06 | Verify Email | Customer | U | Should | Verification link. Required before a reservation request (BR42). Email change re-verifies. |
| FR07 | Update Profile | Customer, Staff | U | Must | Edit name, mobile, password. Staff cannot change own role. |
| FR08 | Deactivate Staff Account *(changed)* | Admin | U | Must | Soft deactivation; ends current sessions immediately (BR60). Cannot deactivate self or last active admin. Snapshot archived (BR62). |
| FR09 | View Customer Trust Profile | Admin, Waitstaff | R | Must | Badge New/Regular/Flagged with member since, completed visits, no-shows, late cancellations, last visit (BR40). |
| FR10 | Clear No-show Flag | Admin | U | Should | Removes a no-show from badge calculation. Reason required. Audited. |

### Tables
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR11 | Create Table | Admin | C | Must | Unique table number, seats, section. Active by default. |
| FR12 | Edit Table | Admin | U | Must | Change number, seats, section. Warn if seats drop below an assigned future party size. |
| FR13 | Deactivate or Reactivate Table | Admin | U | Must | is_active flag. Inactive QR shows 'table unavailable' (BR07). |
| FR14 | Generate Signed Table QR | Admin | C | Must | QR encodes signed URL with qr_token (NFR04). Regenerating invalidates old QR. |
| FR15 | Download or Print QR | Admin | R | Should | PNG/PDF generated on demand; not stored. |
| FR16 | View Floor Status *(changed)* | Admin, Waitstaff | R | Must | Live table grid: status, next assigned reservation, active orders, alerts, ready-to-serve and cash-waiting lists, 'QR ordering paused' banner. |
| FR17 | Seat Walk-in | Waitstaff, Admin | U | Must | Available → Occupied for one or more tables; opens visit rows. |
| FR18 | Clear Table | Waitstaff, Admin | U | Must | Occupied → Available for one table or group; closes visits; confirmation if active orders (BR06). |
| FR19 | Auto-clear Idle Table | System | U | Should | Scheduled job per BR05. |
| FR20 | Override Table Status | Admin | U | Should | Any status with mandatory reason; audited. |
| FR21 | Record Table Status Change *(changed)* | System | C | Must | Occupancy recorded in visit (opened/closed); every status change written to audit_log (action table_status). Replaces table_status_log. |

### Menu
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR22 | Manage Categories | Admin | CRUD | Must | Name, order, optional parent (one level). Cannot delete with items or children. |
| FR23 | Add Menu Item *(changed)* | Admin | C | Must | Name, description, image, category, station, prep minutes, allergen and dietary tags, nutrition, featured flag, at least one size. |
| FR24 | Edit Menu Item *(changed)* | Admin | U | Must | All fields including is_featured toggle (list and form). Past orders unaffected (BR15). |
| FR25 | Archive Menu Item *(changed)* | Admin | U | Must | is_active = 0; hidden from menus; kept for history; snapshot archived (BR62). |
| FR26 | Manage Sizes and Prices | Admin | CRUD | Must | One or more sizes with GST-inclusive price. |
| FR27 | Set Sale Price *(changed)* | Admin | U | Should | Sale price per size with optional start/end. Drives Specials (BR59). |
| FR28 | Manage Add-on Groups *(changed)* | Admin | CRUD | Must | Groups belong to one item (not reusable): required flag, min/max. Options with price delta and availability. Option allergens must be in item tags (BR61). |
| FR29 | Toggle Availability | Admin, Kitchen, Bar | U | Must | Item or add-on option sold out/available; station-scoped (BR14); broadcast live. |
| FR30 | Set Daily Limit | Admin | U | Should | Optional daily limit shared across sizes (BR09, BR10). |

### Customer Ordering
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR31 | Scan Table QR *(changed)* | Visitor, Customer | R | Must | Validates signature and active table. Logged-out users see QR login page with Call waiter and homepage link, then land on the table ordering menu (BR56). Reserved rules apply (BR02, BR03). |
| FR32 | Browse Menu *(changed)* | Visitor, Customer | R | Must | Categories with automatic Specials first (BR59), items, sizes, sale prices, sold-out state, live updates. Homepage shows featured items and top Special. |
| FR33 | Filter by Allergen and Dietary Tag | Visitor, Customer | R | Should | Filters by item tags (which include add-on allergens, BR61). Disclaimer shown. |
| FR34 | View Nutrition Information | Visitor, Customer | R | Could | Calories, protein, carbohydrates, fat where recorded. |
| FR35 | Add Item to Cart *(changed)* | Customer | C | Must | Requires table context; otherwise 'Scan the QR code on your table to order' (BR57). Size, add-ons (BR16), quantity, special request (BR17). Blocked when QR ordering paused (BR58). |
| FR36 | Edit Cart | Customer | U, D | Must | Change quantity/options or remove lines; live totals (BR21). |
| FR37 | Checkout *(changed)* | Customer | C | Must | Revalidates items and prices (BR08), applies 5× buffer stock check (BR09), creates pending_payment order with snapshots, idempotency key. |
| FR38 | Track Order Status | Customer | R | Must | Live timeline (paid, preparing, ready, served), station ETA ranges, ready alert while page open. |
| FR39 | View Order History | Customer | R | Should | 'My orders' with status, totals, receipts, refund state. |
| FR40 | Call Waiter *(changed)* | Visitor, Customer | C | Should | From table ordering page or QR login page without login. Floor alert (live only). Cooldown BR50. |
| FR41 | Cancel Unpaid Order *(changed)* | Customer | U | Should | Customer can cancel only pending_payment orders. Paid orders need staff (BR29). |
| FR42 | Take Order for Table *(changed)* | Waitstaff, Admin | C | Must | Staff build an order for a table; exact stock check; pay by cash or Stripe QR (BR53). Allowed when QR ordering is paused. |

### AI Assistance
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR43 | AI Menu Chatbot *(changed)* | Visitor, Customer | R | Should | Answers menu, dietary, price and venue questions using live data via GitHub Models (OpenAI models) (BR46–BR49). |
| FR44 | AI Meal Builder *(changed)* | Visitor, Customer | R | Should | Budget, party size, dietary needs, preferences → validated suggestions. 'Add to cart' only in table context (BR47, BR49). |
| FR45 | Monitor AI Usage *(changed)* | Admin | R | Could | Requests, tokens and success/busy rate from audit_log ai_request entries. |

### Payments and Refunds
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR46 | Pay by Stripe Checkout | Customer | C | Must | Creates Stripe Checkout Session (test mode) for exact amount; redirects to Stripe. |
| FR47 | Confirm Stripe Payment by Verification *(changed)* | System, Customer | U | Must | Server retrieves Checkout Session from Stripe API on return, on 'Check payment status' button, or by reconcile job. Marks paid once (BR25). No webhooks. |
| FR48 | Request Cash Payment | Customer | C | Must | Order stays pending_payment with cash method; floor 'cash waiting' list updates live. |
| FR49 | Record Cash Payment | Waitstaff, Admin | C | Must | Amount received, change, rounding (BR22), optional adjustment with category and note (BR23). Warns if exact stock check would fail. |
| FR50 | Clean Up Unpaid Orders *(changed)* | System | U | Must | Daily at closing time: expire open Stripe sessions via API, cancel remaining pending_payment orders (BR26). No timed expiry. |
| FR51 | Request Refund *(changed)* | Waitstaff, Kitchen, Bar, Admin | C | Should | Staff only; customers cannot. Select lines, quantity, reason; admin alerted. |
| FR52 | Issue Refund *(changed)* | Admin | C | Must | Approve/reject; method Stripe (API), cash or manual (reference). Partial per line. 'Return to stock' checkbox unticked by default (BR13, BR27). |
| FR53 | Generate Receipt | Customer, Staff | R | Must | PDF with lines, options, sale discounts, GST, method, rounding, adjustments, refunds. |
| FR54 | Cash Reconciliation *(removed)* | — | — | Removed | Removed to keep ERD size. Cash by staff shown in Sales Report (FR82). |

### Kitchen and Bar Display
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR55 | Route Order Lines to Stations *(changed)* | System | U | Must | On payment, lines appear on Kitchen or Bar display by order_item.destination; station ETAs set on orders (BR30). |
| FR56 | View Station Queue | Kitchen, Bar, Admin | R | Must | Live queue of paid orders' lines for the station, oldest first, with options, requests, table, elapsed time, stock conflict flag. |
| FR57 | Filter Station Queue | Kitchen, Bar, Admin | R | Should | Station switch (Admin), status and time filters. |
| FR58 | Update Line Status *(changed)* | Kitchen, Bar | U | Must | Start (preparing) and Ready for an order's lines at the station; order status derived (BR28). |
| FR59 | Adjust Station ETA *(changed)* | Kitchen, Bar | U | Could | Add/subtract minutes to orders.kitchen_eta_at or bar_eta_at; customer ETA updates live. |
| FR60 | Mark Served | Waitstaff | U | Must | Ready lines of a station marked served; order becomes served when all lines served. |

### Reservations
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR61 | View Available Times *(changed)* | Visitor, Customer | R | Must | Times from slot capacity, closed weekdays, max days ahead, lead time, online pause (BR32–BR35, BR58). |
| FR62 | Submit Reservation Request | Customer | C | Must | Date, time, party size, notes; verified email and mobile (BR42). Status requested; email sent. |
| FR63 | Review Reservation Request | Waitstaff, Admin | U | Must | Approve (capacity check) or decline with optional reason; trust profile shown. |
| FR64 | Create Staff Reservation *(changed)* | Waitstaff, Admin | C | Should | Phone booking for an account or guest name/mobile; confirmed immediately. Allowed when online bookings paused. |
| FR65 | Assign Tables to Reservation *(changed)* | Waitstaff, Admin | U | Must | One or more tables (seats ≥ party, no overlap) by T–30; creates visit rows with opened_at NULL (BR04). |
| FR66 | Update Reservation *(changed)* | Customer, Waitstaff, Admin | U | Must | Customer date/time/party changes return booking to requested and are locked within 2 hours (BR36); staff can always edit. |
| FR67 | Cancel Reservation *(changed)* | Customer, Waitstaff, Admin | U | Must | From requested/confirmed; customer sees warning that cancelling may affect future bookings; late cancellation recorded (BR38). |
| FR68 | View Reservation List | Waitstaff, Admin | R | Must | By date and status; unassigned bookings inside T–30 highlighted. |
| FR69 | Seat Reservation | Customer (holder), Waitstaff | U | Must | Holder scan within window or staff open → linked tables Occupied, visits opened, reservation seated. |
| FR70 | Mark No-show | Waitstaff, Admin | U | Must | After grace, system suggests; staff confirm; account flagged; visits closed (no_show). |
| FR71 | Reservation Floor Alerts | System | C | Must | Unassigned at T–30 (floor) and T–15 (admin); Reserved switch at T–30 with place-sign alert; linked table still occupied warning. |
| FR72 | View My Reservations | Customer | R | Must | Upcoming and past with update/cancel actions. |

### Notifications
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR73 | Staff Real-time Alerts *(changed)* | System | C | Must | Live-only Reverb alerts: new lines, ready to serve, cash waiting, call waiter, refund request, stock conflict, reservation alerts. Not stored; lists rebuilt from data on reconnect. |
| FR74 | Send Reservation Emails | System | C | Must | Queued emails: received, confirmed, declined, expired, cancelled. |
| FR75 | Send Reservation Reminder | System | C | Should | Email X hours before booking (default 24). |

### Feedback
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR76 | Submit Feedback | Customer | C | Should | One per served, paid QR order; food and service 1–5; optional comment. |
| FR77 | View and Reply to Feedback | Admin | R, U | Should | Filter and reply; cannot edit (BR44). |
| FR78 | Hide Abusive Feedback | Admin | U | Should | Hide/unhide with reason; excluded from averages. |
| FR79 | Feature Review | Admin | U | Could | Feature/unfeature any non-hidden review. |
| FR80 | Public Ratings Section | Visitor | R | Could | Homepage true average food/service with count (≥10) and up to 3 'Featured reviews' (BR45). |

### Reporting
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR81 | Admin Live Dashboard *(changed)* | Admin | R | Must | 15 widgets defined in 8.5: 8 KPI tiles, 3 charts, 4 lists. |
| FR82 | Sales Report *(changed)* | Admin | R | Must | Gross, GST, sale discounts, cash adjustments, refunds, net, method split, order source, cash by staff. |
| FR83 | Item and Category Report | Admin | R | Should | Top/bottom sellers, category sales, sold-out occurrences. |
| FR84 | Operations Report | Admin | R | Could | Peak hours, average prep time per station, ETA accuracy, table turnover (from visit). |
| FR85 | Reservation Report | Admin | R | Should | Bookings, covers, approval/decline rate, no-show rate, late cancellations, walk-in visits. |
| FR86 | Feedback Report | Admin | R | Should | Averages and trend, distribution, hidden count. |
| FR87 | Staff Activity Report | Admin | R | Should | Per staff: cash payments, adjustments, refund requests, toggles, overrides. |
| FR88 | Export Report | Admin | R | Should | PDF or CSV for any report. |

### Audit and Settings
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR89 | Record Audit Event *(changed)* | System | C | Must | Logs logins, staff changes, menu/price changes, toggles, table status, payments, adjustments, refunds, reservation decisions, no-shows, flag clears, feedback moderation, settings, AI requests. |
| FR90 | Search Audit Log | Admin | R | Must | Filter by user, action, entity, date; view before/after JSON. |
| FR91 | Manage Venue Settings *(changed)* | Admin | U | Must | Venue details (name, address, phone, email, socials), opening/closing time, closed weekdays, reservation rules, timers, buffer multiplier (see 6.5). |
| FR92 | Manage Blackout Dates *(removed)* | — | — | Removed | Removed. Use Pause Online Reservations (FR97), inactive time slots, or staff declining requests. |

### Operational Controls (added v3)
| ID | Requirement | Actor | CRUD | Priority | Description and rules |
|---|---|---|---|---|---|
| FR93 | Cancel Unpaid Order (Staff) *(new)* | Waitstaff, Admin | U | Must | Cancel a pending_payment order with reason; expires any open Stripe session (BR63). |
| FR94 | Resolve Stock Conflict *(new)* | Kitchen, Bar, Admin | U | Must | Choose 'will make it' or 'raise refund request'; clears has_stock_conflict; audited (BR54). |
| FR95 | Unassign or Reassign Tables *(new)* | Waitstaff, Admin | U | Must | Remove or change tables linked to a confirmed reservation; closes visit rows (BR64). |
| FR96 | Pause QR Ordering *(new)* | Admin | U | Should | Setting qr_ordering_enabled; customers can browse but not add to cart or checkout (BR58). |
| FR97 | Pause Online Reservations *(new)* | Admin | U | Should | Setting reservations_online_enabled; wizard shows call-us message (BR58). |
| FR98 | Toggle AI Assistant *(new)* | Admin | U | Should | Setting ai_enabled; hides chat widget and meal builder links (BR49). |
| FR99 | View Archived Records *(new)* | Admin | R | Could | Read-only tab listing historical_data_management snapshots with JSON view (BR62). |
| FR100 | Manage Time Slots *(new)* | Admin | CRUD | Must | Slot time, max covers, active flag. |
| FR101 | Manage Allergens and Dietary Tags *(new)* | Admin | CRUD | Must | Reference lists used by items and filters. |
| FR102 | Search and View Orders *(new)* | Admin | R | Must | Search by number, date, table, status; detail with lines, payments, refunds, history. |
| FR103 | View Customer List *(new)* | Admin | R | Should | Search customers; open trust profile; clear no-show (FR10). |

## 3.5 Business rules
| Area | ID | Rule | Status |
|---|---|---|---|
| Tables | BR01 | A table becomes Occupied on its first paid order or when staff seat guests. pending_payment orders do not change status. |  |
| Tables | BR02 | On a Reserved table only the holder (within the unlock window) or staff can order. Others see 'Reserved for <first name> <last initial>' and Call waiter. |  |
| Tables | BR03 | Holder unlock window: 15 minutes before to 15 minutes after booking time (grace). |  |
| Tables | BR04 | Assigning tables creates visit rows with opened_at NULL. Status becomes Reserved only via the scheduler at T–30, or immediately if assigned inside 30 minutes, with a 'place reserved sign' alert. | changed |
| Tables | BR05 | Auto-clear sets Occupied → Available only when no pending_payment, paid, preparing or ready orders exist and no paid order within idle minutes (default 45). |  |
| Tables | BR06 | Clearing a table with active orders requires confirmation. |  |
| Tables | BR07 | Inactive tables cannot be ordered from, seated or assigned. |  |
| Ordering | BR08 | Checkout revalidates availability, options and current prices; removed lines are reported to the customer. |  |
| Ordering | BR09 | No stock holds. QR checkout requires daily_limit − sold_today ≥ buffer (default 5) × total cart quantity of that item. QR menus show Sold out when remaining < buffer. Staff-taken orders use the exact check. | changed |
| Ordering | BR10 | sold_today increases atomically when payment succeeds: sold_today + qty ≤ daily_limit, shared across sizes. Items with NULL limit skip checks. | changed |
| Ordering | BR11 | Daily counters reset at opening_time (Australia/Melbourne). |  |
| Ordering | BR12 | Lowering a limit below sold_today sells the item out; paid orders never affected; reactivating does not reset the counter. |  |
| Ordering | BR13 | Refunded quantity returns to sold_today only if the admin ticks 'Return to stock' (default unticked). Cancelled unpaid orders never touched stock. | changed |
| Ordering | BR14 | Kitchen and Bar toggle availability only for their own station's items and options. |  |
| Ordering | BR15 | Order lines snapshot item name, size name, original and charged price, and options with prices. |  |
| Ordering | BR16 | Add-on selections must satisfy each group's required flag and min/max. |  |
| Ordering | BR17 | Special requests ≤ 200 characters, no price effect. |  |
| Ordering | BR18 | One checkout creates one order. Lines appear on station displays only after payment. | changed |
| Pricing | BR19 | Prices AUD, GST-inclusive. GST = total / 11. |  |
| Pricing | BR20 | Sale price applies only inside its start/end window. Add-ons always full price. |  |
| Pricing | BR21 | Line total = (charged size price + option prices) × quantity. Order total = sum of lines. |  |
| Pricing | BR22 | Cash totals round to nearest 5 cents (rounding_amount). Stripe exact. |  |
| Pricing | BR23 | Staff may adjust the final cash amount by any amount; category and note mandatory; reported per staff. |  |
| Payments | BR24 | Stripe amount fixed at session creation; money back only through refunds. |  |
| Payments | BR25 | A Stripe payment is marked succeeded only after the server retrieves the Checkout Session from the Stripe API and payment_status = paid (return URL, Check payment button, or reconcile job). Redirect alone is never proof. Idempotent on stripe_session_id. No webhooks; Stripe test mode. | changed |
| Payments | BR26 | Orders do not expire. At closing_time the cleanup job expires open Stripe sessions via API, then cancels remaining pending_payment orders. | changed |
| Payments | BR27 | Only staff request refunds; only Admin issues them; per line or amount; reason required; orders.payment_status recalculated. | changed |
| Order status | BR28 | orders.status: pending_payment, paid, preparing, ready, served, cancelled. orders.payment_status: unpaid, paid, partially_refunded, refunded. order_item.status: pending, preparing, ready, served, cancelled. Order is preparing when any active line is preparing, ready when all active lines are ready or served, served when all active lines are served. | changed |
| Order status | BR29 | Customers cancel only pending_payment orders. Paid orders are handled by staff refund requests. | changed |
| ETA | BR30 | Station ETA = longest prep_minutes of the station's lines + (orders ahead in that station queue × avg minutes per order). Stored as orders.kitchen_eta_at / bar_eta_at; shown as a 5-minute range; staff adjustments apply. | changed |
| Reservations | BR31 | Online requests need staff approval; staff-created bookings are confirmed. |  |
| Reservations | BR32 | Requested and confirmed bookings count against slot max_covers. |  |
| Reservations | BR33 | Approval checks slot capacity; assignment checks seats ≥ party size, active tables, no overlap. |  |
| Reservations | BR34 | Duration by party size: 1–2 guests 90 min, 3–6 guests 120 min, 7+ guests 150 min (settings). |  |
| Reservations | BR35 | Online requests need ≥ 2 hours lead time, ≤ 60 days ahead, party ≤ online maximum, not on closed weekdays. | changed |
| Reservations | BR36 | Customer changes to date, time or party size return the booking to requested and unlink tables, and are blocked within 2 hours of the booking. Notes stay editable. Staff can always edit. | changed |
| Reservations | BR37 | Requests unreviewed 1 hour before booking time expire; customer emailed. |  |
| Reservations | BR38 | Cancellation < 2 hours before booking is recorded as late; does not flag the account. Customers see a warning before cancelling. | changed |
| Reservations | BR39 | After grace the system suggests a no-show; only staff confirm; account flagged; visits closed. |  |
| Reservations | BR40 | Badge: Flagged if ≥ 1 uncleared no-show in last 12 months; Regular if ≥ 3 completed visits and not Flagged; else New. Calculated on read. |  |
| Reservations | BR41 | Customer names shown to other customers: first name + last initial. |  |
| Reservations | BR42 | Reservation requests need a verified email and a mobile number. |  |
| Feedback | BR43 | One feedback per served, paid QR order; food and service 1–5. |  |
| Feedback | BR44 | Admin cannot edit feedback; hiding needs a reason and is logged. |  |
| Feedback | BR45 | Public average uses non-hidden feedback, shown after ≥ 10 reviews. Any non-hidden review can be featured, labelled 'Featured reviews', with first name, last initial and date. |  |
| AI | BR46 | AI context: currently available items, sizes, options, prices, allergens, nutrition, plus venue name, address and hours. No customer personal data sent. | changed |
| AI | BR47 | AI returns structured item/size/option IDs; server validates against live menu, drops invalid IDs, calculates totals. AI never places orders. |  |
| AI | BR48 | AI states only stored allergen tags, shows a fixed disclaimer, declines off-topic questions. |  |
| AI | BR49 | AI is available to everyone including visitors, with no app rate limit. Provider limit errors show 'assistant busy'. ai_enabled = 0 hides it. 'Add to cart' from suggestions appears only in table context. | changed |
| General | BR50 | Call waiter at most once per 2 minutes per table (cache). Works from the QR login page without login. | changed |
| General | BR51 | Staff sessions expire after 30 minutes of inactivity. |  |
| General | BR52 | Mobile numbers unique across customer accounts. |  |
| Ordering | BR53 | Staff-taken orders: same cart rules, exact stock check, created_by staff, no customer account, paid by cash or Stripe QR before reaching stations, not eligible for feedback. |  |
| Ordering | BR54 | If the exact stock check fails at payment, the order is still paid, has_stock_conflict = 1, and station and admin are alerted. Staff resolve it ('will make it' or refund request), which clears the flag. | new |
| Reservations | BR55 | A seated reservation becomes completed when all its visit rows are closed. | new |
| Access | BR56 | Scanning a table QR requires customer login before the ordering menu. The QR login page shows Call waiter and a homepage link; after login the user returns to that table's ordering menu, which links to the homepage. | new |
| Ordering | BR57 | Add to cart outside table context shows 'Scan the QR code on your table to order'. The cart belongs to one table; scanning another table asks to clear it. | new |
| Settings | BR58 | qr_ordering_enabled = 0 blocks customer add to cart and checkout (staff orders allowed). reservations_online_enabled = 0 blocks customer requests (staff bookings allowed). | new |
| Menu | BR59 | Specials: an item is listed while any size has an active sale. Specials shows first in category filters when non-empty. Homepage offer block shows the Special with the largest percentage discount and its end date; hidden when none. | new |
| Access | BR60 | Deactivating a staff account deletes their sessions immediately. | new |
| Menu | BR61 | Allergens of add-on options must be included in the parent item's allergen tags (options have no own allergen list). | new |
| General | BR62 | When a menu item, category, table or staff account is archived or deactivated, a JSON snapshot is written to historical_data_management linked to the audit_log entry. | new |
| Payments | BR63 | Waitstaff and Admin may cancel pending_payment orders with a reason; any open Stripe session is expired first. | new |
| Reservations | BR64 | Unassigning tables closes their visit rows (close_reason unassigned); a table Reserved for that booking returns to Available. | new |

## 3.6 Non-functional requirements
| ID | Category | Requirement |
|---|---|---|
| NFR01 | Security | All traffic HTTPS including WebSockets (wss). |
| NFR02 | Security | Validation via Form Requests; Eloquent/parameterised queries; escaped output. |
| NFR03 | Security | CSRF tokens on all state-changing web requests (no exemptions; no webhook routes). |
| NFR04 | Security | Table QR URLs are Laravel signed routes containing qr_token; tampered or old signatures rejected. |
| NFR05 | Security | bcrypt/Argon2 passwords; login limited to 5 attempts per minute per email + IP. |
| NFR06 | Security | Policies and role middleware; private broadcast channels authorised in routes/channels.php. |
| NFR07 | Payments | Stripe hosted Checkout in test mode; no card data handled or stored; API keys in .env only. |
| NFR08 | Performance | Menu page < 2 s on 4G; API p95 < 500 ms at expected load. |
| NFR09 | Real-time | Events delivered < 2 s; clients re-fetch state on reconnect; broadcasts sent after DB commit. |
| NFR10 | Reliability | Supervisor keeps Reverb and queue workers alive; scheduler every minute; daily DB backup kept 14 days. |
| NFR11 | Usability | Mobile-first public site with hamburger ≤ 820 px; staff/admin sidebar collapses to hamburger ≤ 900 px; target WCAG 2.1 AA. |
| NFR12 | Privacy | Privacy policy under the Australian Privacy Principles, disclosing attendance history; minimal data; no personal data sent to AI; chat content not stored. |
| NFR13 | Localisation | Timezone Australia/Melbourne; AUD; Australian date format. |
| NFR14 | Auditability | audit_log append-only; no update/delete in application. |
| NFR15 | Email | Queued transactional emails; no marketing email. |
| NFR16 | Testability | Selenium WebDriver end-to-end tests; every tested control has a stable data-testid; business rules covered by Laravel feature/unit tests. |
| NFR17 | Licensing | Uena admin template is visual reference only; no copied markup/CSS; Coolaroo colour tokens reused. |
| NFR18 | Validation | Rendered homepage passes W3C HTML validation with no errors or warnings; style.css passes W3C CSS validation; all PHP passes php -l. |
| NFR19 | Maintainability | Laravel conventions; all business rules in Services; status changes only through enum transition maps. |
