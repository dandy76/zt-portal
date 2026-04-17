<div class="admin-header">
    <h1>Audit Log</h1>
    <button class="help-toggle" title="Help">?</button>
</div>
<div class="help-panel">
    <h3>Audit Log</h3>
    <p>Every action in the portal is logged. Use the filters to search for specific events.</p>
    <ul>
        <li><code>login</code> — Successful login</li>
        <li><code>login_fail</code> — Wrong password</li>
        <li><code>login_blocked</code> — Rate limited (too many attempts)</li>
        <li><code>2fa_fail</code> — Wrong 2FA code</li>
        <li><code>2fa_enrolled</code> — User completed 2FA setup</li>
        <li><code>ip_mismatch</code> — User's IP doesn't match their WireGuard IP (session terminated)</li>
        <li><code>grant</code> — User activated a resource</li>
        <li><code>revoke</code> — User deactivated a resource</li>
        <li><code>keepalive</code> — Browser refreshed active sessions</li>
        <li><code>logout</code> — User logged out (all active sessions revoked)</li>
        <li><code>admin_*</code> — Admin actions (create user, update resource, kill session, etc.)</li>
        <li><code>mikrotik_error</code> — Failed API call to MikroTik</li>
    </ul>
</div>

<div class="panel">
    <form id="audit-filters" class="filter-bar">
        <div class="form-group">
            <label for="af-action">Action</label>
            <select id="af-action" name="action">
                <option value="">All</option>
            </select>
        </div>
        <div class="form-group">
            <label for="af-user">User</label>
            <select id="af-user" name="user_id">
                <option value="">All</option>
            </select>
        </div>
        <div class="form-group">
            <label for="af-from">From</label>
            <input type="date" id="af-from" name="from">
        </div>
        <div class="form-group">
            <label for="af-to">To</label>
            <input type="date" id="af-to" name="to">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-reset-filters">Reset</button>
    </form>
</div>

<div class="panel">
    <table class="data-table" id="audit-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
                <th>Source IP</th>
            </tr>
        </thead>
        <tbody id="audit-body">
            <tr><td colspan="5" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>

    <div class="pagination" id="audit-pagination"></div>
</div>
