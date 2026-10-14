-- 005: administrator profile details
-- The signed-in administrator keeps a designation and an e-mail address
-- alongside their name. The designation is printed under the signature line
-- of every report, so it is no longer the hardcoded word "Administrator".
-- Existing rows keep their username, name and password untouched.

ALTER TABLE users
    ADD COLUMN email    VARCHAR(100) NULL AFTER full_name,
    ADD COLUMN position VARCHAR(100) NULL AFTER email;
