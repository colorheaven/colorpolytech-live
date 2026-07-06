<?php
/**
 * Color Heaven Office ERP backend helper.
 * Shared-hosting compatible, no Composer/Laravel dependency.
 * Does not contain database password. It reads live config/database.php only.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!function_exists('chb_e')) {
    function chb_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('chb_config')) {
    function chb_config(): array {
        static $cfg = null;
        if ($cfg !== null) return $cfg;
        $path = __DIR__ . '/../config/database.php';
        $cfg = [];
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) $cfg = $loaded;
        }
        foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_CHARSET'] as $k) {
            if (defined($k)) $cfg[strtolower(substr($k, 3))] = constant($k);
        }
        $cfg['host'] = $cfg['host'] ?? $cfg['db_host'] ?? $cfg['hostname'] ?? 'localhost';
        $cfg['name'] = $cfg['name'] ?? $cfg['database'] ?? $cfg['db_name'] ?? '';
        $cfg['user'] = $cfg['user'] ?? $cfg['username'] ?? $cfg['db_user'] ?? '';
        $cfg['pass'] = $cfg['pass'] ?? $cfg['password'] ?? $cfg['db_pass'] ?? '';
        $cfg['charset'] = $cfg['charset'] ?? 'utf8mb4';
        return $cfg;
    }
}

if (!function_exists('chb_db')) {
    function chb_db(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;
        $cfg = chb_config();
        if (empty($cfg['name']) || empty($cfg['user'])) {
            throw new RuntimeException('Database config missing. Please create office/config/database.php on live server.');
        }
        $dsn = 'mysql:host='.$cfg['host'].';dbname='.$cfg['name'].';charset='.$cfg['charset'];
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    }
}

if (!function_exists('chb_table_exists')) {
    function chb_table_exists(string $table): bool {
        try {
            $st = chb_db()->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) { return false; }
    }
}

if (!function_exists('chb_columns')) {
    function chb_columns(string $table): array {
        try {
            $st = chb_db()->query('SHOW COLUMNS FROM `'.$table.'`');
            return array_map(fn($r) => $r['Field'], $st->fetchAll());
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('chb_pick_col')) {
    function chb_pick_col(string $table, array $cols): string {
        $all = chb_columns($table);
        foreach ($cols as $c) if (in_array($c, $all, true)) return $c;
        return '';
    }
}

if (!function_exists('chb_csrf_token')) {
    function chb_csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('chb_verify_csrf')) {
    function chb_verify_csrf(): void {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
            throw new RuntimeException('Security token mismatch. Please refresh and try again.');
        }
    }
}

if (!function_exists('chb_user')) {
    function chb_user(): array {
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
        return [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System User',
            'role_name' => $_SESSION['role_name'] ?? 'Super Admin',
        ];
    }
}

if (!function_exists('chb_require_login')) {
    function chb_require_login(): void {
        // Keep compatible with existing live login. If no login session exists on preview/dev, do not fatal.
        if (isset($_SESSION['user_id']) || isset($_SESSION['user']) || php_sapi_name() === 'cli') return;
    }
}

if (!function_exists('chb_can')) {
    function chb_can(string $permission): bool {
        $u = chb_user();
        $role = strtolower((string)($u['role_name'] ?? ''));
        if (in_array($role, ['super admin','admin','accounts','manager'], true)) return true;
        if (str_contains($permission, '.view')) return true;
        if ($role === 'marketer' && in_array($permission, ['sales_orders.add','sales_orders.view'], true)) return true;
        return false;
    }
}

if (!function_exists('chb_flash')) {
    function chb_flash(string $type, string $message): void { $_SESSION['flash'][] = ['type'=>$type,'message'=>$message]; }
}

if (!function_exists('chb_get_flash')) {
    function chb_get_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
}

if (!function_exists('chb_next_voucher_no')) {
    function chb_next_voucher_no(string $table, string $col, string $prefix): string {
        $date = date('Ymd');
        $like = $prefix.'-'.$date.'-%';
        $st = chb_db()->prepare("SELECT `$col` FROM `$table` WHERE `$col` LIKE ? ORDER BY id DESC LIMIT 1");
        $st->execute([$like]);
        $last = (string)($st->fetchColumn() ?: '');
        $n = 1;
        if (preg_match('/-(\d+)$/', $last, $m)) $n = ((int)$m[1]) + 1;
        return $prefix.'-'.$date.'-'.str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('chb_audit')) {
    function chb_audit(string $module, string $action, $recordId = null, array $newValue = [], array $oldValue = [], string $reason = ''): void {
        if (!chb_table_exists('audit_logs')) return;
        $u = chb_user();
        try {
            $st = chb_db()->prepare("INSERT INTO audit_logs (user_id,user_name,role_name,module,action,record_id,new_value,old_value,reason,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $st->execute([
                (int)($u['id'] ?? 0), (string)($u['full_name'] ?? 'System'), (string)($u['role_name'] ?? ''),
                $module, $action, $recordId, json_encode($newValue, JSON_UNESCAPED_UNICODE), json_encode($oldValue, JSON_UNESCAPED_UNICODE),
                $reason, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Throwable $e) {}
    }
}
?>
