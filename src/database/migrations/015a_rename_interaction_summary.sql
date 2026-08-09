-- 015a_rename_interaction_summary.sql
-- Rename interactions.summary -> interaction_subject to match code + schema.sql.
--
-- Numbered 015a, not 015: it previously shared the number 015 with
-- 015_create_inventory_movements.sql, which left their apply order decided by
-- how the filenames happened to sort rather than by anyone's intent. The two are
-- independent, so the suffix only makes the order deterministic. It cannot move
-- to 022 — migrations 018 and 020 reference the post-rename column name and so
-- must run after this one.
--
-- Idempotent + portable: only renames if `summary` still exists, so it is safe to
-- run whether or not the column was already renamed. Uses CHANGE (not the MySQL
-- 8.0-only "RENAME COLUMN") so it also works on MySQL 5.7 / MariaDB.
SET @needs := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interactions' AND COLUMN_NAME = 'summary');
SET @sql := IF(@needs > 0,
  'ALTER TABLE interactions CHANGE summary interaction_subject TEXT NOT NULL',
  'DO 0');
PREPARE _m011 FROM @sql;
EXECUTE _m011;
DEALLOCATE PREPARE _m011;
