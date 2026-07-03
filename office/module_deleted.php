<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
http_response_code(410);
$title = 'Module deleted';
include __DIR__.'/includes/header.php';
?>
<div class="alert alert-warning">
    <h4 class="alert-heading">Module deleted</h4>
    <p class="mb-0">This Office ERP module has been removed from the active user interface. Old records and database tables were not deleted.</p>
</div>
<a class="btn btn-primary" href="dashboard.php">Back to Dashboard</a>
<?php include __DIR__.'/includes/footer.php'; ?>
