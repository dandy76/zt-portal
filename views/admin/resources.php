<div class="admin-header">
    <h1>Resources</h1>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <button class="help-toggle" title="Help">?</button>
        <button class="btn btn-primary btn-sm" id="btn-add-resource">+ Add Resource</button>
    </div>
</div>
<div class="help-panel">
    <h3>Network Resources</h3>
    <p>Resources define what network services users can activate through the portal. Each resource maps to a MikroTik firewall address-list.</p>
    <ul>
        <li><strong>Name</strong> — Human-readable name shown to users (e.g. "RDP Server", "File Share")</li>
        <li><strong>Address List Name</strong> — The MikroTik address-list name (e.g. <code>auth_rdp</code>). Must match the <code>src-address-list</code> in your MikroTik firewall rule</li>
        <li><strong>Destination</strong> — Target IP/subnet the user will access (e.g. <code>10.0.50.0/24</code>). Informational — the actual filtering is done by MikroTik rules</li>
        <li><strong>Port</strong> — Destination port(s) (e.g. <code>3389</code> or <code>445,139</code>). Informational</li>
        <li><strong>Timeout</strong> — How long access stays active (minutes). MikroTik auto-removes the address-list entry after this time</li>
    </ul>
    <h3>How it works</h3>
    <p>When a user activates a resource:</p>
    <ul>
        <li>Portal calls MikroTik REST API → adds user's WG IP to the address-list</li>
        <li>MikroTik firewall rule matches <code>src-address-list=auth_xxx</code> → allows traffic</li>
        <li>After timeout expires → MikroTik removes the entry → access revoked</li>
        <li>JS keepalive refreshes the timeout every 5 minutes while browser is open</li>
    </ul>
    <h3>MikroTik firewall rule example</h3>
    <p><code>/ip firewall filter add chain=forward src-address-list=auth_rdp dst-address=10.0.50.0/24 dst-port=3389 protocol=tcp action=accept</code></p>
</div>

<div id="resource-form-panel" class="panel form-panel" style="display:none">
    <h2 id="resource-form-title">Add Resource</h2>
    <form id="resource-form">
        <input type="hidden" id="res-edit-id">
        <div class="form-row">
            <div class="form-group">
                <label for="rf-name">Name</label>
                <input type="text" id="rf-name" name="name" required placeholder="RDP Server">
            </div>
            <div class="form-group">
                <label for="rf-list">Address List Name</label>
                <input type="text" id="rf-list" name="address_list_name" required placeholder="auth_rdp">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="rf-dst">Destination Address</label>
                <input type="text" id="rf-dst" name="dst_address" required placeholder="10.0.50.0/24">
            </div>
            <div class="form-group">
                <label for="rf-port">Port(s)</label>
                <input type="text" id="rf-port" name="dst_port" required placeholder="3389">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="rf-proto">Protocol</label>
                <select id="rf-proto" name="protocol">
                    <option value="tcp">TCP</option>
                    <option value="udp">UDP</option>
                    <option value="tcp+udp">TCP+UDP</option>
                </select>
            </div>
            <div class="form-group">
                <label for="rf-timeout">Timeout (minutes)</label>
                <input type="number" id="rf-timeout" name="timeout_minutes" value="10" min="1" max="1440">
            </div>
        </div>
        <div class="form-group">
            <label for="rf-desc">Description</label>
            <textarea id="rf-desc" name="description" rows="2"></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" id="btn-cancel-resource">Cancel</button>
        </div>
    </form>
</div>

<div class="panel">
    <table class="data-table" id="resources-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address List</th>
                <th>Destination</th>
                <th>Port</th>
                <th>Protocol</th>
                <th>Timeout</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="resources-body">
            <tr><td colspan="9" class="text-muted">Loading...</td></tr>
        </tbody>
    </table>
</div>
