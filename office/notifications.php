<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
$title = 'Notifications';

function n_cols(): array { try { return table_exists('notifications') ? table_columns('notifications') : []; } catch (Throwable $e) { return []; } }
function n_has(string $col): bool { return in_array($col, n_cols(), true); }
function n_pick(array $cols): string { $all=n_cols(); foreach($cols as $c){ if(in_array($c,$all,true)) return $c; } return ''; }
function n_scope_where(string &$where, array &$params): void {
    $u = user();
    if (n_has('user_id') && !empty($u['id'])) {
        $where .= ' AND (user_id IS NULL OR user_id=?)';
        $params[] = $u['id'];
    }
}
function n_mark_read(?int $id=null): int {
    if (!table_exists('notifications') || !n_has('is_read')) return 0;
    $sets = ['is_read=1'];
    if (n_has('read_at')) $sets[] = 'read_at=NOW()';
    $where = 'is_read=0';
    $params = [];
    n_scope_where($where, $params);
    if ($id) { $where .= ' AND id=?'; $params[] = $id; }
    $st = db()->prepare('UPDATE notifications SET '.implode(',', $sets).' WHERE '.$where);
    $st->execute($params);
    return $st->rowCount();
}
function n_unread_count(): int {
    if (!table_exists('notifications') || !n_has('is_read')) return 0;
    $where = 'is_read=0'; $params=[]; n_scope_where($where,$params);
    return table_count('notifications',$where,$params);
}

$msg = '';
$viewId = (int)($_GET['id'] ?? 0);
if ($viewId > 0) {
    $changed = n_mark_read($viewId);
    $msg = $changed ? 'Notification marked as read.' : 'Notification already read.';
} elseif (isset($_GET['mark_all']) || !isset($_GET['unread_only'])) {
    $changed = n_mark_read(null);
    if ($changed > 0) $msg = $changed.' notification(s) marked as read.';
}

$titleCol = n_pick(['title','subject','module','type']);
$messageCol = n_pick(['message','body','description','details']);
$linkCol = n_pick(['link','url','action_url']);
$createdCol = n_pick(['created_at','sent_at','date']);
$moduleCol = n_pick(['module','type']);

$where='1=1'; $params=[]; n_scope_where($where,$params);
if (isset($_GET['unread_only']) && n_has('is_read')) $where.=' AND is_read=0';
$order = $createdCol ? "`$createdCol` DESC, id DESC" : 'id DESC';
$rows=[];
if (table_exists('notifications')) {
    $st = db()->prepare("SELECT * FROM notifications WHERE $where ORDER BY $order LIMIT 200");
    $st->execute($params);
    $rows = $st->fetchAll();
}
$unread = n_unread_count();
include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3>Notifications</h3><p class="text-muted mb-0">Opening this page marks all visible unread notifications as read. Opening a single notification marks only that one as read.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="notifications.php?unread_only=1">Unread Only</a><a class="btn btn-primary" href="notifications.php?mark_all=1">View All / Mark All Read</a></div>
</div>
<?php if($msg): ?><div class="alert alert-success"><?=e($msg)?> Current unread badge count: <?=e($unread)?></div><?php endif; ?>
<?php if(!table_exists('notifications')): ?>
    <div class="alert alert-warning">Notifications table not found.</div>
<?php else: ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Status</th><th>Notification</th><th>Module</th><th>Date</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php foreach($rows as $r): $isRead = !empty($r['is_read']); $link=$linkCol?trim((string)($r[$linkCol]??'')):''; ?>
        <tr class="<?=$isRead?'':'table-warning'?>">
            <td><?=$isRead?'<span class="badge text-bg-secondary">Read</span>':'<span class="badge text-bg-danger">Unread</span>'?></td>
            <td><strong><?=e($titleCol?($r[$titleCol]??'Notification'):'Notification')?></strong><br><small class="text-muted"><?=e($messageCol?($r[$messageCol]??''):'')?></small></td>
            <td><?=e($moduleCol?($r[$moduleCol]??''):'')?></td>
            <td><?=e($createdCol?($r[$createdCol]??''):'')?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="notifications.php?id=<?=$r['id']?>">View</a><?php if($link): ?> <a class="btn btn-sm btn-outline-secondary" href="<?=e($link)?>">Open</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No notifications found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
