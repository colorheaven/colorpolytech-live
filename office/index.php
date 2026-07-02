<?php
/**
 * Color Polytech Office ERP
 * Domain: office.colorpolytech.com
 */
declare(strict_types=1);

function office_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function office_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', office_is_https() ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_name('COLORPOLYTECH_OFFICE');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => office_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

office_start_secure_session();

$pageTitle = 'Color Polytech Office ERP';
$sessionStatus = session_status() === PHP_SESSION_ACTIVE ? 'OK' : 'Not started';
$httpsStatus = office_is_https() ? 'HTTPS detected' : 'HTTPS not detected';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 560px;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Office ERP</h1>
                <p class="text-muted">Starter page for customers, suppliers, products, orders, delivery, invoice, collection, reports, users, approval, ledger and dashboard.</p>
                <div class="alert alert-info mb-0">
                    <strong>Live login base check:</strong><br>
                    Session: <?= htmlspecialchars($sessionStatus, ENT_QUOTES, 'UTF-8') ?><br>
                    HTTPS/Cookie mode: <?= htmlspecialchars($httpsStatus, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
