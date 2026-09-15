-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.4 - Server data overrides (config / items / creatures editors)
--
-- admin/modules/config_editor.php, items_editor.php and creatures_editor.php
-- let admins edit or add single records on top of the bulk-uploaded
-- config.lua / items.xml / monster files handled by admin/modules/serverinfo.php.
-- Overrides are stored here and merged over the parsed cache at read time
-- (serverdata_apply_overrides(), called from serverdata_load()), so a later
-- re-upload of the source file never discards a manual edit.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `znote_serverdata_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(16) NOT NULL,
  `record_key` varchar(191) NOT NULL,
  `data` text NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` varchar(64) DEFAULT NULL,
  `updated_at` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_key` (`source`, `record_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
