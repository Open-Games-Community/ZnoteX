-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.3 - Login attempt tracking / IP lockout
--
-- Every login attempt (success or failure) is logged by IP. Admin Panel >
-- Security > Login Protection reads this to lock an IP out after too many
-- failures in a short window. Rows are pruned automatically as new ones are
-- written, so this table never grows without bound.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `znote_login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `username` varchar(32) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
