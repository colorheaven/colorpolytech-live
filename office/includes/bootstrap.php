<?php
/**
 * Legacy Office ERP compatibility bootstrap.
 *
 * Older Office pages call db(), table_exists(), require_login(), setting(), etc.
 * Newer pages use chb_* helpers. Keep both APIs backed by the same PDO
 * connection so live modules do not fail on missing bootstrap/database helpers.
 */
require_once __DIR__ . '/ch_backend.php';

if (!function_exists('e')) {
    function e($value): string { return chb_e($value); }
}

if (!function_exists('db')) {
    function db(): PDO { return chb_db(); }
}

if (!function_exists('table_exists')) {
    function table_exists(string $table): bool { return chb_table_exists($table); }
}

if (!function_exists('table_columns')) {
    function table_columns(string $table): array { return chb_columns($table); }
}

if (!function_exists('column_exists')) {
    function column_exists(string $table, string $column): bool {
        return in_array($column, table_columns($table), true);
    }
}

if (!function_exists('table_count')) {
    function table_count(string $table, string $where = '1=1', array $params = []): int {
        if (!table_exists($table)) return 0;
        $st = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
        $st->execute($params);
        return (int)$st->fetchColumn();
    }
}

if (!function_exists('user')) {
    function user(): array { return chb_user(); }
}

if (!function_exists('can')) {
    function can(string $permission): bool { return chb_can($permission); }
}

if (!function_exists('require_perm')) {
    function require_perm(string $permission): void {
        if (can($permission)) return;
        http_response_code(403);
        echo 'Permission denied.';
        exit;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void {
        chb_require_login();
    }
}

if (!function_exists('setting')) {
    function setting(string $key, $default = '') {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];

        $lookups = [
            ['settings', ['setting_key', 'key', 'name'], ['setting_value', 'value']],
            ['app_settings', ['setting_key', 'key', 'name'], ['setting_value', 'value']],
            ['system_settings', ['setting_key', 'key', 'name'], ['setting_value', 'value']],
            ['company_settings', ['setting_key', 'key', 'name'], ['setting_value', 'value']],
        ];

        foreach ($lookups as [$table, $keyCols, $valueCols]) {
            try {
                if (!table_exists($table)) continue;
                $cols = table_columns($table);
                $keyCol = '';
                $valueCol = '';
                foreach ($keyCols as $candidate) {
                    if (in_array($candidate, $cols, true)) { $keyCol = $candidate; break; }
                }
                foreach ($valueCols as $candidate) {
                    if (in_array($candidate, $cols, true)) { $valueCol = $candidate; break; }
                }
                if ($keyCol === '' || $valueCol === '') continue;
                $st = db()->prepare("SELECT `$valueCol` FROM `$table` WHERE `$keyCol`=? LIMIT 1");
                $st->execute([$key]);
                $value = $st->fetchColumn();
                if ($value !== false && $value !== null) {
                    $cache[$key] = $value;
                    return $value;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        $cache[$key] = $default;
        return $default;
    }
}
?>
