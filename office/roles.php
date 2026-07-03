<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('roles.view');
$title = 'Roles & Permissions';

function role_slug_from_name($name){
    $slug = strtolower(trim((string)$name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'role';
}

function role_permission_ids($roleId){
    if (!table_exists('role_permissions')) return [];
    $s = db()->prepare('SELECT permission_id FROM role_permissions WHERE role_id=? AND allowed=1');
    $s->execute([(int)$roleId]);
    return array_map('intval', array_column($s->fetchAll(), 'permission_id'));
}

function save_role_permissions($roleId, array $permissionIds){
    db()->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([(int)$roleId]);
    if (!$permissionIds) return;
    $ins = db()->prepare('INSERT INTO role_permissions(role_id,permission_id,allowed) VALUES(?,?,1)');
    foreach (array_values(array_unique(array_map('intval', $permissionIds))) as $pid) {
        if ($pid > 0) $ins->execute([(int)$roleId, $pid]);
    }
}

$action = $_GET['action'] ?? 'list';
$msg = '';

try {
    if ($action === 'delete') {
        check_csrf();
        require_perm('roles.delete');
        $id = (int)($_GET['id'] ?? 0);
        $s = db()->prepare('SELECT * FROM roles WHERE id=? LIMIT 1');
        $s->execute([$id]);
        $role = $s->fetch();
        if (!$role) throw new Exception('Role not found.');
        if (!empty($role['is_system'])) throw new Exception('System roles cannot be deleted.');
        if (table_count('users', 'role_id=?', [$id]) > 0) throw new Exception('This role has assigned users.');
        db()->beginTransaction();
        db()->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$id]);
        db()->prepare('DELETE FROM roles WHERE id=?')->execute([$id]);
        log_action('Roles','delete',$role,'',$id);
        db()->commit();
        header('Location: roles.php?msg=deleted'); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $postAction = $_POST['post_action'] ?? '';
        if ($postAction === 'save_role') {
            $id = (int)($_POST['id'] ?? 0);
            require_perm('roles.'.($id ? 'edit' : 'add'));
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $slug = role_slug_from_name($_POST['slug'] ?? $name);
            if ($name === '') throw new Exception('Role name is required.');
            if ($id) {
                $old = db()->prepare('SELECT * FROM roles WHERE id=? LIMIT 1');
                $old->execute([$id]);
                $oldRole = $old->fetch();
                if (!$oldRole) throw new Exception('Role not found.');
                db()->prepare('UPDATE roles SET name=?, slug=?, description=? WHERE id=?')->execute([$name,$slug,$description,$id]);
                log_action('Roles','edit',$oldRole,$_POST,$id);
                $msg = 'updated';
            } else {
                db()->prepare('INSERT INTO roles(name,slug,description,is_system) VALUES(?,?,?,0)')->execute([$name,$slug,$description]);
                $id = (int)db()->lastInsertId();
                log_action('Roles','add','',$_POST,$id);
                $msg = 'created';
            }
            header('Location: roles.php?msg='.$msg.'&role='.$id); exit;
        }

        if ($postAction === 'save_permissions') {
            require_perm('roles.edit');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $s = db()->prepare('SELECT * FROM roles WHERE id=? LIMIT 1');
            $s->execute([$roleId]);
            $role = $s->fetch();
            if (!$role) throw new Exception('Role not found.');
            $ids = $_POST['permissions'] ?? [];
            if (!is_array($ids)) $ids = [];
            save_role_permissions($roleId, $ids);
            log_action('Roles','permissions_update','role_id='.$roleId,'permissions='.count($ids),$roleId);
            header('Location: roles.php?msg=permissions_saved&role='.$roleId); exit;
        }
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $msg = $e->getMessage();
}

$editRole = null;
if ($action === 'form' && !empty($_GET['id'])) {
    require_perm('roles.edit');
    $s = db()->prepare('SELECT * FROM roles WHERE id=? LIMIT 1');
    $s->execute([(int)$_GET['id']]);
    $editRole = $s->fetch();
}

$roles = db()->query('SELECT r.*, COUNT(u.id) user_count FROM roles r LEFT JOIN users u ON u.role_id=r.id GROUP BY r.id ORDER BY r.id')->fetchAll();
$perms = db()->query('SELECT id, COALESCE(code, CONCAT(module,\'.\',action)) code, module, action, label FROM permissions ORDER BY module, action')->fetchAll();
$grouped = [];
$disabledPermissionModules = [
    'payments','expenses','purchases','cash_bank','cash_accounts','bank_accounts','expense_heads','journal_vouchers','contra_vouchers','profit_loss'
];
foreach ($perms as $p) {
    if (in_array((string)$p['module'], $disabledPermissionModules, true)) continue;
    $grouped[$p['module']][] = $p;
}
$selectedRoleId = (int)($_GET['role'] ?? ($roles[0]['id'] ?? 0));
$selectedIds = $selectedRoleId ? role_permission_ids($selectedRoleId) : [];
$selectedMap = array_fill_keys($selectedIds, true);

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3>Roles & Permissions</h3><p class="text-muted mb-0">Admin-controlled role matrix with disabled modules hidden.</p></div>
    <?php if (can('roles.add')): ?><a class="btn btn-primary" href="roles.php?action=form"><i class="bi bi-plus-lg"></i> Add Role</a><?php endif; ?>
</div>
<?php if ($msg): ?><div class="alert alert-<?=str_contains($msg,'not') || str_contains($msg,'cannot') || str_contains($msg,'required') ? 'danger' : 'success'?>"><?=e($msg)?></div><?php endif; ?>
<?php if (isset($_GET['msg'])): ?><div class="alert alert-success">Role <?=e(str_replace('_',' ', $_GET['msg']))?>.</div><?php endif; ?>

<?php if ($action === 'form'): ?>
<div class="card mb-4"><div class="card-body">
    <h4><?= $editRole ? 'Edit Role' : 'Create Role' ?></h4>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="post_action" value="save_role">
        <input type="hidden" name="id" value="<?=e($editRole['id'] ?? 0)?>">
        <div class="col-md-4"><label class="form-label">Role Name</label><input class="form-control" name="name" value="<?=e($editRole['name'] ?? '')?>" required></div>
        <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" value="<?=e($editRole['slug'] ?? '')?>" placeholder="auto-generated if blank"></div>
        <div class="col-md-4"><label class="form-label">Description</label><input class="form-control" name="description" value="<?=e($editRole['description'] ?? '')?>"></div>
        <div class="col-12"><button class="btn btn-primary">Save Role</button> <a class="btn btn-light" href="roles.php">Cancel</a></div>
    </form>
</div></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Role</th><th>Users</th><th>System</th><th></th></tr></thead><tbody>
            <?php foreach ($roles as $r): ?>
                <tr class="<?=((int)$r['id'] === $selectedRoleId) ? 'table-primary' : ''?>">
                    <td><a href="roles.php?role=<?=$r['id']?>" class="text-decoration-none"><strong><?=e($r['name'])?></strong><br><small class="text-muted"><?=e($r['description'])?></small></a></td>
                    <td><?=e($r['user_count'])?></td><td><?=!empty($r['is_system']) ? 'Yes' : 'No'?></td>
                    <td class="text-nowrap"><?php if (can('roles.edit')): ?><a class="btn btn-sm btn-outline-primary" href="roles.php?action=form&id=<?=$r['id']?>">Edit</a><?php endif; ?> <?php if (can('roles.delete') && empty($r['is_system'])): ?><a class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this role?')" href="roles.php?action=delete&id=<?=$r['id']?>&csrf=<?=csrf()?>">Delete</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody></table></div></div>
    </div>
    <div class="col-lg-8">
        <form method="post" class="card"><div class="card-body">
            <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="post_action" value="save_permissions">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h4 class="mb-1">Permission Matrix</h4><select class="form-select" name="role_id" onchange="window.location='roles.php?role='+this.value"><?php foreach ($roles as $r): ?><option value="<?=$r['id']?>" <?=((int)$r['id']===$selectedRoleId)?'selected':''?>><?=e($r['name'])?></option><?php endforeach; ?></select></div><?php if (can('roles.edit')): ?><div class="align-self-end"><button class="btn btn-primary">Save Permissions</button></div><?php endif; ?></div>
            <div class="row g-3"><?php foreach ($grouped as $module => $items): ?><div class="col-md-6"><div class="border rounded p-3 h-100"><strong><?=e(ucwords(str_replace('_',' ', $module)))?></strong><div class="row g-1 mt-2"><?php foreach ($items as $p): ?><label class="col-6 small form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?=$p['id']?>" <?=!empty($selectedMap[(int)$p['id']]) ? 'checked' : ''?>><span class="form-check-label"><?=e($p['action'])?></span></label><?php endforeach; ?></div></div></div><?php endforeach; ?></div>
        </div></form>
    </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
