<?php
declare(strict_types=1);

class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->config = [
            'db_host'     => $this->env('DB_HOST', 'db'),
            'db_name'     => $this->env('DB_NAME', 'zt_portal'),
            'db_user'     => $this->env('DB_USER', 'ztportal'),
            'db_pass'     => $this->env('DB_PASS', ''),
            'mt_api_url'  => $this->env('MT_API_URL', 'https://10.0.40.1/rest'),
            'mt_api_user' => $this->env('MT_API_USER', 'api_portal'),
            'mt_api_pass' => $this->env('MT_API_PASS', ''),
            'app_secret'  => $this->env('APP_SECRET', ''),
            'timezone'    => 'Europe/Athens',
            'session_timeout' => 1800, // 30 minutes
            'rate_limit_attempts' => 5,
            'rate_limit_window'   => 900,  // 15 minutes
            'rate_limit_block'    => 1800, // 30 minutes
        ];

        date_default_timezone_set($this->config['timezone']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    private function env(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
