-- 006: school assignment
-- Where an employee actually works, kept as an administrator-managed list in
-- the same shape as departments and positions. Separate from the department:
-- the department is the office they belong to, this is the station they are
-- posted at. Existing records are left unassigned.

CREATE TABLE IF NOT EXISTS school_assignments (
    school_assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_name          VARCHAR(150) NOT NULL,
    description          VARCHAR(255) NULL,
    PRIMARY KEY (school_assignment_id),
    UNIQUE KEY uq_school_assignments_name (school_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees
    ADD COLUMN school_assignment_id INT UNSIGNED NULL AFTER position,
    ADD COLUMN school_assignment VARCHAR(150) NULL
        COMMENT 'Name as at the time of saving, so reports survive a rename' AFTER school_assignment_id,
    ADD CONSTRAINT fk_employees_school_assignment
        FOREIGN KEY (school_assignment_id) REFERENCES school_assignments (school_assignment_id)
        ON UPDATE CASCADE ON DELETE SET NULL;
