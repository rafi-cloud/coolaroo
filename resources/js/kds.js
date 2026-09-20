import { listenOn, onReconnect, privateChannel, refreshFrom } from './realtime';

/** S30, FR56-FR59, BR54. One station per page; the channel comes from the DOM. */
export function initKds() {
    const page = document.querySelector('[data-kds-page]');
    if (!page) {
        return;
    }

    const refresh = () => refreshFrom(page.dataset.stateUrl, (state) => {
        page.dispatchEvent(new CustomEvent('kds:state', { detail: state }));
    });

    listenOn(privateChannel(`station.${page.dataset.destination}`), [
        'OrderPaid',
        'OrderLinesUpdated',
        'StockConflictDetected',
    ], refresh);

    onReconnect(refresh);
}
