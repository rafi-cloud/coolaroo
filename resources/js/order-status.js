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

export function initOrderStatus() {
    const page = document.querySelector('[data-order-page]');
    if (!page) {
        return;
    }

    const stateUrl = page.dataset.stateUrl;
    let lastStatus = page.dataset.status;

    const timer = setInterval(() => {
        fetch(stateUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((state) => {
                if (!state) {
                    return;
                }

                applyStatus(page, state.status, lastStatus);
                lastStatus = state.status;

                if (state.status === 'served' || state.status === 'cancelled') {
                    clearInterval(timer);
                }
            })
            .catch(() => {});
    }, 8000);
}
