<?php
declare(strict_types=1);

class Settings
{
    private static array $cache = [];

    public static function get(string $key, string $default = ''): string
    {
        if (empty(self::$cache)) {
            self::loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?',
            [$key, $value, $value]
        );
        self::$cache[$key] = $value;
    }

    public static function getAll(): array
    {
        self::loadAll();
        return self::$cache;
    }

    private static function loadAll(): void
    {
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM app_settings');
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }
}
