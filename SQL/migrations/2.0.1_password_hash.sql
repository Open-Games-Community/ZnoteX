-- Website-only password modernization: accounts.password (SHA1, read by the
-- game server) is never touched. This column holds a modern password_hash()
-- value used only to verify website logins; it is filled in lazily as each
-- account logs in or changes its password.
--
-- Only needed for databases created BEFORE 2.0.1. A fresh import of
-- SQL/znote_schema.sql already contains this column.

ALTER TABLE `znote_accounts`
  ADD COLUMN `password_hash` varchar(255) DEFAULT NULL AFTER `secret`;
