<?php
// modules/requirements/types.php
// Requirement types maintenance (the master document checklist).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$defaults = [
    'SSS Number/E-1 Form', 'PhilHealth MDR', 'Pag-IBIG MDF', 'TIN/BIR Form 1902',
    'NBI Clearance', 'Barangay Clearance', 'Health Certificate', 'PSA Birth Certificate',
    'Resume/Biodata', '2x2 ID Pictures',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'seed') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO requirement_types (requirement_name) VALUES (?)');
        foreach ($defaults as $name) {
            $stmt->execute([$name]);
        }
        flash_set('success', 'Default requirement list added. Edit or deactivate items as needed.');
    } elseif ($action === 'save') {
        $id   = (int)($_POST['requirement_type_id'] ?? 0);
        $name = trim((string)($_POST['requirement_name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? '')) ?: null;
        if ($name === '') {
            flash_set('danger', 'Requirement name is required.');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE requirement_types SET requirement_name=?, description=? WHERE requirement_type_id=?')
                        ->execute([$name, $desc, $id]);
                    flash_set('success', 'Requirement updated.');
                } else {
                    $pdo->prepare('INSERT INTO requirement_types (requirement_name, description) VALUES (?,?)')
                        ->execute([$name, $desc]);
                    flash_set('success', "Requirement \"$name\" added.");
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash_set('danger', "A requirement named \"$name\" already exists.");
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['requirement_type_id'] ?? 0);
        $pdo->prepare('UPDATE requirement_types SET is_active = 1 - is_active WHERE requirement_type_id = ?')->execute([$id]);
        flash_set('success', 'Requirement status changed.');
    }
    redirect('/modules/requirements/types.php');
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM requirement_types WHERE requirement_type_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$types = $pdo->query(
    'SELECT rt.*, COUNT(er.employee_requirement_id) AS in_use
     FROM requirement_types rt
     LEFT JOIN employee_requirements er ON er.requirement_type_id = rt.requirement_type_id
     GROUP BY rt.requirement_type_id ORDER BY rt.requirement_name'
)->fetchAll();

$page_title = 'Requirement Types';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-card-checklist me-2"></i>Requirement Types</h1>
    <div>
        <a href="monitor.php" class="btn btn-outline-secondary"><i class="bi bi-clipboard-data me-1"></i>Monitor</a>
        <?php if (!$types): ?>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="seed">
            <button class="btn btn-danger"><i class="bi bi-magic me-1"></i>Add Default PH Requirements</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= $editing ? 'Edit Requirement' : 'Add Requirement' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editing): ?>
                        <input type="hidden" name="requirement_type_id" value="<?= (int)$editing['requirement_type_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Requirement Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="requirement_name" maxlength="100" required
                               value="<?= e($editing['requirement_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" maxlength="255"><?= e($editing['description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-save me-1"></i><?= $editing ? 'Update' : 'Save' ?></button>
                    <?php if ($editing): ?><a href="types.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Requirement List</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Requirement</th><th>Description</th><th class="text-center">Tracked For</th><th class="text-center">Active</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$types): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            No requirements defined. Use the form or the "Add Default PH Requirements" button.
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($types as $t): ?>
                        <tr class="<?= $t['is_active'] ? '' : 'table-secondary' ?>">
                            <td class="fw-semibold"><?= e($t['requirement_name']) ?></td>
                            <td class="small"><?= e($t['description'] ?? '') ?></td>
                            <td class="text-center"><span class="badge text-bg-secondary"><?= (int)$t['in_use'] ?> employee(s)</span></td>
                            <td class="text-center">
                                <?php if ($t['is_active']): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="types.php?edit=<?= (int)$t['requirement_type_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="requirement_type_id" value="<?= (int)$t['requirement_type_id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" title="<?= $t['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi bi-power"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            New employees automatically get a checklist row for every <em>active</em> requirement.
            Deactivating hides a requirement from checklists without deleting history.
        </p>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
