import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

/** FR59: "customer ETA updates live." range is {from, to} strings, or null once the station is done. */
function applyEta(page, key, range) {
    const node = page.querySelector(`[data-testid="order-eta-${key}"]`);
    if (!node) {
        return;
    }

    node.hidden = !range;
    if (range) {
        node.textContent = `${key === 'kitchen' ? 'Kitchen' : 'Bar'}: ready between ${range.from} and ${range.to}`;
    }
}

export function initOrderStatus() {
    const page = document.querySelector('[data-order-page]');
    if (!page) {
        return;
    }

    const renderedStatus = page.dataset.status;
    const renderedPaymentStatus = page.dataset.paymentStatus;
    let timer = null;
    let reloading = false;

    // The heading, the pay box, the ready alert and the feedback form are all
    // status-dependent Blade. Re-rendering them here would be a second copy of
    // those rules, so a state change re-renders the whole page instead.
    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        if (reloading) {
            return;
        }

        if (state.status !== renderedStatus || state.payment_status !== renderedPaymentStatus) {
            reloading = true;
            clearInterval(timer);
            window.location.reload();

            return;
        }

        applyEta(page, 'kitchen', state.kitchen_eta);
        applyEta(page, 'bar', state.bar_eta);

        const etaGrid = page.querySelector('[data-eta-grid]');
        if (etaGrid) {
            etaGrid.hidden = !state.kitchen_eta && !state.bar_eta;
        }

        if (state.status === 'served' || state.status === 'cancelled') {
            clearInterval(timer);
        }
    });

    // The poll stays as the floor under Echo: Reverb is a separate process and
    // may not be running, and NFR09's reconnect rule assumes the socket can
    // drop entirely.
    timer = setInterval(refresh, 8000);

    listenOn(privateChannel(`order.${page.dataset.orderId}`), [
        'OrderStatusChanged',
        'OrderLinesUpdated',
    ], refresh);

    onReconnect(refresh);
}
