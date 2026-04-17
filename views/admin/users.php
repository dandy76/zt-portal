<div class="admin-header">
    <h1>Users</h1>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <button class="help-toggle" title="Help">?</button>
        <button class="btn btn-primary btn-sm" id="btn-add-user">+ Add User</button>
    </div>
</div>
<div class="help-panel">
    <h3>User Management</h3>
    <ul>
        <li><strong>Username</strong> — Login username for the portal</li>
        <li><strong>WireGuard IP</strong> — The fixed WG IP assigned to this user in MikroTik (e.g. <code>10.1.40.10</code>). The portal verifies this IP on every request. Set to <code>0.0.0.0</code> to skip IP check (admin initial setup only)</li>
        <li><strong>Allowed IPs</strong> — Optional JSON array of additional IPs that can login besides the WG IP (e.g. <code>["192.168.1.50"]</code>). Leave empty for WG-only access</li>
        <li><strong>Role</strong> — <code>user</code> sees only the portal. <code>admin</code> sees admin panel</li>
        <li><strong>2FA</strong> — Users must setup 2FA on first login. Use "Reset 2FA" to force re-enrollment</li>
        <li><strong>Password Generator</strong> — Click the refresh icon next to password field to generate a strong random password</li>
    </ul>
</div>

<div id="user-form-panel" class="panel form-panel" style="display:none">
    <h2 id="user-form-title">Add User</h2>
    <form id="user-form">
        <input type="hidden" id="user-edit-id">
        <div class="form-row">
            <div class="form-group">
                <label for="uf-username">Username</label>
                <input type="text" id="uf-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="uf-password">Password</label>
                <div class="input-with-btn">
                    <input type="text" id="uf-password" name="password" autocomplete="off">
                    <button type="button" class="btn btn-sm btn-secondary" id="btn-gen-password" title="Generate password">&#8634;</button>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="uf-display-name">Display Name</label>
                <input type="text" id="uf-display-name" name="display_name">
            </div>
            <div class="form-group">
                <label for="uf-wg-ip">WireGuard IP</label>
                <input type="text" id="uf-wg-ip" name="wireguard_ip" required placeholder="10.1.40.x">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="uf-role">Role</label>
                <select id="uf-role" name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="uf-allowed-ips">Allowed IPs (JSON array, optional)</label>
                <input type="text" id="uf-allowed-ips" name="allowed_source_ips" placeholder='["192.168.1.50"]'>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" id="btn-cancel-user">Cancel</button>
        </div>
    </form>
</div>

<div class="panel">
    <table class="data-table" id="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Display Name</th>
                <th>WG IP</th>
                <th>Role</th>
                <th>2FA</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="users-body">
            <tr><td colspan="8" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>
</div>
