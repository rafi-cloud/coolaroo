# 1. Introduction

## 1.1 Purpose
Defines the complete design of Coolaroo RMS so it can be implemented, tested and assessed without further interpretation.

## 1.2 Scope
**In scope:** public website with menu, reviews and reservations; QR table ordering with Stripe (test mode) and cash payment; kitchen and bar displays; waitstaff floor view; staff-taken orders; reservations with staff approval, table assignment and no-show tracking; AI menu chatbot and meal builder; feedback; admin dashboard, reports, settings and audit log; local (XAMPP) and VPS deployment.

**Out of scope:** real payment processing (Stripe test mode only), SMS, marketing email, inventory beyond daily limits, split bills, tips, surcharges, meal bundles, happy-hour pricing, waitlist, deposits, blackout dates, cash reconciliation, customer anonymisation.

## 1.3 Glossary
| Term | Meaning |
|---|---|
| Station / destination | Kitchen or Bar, where an item is prepared |
| KDS | Kitchen/bar display screen |
| Visit | A table's occupancy period or reservation assignment (table `visit`) |
| Table context | The customer scanned a table QR and the cart is bound to that table |
| Holder | Customer account that owns a reservation |
| T–30 | 30 minutes before a reservation time |
| Buffer | Multiplier (default 5) used for the QR checkout stock check |
| Special | Menu item with an active sale price |
| Staff-taken order | Order entered by waitstaff for a table |
| GitHub Models | AI provider giving free access to OpenAI models via an OpenAI-compatible API |

## 1.4 References
| Reference | Location |
|---|---|
| Lecturer-approved ERD (revised) | `docs/erd/Coolaroo_RMS_ERD_v2.drawio` |
| Report guideline (A6) | Project management, construction, system testing, supporting docs |
| Homepage design | `public/index.html` prototype, `public/css/style.css` |
| Admin layout and colour reference | `dashboard.html`, `dashboard.css` |
