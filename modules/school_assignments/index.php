<?php
// modules/school_assignments/index.php
// School assignment list and add/edit form (same layout as Departments).
// This is where an employee is actually posted, as against the department
// they belong to.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// When ?edit=ID is present, pre-fill the form with that school.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM school_assignments WHERE school_assignment_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$schools = $pdo->query(
    'SELECT s.school_assignment_id, s.school_name, s.description,
            COUNT(e.employee_id) AS employee_count
     FROM school_assignments s
     LEFT JOIN employees e ON e.school_assignment_id = s.school_assignment_id
     GROUP BY s.school_assignment_id, s.school_name, s.description
     ORDER BY s.school_name'
)->fetchAll();

$page_title = 'School Assignments';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-building me-2"></i>School Assignments</h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <?= $editing ? 'Edit School Assignment' : 'Add School Assignment' ?>
            </div>
            <div class="card-body">
                <form method="post" action="save.php">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="school_assignment_id" value="<?= (int)$editing['school_assignment_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" for="school_name">School / Workplace <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="school_name" name="school_name"
                               maxlength="150" required data-uppercase value="<?= e($editing['school_name'] ?? '') ?>">
                        <div class="form-text">Where the employee is posted. Appears in the Add Employee form.</div>
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
            <div class="card-header bg-white fw-semibold">School Assignment List</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>School / Workplace</th>
                            <th>Description</th>
                            <th class="text-center">Employees</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$schools): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No school assignments yet. Add one using the form.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($schools as $school): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($school['school_name']) ?></td>
                            <td><?= e($school['description']) ?></td>
                            <td class="text-center">
                                <span class="badge text-bg-secondary"><?= (int)$school['employee_count'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="index.php?edit=<?= (int)$school['school_assignment_id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="delete.php" class="d-inline"
                                      data-confirm="Delete '<?= e($school['school_name']) ?>'? Employees posted there will have no school assignment.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="school_assignment_id" value="<?= (int)$school['school_assignment_id'] ?>">
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
