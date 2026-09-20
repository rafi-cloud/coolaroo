import { listenOn, onReconnect, publicChannel } from './realtime';

function applyAvailability({ entity, id, is_available: isAvailable }) {
    document.querySelectorAll(`[data-${entity.replace('_', '-')}-id="${id}"]`).forEach((node) => {
        node.classList.toggle('is-sold-out', !isAvailable);

        node.querySelectorAll('button, input').forEach((control) => {
            control.disabled = !isAvailable;
        });
    });
}

function applySetting({ key, value }) {
    if (key !== 'qr_ordering_enabled') {
        return;
    }

    const banner = document.querySelector('[data-ordering-paused]');
    if (banner) {
        banner.hidden = value === '1';
    }
}

/**
 * FR29, BR58, 07.8. The public menu has no per-visitor state endpoint, so this
 * is the one module that renders from the payload itself.
 */
export function initMenuLive() {
    if (!document.querySelector('[data-menu-live]')) {
        return;
    }

    const channel = publicChannel('menu');

    listenOn(channel, ['MenuAvailabilityChanged'], applyAvailability);
    listenOn(channel, ['SettingSwitched'], applySetting);

    onReconnect(() => window.location.reload());
}
