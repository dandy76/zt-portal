<div class="admin-header">
    <h1>Permissions</h1>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <button class="help-toggle" title="Help">?</button>
        <button class="btn btn-secondary btn-sm" id="btn-perm-expand-all">Expand all</button>
        <button class="btn btn-secondary btn-sm" id="btn-perm-collapse-all">Collapse all</button>
    </div>
</div>
<div class="help-panel">
    <h3>User Permissions</h3>
    <p>Group view per user. Click το header για expand, τικάρε/ξετικάρε resources για grant/revoke (save γίνεται άμεσα).</p>
    <ul>
        <li><strong>Edit</strong> — άνοιγμα του user (display name, WG IP, role, password) χωρίς να φύγεις από τη σελίδα</li>
        <li><strong>Search</strong> — φιλτράρει username, display name και resource name</li>
    </ul>
</div>

<div class="panel" style="margin-bottom:0.75rem">
    <input type="text" id="perm-search" class="form-input" placeholder="Search username / display name / resource..." style="width:100%">
</div>

<div id="perm-user-edit" class="panel form-panel" style="display:none">
    <h2>Edit user <span id="pue-username" class="mono"></span></h2>
    <form id="perm-user-edit-form">
        <input type="hidden" id="pue-id">
        <div class="form-row">
            <div class="form-group">
                <label for="pue-display">Display name</label>
                <input type="text" id="pue-display" name="display_name">
            </div>
            <div class="form-group">
                <label for="pue-wgip">WireGuard IP</label>
                <input type="text" id="pue-wgip" name="wireguard_ip" placeholder="10.1.40.x or 0.0.0.0">
            </div>
            <div class="form-group">
                <label for="pue-role">Role</label>
                <select id="pue-role" name="role">
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="pue-password">New password <span class="text-muted">(leave blank to keep)</span></label>
                <input type="password" id="pue-password" name="password" autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" id="btn-pue-cancel">Cancel</button>
        </div>
    </form>
</div>

<div class="panel" id="perm-list-panel">
    <div id="perm-list" class="perm-list">
        <div class="text-muted" style="padding:1rem">Loading...</div>
    </div>
</div>
