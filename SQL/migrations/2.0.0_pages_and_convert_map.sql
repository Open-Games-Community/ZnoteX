-- Adds database-backed pages and legacy conversion mapping tables.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS `znote_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `title` varchar(100) NOT NULL,
  `body` mediumtext NOT NULL,
  `created` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  `player_id` int NOT NULL DEFAULT '0',
  `access` tinyint NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_convert_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `source_table` varchar(64) NOT NULL,
  `source_id` varchar(64) NOT NULL,
  `target_table` varchar(64) NOT NULL,
  `target_id` int NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_row` (`source`, `source_table`, `source_id`, `target_table`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_legacy_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `schema_sql` longtext NOT NULL,
  `row_count` int NOT NULL DEFAULT '0',
  `captured` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_table` (`source`, `table_name`)
) ENGINE=InnoDB;

SET @znote_add_schema_sql := IF(
  (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'znote_legacy_tables'
    AND COLUMN_NAME = 'schema_sql'
  ) = 0,
  'ALTER TABLE `znote_legacy_tables` ADD `schema_sql` longtext NULL AFTER `table_name`',
  'SELECT 1'
);
PREPARE znote_add_schema_sql_stmt FROM @znote_add_schema_sql;
EXECUTE znote_add_schema_sql_stmt;
DEALLOCATE PREPARE znote_add_schema_sql_stmt;

CREATE TABLE IF NOT EXISTS `znote_legacy_rows` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `source_pk` varchar(128) NOT NULL DEFAULT '',
  `row_json` longtext NOT NULL,
  `captured` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `source_table` (`source`, `table_name`),
  KEY `source_pk` (`source`, `table_name`, `source_pk`)
) ENGINE=InnoDB;
