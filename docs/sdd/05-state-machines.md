# 5. State Machines

Each status is a PHP backed enum with `canTransitionTo()`. All changes go through one service method that checks guards, sets timestamps, writes `order_status_history` / `audit_log`, and broadcasts after commit. Invalid transitions throw `InvalidTransitionException` (HTTP 409).

## 5.1 orders.status (fulfilment)
```mermaid
stateDiagram-v2
  [*] --> pending_payment : checkout
  pending_payment --> paid : Stripe verified / cash recorded
  pending_payment --> cancelled : customer, staff or daily cleanup
  paid --> preparing : any line started
  preparing --> ready : all active lines ready or served
  ready --> served : all active lines served
  paid --> cancelled : all lines refunded before start
  served --> [*]
  cancelled --> [*]
```
| From → To | Trigger | Actor | Guard | Side effects |
|---|---|---|---|---|
| pending_payment → paid | Stripe session paid / cash recorded | System, Waitstaff | One succeeded payment | Exact stock deduction (conflict flag if fails), lines pending on stations, ETAs, table Occupied + visit if needed, paid_at, broadcast |
| pending_payment → cancelled | Cancel / cleanup | Customer, Waitstaff, Admin, Scheduler | No succeeded payment | Expire Stripe session, cancelled_at |
| paid → preparing | Line started | Derived | — | started_at |
| preparing → ready | Lines ready | Derived | — | ready_at, floor alert |
| ready → served | Lines served | Derived | — | served_at, feedback unlocked (QR orders) |
| paid → cancelled | Full refund completed | System | No line preparing | Lines cancelled |

## 5.2 orders.payment_status
```mermaid
stateDiagram-v2
  [*] --> unpaid
  unpaid --> paid : payment succeeded
  paid --> partially_refunded : refund completed, less than amount
  partially_refunded --> partially_refunded : another partial refund
  paid --> refunded : refunds = amount
  partially_refunded --> refunded : refunds = amount
```

## 5.3 order_item.status (station line)
```mermaid
stateDiagram-v2
  [*] --> pending : order paid
  pending --> preparing : Start (station)
  preparing --> ready : Ready (station)
  ready --> served : Served (waitstaff)
  pending --> cancelled : refunded_qty = quantity
  preparing --> cancelled : refunded_qty = quantity
```
Lines of the same order and destination move together when staff press Start / Ready / Served.

## 5.4 payment.status (one row per attempt)
```mermaid
stateDiagram-v2
  [*] --> pending : session created / cash chosen
  pending --> succeeded : session retrieved paid / cash recorded
  pending --> failed : Stripe reports failure
  pending --> expired : method switched / order cancelled / session expired
```
Stripe verification sequence:
```mermaid
sequenceDiagram
  participant C as Customer
  participant A as Laravel
  participant S as Stripe (test)
  C->>A: Pay by card
  A->>S: Create Checkout Session (order_id, payment_id)
  A-->>C: Redirect to Stripe
  C->>S: Pay
  S-->>C: Redirect to /payment/success?session_id
  C->>A: GET success
  A->>S: Retrieve session
  S-->>A: payment_status = paid
  A->>A: PaymentService::markPaid (transaction)
  A-->>C: Order status page
  Note over A,S: If customer never returns: "Check payment status" button or payments:reconcile job retrieves the session
```

## 5.5 refund.status
```mermaid
stateDiagram-v2
  [*] --> requested : staff request
  [*] --> processing : admin-created Stripe refund
  requested --> processing : approve (Stripe)
  requested --> completed : approve (cash / manual)
  requested --> rejected : reject with reason
  processing --> completed : Stripe refund succeeded
  processing --> failed : Stripe error
  failed --> processing : retry
```
On completion: refunded_qty updated, payment_status recalculated, stock returned only if return_to_stock = 1.

## 5.6 restaurant_table.status
```mermaid
stateDiagram-v2
  [*] --> available
  available --> occupied : first paid order / staff seat
  available --> reserved : scheduler at T-30 (assigned booking)
  reserved --> occupied : holder scan in window / staff open
  reserved --> available : booking cancelled, no-show or tables unassigned
  occupied --> available : staff clear / auto-clear
```
Admin override: any → any with reason. Inactive tables are excluded from all transitions.

## 5.7 visit lifecycle
```mermaid
stateDiagram-v2
  [*] --> assigned : tables assigned to reservation (opened_at NULL)
  [*] --> open : walk-in seat / first paid order
  assigned --> open : seated
  assigned --> closed : unassigned, cancelled or no-show
  open --> closed : cleared (staff_clear / auto_clear)
```

## 5.8 reservation.status
```mermaid
stateDiagram-v2
  [*] --> requested : customer request
  [*] --> confirmed : staff phone booking
  requested --> confirmed : approve
  requested --> declined : decline
  requested --> expired : unreviewed 1 h before
  requested --> cancelled : cancel
  confirmed --> requested : customer changes date/time/party (2 h or more before)
  confirmed --> cancelled : cancel
  confirmed --> seated : holder scan / staff open
  confirmed --> no_show : staff confirm after grace
  seated --> completed : all visits closed
```
