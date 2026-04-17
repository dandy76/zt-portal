<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">&#9671;</span>
            <h1>Two-Factor Auth</h1>
            <p class="text-muted">Enter the code from your authenticator app</p>
        </div>

        <div id="2fa-error" class="alert alert-danger" style="display:none"></div>

        <form id="verify-2fa-form">
            <div class="form-group">
                <label for="totp-code">6-digit code</label>
                <input type="text" id="totp-code" name="code" required autofocus
                       maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                       autocomplete="one-time-code" placeholder="000000"
                       class="input-code">
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                Verify
            </button>
        </form>

        <div class="auth-footer">
            <a href="/login" class="text-muted">Back to login</a>
        </div>
    </div>
</div>
