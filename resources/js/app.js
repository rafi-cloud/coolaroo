//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import { initDashboard } from './dashboard';
import { initFloor } from './floor';
import { initKds } from './kds';
import { initMenuLive } from './menu';
import { initOrderStatus } from './order-status';
import { initRefundModals } from './refund-modal';

document.addEventListener('DOMContentLoaded', () => {
    initOrderStatus();
    initFloor();
    initKds();
    initDashboard();
    initMenuLive();
    initRefundModals();
});
