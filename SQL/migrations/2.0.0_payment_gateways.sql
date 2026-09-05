-- Stripe / Mercado Pago transaction ledger.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS `znote_payment_transactions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `reference` varchar(128) NOT NULL,
  `provider_reference` varchar(128) DEFAULT NULL,
  `account_id` int NOT NULL,
  `price` decimal(11,2) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `points` int NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `credited` tinyint NOT NULL DEFAULT '0',
  `test_mode` tinyint NOT NULL DEFAULT '0',
  `created_at` int NOT NULL,
  `updated_at` int NOT NULL,
  `credited_at` int DEFAULT NULL,
  `payload` longtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_reference_internal` (`provider`, `reference`),
  KEY `provider_reference_external` (`provider`, `provider_reference`),
  KEY `account_status` (`account_id`, `status`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_payment_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `event_id` varchar(128) NOT NULL,
  `provider_reference` varchar(128) DEFAULT NULL,
  `payment_reference` varchar(128) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'received',
  `payload` longtext,
  `received_at` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_event` (`provider`, `event_id`),
  KEY `payment_reference` (`provider`, `payment_reference`)
) ENGINE=InnoDB;
