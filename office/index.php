<?php
/**
 * Office ERP front controller.
 * Root URL should open the real ERP, not the old starter/status page.
 */
declare(strict_types=1);

$targets = [
    __DIR__ . '/dashboard.php' => 'dashboard.php',
    __DIR__ . '/login.php' => 'login.php',
];

foreach ($targets as $file => $url) {
    if (is_file($file)) {
        header('Location: ' . $url, true, 302);
        exit;
    }
}

http_response_code(503);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Office ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 560px;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Office ERP</h1>
                <div class="alert alert-warning mb-0">
                    ERP files are not fully deployed. Please deploy the latest GitHub commit to the office subdomain folder.
                </div>
            </div>
        </div>
    </main>
</body>
</html>
