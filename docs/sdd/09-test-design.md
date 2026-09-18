# 9. Test Design

## 9.1 Approach
| Level | Tool | Scope |
|---|---|---|
| End-to-end (report module tests) | **Selenium WebDriver** (Chrome, Edge, Firefox) — no approval needed per guideline | Test cases 9.4, black-box |
| Feature / unit | Laravel (Pest or PHPUnit) | Business rules BR01–BR64, services, transitions (white-box) |
| Browser platforms | Chrome, Edge, Firefox, Safari (iOS) | Main flows per browser |
| Responsiveness | Chrome DevTools | 1440, 1024, 820, 768, 390, 360 px; hamburger check |
| Validation | W3C HTML (homepage), W3C CSS (style.css), `php -l` all PHP | Zero errors/warnings |
| Database performance | HammerDB + phpMyAdmin monitor | Seeded realistic volumes |

## 9.2 Conventions
- Test ID `TC-UCxx-nn`; tester name, date and time recorded per run.
- Selectors use `data-testid` only.
- Stripe test card 4242 4242 4242 4242; seeded data via `php artisan db:seed --class=DemoSeeder`.
- Results: Passed / Failed / Partially passed with rationale.

## 9.3 Result record template
| Test ID | Tester | Date/time | Browser/OS | Result | Issue | Resolution |
|---|---|---|---|---|---|---|

## 9.4 Test cases
| ID | UC | Refs | Title | Steps | Expected |
|---|---|---|---|---|---|
| TC-UC01-01 | UC01 | FR32, BR59 | Specials listed first | Set sale on an item; open /menu | Specials filter first, item shows strikethrough price |
| TC-UC01-02 | UC01 | FR33 | Allergen filter | Filter out Dairy | No items tagged Dairy shown |
| TC-UC01-03 | UC01 | BR57 | Add to cart without table | On /menu tap Add to cart | Scan-QR prompt shown, cart unchanged |
| TC-UC02-01 | UC02 | FR40, BR50 | Call waiter from QR login page | Open QR URL logged out; tap Call waiter | Confirmation shown; floor view receives alert |
| TC-UC02-02 | UC02 | BR50 | Call waiter cooldown | Tap Call waiter twice within 2 minutes | Second tap shows 'Already requested' |
| TC-UC03-01 | UC03 | FR80, BR45 | Ratings hidden below 10 | Seed 9 reviews; open homepage | Averages hidden; featured cards shown |
| TC-UC04-01 | UC04 | FR01 | Register new customer | Fill valid form; submit | Account created, logged in, verification email queued |
| TC-UC04-02 | UC04 | BR52 | Duplicate mobile | Register with existing mobile | Validation error on mobile |
| TC-UC05-01 | UC05 | FR03 | Staff login by role | Log in as kitchen | Redirected to kitchen display |
| TC-UC05-02 | UC05 | NFR05 | Login throttling | 6 wrong passwords | Throttle message after 5 |
| TC-UC05-03 | UC05 | BR60 | Deactivated staff | Admin deactivates logged-in waiter; waiter refreshes | Waiter logged out and refused |
| TC-UC06-01 | UC06 | FR07 | Update profile | Change name; save | Name updated |
| TC-UC07-01 | UC07 | FR31, BR56 | Scan logged out | Open QR URL logged out; log in | QR login page, then table ordering menu with homepage link |
| TC-UC07-02 | UC07 | NFR04 | Tampered QR | Change signature in URL | 403 page |
| TC-UC07-03 | UC07 | BR02 | Reserved table, other user | Scan reserved table as non-holder | Reserved notice with Call waiter |
| TC-UC07-04 | UC07 | FR69, BR03 | Holder scan in window | Scan as holder 10 min before | Reservation seated; tables Occupied; ordering opens |
| TC-UC08-01 | UC08 | FR35, BR16 | Required add-on missing | Add burger without sauce | Error; line not added |
| TC-UC08-02 | UC08 | BR58 | QR ordering paused | Admin pauses; customer taps Add to cart | Paused message |
| TC-UC09-01 | UC09 | FR43, BR49 | Visitor uses chatbot | Logged out, ask 'What is vegetarian?' | Answer lists vegetarian items from live menu |
| TC-UC09-02 | UC09 | FR44, BR47 | Meal builder outside table | Submit budget $60, 2 people | Suggestions within budget; scan prompt instead of Add to cart |
| TC-UC09-03 | UC09 | BR49 | AI disabled | Admin sets ai_enabled off | Chat widget and meal builder links hidden |
| TC-UC10-01 | UC10 | FR46, FR47, BR25 | Stripe test payment | Checkout; pay with Stripe test card 4242… | Return page verifies; order paid; lines on KDS |
| TC-UC10-02 | UC10 | BR25 | Redirect without payment | Open success URL with unpaid session_id | Order stays pending_payment |
| TC-UC10-03 | UC10 | BR09 | 5× buffer | Limit 20, sold 12; order 3 | Blocked: 'You can order up to 1' |
| TC-UC10-04 | UC10 | BR54 | Stock conflict | Two paid orders exceed exact limit | Second order paid with conflict flag and alert |
| TC-UC10-05 | UC10 | FR48 | Choose cash | Checkout; choose cash | Waiting screen; floor cash list shows order |
| TC-UC11-01 | UC11 | FR38 | Live status | Kitchen marks ready | Customer timeline updates without refresh |
| TC-UC11-02 | UC11 | FR53 | Receipt PDF | Download receipt | PDF with lines, GST, method |
| TC-UC12-01 | UC12 | FR41 | Cancel unpaid | Cancel pending order | Order cancelled |
| TC-UC12-02 | UC12 | BR29 | No cancel when paid | Open paid order | No cancel button |
| TC-UC13-01 | UC13 | FR62 | Request reservation | Verified customer books valid slot | Status requested; email queued |
| TC-UC13-02 | UC13 | BR35 | Closed weekday | Open calendar | Mondays not selectable |
| TC-UC13-03 | UC13 | BR58 | Online bookings paused | Admin pauses; open wizard | Call-us message with venue phone |
| TC-UC14-01 | UC14 | BR36 | Change locked < 2 h | Edit time 1 h before | Fields locked with call-us message |
| TC-UC14-02 | UC14 | FR67, BR38 | Cancel warning | Tap cancel | Warning dialog; confirm → cancelled, late flag if < 2 h |
| TC-UC15-01 | UC15 | FR76 | Submit feedback | Served order → rate 5/4 | Feedback saved; form hidden |
| TC-UC16-01 | UC16 | FR16 | Floor state | Open floor view | Tables with correct status colours and lists |
| TC-UC17-01 | UC17 | FR17 | Seat walk-in | Seat two tables | Both Occupied; visits opened |
| TC-UC18-01 | UC18 | BR06 | Clear with active order | Clear table with preparing order | Confirmation required |
| TC-UC18-02 | UC18 | BR55 | Reservation completes | Clear last table of seated booking | Reservation completed |
| TC-UC19-01 | UC19 | FR42, BR53 | Staff order cash | Take order; record cash | Order paid; created_by staff; lines on KDS |
| TC-UC20-01 | UC20 | BR23 | Adjustment needs note | Enter adjustment without note | Validation error |
| TC-UC20-02 | UC20 | BR22 | Cash rounding | Due $23.47 | Rounded $23.45; change correct |
| TC-UC21-01 | UC21 | FR60 | Mark served | Serve kitchen lines | Lines served; order served when all served |
| TC-UC22-01 | UC22 | FR63 | Approve request | Approve pending booking | Confirmed; email queued |
| TC-UC22-02 | UC22 | BR33 | Over capacity | Approve beyond max covers | Blocked |
| TC-UC23-01 | UC23 | FR64 | Phone booking guest | Create with guest name/mobile | Confirmed booking |
| TC-UC24-01 | UC24 | FR65, BR04 | Assign tables | Assign two tables to party of 6 | Visits created; Reserved at T–30 |
| TC-UC24-02 | UC24 | FR95, BR64 | Unassign | Unassign reserved table | Visit closed; table Available |
| TC-UC25-01 | UC25 | FR70, BR39 | Mark no-show | After grace confirm no-show | Status no_show; badge Flagged |
| TC-UC26-01 | UC26 | FR51 | Kitchen requests refund | Request refund for 1 line | Refund requested; admin alert |
| TC-UC28-01 | UC28 | FR56, FR58 | Start and ready | Start then Ready | Status preparing then ready; floor alert |
| TC-UC28-02 | UC28 | FR59 | Adjust ETA | +10 min | Customer ETA updates |
| TC-UC29-01 | UC29 | FR29, BR14 | Bar toggles bar item | Bar marks drink sold out | Menu shows sold out; kitchen items not listed for bar |
| TC-UC30-01 | UC30 | FR02 | Create staff | Admin creates waitstaff | Account active with role |
| TC-UC30-02 | UC30 | FR08 | Last admin protection | Deactivate last admin | Blocked |
| TC-UC31-01 | UC31 | FR14 | Regenerate QR | Regenerate then scan old QR | Old QR rejected |
| TC-UC32-01 | UC32 | FR23, FR26 | Create item with sizes | Create item with 2 sizes | Item visible with 'from' price |
| TC-UC32-02 | UC32 | FR24 | Feature toggle | Mark item featured | Item appears on homepage menu |
| TC-UC33-01 | UC33 | FR52, BR13 | Stripe partial refund | Refund 1 line, return to stock unticked | Refund completed; payment_status partially_refunded; stock unchanged |
| TC-UC33-02 | UC33 | FR52 | Manual refund | Method manual with reference | Refund completed with reference |
| TC-UC34-01 | UC34 | FR78 | Hide feedback | Hide with reason | Excluded from public average |
| TC-UC35-01 | UC35 | FR81 | Dashboard widgets | Open dashboard | All 15 widgets render with seeded data |
| TC-UC35-02 | UC35 | FR88 | Export CSV | Export sales report | CSV downloaded |
| TC-UC36-01 | UC36 | FR90 | Filter audit log | Filter by refund_complete | Matching entries only |
| TC-UC37-01 | UC37 | FR91 | Update venue phone | Change phone | Footer and call-us messages show new phone |
| TC-UC37-02 | UC37 | FR100 | Deactivate slot | Deactivate 18:00 | Slot not offered online |
| TC-UC38-01 | UC38 | FR10 | Clear no-show | Clear with reason | Badge recalculated |
| TC-UC39-01 | UC39 | FR47 | Reconcile job | Pay via Stripe, close tab; wait for job | Order marked paid |
| TC-UC39-02 | UC39 | FR50, BR26 | Unpaid cleanup | Run cleanup at closing | Pending orders cancelled |
| TC-UC40-01 | UC40 | FR93 | Staff cancels unpaid | Cancel cash-waiting order with reason | Order cancelled; audited |
| TC-UC41-01 | UC41 | FR94 | Resolve conflict | Choose 'will make it' | Flag cleared; audited |
