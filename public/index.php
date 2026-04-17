<?php
declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/AuditLog.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/TOTP.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MikroTikAPI.php';
require_once __DIR__ . '/../src/AccessManager.php';

// Init config
$config = Config::getInstance();

// Session
session_start();

// CSRF token init
Router::csrfToken();

$router = new Router();

// ─── Public routes ───
$router->get('/', function () {
    if (Auth::isLoggedIn()) {
        Router::redirect('/portal');
    } else {
        Router::redirect('/login');
    }
});

$router->get('/login', function () {
    if (Auth::isLoggedIn()) {
        Router::redirect('/portal');
    }
    Router::view('login');
});

$router->get('/setup-2fa', function () {
    if (!Auth::isPending2FASetup()) {
        Router::redirect('/login');
    }
    $user = Auth::getPending2FAUser();
    $totp = new TOTP();
    $secret = $totp->generateSecret();
    $_SESSION['pending_totp_secret'] = $secret;
    $issuer = Settings::get('totp_issuer', 'ZT-Portal');
    $qrUrl = $totp->getQRCodeUrl($issuer, $user['username'], $secret);
    Router::view('setup-2fa', ['qrUrl' => $qrUrl, 'secret' => $secret, 'user' => $user]);
});

$router->get('/verify-2fa', function () {
    if (!Auth::isPending2FA()) {
        Router::redirect('/login');
    }
    Router::view('verify-2fa');
});

$router->get('/portal', function () {
    Auth::requireLogin();
    $userId = Auth::userId();
    $resources = AccessManager::getUserResources($userId);
    Router::view('portal', ['resources' => $resources]);
});

// ─── Admin page routes ───
$router->get('/admin', function () {
    Auth::requireAdmin();
    Router::redirect('/admin/dashboard');
});

$router->get('/admin/dashboard', function () {
    Auth::requireAdmin();
    Router::view('admin/dashboard');
});

$router->get('/admin/users', function () {
    Auth::requireAdmin();
    Router::view('admin/users');
});

$router->get('/admin/resources', function () {
    Auth::requireAdmin();
    Router::view('admin/resources');
});

$router->get('/admin/permissions', function () {
    Auth::requireAdmin();
    Router::view('admin/permissions');
});

$router->get('/admin/sessions', function () {
    Auth::requireAdmin();
    Router::view('admin/sessions');
});

$router->get('/admin/audit', function () {
    Auth::requireAdmin();
    Router::view('admin/audit');
});

$router->get('/admin/settings', function () {
    Auth::requireAdmin();
    Router::view('admin/settings');
});

// ─── Auth API ───
$router->post('/api/login', function () {
    $input = Router::getInput();
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        Router::json(['error' => 'Username and password required'], 400);
    }

    $result = Auth::login($username, $password);
    Router::json($result, $result['success'] ? 200 : 401);
});

$router->post('/api/2fa/verify', function () {
    $input = Router::getInput();
    $code = trim($input['code'] ?? '');

    if (!$code) {
        Router::json(['error' => 'Code required'], 400);
    }

    $result = Auth::verify2FA($code);
    Router::json($result, $result['success'] ? 200 : 401);
});

$router->post('/api/2fa/enroll', function () {
    $input = Router::getInput();
    $code = trim($input['code'] ?? '');

    if (!$code) {
        Router::json(['error' => 'Code required'], 400);
    }

    $result = Auth::enroll2FA($code);
    Router::json($result, $result['success'] ? 200 : 400);
});

$router->post('/api/logout', function () {
    $userId = Auth::userId();
    if ($userId) {
        AccessManager::revokeAll($userId);
        AuditLog::log('logout', $userId);
    }
    Auth::logout();
    Router::json(['success' => true]);
});

// ─── Portal API ───
$router->get('/api/resources', function () {
    Auth::requireLogin();
    $resources = AccessManager::getUserResources(Auth::userId());
    Router::json(['success' => true, 'resources' => $resources]);
});

$router->post('/api/access/grant', function () {
    Auth::requireLogin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();
    $resourceId = (int) ($input['resource_id'] ?? 0);
    $result = AccessManager::grantAccess(Auth::userId(), $resourceId);
    Router::json($result, $result['success'] ? 200 : 400);
});

$router->post('/api/access/revoke', function () {
    Auth::requireLogin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();
    $resourceId = (int) ($input['resource_id'] ?? 0);
    $result = AccessManager::revokeAccess(Auth::userId(), $resourceId);
    Router::json($result, $result['success'] ? 200 : 400);
});

$router->post('/api/access/grant-all', function () {
    Auth::requireLogin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $result = AccessManager::grantAll(Auth::userId());
    Router::json($result);
});

$router->post('/api/access/revoke-all', function () {
    Auth::requireLogin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    AccessManager::revokeAll(Auth::userId());
    Router::json(['success' => true]);
});

$router->post('/api/keepalive', function () {
    Auth::requireLogin();
    Auth::verifySourceIP();
    $result = AccessManager::keepalive(Auth::userId());
    Router::json($result);
});

// ─── Admin API ───
$router->get('/api/admin/dashboard', function () {
    Auth::requireAdmin();
    $activeSessions = Database::fetchOne('SELECT COUNT(*) as cnt FROM sessions WHERE status = "active"');
    $recentLogins = Database::fetchOne('SELECT COUNT(*) as cnt FROM audit_log WHERE action = "login" AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $failedLogins = Database::fetchOne('SELECT COUNT(*) as cnt FROM audit_log WHERE action = "login_fail" AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $totalUsers = Database::fetchOne('SELECT COUNT(*) as cnt FROM users');

    Router::json([
        'active_sessions' => (int) $activeSessions['cnt'],
        'recent_logins' => (int) $recentLogins['cnt'],
        'failed_logins' => (int) $failedLogins['cnt'],
        'total_users' => (int) $totalUsers['cnt'],
    ]);
});

$router->get('/api/admin/users', function () {
    Auth::requireAdmin();
    $users = Database::fetchAll(
        'SELECT id, username, display_name, wireguard_ip, role, enabled, totp_enabled,
                allowed_source_ips, created_at, updated_at
         FROM users ORDER BY id'
    );
    Router::json(['users' => $users]);
});

$router->post('/api/admin/users', function () {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $displayName = trim($input['display_name'] ?? '');
    $wireguardIp = trim($input['wireguard_ip'] ?? '');
    $role = ($input['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

    if (!$username || !$password || !$wireguardIp) {
        Router::json(['error' => 'Username, password and WireGuard IP required'], 400);
    }

    $existing = Database::fetchOne('SELECT id FROM users WHERE username = ?', [$username]);
    if ($existing) {
        Router::json(['error' => 'Username already exists'], 400);
    }

    $id = Database::insert(
        'INSERT INTO users (username, password_hash, display_name, wireguard_ip, role) VALUES (?, ?, ?, ?, ?)',
        [$username, password_hash($password, PASSWORD_BCRYPT), $displayName, $wireguardIp, $role]
    );

    AuditLog::log('admin_create_user', Auth::userId(), json_encode(['created_user' => $username]));
    Router::json(['success' => true, 'id' => $id]);
});

$router->put('/api/admin/users/{id}', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    $input = Router::getInput();

    $fields = [];
    $values = [];

    if (isset($input['display_name'])) {
        $fields[] = 'display_name = ?';
        $values[] = trim($input['display_name']);
    }
    if (isset($input['wireguard_ip'])) {
        $fields[] = 'wireguard_ip = ?';
        $values[] = trim($input['wireguard_ip']);
    }
    if (isset($input['role'])) {
        $fields[] = 'role = ?';
        $values[] = $input['role'] === 'admin' ? 'admin' : 'user';
    }
    if (isset($input['allowed_source_ips'])) {
        $fields[] = 'allowed_source_ips = ?';
        $values[] = $input['allowed_source_ips'];
    }
    if (!empty($input['password'])) {
        $fields[] = 'password_hash = ?';
        $values[] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    if (!$fields) {
        Router::json(['error' => 'No fields to update'], 400);
    }

    $values[] = $id;
    Database::execute('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $values);
    AuditLog::log('admin_update_user', Auth::userId(), json_encode(['user_id' => $id]));
    Router::json(['success' => true]);
});

$router->delete('/api/admin/users/{id}', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];

    if ($id === Auth::userId()) {
        Router::json(['error' => 'Cannot delete yourself'], 400);
    }

    Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    AuditLog::log('admin_delete_user', Auth::userId(), json_encode(['deleted_user_id' => $id]));
    Router::json(['success' => true]);
});

$router->post('/api/admin/users/{id}/toggle', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    Database::execute('UPDATE users SET enabled = NOT enabled WHERE id = ?', [$id]);
    AuditLog::log('admin_toggle_user', Auth::userId(), json_encode(['user_id' => $id]));
    Router::json(['success' => true]);
});

$router->post('/api/admin/users/{id}/reset-2fa', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    Database::execute('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?', [$id]);
    AuditLog::log('admin_reset_2fa', Auth::userId(), json_encode(['user_id' => $id]));
    Router::json(['success' => true]);
});

// ─── Admin Resources API ───
$router->get('/api/admin/resources', function () {
    Auth::requireAdmin();
    $resources = Database::fetchAll('SELECT * FROM resources ORDER BY id');
    Router::json(['resources' => $resources]);
});

$router->post('/api/admin/resources', function () {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();

    $name = trim($input['name'] ?? '');
    $addressListName = trim($input['address_list_name'] ?? '');
    $dstAddress = trim($input['dst_address'] ?? '');
    $dstPort = trim($input['dst_port'] ?? '');
    $protocol = $input['protocol'] ?? 'tcp';
    $timeoutMinutes = (int) ($input['timeout_minutes'] ?? 10);
    $description = trim($input['description'] ?? '');

    if (!$name || !$addressListName || !$dstAddress || !$dstPort) {
        Router::json(['error' => 'Name, address list, destination and port required'], 400);
    }

    $id = Database::insert(
        'INSERT INTO resources (name, description, address_list_name, dst_address, dst_port, protocol, timeout_minutes)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$name, $description, $addressListName, $dstAddress, $dstPort, $protocol, $timeoutMinutes]
    );

    AuditLog::log('admin_create_resource', Auth::userId(), json_encode(['resource' => $name]));
    Router::json(['success' => true, 'id' => $id]);
});

$router->put('/api/admin/resources/{id}', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    $input = Router::getInput();

    $fields = [];
    $values = [];

    foreach (['name', 'description', 'address_list_name', 'dst_address', 'dst_port', 'protocol'] as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = trim($input[$field]);
        }
    }
    if (isset($input['timeout_minutes'])) {
        $fields[] = 'timeout_minutes = ?';
        $values[] = (int) $input['timeout_minutes'];
    }
    if (isset($input['enabled'])) {
        $fields[] = 'enabled = ?';
        $values[] = (int) $input['enabled'];
    }

    if (!$fields) {
        Router::json(['error' => 'No fields to update'], 400);
    }

    $values[] = $id;
    Database::execute('UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?', $values);
    AuditLog::log('admin_update_resource', Auth::userId(), json_encode(['resource_id' => $id]));
    Router::json(['success' => true]);
});

$router->delete('/api/admin/resources/{id}', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    Database::execute('DELETE FROM resources WHERE id = ?', [$id]);
    AuditLog::log('admin_delete_resource', Auth::userId(), json_encode(['resource_id' => $id]));
    Router::json(['success' => true]);
});

// ─── Admin Permissions API ───
$router->get('/api/admin/permissions', function () {
    Auth::requireAdmin();
    $perms = Database::fetchAll(
        'SELECT p.*, u.username, r.name as resource_name
         FROM user_permissions p
         JOIN users u ON p.user_id = u.id
         JOIN resources r ON p.resource_id = r.id
         ORDER BY u.username, r.name'
    );
    Router::json(['permissions' => $perms]);
});

$router->post('/api/admin/permissions', function () {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();
    $userId = (int) ($input['user_id'] ?? 0);
    $resourceId = (int) ($input['resource_id'] ?? 0);

    if (!$userId || !$resourceId) {
        Router::json(['error' => 'User and resource required'], 400);
    }

    $existing = Database::fetchOne(
        'SELECT id FROM user_permissions WHERE user_id = ? AND resource_id = ?',
        [$userId, $resourceId]
    );
    if ($existing) {
        Router::json(['error' => 'Permission already exists'], 400);
    }

    $id = Database::insert(
        'INSERT INTO user_permissions (user_id, resource_id, granted_by) VALUES (?, ?, ?)',
        [$userId, $resourceId, Auth::userId()]
    );

    AuditLog::log('admin_grant_permission', Auth::userId(), json_encode(['user_id' => $userId, 'resource_id' => $resourceId]));
    Router::json(['success' => true, 'id' => $id]);
});

$router->delete('/api/admin/permissions/{id}', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    Database::execute('DELETE FROM user_permissions WHERE id = ?', [$id]);
    AuditLog::log('admin_revoke_permission', Auth::userId(), json_encode(['permission_id' => $id]));
    Router::json(['success' => true]);
});

// ─── Admin Sessions API ───
$router->get('/api/admin/sessions', function () {
    Auth::requireAdmin();
    $sessions = Database::fetchAll(
        'SELECT s.*, u.username, r.name as resource_name
         FROM sessions s
         JOIN users u ON s.user_id = u.id
         JOIN resources r ON s.resource_id = r.id
         WHERE s.status = "active"
         ORDER BY s.granted_at DESC'
    );
    Router::json(['sessions' => $sessions]);
});

$router->post('/api/admin/sessions/{id}/kill', function (array $params) {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $id = (int) $params['id'];
    $result = AccessManager::killSession($id, Auth::userId());
    Router::json($result, $result['success'] ? 200 : 400);
});

// ─── Admin Audit API ───
$router->get('/api/admin/audit', function () {
    Auth::requireAdmin();
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 50;
    $filters = [
        'user_id' => $_GET['user_id'] ?? null,
        'action' => $_GET['action'] ?? null,
        'from' => $_GET['from'] ?? null,
        'to' => $_GET['to'] ?? null,
    ];

    $result = AuditLog::getRecent($perPage, ($page - 1) * $perPage, $filters);
    $result['page'] = $page;
    $result['per_page'] = $perPage;
    $result['total_pages'] = (int) ceil($result['total'] / $perPage);
    Router::json($result);
});

// ─── Admin MikroTik Test ───
$router->get('/api/admin/mt-test', function () {
    Auth::requireAdmin();
    $mt = new MikroTikAPI();
    $result = $mt->testConnection();
    Router::json($result);
});

// ─── Admin Settings API ───
$router->get('/api/admin/settings', function () {
    Auth::requireAdmin();
    Router::json(['settings' => Settings::getAll()]);
});

$router->post('/api/admin/settings', function () {
    Auth::requireAdmin();
    if (!Router::verifyCsrf()) {
        Router::json(['error' => 'Invalid CSRF token'], 403);
    }
    $input = Router::getInput();
    $allowed = ['totp_issuer', 'portal_title', 'session_timeout', 'default_timeout_minutes'];

    foreach ($input as $key => $value) {
        if ($key === 'csrf_token') continue;
        if (in_array($key, $allowed, true)) {
            Settings::set($key, trim($value));
        }
    }

    AuditLog::log('admin_update_settings', Auth::userId(), json_encode($input));
    Router::json(['success' => true]);
});

// Dispatch
$router->dispatch();
