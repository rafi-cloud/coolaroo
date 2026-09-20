import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

const TIMELINE_STEPS = ['paid', 'preparing', 'ready', 'served'];

function applyStatus(page, status, lastStatus) {
    page.querySelectorAll('[data-step]').forEach((step) => {
        const stepIndex = TIMELINE_STEPS.indexOf(step.dataset.step);
        const currentIndex = TIMELINE_STEPS.indexOf(status);
        step.classList.toggle('is-done', currentIndex !== -1 && stepIndex <= currentIndex);
        step.classList.toggle('is-current', stepIndex === currentIndex);
    });

    const badge = page.querySelector('[data-testid="order-status-badge"]');
    if (badge) {
        badge.className = 'status-pill status-pill-' + status;
    }

    const alertBox = page.querySelector('[data-ready-alert]');
    if (alertBox && status === 'ready' && lastStatus !== 'ready') {
        alertBox.hidden = false;
    }
}

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

    let lastStatus = page.dataset.status;
    let timer = null;

    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        applyStatus(page, state.status, lastStatus);
        applyEta(page, 'kitchen', state.kitchen_eta);
        applyEta(page, 'bar', state.bar_eta);
        lastStatus = state.status;

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
