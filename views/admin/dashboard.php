<div class="admin-header">
    <h1>Admin Dashboard</h1>
    <button class="help-toggle" title="Help">?</button>
</div>
<div class="help-panel">
    <h3>Dashboard</h3>
    <p>Overview of the Zero Trust Portal status.</p>
    <ul>
        <li><strong>Active Sessions</strong> — Users currently accessing network resources through the portal</li>
        <li><strong>Logins (24h)</strong> — Successful logins in the last 24 hours</li>
        <li><strong>Failed Logins (24h)</strong> — Failed login attempts (wrong password, 2FA fail, IP mismatch)</li>
        <li><strong>Total Users</strong> — All registered users in the system</li>
    </ul>
    <p>The Recent Activity table shows the latest audit log entries — logins, access grants, revokes, and admin actions.</p>
</div>

<div class="stats-grid" id="dashboard-stats">
    <div class="stat-card">
        <div class="stat-value" id="stat-active-sessions">--</div>
        <div class="stat-label">Active Sessions</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="stat-recent-logins">--</div>
        <div class="stat-label">Logins (24h)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="stat-failed-logins">--</div>
        <div class="stat-label">Failed Logins (24h)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="stat-total-users">--</div>
        <div class="stat-label">Total Users</div>
    </div>
</div>

<div class="panel">
    <h2>Recent Activity</h2>
    <table class="data-table" id="recent-audit">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
                <th>Source IP</th>
            </tr>
        </thead>
        <tbody id="recent-audit-body">
            <tr><td colspan="5" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>
</div>
