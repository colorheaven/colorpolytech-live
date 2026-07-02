<?php
/**
 * Color Polytech Admin CMS
 * Domain: admin.colorpolytech.com
 */
declare(strict_types=1);

$pageTitle = 'Color Polytech Admin CMS';
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
        <div class="card shadow-sm mx-auto" style="max-width: 520px;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Admin CMS</h1>
                <p class="text-muted mb-0">Starter page for website content management. Login module will be added here.</p>
            </div>
        </div>
    </main>
</body>
</html>
