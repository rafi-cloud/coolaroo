import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

/** S32, FR81. Counters and the "needs attention" list. */
export function initDashboard() {
    const page = document.querySelector('[data-dashboard-page]');
    if (!page) {
        return;
    }

    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        page.dispatchEvent(new CustomEvent('dashboard:state', { detail: state }));
    });

    listenOn(privateChannel('admin'), [
        'OrderPaid',
        'StockConflictDetected',
        'RefundRequested',
        'TableStatusChanged',
        'ReservationAlert',
    ], refresh);

    onReconnect(refresh);
}
