<?php
declare(strict_types=1);

class AccessManager
{
    public static function getUserResources(int $userId): array
    {
        $resources = Database::fetchAll(
            'SELECT r.*, p.id as permission_id
             FROM resources r
             JOIN user_permissions p ON r.id = p.resource_id
             WHERE p.user_id = ? AND r.enabled = 1
             ORDER BY r.name',
            [$userId]
        );

        // Check active sessions for each resource
        foreach ($resources as &$resource) {
            $session = Database::fetchOne(
                'SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_left FROM sessions
                 WHERE user_id = ? AND resource_id = ? AND status = "active" AND expires_at > NOW()',
                [$userId, $resource['id']]
            );

            if ($session) {
                $resource['active'] = true;
                $resource['session_id'] = $session['id'];
                $resource['expires_at'] = $session['expires_at'];
                $resource['expires_in_seconds'] = max(0, (int) $session['seconds_left']);
            } else {
                $resource['active'] = false;
                $resource['session_id'] = null;
                $resource['expires_at'] = null;
                $resource['expires_in_seconds'] = 0;
            }
        }

        return $resources;
    }

    public static function grantAccess(int $userId, int $resourceId): array
    {
        // Verify permission
        $perm = Database::fetchOne(
            'SELECT * FROM user_permissions WHERE user_id = ? AND resource_id = ?',
            [$userId, $resourceId]
        );

        if (!$perm) {
            return ['success' => false, 'error' => 'No permission for this resource'];
        }

        $resource = Database::fetchOne('SELECT * FROM resources WHERE id = ? AND enabled = 1', [$resourceId]);
        if (!$resource) {
            return ['success' => false, 'error' => 'Resource not found or disabled'];
        }

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        $sourceIp = $user['wireguard_ip'];

        // If 0.0.0.0 (admin initial), use actual REMOTE_ADDR
        if ($sourceIp === '0.0.0.0') {
            $sourceIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        $comment = sprintf('user:%s session:auto', $user['username']);

        // Call MikroTik API
        $mt = new MikroTikAPI();
        $success = $mt->addToAddressList(
            $resource['address_list_name'],
            $sourceIp,
            $resource['timeout_minutes'],
            $comment
        );

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to add to MikroTik address list. Check API connection.'];
        }

        // Mark any old active sessions as expired
        Database::execute(
            'UPDATE sessions SET status = "expired" WHERE user_id = ? AND resource_id = ? AND status = "active"',
            [$userId, $resourceId]
        );

        // Create new session (use MySQL NOW() to avoid timezone mismatch)
        $timeoutMin = (int) $resource['timeout_minutes'];
        $sessionId = Database::insert(
            "INSERT INTO sessions (user_id, resource_id, source_ip, mikrotik_list_name, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL $timeoutMin MINUTE))",
            [$userId, $resourceId, $sourceIp, $resource['address_list_name']]
        );
        $expiresRow = Database::fetchOne('SELECT expires_at FROM sessions WHERE id = ?', [$sessionId]);
        $expiresAt = $expiresRow['expires_at'] ?? '';

        AuditLog::log('grant', $userId, json_encode([
            'resource' => $resource['name'],
            'list' => $resource['address_list_name'],
            'ip' => $sourceIp,
            'timeout' => $resource['timeout_minutes'],
        ]));

        return [
            'success' => true,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'expires_in_seconds' => $resource['timeout_minutes'] * 60,
        ];
    }

    public static function revokeAccess(int $userId, int $resourceId): array
    {
        $session = Database::fetchOne(
            'SELECT s.*, r.address_list_name, r.name as resource_name
             FROM sessions s
             JOIN resources r ON s.resource_id = r.id
             WHERE s.user_id = ? AND s.resource_id = ? AND s.status = "active"',
            [$userId, $resourceId]
        );

        if (!$session) {
            return ['success' => false, 'error' => 'No active session found'];
        }

        $mt = new MikroTikAPI();
        $mt->removeFromAddressList($session['address_list_name'], $session['source_ip']);

        Database::execute('UPDATE sessions SET status = "expired" WHERE id = ?', [$session['id']]);

        AuditLog::log('revoke', $userId, json_encode([
            'resource' => $session['resource_name'],
            'list' => $session['address_list_name'],
            'ip' => $session['source_ip'],
        ]));

        return ['success' => true];
    }

    public static function grantAll(int $userId): array
    {
        $resources = Database::fetchAll(
            'SELECT r.id FROM resources r
             JOIN user_permissions p ON r.id = p.resource_id
             WHERE p.user_id = ? AND r.enabled = 1',
            [$userId]
        );

        $results = [];
        foreach ($resources as $r) {
            $results[] = self::grantAccess($userId, $r['id']);
        }

        $failed = array_filter($results, fn($r) => !$r['success']);

        return [
            'success' => empty($failed),
            'granted' => count($results) - count($failed),
            'failed' => count($failed),
        ];
    }

    public static function revokeAll(int $userId): void
    {
        $sessions = Database::fetchAll(
            'SELECT s.*, r.address_list_name
             FROM sessions s
             JOIN resources r ON s.resource_id = r.id
             WHERE s.user_id = ? AND s.status = "active"',
            [$userId]
        );

        $mt = new MikroTikAPI();
        foreach ($sessions as $session) {
            $mt->removeFromAddressList($session['address_list_name'], $session['source_ip']);
            Database::execute('UPDATE sessions SET status = "expired" WHERE id = ?', [$session['id']]);
        }

        if ($sessions) {
            AuditLog::log('revoke_all', $userId, json_encode(['count' => count($sessions)]));
        }
    }

    public static function keepalive(int $userId): array
    {
        $sessions = Database::fetchAll(
            'SELECT s.*, r.address_list_name, r.timeout_minutes, r.name as resource_name
             FROM sessions s
             JOIN resources r ON s.resource_id = r.id
             WHERE s.user_id = ? AND s.status = "active"',
            [$userId]
        );

        if (empty($sessions)) {
            return ['success' => true, 'sessions' => []];
        }

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        $mt = new MikroTikAPI();
        $result = [];

        foreach ($sessions as $session) {
            $comment = sprintf('user:%s session:%d', $user['username'], $session['id']);
            $mt->refreshAddressListEntry(
                $session['address_list_name'],
                $session['source_ip'],
                $session['timeout_minutes'],
                $comment
            );

            $timeout = (int) $session['timeout_minutes'];
            Database::execute(
                "UPDATE sessions SET last_keepalive = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL $timeout MINUTE) WHERE id = ?",
                [$session['id']]
            );

            $result[] = [
                'resource_id' => (int) $session['resource_id'],
                'name' => $session['resource_name'],
                'expires_in_seconds' => $session['timeout_minutes'] * 60,
            ];
        }

        AuditLog::log('keepalive', $userId, json_encode(['count' => count($sessions)]));

        return ['success' => true, 'sessions' => $result];
    }

    public static function killSession(int $sessionId, int $adminUserId): array
    {
        $session = Database::fetchOne(
            'SELECT s.*, r.address_list_name, r.name as resource_name, u.username
             FROM sessions s
             JOIN resources r ON s.resource_id = r.id
             JOIN users u ON s.user_id = u.id
             WHERE s.id = ? AND s.status = "active"',
            [$sessionId]
        );

        if (!$session) {
            return ['success' => false, 'error' => 'Session not found or not active'];
        }

        $mt = new MikroTikAPI();
        $mt->removeFromAddressList($session['address_list_name'], $session['source_ip']);

        Database::execute('UPDATE sessions SET status = "killed" WHERE id = ?', [$sessionId]);

        AuditLog::log('admin_kill_session', $adminUserId, json_encode([
            'session_id' => $sessionId,
            'user' => $session['username'],
            'resource' => $session['resource_name'],
        ]));

        return ['success' => true];
    }
}
