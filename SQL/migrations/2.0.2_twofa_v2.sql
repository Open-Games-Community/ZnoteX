-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.2 - Website 2FA v2
--
-- Independent of the game engine: nothing here touches `accounts`, so it works
-- the same on TFS, Canary, otHire or BlackTek. The old TFS-tied system
-- (accounts.secret, znote_accounts.secret) is untouched and keeps working.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `znote_2fa` (
  `account_id` int NOT NULL,
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `email_otp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `recovery_codes` text,
  `session_version` int NOT NULL DEFAULT '1',
  `updated_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_2fa_email_codes` (
  `account_id` int NOT NULL,
  `code_hash` varchar(64) NOT NULL,
  `expires_at` int NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_2fa_trusted_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int NOT NULL,
  `expires_at` int NOT NULL,
  `last_used_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
