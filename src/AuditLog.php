<?php
declare(strict_types=1);

class AuditLog
{
    public static function log(string $action, ?int $userId = null, ?string $details = null, ?string $sourceIp = null): void
    {
        $sourceIp = $sourceIp ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        Database::insert(
            'INSERT INTO audit_log (user_id, action, details, source_ip) VALUES (?, ?, ?, ?)',
            [$userId, $action, $details, $sourceIp]
        );
    }

    public static function getRecent(int $limit = 50, int $offset = 0, ?array $filters = null): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::fetchAll(
            "SELECT a.*, u.username
             FROM audit_log a
             LEFT JOIN users u ON a.user_id = u.id
             $whereClause
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $total = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM audit_log a $whereClause",
            $params
        );

        return [
            'rows' => $rows,
            'total' => (int) ($total['cnt'] ?? 0),
        ];
    }

    public static function getActions(): array
    {
        return Database::fetchAll(
            'SELECT DISTINCT action FROM audit_log ORDER BY action'
        );
    }
}
