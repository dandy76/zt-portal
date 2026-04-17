<?php
/**
 * Cleanup script — runs via cron every minute
 * Marks expired sessions in the DB for accurate status display.
 * The actual firewall cleanup is handled by MikroTik's built-in timeout.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';

Config::getInstance();

try {
    // Mark expired sessions
    $count = Database::execute(
        'UPDATE sessions SET status = "expired" WHERE status = "active" AND expires_at < NOW()'
    );

    if ($count > 0) {
        echo date('[Y-m-d H:i:s]') . " Cleaned up $count expired sessions\n";
    }

    // Cleanup old rate limit entries (older than 1 hour)
    Database::execute(
        'DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );

} catch (Exception $e) {
    echo date('[Y-m-d H:i:s]') . " ERROR: " . $e->getMessage() . "\n";
}
