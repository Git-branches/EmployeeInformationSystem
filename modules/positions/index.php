<?php
// modules/positions/index.php
// Position list and add/edit form (same layout as Departments).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// When ?edit=ID is present, pre-fill the form with that position.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM positions WHERE position_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$positions = $pdo->query(
    'SELECT p.position_id, p.position_name, p.description,
            COUNT(e.employee_id) AS employee_count
     FROM positions p
     LEFT JOIN employees e ON e.position_id = p.position_id
     GROUP BY p.position_id, p.position_name, p.description
     ORDER BY p.position_name'
)->fetchAll();

$page_title = 'Positions';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-briefcase me-2"></i>Positions</h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <?= $editing ? 'Edit Position' : 'Add Position' ?>
            </div>
            <div class="card-body">
                <form method="post" action="save.php">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="position_id" value="<?= (int)$editing['position_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="position_name">Position Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="position_name" name="position_name"
                               maxlength="100" required data-uppercase value="<?= e($editing['position_name'] ?? '') ?>">
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
            <div class="card-header bg-white fw-semibold">Position List</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Position</th>
                            <th>Description</th>
                            <th class="text-center">Employees</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$positions): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No positions yet. Add one using the form.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($positions as $pos): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($pos['position_name']) ?></td>
                            <td><?= e($pos['description']) ?></td>
                            <td class="text-center">
                                <span class="badge text-bg-secondary"><?= (int)$pos['employee_count'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="index.php?edit=<?= (int)$pos['position_id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="delete.php" class="d-inline"
                                      data-confirm="Delete position '<?= e($pos['position_name']) ?>'? Employees holding it will have no position.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="position_id" value="<?= (int)$pos['position_id'] ?>">
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
