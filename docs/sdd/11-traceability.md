# 11. Traceability Matrix

| FR | Requirement | Use case | Screens | Tables | Tests |
|---|---|---|---|---|---|
| FR01 | Customer Registration | UC04 | S07 | customer | TC-UC04-01 |
| FR02 | Create Staff Account | UC30 | S39 | staff, role | TC-UC30-01 |
| FR03 | Login and Logout | UC05 | S07, S21 | customer, staff | TC-UC05-01 |
| FR04 | Role-Based Access Control | All | All | role, staff | TC-UC07-02 |
| FR05 | Reset Password | UC05 | S07 | customer, staff | TC-UC05-02 |
| FR06 | Verify Email | UC04 | S07 | customer | TC-UC04-01, TC-UC04-02 |
| FR07 | Update Profile | UC06 | S20 | customer, staff | TC-UC06-01 |
| FR08 | Deactivate Staff Account | UC30 | S39 | staff, historical_data_management | TC-UC30-02 |
| FR09 | View Customer Trust Profile | UC22, UC38 | S28, S38 | customer, reservation, orders | TC-UC22-01, TC-UC22-02, TC-UC38-01 |
| FR10 | Clear No-show Flag | UC38 | S38 | reservation, audit_log | TC-UC38-01 |
| FR11 | Create Table | UC31 | S37 | restaurant_table | TC-UC31-01 |
| FR12 | Edit Table | UC31 | S37 | restaurant_table, visit | TC-UC31-01 |
| FR13 | Deactivate or Reactivate Table | UC31 | S37, S06 | restaurant_table | TC-UC31-01 |
| FR14 | Generate Signed Table QR | UC31 | S37 | restaurant_table | TC-UC31-01 |
| FR15 | Download or Print QR | UC31 | S37 | restaurant_table | TC-UC31-01 |
| FR16 | View Floor Status | UC16 | S22 | restaurant_table, visit, orders, reservation | TC-UC16-01 |
| FR17 | Seat Walk-in | UC17 | S23 | restaurant_table, visit | TC-UC17-01 |
| FR18 | Clear Table | UC18 | S23 | restaurant_table, visit | TC-UC18-01, TC-UC18-02 |
| FR19 | Auto-clear Idle Table | UC39 | — | restaurant_table, visit | TC-UC39-01, TC-UC39-02 |
| FR20 | Override Table Status | UC31 | S37 | restaurant_table, audit_log | TC-UC31-01 |
| FR21 | Record Table Status Change | UC17, UC18 | — | visit, audit_log | TC-UC17-01, TC-UC18-01, TC-UC18-02 |
| FR22 | Manage Categories | UC32 | S36 | menu_category | TC-UC32-01, TC-UC32-02 |
| FR23 | Add Menu Item | UC32 | S35 | menu_item, menu_item_size, menu_item_allergen, menu_item_dietary_tag | TC-UC32-01 |
| FR24 | Edit Menu Item | UC32 | S35 | menu_item | TC-UC32-02 |
| FR25 | Archive Menu Item | UC32 | S35 | menu_item, historical_data_management | TC-UC32-01, TC-UC32-02 |
| FR26 | Manage Sizes and Prices | UC32 | S35 | menu_item_size | TC-UC32-01 |
| FR27 | Set Sale Price | UC32 | S35 | menu_item_size | TC-UC32-01, TC-UC32-02 |
| FR28 | Manage Add-on Groups | UC32 | S35 | add_on_group, add_on_option | TC-UC32-01, TC-UC32-02 |
| FR29 | Toggle Availability | UC29 | S31 | menu_item, add_on_option | TC-UC29-01 |
| FR30 | Set Daily Limit | UC32 | S35 | menu_item | TC-UC32-01, TC-UC32-02 |
| FR31 | Scan Table QR | UC07 | S04, S05, S06, S07 | restaurant_table, reservation, visit | TC-UC07-01 |
| FR32 | Browse Menu | UC01 | S01, S02, S04 | menu_category, menu_item, menu_item_size | TC-UC01-01 |
| FR33 | Filter by Allergen and Dietary Tag | UC01 | S02, S04 | menu_item_allergen, menu_item_dietary_tag | TC-UC01-02 |
| FR34 | View Nutrition Information | UC01 | S03 | menu_item | TC-UC01-01, TC-UC01-02 |
| FR35 | Add Item to Cart | UC08 | S03, S04, S16 | — (session cart) | TC-UC08-01 |
| FR36 | Edit Cart | UC08 | S08 | — (session cart) | TC-UC08-01, TC-UC08-02 |
| FR37 | Checkout | UC10 | S08, S09 | orders, order_item, order_status_history | TC-UC10-01, TC-UC10-02 |
| FR38 | Track Order Status | UC11 | S12 | orders, order_item, order_status_history | TC-UC11-01 |
| FR39 | View Order History | UC11 | S13 | orders | TC-UC11-01, TC-UC11-02 |
| FR40 | Call Waiter | UC02 | S04, S07 | — (cache) | TC-UC02-01 |
| FR41 | Cancel Unpaid Order | UC12 | S12 | orders | TC-UC12-01 |
| FR42 | Take Order for Table | UC19 | S24, S25, S26 | orders, order_item, payment | TC-UC19-01 |
| FR43 | AI Menu Chatbot | UC09 | S17 | audit_log | TC-UC09-01 |
| FR44 | AI Meal Builder | UC09 | S16 | audit_log | TC-UC09-02 |
| FR45 | Monitor AI Usage | UC35 | S41 | audit_log | TC-UC35-01, TC-UC35-02 |
| FR46 | Pay by Stripe Checkout | UC10 | S09 | payment | TC-UC10-01 |
| FR47 | Confirm Stripe Payment by Verification | UC10, UC39 | S10, S12 | payment, orders, order_item, menu_item, visit | TC-UC10-01, TC-UC39-01 |
| FR48 | Request Cash Payment | UC10 | S09, S11 | payment | TC-UC10-05 |
| FR49 | Record Cash Payment | UC20 | S25 | payment, orders, order_item, menu_item | TC-UC20-01, TC-UC20-02 |
| FR50 | Clean Up Unpaid Orders | UC39 | — | orders, payment | TC-UC39-02 |
| FR51 | Request Refund | UC26 | S29 | refund | TC-UC26-01 |
| FR52 | Issue Refund | UC33 | S34 | refund, order_item, orders, menu_item | TC-UC33-01, TC-UC33-02 |
| FR53 | Generate Receipt | UC11 | S14 | orders, order_item, payment, refund | TC-UC11-02 |
| FR54 | Cash Reconciliation | — | — | — | — |
| FR55 | Route Order Lines to Stations | UC10 | S30 | order_item, orders | TC-UC10-01, TC-UC10-02 |
| FR56 | View Station Queue | UC28 | S30 | orders, order_item | TC-UC28-01 |
| FR57 | Filter Station Queue | UC28 | S30 | order_item | TC-UC28-01, TC-UC28-02 |
| FR58 | Update Line Status | UC28 | S30 | order_item, orders, order_status_history | TC-UC28-01 |
| FR59 | Adjust Station ETA | UC28 | S30 | orders | TC-UC28-02 |
| FR60 | Mark Served | UC21 | S22 | order_item, orders | TC-UC21-01 |
| FR61 | View Available Times | UC13 | S18 | slot_capacity, reservation, setting | TC-UC13-01, TC-UC13-02 |
| FR62 | Submit Reservation Request | UC13 | S18 | reservation | TC-UC13-01 |
| FR63 | Review Reservation Request | UC22 | S27, S28 | reservation | TC-UC22-01 |
| FR64 | Create Staff Reservation | UC23 | S27 | reservation | TC-UC23-01 |
| FR65 | Assign Tables to Reservation | UC24 | S27 | visit, restaurant_table | TC-UC24-01 |
| FR66 | Update Reservation | UC14 | S19, S27 | reservation, visit | TC-UC14-01, TC-UC14-02 |
| FR67 | Cancel Reservation | UC14 | S19, S27 | reservation, visit | TC-UC14-02 |
| FR68 | View Reservation List | UC22 | S27 | reservation, visit | TC-UC22-01, TC-UC22-02 |
| FR69 | Seat Reservation | UC25, UC07 | S27, S04 | reservation, visit, restaurant_table | TC-UC07-04 |
| FR70 | Mark No-show | UC25 | S27 | reservation, visit | TC-UC25-01 |
| FR71 | Reservation Floor Alerts | UC39, UC16 | S22 | reservation, visit, restaurant_table | TC-UC39-01, TC-UC39-02, TC-UC16-01 |
| FR72 | View My Reservations | UC14 | S19 | reservation | TC-UC14-01, TC-UC14-02 |
| FR73 | Staff Real-time Alerts | UC16 | S22, S30, S32 | — | TC-UC16-01 |
| FR74 | Send Reservation Emails | UC13, UC14, UC22 | — | reservation | TC-UC13-01, TC-UC13-02, TC-UC14-01, TC-UC14-02, TC-UC22-01, TC-UC22-02 |
| FR75 | Send Reservation Reminder | UC39 | — | reservation | TC-UC39-01, TC-UC39-02 |
| FR76 | Submit Feedback | UC15 | S15 | feedback | TC-UC15-01 |
| FR77 | View and Reply to Feedback | UC34 | S40 | feedback | TC-UC34-01 |
| FR78 | Hide Abusive Feedback | UC34 | S40 | feedback | TC-UC34-01 |
| FR79 | Feature Review | UC34 | S40 | feedback | TC-UC34-01 |
| FR80 | Public Ratings Section | UC03 | S01 | feedback | TC-UC03-01 |
| FR81 | Admin Live Dashboard | UC35 | S32 | orders, payment, refund, reservation, feedback, menu_item | TC-UC35-01 |
| FR82 | Sales Report | UC35 | S41 | orders, payment, refund | TC-UC35-01, TC-UC35-02 |
| FR83 | Item and Category Report | UC35 | S41 | order_item, menu_item, audit_log | TC-UC35-01, TC-UC35-02 |
| FR84 | Operations Report | UC35 | S41 | orders, order_item, visit | TC-UC35-01, TC-UC35-02 |
| FR85 | Reservation Report | UC35 | S41 | reservation, visit | TC-UC35-01, TC-UC35-02 |
| FR86 | Feedback Report | UC35 | S41 | feedback | TC-UC35-01, TC-UC35-02 |
| FR87 | Staff Activity Report | UC35 | S41 | audit_log, payment, refund | TC-UC35-01, TC-UC35-02 |
| FR88 | Export Report | UC35 | S41 | — | TC-UC35-02 |
| FR89 | Record Audit Event | All | — | audit_log | Covered by feature tests |
| FR90 | Search Audit Log | UC36 | S42 | audit_log | TC-UC36-01 |
| FR91 | Manage Venue Settings | UC37 | S43 | setting | TC-UC37-01 |
| FR92 | Manage Blackout Dates | — | — | — | — |
| FR93 | Cancel Unpaid Order (Staff) | UC40 | S22, S33 | orders, payment, audit_log | TC-UC40-01 |
| FR94 | Resolve Stock Conflict | UC41 | S30, S33 | orders, refund, audit_log | TC-UC41-01 |
| FR95 | Unassign or Reassign Tables | UC24 | S27 | visit, restaurant_table | TC-UC24-02 |
| FR96 | Pause QR Ordering | UC37 | S43, S22 | setting | TC-UC37-01, TC-UC37-02 |
| FR97 | Pause Online Reservations | UC37 | S43, S18 | setting | TC-UC37-01, TC-UC37-02 |
| FR98 | Toggle AI Assistant | UC37 | S43 | setting | TC-UC37-01, TC-UC37-02 |
| FR99 | View Archived Records | UC36 | S42 | historical_data_management | TC-UC36-01 |
| FR100 | Manage Time Slots | UC37 | S43 | slot_capacity | TC-UC37-02 |
| FR101 | Manage Allergens and Dietary Tags | UC32 | S36 | allergen, dietary_tag | TC-UC32-01, TC-UC32-02 |
| FR102 | Search and View Orders | UC33 | S33 | orders, order_item, payment, refund, order_status_history | TC-UC33-01, TC-UC33-02 |
| FR103 | View Customer List | UC38 | S38 | customer | TC-UC38-01 |
