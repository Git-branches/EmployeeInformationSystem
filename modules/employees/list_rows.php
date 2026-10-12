<?php
// modules/employees/list_rows.php
// The <tbody> rows of the employee list. Rendered inside the page by
// index.php and on its own by search.php, which replaces them as the
// administrator types in the search box.

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}
?>
<?php if (!$employees): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
<?php endif; ?>
<?php foreach ($employees as $emp): ?>
    <tr>
        <td>
            <div class="d-flex align-items-center gap-2">
                <?php if ($emp['photo_path']): ?>
                    <img src="<?= BASE_URL . '/' . e($emp['photo_path']) ?>" class="rounded-circle" width="36" height="36" style="object-fit:cover" alt="">
                <?php else: ?>
                    <i class="bi bi-person-circle fs-3 text-secondary"></i>
                <?php endif; ?>
                <span class="fw-semibold"><?= e(employee_display_name($emp)) ?></span>
            </div>
        </td>
        <td><?= e($emp['employee_no'] ?? '—') ?></td>
        <td><?= e($emp['department_name'] ?? 'Unassigned') ?></td>
        <td><?= e($emp['position'] ?? '—') ?></td>
        <td class="text-center">
            <span class="badge <?= $emp['applicant_type'] === 'New' ? 'text-bg-info' : 'text-bg-primary' ?>">
                <?= e($emp['applicant_type']) ?>
            </span>
        </td>
        <td class="text-center">
            <span class="badge <?= $status_badge[$emp['employment_status']] ?>"><?= e($emp['employment_status']) ?></span>
        </td>
        <td class="text-center">
            <?php if ((int)$emp['pending_reqs'] > 0): ?>
                <span class="badge text-bg-danger"><?= (int)$emp['pending_reqs'] ?> pending</span>
            <?php else: ?>
                <span class="badge text-bg-success">Complete</span>
            <?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
            <a href="view.php?id=<?= (int)$emp['employee_id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
            <a href="edit.php?id=<?= (int)$emp['employee_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" action="delete.php" class="d-inline"
                  data-confirm="Delete <?= e(employee_display_name($emp)) ?>? This also removes their requirements records.">
                <?= csrf_field() ?>
                <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
