<?php
declare(strict_types=1);

class Auth
{
    public static function login(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Rate limiting check
        if (self::isRateLimited($ip)) {
            AuditLog::log('login_blocked', null, json_encode(['username' => $username, 'reason' => 'rate_limit']), $ip);
            return ['success' => false, 'error' => 'Too many failed attempts. Try again later.'];
        }

        $user = Database::fetchOne(
            'SELECT * FROM users WHERE username = ? AND enabled = 1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($ip);
            AuditLog::log('login_fail', $user['id'] ?? null, json_encode(['username' => $username]), $ip);
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Source IP verification (skip for admin 0.0.0.0 on first setup)
        if ($user['wireguard_ip'] !== '0.0.0.0') {
            if (!self::isAllowedIP($ip, $user)) {
                AuditLog::log('ip_mismatch', $user['id'], json_encode([
                    'expected' => $user['wireguard_ip'],
                    'actual' => $ip,
                ]), $ip);
                return ['success' => false, 'error' => 'Access denied from this IP address'];
            }
        }

        // Password OK — check 2FA status
        session_regenerate_id(true);

        if (!$user['totp_enabled']) {
            // 2FA not set up yet — force enrollment
            $_SESSION['pending_2fa_setup'] = true;
            $_SESSION['pending_user_id'] = $user['id'];
            AuditLog::log('login_pending_2fa_setup', $user['id'], null, $ip);
            return ['success' => true, 'redirect' => '/setup-2fa', 'requires_2fa_setup' => true];
        }

        // 2FA enabled — require verification
        $_SESSION['pending_2fa'] = true;
        $_SESSION['pending_user_id'] = $user['id'];
        return ['success' => true, 'redirect' => '/verify-2fa', 'requires_2fa' => true];
    }

    public static function verify2FA(string $code): array
    {
        if (!self::isPending2FA()) {
            return ['success' => false, 'error' => 'No pending 2FA verification'];
        }

        $userId = $_SESSION['pending_user_id'];
        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $totp = new TOTP();
        if (!$totp->verifyCode($user['totp_secret'], $code)) {
            AuditLog::log('2fa_fail', $userId, null, $_SERVER['REMOTE_ADDR'] ?? '');
            return ['success' => false, 'error' => 'Invalid 2FA code'];
        }

        // Success — complete login
        self::completeLogin($user);
        return ['success' => true, 'redirect' => '/portal'];
    }

    public static function enroll2FA(string $code): array
    {
        if (!self::isPending2FASetup()) {
            return ['success' => false, 'error' => 'No pending 2FA setup'];
        }

        $secret = $_SESSION['pending_totp_secret'] ?? '';
        if (!$secret) {
            return ['success' => false, 'error' => 'No TOTP secret found. Start setup again.'];
        }

        $totp = new TOTP();
        if (!$totp->verifyCode($secret, $code)) {
            return ['success' => false, 'error' => 'Invalid code. Scan the QR code and try again.'];
        }

        $userId = $_SESSION['pending_user_id'];

        // Save secret and enable 2FA
        Database::execute(
            'UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?',
            [$secret, $userId]
        );

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);

        unset($_SESSION['pending_2fa_setup'], $_SESSION['pending_totp_secret']);

        self::completeLogin($user);
        AuditLog::log('2fa_enrolled', $userId, null, $_SERVER['REMOTE_ADDR'] ?? '');

        return ['success' => true, 'redirect' => '/portal'];
    }

    private static function completeLogin(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['wireguard_ip'] = $user['wireguard_ip'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_setup'], $_SESSION['pending_user_id']);

        AuditLog::log('login', $user['id'], null, $_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function isLoggedIn(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        $config = Config::getInstance();
        if (time() - ($_SESSION['last_activity'] ?? 0) > $config->get('session_timeout')) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function isPending2FA(): bool
    {
        return !empty($_SESSION['pending_2fa']) && !empty($_SESSION['pending_user_id']);
    }

    public static function isPending2FASetup(): bool
    {
        return !empty($_SESSION['pending_2fa_setup']) && !empty($_SESSION['pending_user_id']);
    }

    public static function getPending2FAUser(): ?array
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId) return null;
        return Database::fetchOne('SELECT id, username, display_name FROM users WHERE id = ?', [$userId]);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                Router::json(['error' => 'Not authenticated'], 401);
            }
            Router::redirect('/login');
        }
        self::verifySourceIP();
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                Router::json(['error' => 'Admin access required'], 403);
            }
            Router::redirect('/portal');
        }
    }

    public static function verifySourceIP(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $wgIp = $_SESSION['wireguard_ip'] ?? '';

        // Skip for admin with 0.0.0.0 (initial setup)
        if ($wgIp === '0.0.0.0') return;

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [self::userId()]);
        if (!$user) return;

        if (!self::isAllowedIP($ip, $user)) {
            AuditLog::log('ip_mismatch', self::userId(), json_encode([
                'expected' => $wgIp,
                'actual' => $ip,
            ]), $ip);
            self::logout();
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                Router::json(['error' => 'IP address mismatch'], 403);
            }
            Router::redirect('/login?error=ip_mismatch');
        }
    }

    private static function isAllowedIP(string $ip, array $user): bool
    {
        if ($ip === $user['wireguard_ip']) {
            return true;
        }

        if (!empty($user['allowed_source_ips'])) {
            $allowed = json_decode($user['allowed_source_ips'], true);
            if (is_array($allowed) && in_array($ip, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function username(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    private static function isRateLimited(string $ip): bool
    {
        $config = Config::getInstance();
        $window = $config->get('rate_limit_window');
        $maxAttempts = $config->get('rate_limit_attempts');

        $result = Database::fetchOne(
            'SELECT COUNT(*) as cnt FROM rate_limits
             WHERE ip_address = ? AND attempt_type = "login"
             AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [$ip, $window]
        );

        return ((int) ($result['cnt'] ?? 0)) >= $maxAttempts;
    }

    private static function recordFailedAttempt(string $ip): void
    {
        Database::insert(
            'INSERT INTO rate_limits (ip_address, attempt_type) VALUES (?, "login")',
            [$ip]
        );
    }
}
