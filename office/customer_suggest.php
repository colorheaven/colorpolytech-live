<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('customers.view');
header('Content-Type: application/json; charset=utf-8');

function cs_cols(): array {
    try { return table_exists('customers') ? table_columns('customers') : []; } catch (Throwable $e) { return []; }
}
function cs_pick(array $candidates): string {
    $cols = cs_cols();
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return '';
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || !table_exists('customers')) { echo json_encode([]); exit; }

$nameCol = cs_pick(['customer_name','name','company_name']);
$codeCol = cs_pick(['customer_code','code','customer_no']);
$mobileCol = cs_pick(['sms_number','contact_number','mobile','phone']);
if (!$nameCol) { echo json_encode([]); exit; }

$where = "`$nameCol` LIKE ?";
$params = ["%$q%"];
if ($codeCol) { $where .= " OR `$codeCol` LIKE ?"; $params[] = "%$q%"; }
if ($mobileCol) { $where .= " OR `$mobileCol` LIKE ?"; $params[] = "%$q%"; }

$select = "id, `$nameCol` AS name";
if ($codeCol) $select .= ", `$codeCol` AS code";
if ($mobileCol) $select .= ", `$mobileCol` AS mobile";

$st = db()->prepare("SELECT $select FROM customers WHERE ($where) ORDER BY `$nameCol` LIMIT 12");
$st->execute($params);
$rows = [];
foreach ($st->fetchAll() as $r) {
    $labelParts = [];
    if (!empty($r['code'])) $labelParts[] = $r['code'];
    if (!empty($r['mobile'])) $labelParts[] = $r['mobile'];
    $rows[] = [
        'id' => (int)$r['id'],
        'name' => (string)$r['name'],
        'label' => trim((string)$r['name'].($labelParts ? ' — '.implode(' | ', $labelParts) : '')),
    ];
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
