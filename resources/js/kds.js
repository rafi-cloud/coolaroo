import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

/** S30, FR56-FR59, BR54. One station per page; the channel comes from the DOM. */
export function initKds() {
    const page = document.querySelector('[data-kds-page]');
    if (!page) {
        return;
    }

    const rendered = page.dataset.signature;
    let timer = null;
    let reloading = false;

    // A ticket is a form per line, a per-station ETA control and a stock
    // conflict banner. Rebuilding that here would be a second copy of the
    // Blade, so the board re-renders itself whenever the station's state
    // stops matching the one this page was drawn from.
    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        if (reloading || state.signature === rendered) {
            return;
        }

        reloading = true;
        clearInterval(timer);
        window.location.reload();
    });

    // Reverb is a separate process and the broadcast itself is queued, so the
    // poll is the floor under the socket rather than a duplicate of it.
    timer = setInterval(refresh, 10000);

    listenOn(privateChannel(`station.${page.dataset.destination}`), [
        'OrderPaid',
        'OrderLinesUpdated',
        'StockConflictDetected',
    ], refresh);

    onReconnect(refresh);

    // Ticking a dish submits its own form. The box is disabled immediately so a
    // second tick cannot post twice while the round trip is in flight; the
    // re-rendered ticket comes back with the line already ready.
    page.addEventListener('change', (event) => {
        const box = event.target.closest('[data-line-ready]');
        if (!box || !box.checked) {
            return;
        }

        box.disabled = true;
        box.form.submit();
    });
}
