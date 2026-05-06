/**
 * IT Inventory Manager – Frontend JavaScript
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Utility: get CSRF token from a hidden field if present on the page
    ------------------------------------------------------------------ */
    function getCsrfToken() {
        const el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    /* ------------------------------------------------------------------
       Utility: POST JSON via fetch, returns parsed response
    ------------------------------------------------------------------ */
    async function postJson(url, body) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        return resp.json();
    }

    /* ------------------------------------------------------------------
       Dashboard: auto-refresh stats every 30 s
    ------------------------------------------------------------------ */
    function initDashboardRefresh() {
        const totalEl   = document.getElementById('statTotal');
        const onlineEl  = document.getElementById('statOnline');
        const offlineEl = document.getElementById('statOffline');
        const tsEl      = document.getElementById('lastRefreshed');

        if (!totalEl) return; // not on dashboard

        setInterval(async () => {
            try {
                const resp = await fetch('/api/computers', { credentials: 'same-origin' });
                if (!resp.ok) return;
                const data = await resp.json();

                const online  = (data.computers || []).filter(c => c.status === 'online').length;
                const offline = (data.computers || []).filter(c => c.status === 'offline').length;

                totalEl.textContent   = data.total   ?? totalEl.textContent;
                onlineEl.textContent  = online;
                offlineEl.textContent = offline;
                if (tsEl) {
                    tsEl.textContent = 'Last refreshed: ' + new Date().toLocaleTimeString();
                }
            } catch (_) { /* network error – ignore */ }
        }, 30000);
    }

    /* ------------------------------------------------------------------
       Computers list: live AJAX search (debounced, 400 ms)
    ------------------------------------------------------------------ */
    function initComputerSearch() {
        const input = document.getElementById('searchInput');
        const form  = document.getElementById('searchForm');
        if (!input || !form) return;

        let timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 400);
        });
    }

    /* ------------------------------------------------------------------
       Status filter: auto-submit on change
    ------------------------------------------------------------------ */
    function initStatusFilter() {
        const sel = document.getElementById('statusFilter');
        if (sel) {
            sel.addEventListener('change', function () {
                document.getElementById('searchForm')?.submit();
            });
        }
    }

    /* ------------------------------------------------------------------
       Commands page: poll pending command statuses every 15 s
    ------------------------------------------------------------------ */
    function initCommandStatusPolling() {
        const rows = document.querySelectorAll('tr[data-command-id]');
        if (!rows.length) return;

        const pendingRows = [...rows].filter(r =>
            r.querySelector('.badge')?.textContent?.trim() === 'pending' ||
            r.querySelector('.badge')?.textContent?.trim() === 'sent'
        );

        if (!pendingRows.length) return;

        setInterval(async () => {
            try {
                const resp = await fetch('/api/computers', { credentials: 'same-origin' });
                if (resp.ok) {
                    // Refresh page to reflect updated statuses
                    window.location.reload();
                }
            } catch (_) { /* ignore */ }
        }, 15000);
    }

    /* ------------------------------------------------------------------
       Delete confirmation (global handler for data-confirm attribute)
    ------------------------------------------------------------------ */
    function initConfirmActions() {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const msg  = form.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    /* ------------------------------------------------------------------
       Bootstrap tooltips
    ------------------------------------------------------------------ */
    function initTooltips() {
        if (typeof bootstrap === 'undefined') return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    }

    /* ------------------------------------------------------------------
       Highlight active nav link based on current path
    ------------------------------------------------------------------ */
    function highlightActiveNav() {
        const path = window.location.pathname;
        document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href !== '/' && path.startsWith(href)) {
                link.classList.add('active');
            } else if (href === '/dashboard' && (path === '/' || path === '/dashboard')) {
                link.classList.add('active');
            }
        });
    }

    /* ------------------------------------------------------------------
       Init
    ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        initDashboardRefresh();
        initComputerSearch();
        initStatusFilter();
        initCommandStatusPolling();
        initConfirmActions();
        initTooltips();
        highlightActiveNav();
    });

})();
