/**
 * The refund modals on My orders. Scoped to that page so it cannot collide
 * with the menu page, which carries its own inline modal script.
 */
export function initRefundModals() {
    const page = document.querySelector('[data-testid="orders-page"]');
    if (!page) {
        return;
    }

    const open = (modal) => {
        if (!modal) {
            return;
        }
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        modal.querySelector('select, input, textarea')?.focus();
    };

    const close = (modal) => {
        if (!modal) {
            return;
        }
        modal.classList.remove('open');
        document.body.style.overflow = '';
    };

    page.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-open-modal]');
        if (opener) {
            e.preventDefault();
            open(document.getElementById(opener.getAttribute('data-open-modal')));

            return;
        }

        const closer = e.target.closest('[data-close-modal]');
        if (closer) {
            e.preventDefault();
            close(document.getElementById(closer.getAttribute('data-close-modal')));

            return;
        }

        // A click on the scrim itself, outside the card, closes it.
        if (e.target.classList.contains('modal-scrim')) {
            close(e.target);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            page.querySelectorAll('.modal-scrim.open').forEach(close);
        }
    });

    // Validation sends the customer back here with the modal already marked
    // open in Blade, so the body scroll lock has to match on load.
    if (page.querySelector('.modal-scrim.open')) {
        document.body.style.overflow = 'hidden';
    }
}
