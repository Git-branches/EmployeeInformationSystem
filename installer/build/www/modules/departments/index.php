<?php
// modules/departments/index.php
// Department list and add/edit form.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// When ?edit=ID is present, pre-fill the form with that department.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM departments WHERE department_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$departments = $pdo->query(
    'SELECT d.department_id, d.department_name, d.description,
            COUNT(e.employee_id) AS employee_count
     FROM departments d
     LEFT JOIN employees e ON e.department_id = d.department_id
     GROUP BY d.department_id, d.department_name, d.description
     ORDER BY d.department_name'
)->fetchAll();

$page_title = 'Departments';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2"></i>Departments</h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <?= $editing ? 'Edit Department' : 'Add Department' ?>
            </div>
            <div class="card-body">
                <form method="post" action="save.php">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="department_id" value="<?= (int)$editing['department_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="department_name">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="department_name" name="department_name"
                               maxlength="100" required value="<?= e($editing['department_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  maxlength="255"><?= e($editing['description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-1"></i><?= $editing ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($editing): ?>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Department List</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Department</th>
                            <th>Description</th>
                            <th class="text-center">Employees</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$departments): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No departments yet. Add one using the form.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($dept['department_name']) ?></td>
                            <td><?= e($dept['description']) ?></td>
                            <td class="text-center">
                                <span class="badge text-bg-secondary"><?= (int)$dept['employee_count'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="index.php?edit=<?= (int)$dept['department_id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="delete.php" class="d-inline"
                                      data-confirm="Delete department '<?= e($dept['department_name']) ?>'? Employees under it will become unassigned.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="department_id" value="<?= (int)$dept['department_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
