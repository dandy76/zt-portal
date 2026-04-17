<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">&#9671;</span>
            <h1>ZT Portal</h1>
            <p class="text-muted">Zero Trust Network Access</p>
        </div>

        <div id="login-error" class="alert alert-danger" style="display:none"></div>

        <?php if (!empty($_GET['error']) && $_GET['error'] === 'ip_mismatch'): ?>
        <div class="alert alert-danger">Session terminated: IP address mismatch detected.</div>
        <?php endif; ?>

        <form id="login-form" autocomplete="on">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       autocomplete="username" placeholder="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="password">
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="btn-login">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            <span class="text-muted">Secure access portal</span>
        </div>
    </div>
</div>
