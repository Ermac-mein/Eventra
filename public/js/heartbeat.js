/**
 * Heartbeat & Session Timeout Component
 *
 * 1. Tracks user interaction timestamps to detect 30-minute inactivity.
 * 2. Calls the server heartbeat API every 60 seconds to keep is_online fresh.
 * 3. On 30-minute inactivity, calls logout API and redirects to login.
 */
(function () {
    'use strict';

    // ── Configuration ──────────────────────────────────────────────────────────
    const INACTIVITY_LIMIT_MS = 120 * 60 * 1000; // 2 hours (Increased from 30 minutes)
    const HEARTBEAT_INTERVAL_MS = 60 * 1000;     // 60 seconds
    const CHECK_INTERVAL_MS = 30 * 1000;         // check every 30 s

    // ── Determine correct paths regardless of page depth ──────────────────────
    const relBase = typeof getBasePath === 'function' ? getBasePath() : '../../';
    const HEARTBEAT_URL = relBase + 'api/heartbeat.php';
    const LOGOUT_URL = relBase + 'api/auth/logout.php';

    // ── Determine login page URL by role ──────────────────────────────────────
    function getLoginUrl() {
        const path = window.location.pathname;
        if (path.includes('/admin/')) return '/admin/pages/adminLogin.html';
        if (path.includes('/client/')) return '/client/pages/clientLogin.html';
        return '/public/pages/landing.html';
    }

    // ── Track last activity ────────────────────────────────────────────────────
    let lastActivityTime = Date.now();

    const activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    activityEvents.forEach(evt => {
        document.addEventListener(evt, () => {
            lastActivityTime = Date.now();
        }, { passive: true });
    });

    // ── Check if user is authenticated ────────────────────────────────────────
    function isLoggedIn() {
        // Check using the storage helper if available, otherwise check session cookies
        if (typeof storage !== 'undefined') {
            return !!storage.getUser();
        }
        // Fallback: look for any Eventra session cookie
        return document.cookie.split(';').some(c => c.trim().startsWith('EVENTRA_'));
    }

    // ── Inactivity logout ──────────────────────────────────────────────────────
    function checkInactivity() {
        if (!isLoggedIn()) return;

        const idle = Date.now() - lastActivityTime;
        if (idle >= INACTIVITY_LIMIT_MS) {
            doLogout('inactivity');
        }
    }

    async function doLogout(reason) {
        try {
            await fetch(LOGOUT_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' } });
        } catch (_) { /* ignore network error – still redirect */ }

        // Clear local storage keys
        if (typeof storage !== 'undefined') {
            ['admin_user', 'admin_auth_token', 'client_user', 'client_auth_token', 'user', 'auth_token']
                .forEach(k => storage.remove(k));
        }

        if (reason === 'inactivity' && typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'warning',
                title: 'Session Expired',
                text: 'You have been logged out due to 2 hours of inactivity.',
                confirmButtonColor: '#6366f1',
                timer: 5000,
                timerProgressBar: true
            });
        }

        window.location.href = getLoginUrl();
    }

    // ── Server heartbeat ────────────────────────────────────────────────────────
    function sendHeartbeat() {
        if (!isLoggedIn()) return;

        const path = window.location.pathname;
        let portal = 'user';
        if (path.includes('/admin/')) portal = 'admin';
        else if (path.includes('/client/')) portal = 'client';

        fetch(HEARTBEAT_URL, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Eventra-Portal': portal
            }
        }).then(res => {
            if (res.status === 401) {
                // Server explicitly says session is invalid — redirect to login
                doLogout('server');
            } else if (!res.ok) {
            }
        }).catch(err => {
            // Network errors or 404s should NOT trigger a logout to avoid false positives
        });
    }

    // ── Boot ────────────────────────────────────────────────────────────────────
    sendHeartbeat();
    setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
    setInterval(checkInactivity, CHECK_INTERVAL_MS);

    // Expose so admin-main.js can call initHeartbeat() for compat
    window.initHeartbeat = function () { /* already initialized above */ };

})();
