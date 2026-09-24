import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

const ALERT_EVENTS = ['WaiterCalled', 'ReservationAlert'];
const MAX_ALERTS = 5;

/**
 * FR73: WaiterCalled and ReservationAlert have no query behind them (07.8:
 * "not stored"), so they render straight from the live payload, capped, and
 * are gone on reload -- unlike the four data-backed lists refresh() rebuilds.
 */
function pushAlert(page, event, payload) {
    const list = page.querySelector('[data-floor-alerts]');
    if (!list) {
        return;
    }

    const item = document.createElement('li');
    item.className = 'floor-alert-item';
    item.textContent = event === 'WaiterCalled'
        ? `Table ${payload.table_number}: waiter called`
        : `Reservation alert: ${payload.kind.replace(/_/g, ' ')}`;

    list.prepend(item);

    const emptyRow = list.querySelector('[data-alerts-empty]');
    if (emptyRow) {
        emptyRow.hidden = true;
    }

    // cap the ephemeral ones only; the derived rows mirror real state
    const ephemeral = list.querySelectorAll('.floor-alert-item:not([data-derived-alert])');
    for (let i = MAX_ALERTS; i < ephemeral.length; i += 1) {
        ephemeral[i].remove();
    }
}

/** S22, FR16, FR73. Every floor event refreshes the data-backed lists. */
export function initFloor() {
    const page = document.querySelector('[data-floor-page]');
    if (!page) {
        return;
    }

    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        page.dispatchEvent(new CustomEvent('floor:state', { detail: state }));
    });

    const channel = privateChannel('floor');

    listenOn(channel, [
        'OrderStatusChanged',
        'CashPaymentRequested',
        'WaiterCalled',
        'TableStatusChanged',
        'ReservationAlert',
        'SettingSwitched',
    ], refresh);

    ALERT_EVENTS.forEach((event) => listenOn(channel, [event], (payload) => pushAlert(page, event, payload)));

    onReconnect(refresh);
}
