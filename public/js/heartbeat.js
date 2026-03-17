/**
 * Heartbeat Component
 * Maintains user online status by polling the heartbeat API.
 */
(function() {
    function sendHeartbeat() {
        const user = typeof storage !== 'undefined' ? storage.getUser() : null;
        if (!user) return;

        // Using fetch directly to avoid dependency loops if apiFetch is not ready
        fetch('../../api/users/heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).catch(err => console.debug('Heartbeat failed', err));
    }

    // Initial heartbeat
    sendHeartbeat();

    // Poll every 60 seconds
    setInterval(sendHeartbeat, 60000);
})();
