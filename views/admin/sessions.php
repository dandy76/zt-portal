<div class="admin-header">
    <h1>Active Sessions</h1>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <button class="help-toggle" title="Help">?</button>
        <button class="btn btn-secondary btn-sm" id="btn-refresh-sessions">Refresh</button>
    </div>
</div>
<div class="help-panel">
    <h3>Active Sessions</h3>
    <p>Real-time view of all users currently accessing network resources.</p>
    <ul>
        <li><strong>Source IP</strong> — The WireGuard IP that was added to the MikroTik address-list</li>
        <li><strong>Granted</strong> — When the user activated the resource</li>
        <li><strong>Expires</strong> — When MikroTik will auto-remove the address-list entry</li>
        <li><strong>Last Keepalive</strong> — Last time the user's browser refreshed the timeout (every 5 min)</li>
        <li><strong>Kill</strong> — Immediately revokes access by removing the IP from MikroTik address-list and marking session as killed</li>
    </ul>
    <p>If a user closes their browser without logging out, access expires automatically after the timeout. The cleanup cron marks expired sessions every minute.</p>
</div>

<div class="panel">
    <table class="data-table" id="sessions-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Resource</th>
                <th>Source IP</th>
                <th>Granted</th>
                <th>Expires</th>
                <th>Last Keepalive</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="sessions-body">
            <tr><td colspan="8" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>
</div>
