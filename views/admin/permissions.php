<div class="admin-header">
    <h1>Permissions</h1>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <button class="help-toggle" title="Help">?</button>
        <button class="btn btn-primary btn-sm" id="btn-add-permission">+ Grant Permission</button>
    </div>
</div>
<div class="help-panel">
    <h3>User Permissions</h3>
    <p>Assign resources to users. A user can only activate resources they have permission for.</p>
    <ul>
        <li><strong>Grant</strong> — Select a user and resource to give them access</li>
        <li><strong>Revoke</strong> — Remove a permission. If the user has an active session for that resource, it will NOT be killed automatically — use Sessions to kill active sessions</li>
    </ul>
    <p>Users see only their permitted resources on the portal page.</p>
</div>

<div id="perm-form-panel" class="panel form-panel" style="display:none">
    <h2>Grant Permission</h2>
    <form id="permission-form">
        <div class="form-row">
            <div class="form-group">
                <label for="pf-user">User</label>
                <select id="pf-user" name="user_id" required>
                    <option value="">Select user...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="pf-resource">Resource</label>
                <select id="pf-resource" name="resource_id" required>
                    <option value="">Select resource...</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Grant</button>
            <button type="button" class="btn btn-secondary" id="btn-cancel-permission">Cancel</button>
        </div>
    </form>
</div>

<div class="panel">
    <table class="data-table" id="permissions-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Resource</th>
                <th>Granted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="permissions-body">
            <tr><td colspan="5" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>
</div>
