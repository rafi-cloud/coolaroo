import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

/** S22, FR16, FR73. Every floor alert is a nudge to re-fetch /staff/floor/state. */
export function initFloor() {
    const page = document.querySelector('[data-floor-page]');
    if (!page) {
        return;
    }

    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        page.dispatchEvent(new CustomEvent('floor:state', { detail: state }));
    });

    listenOn(privateChannel('floor'), [
        'OrderStatusChanged',
        'CashPaymentRequested',
        'WaiterCalled',
        'TableStatusChanged',
        'ReservationAlert',
        'SettingSwitched',
    ], refresh);

    onReconnect(refresh);
}
