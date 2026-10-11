import './bootstrap';
import Chart from './charts.js';
import Swal from 'sweetalert2';

window.Chart = Chart;
window.Swal = Swal;

const setMenuState = (open) => {
    document.documentElement.dataset.mobileMenuOpen = open ? 'true' : 'false';
};

window.portalModal = {
    show(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    },
    hide(modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    },
};

window.portalToast = {
    show(message) {
        let container = document.getElementById('portal-toast-container');

        if (!container) {
            container = document.createElement('div');
            container.id = 'portal-toast-container';
            container.className = 'fixed bottom-5 right-5 z-[100] flex w-[min(24rem,calc(100vw-2.5rem))] flex-col gap-3';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.append(container);
        }

        const toast = document.createElement('div');
        toast.className = 'flex items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-xl ring-1 ring-black/5';
        toast.setAttribute('role', 'status');

        const icon = document.createElement('i');
        icon.className = 'fas fa-check-circle mt-0.5 text-emerald-600';
        icon.setAttribute('aria-hidden', 'true');

        const content = document.createElement('p');
        content.className = 'flex-1 leading-5';
        content.textContent = message;

        const close = document.createElement('button');
        close.type = 'button';
        close.className = '-mr-1 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700';
        close.setAttribute('aria-label', 'ปิดข้อความแจ้งเตือน');
        close.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';

        const dismiss = () => toast.remove();
        close.addEventListener('click', dismiss);

        toast.append(icon, content, close);
        container.append(toast);
        window.setTimeout(dismiss, 5000);
    },
};

// Phones and tablets stack a page's year list above its records (below the lg
// breakpoint), so after a year is chosen the records are brought into view.
// A short page cannot scroll far enough to bring the records to the top, so
// bottom room is added first (and cleared on the next call).
window.portalScrollToOnPhone = (id) => {
    if (!window.matchMedia('(max-width: 1023px)').matches) return;
    requestAnimationFrame(() => {
        const target = document.getElementById(id);
        if (!target) return;
        document.body.style.paddingBottom = '';
        const offset = parseFloat(getComputedStyle(target).scrollMarginTop) || 0;
        const targetTop = target.getBoundingClientRect().top + window.scrollY - offset;
        const missing = targetTop + window.innerHeight - document.documentElement.scrollHeight;
        if (missing > 0) document.body.style.paddingBottom = `${Math.ceil(missing)}px`;
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
    });
};

window.addEventListener('personnel-saved', (event) => {
    const modal = document.getElementById('personnelFormModal');
    if (modal) window.portalModal.hide(modal);

    window.portalToast.show(event.detail.message);
});

// The leave page's own script shows the toast; this only closes the form.
window.addEventListener('leave-saved', () => {
    const modal = document.getElementById('leaveFormModal');
    if (modal) window.portalModal.hide(modal);
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-mobile-menu-button]')) {
        setMenuState(document.documentElement.dataset.mobileMenuOpen !== 'true');
    }

    if (event.target.closest('[data-mobile-menu-close], [data-mobile-menu-backdrop]')) {
        setMenuState(false);
    }

    const closeButton = event.target.closest('[data-modal-close]');
    if (closeButton) {
        const modal = closeButton.closest('.modal');
        if (modal) window.portalModal.hide(modal);
    }
});
