<?php
// modules/employment_statuses/index.php
// Employment status list and add/edit form (same layout as Departments).
// These are the nature of appointment (Permanent, Casual, ...), separate
// from the Applicant/Active/Inactive/Terminated record status.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// When ?edit=ID is present, pre-fill the form with that status.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM employment_statuses WHERE employment_status_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$statuses = $pdo->query(
    'SELECT s.employment_status_id, s.status_name, s.description,
            COUNT(e.employee_id) AS employee_count
     FROM employment_statuses s
     LEFT JOIN employees e ON e.employment_status_id = s.employment_status_id
     GROUP BY s.employment_status_id, s.status_name, s.description
     ORDER BY s.status_name'
)->fetchAll();

$page_title = 'Employment Status';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-person-badge me-2"></i>Employment Status</h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <?= $editing ? 'Edit Employment Status' : 'Add Employment Status' ?>
            </div>
            <div class="card-body">
                <form method="post" action="save.php">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="employment_status_id" value="<?= (int)$editing['employment_status_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="status_name">Status Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="status_name" name="status_name"
                               maxlength="50" required placeholder="e.g. Permanent"
                               value="<?= e($editing['status_name'] ?? '') ?>">
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
            <div class="card-header bg-white fw-semibold">Employment Status List</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Description</th>
                            <th class="text-center">Employees</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$statuses): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No employment statuses yet. Add one using the form.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($statuses as $st): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($st['status_name']) ?></td>
                            <td><?= e($st['description']) ?></td>
                            <td class="text-center">
                                <span class="badge text-bg-secondary"><?= (int)$st['employee_count'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="index.php?edit=<?= (int)$st['employment_status_id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="delete.php" class="d-inline"
                                      data-confirm="Delete employment status '<?= e($st['status_name']) ?>'? Employees with it will have no employment status.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employment_status_id" value="<?= (int)$st['employment_status_id'] ?>">
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
