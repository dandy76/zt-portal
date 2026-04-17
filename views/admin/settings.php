<div class="admin-header">
    <h1>Settings</h1>
    <button class="help-toggle" title="Help">?</button>
</div>
<div class="help-panel">
    <h3>Portal Settings</h3>
    <ul>
        <li><strong>Portal Title</strong> — The name shown in the navigation bar</li>
        <li><strong>TOTP Issuer</strong> — The name that appears in Google Authenticator / authenticator apps. Example: <code>MyCompany-VPN</code>. Only affects new 2FA enrollments — existing users keep the old name</li>
        <li><strong>Session Timeout</strong> — PHP session idle timeout in seconds (default 1800 = 30 min). If a user is idle for this long, they must re-login</li>
        <li><strong>Default Resource Timeout</strong> — Default timeout for new resources in minutes</li>
    </ul>
    <h3>MikroTik API</h3>
    <p>Tests the connection to the MikroTik REST API. The API URL, username, and password are configured in the <code>.env</code> file on the server. If the test fails, check:</p>
    <ul>
        <li>MikroTik API service is enabled (<code>/ip service print</code>)</li>
        <li>The API user exists with <code>api</code> and <code>rest-api</code> policies</li>
        <li>The QNAP can reach the MikroTik IP</li>
    </ul>
</div>

<div class="panel">
    <h2>General</h2>
    <form id="settings-form">
        <div class="form-row">
            <div class="form-group">
                <label for="sf-title">Portal Title</label>
                <input type="text" id="sf-title" name="portal_title" placeholder="ZT Portal">
            </div>
            <div class="form-group">
                <label for="sf-issuer">TOTP Issuer (Google Authenticator name)</label>
                <input type="text" id="sf-issuer" name="totp_issuer" placeholder="ZT-Portal">
                <small class="text-muted">Changes apply only to new 2FA enrollments</small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="sf-timeout">Session Timeout (seconds)</label>
                <input type="number" id="sf-timeout" name="session_timeout" value="1800" min="300" max="86400">
            </div>
            <div class="form-group">
                <label for="sf-def-timeout">Default Resource Timeout (minutes)</label>
                <input type="number" id="sf-def-timeout" name="default_timeout_minutes" value="10" min="1" max="1440">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>MikroTik API</h2>
    <p class="text-muted">Connection status to MikroTik REST API</p>
    <div id="mt-status" style="margin-top:0.75rem">
        <button class="btn btn-secondary btn-sm" id="btn-test-mt">Test Connection</button>
        <span id="mt-result" style="margin-left:0.75rem"></span>
    </div>
</div>
