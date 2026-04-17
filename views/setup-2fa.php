<div class="auth-container">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <span class="auth-logo">&#9671;</span>
            <h1>Setup 2FA</h1>
            <p class="text-muted">Scan this QR code with your authenticator app</p>
        </div>

        <div id="setup-error" class="alert alert-danger" style="display:none"></div>

        <div class="setup-2fa-content">
            <div class="qr-section">
                <div id="qr-code" class="qr-placeholder"
                     data-uri="<?= htmlspecialchars($qrUrl ?? '') ?>">
                    <canvas id="qr-canvas"></canvas>
                </div>
                <div class="secret-display">
                    <label class="text-muted">Manual entry key:</label>
                    <code class="secret-key"><?= htmlspecialchars($secret ?? '') ?></code>
                </div>
            </div>

            <form id="enroll-2fa-form">
                <div class="form-group">
                    <label for="enroll-code">Enter code to verify</label>
                    <input type="text" id="enroll-code" name="code" required
                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           autocomplete="one-time-code" placeholder="000000"
                           class="input-code">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Activate 2FA
                </button>
            </form>
        </div>
    </div>
</div>
