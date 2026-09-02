-- Run once when the plugin is enabled.
-- Statements must be idempotent: a plugin can be disabled and re-enabled, and
-- ZnoteX does not track which of them have already run.

CREATE TABLE IF NOT EXISTS `znote_coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'points' COMMENT 'points | percent',
  `value` int NOT NULL DEFAULT '0' COMMENT 'points credited, or percent off',
  `uses_max` int NOT NULL DEFAULT '1' COMMENT '0 = unlimited',
  `uses_done` int NOT NULL DEFAULT '0',
  `expires_at` int NOT NULL DEFAULT '0' COMMENT '0 = never',
  `created_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB;

-- One row per (coupon, account). The unique key is what actually stops a code
-- being redeemed twice; the check in PHP only produces a nicer message.
CREATE TABLE IF NOT EXISTS `znote_coupon_uses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coupon_id` int NOT NULL,
  `account_id` int NOT NULL,
  `used_at` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `once_per_account` (`coupon_id`, `account_id`),
  KEY `account` (`account_id`)
) ENGINE=InnoDB;

-- A percent coupon does not pay out on redemption: it parks a discount here,
-- which the next shop purchase consumes.
CREATE TABLE IF NOT EXISTS `znote_coupon_discounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `percent` int NOT NULL DEFAULT '0',
  `expires_at` int NOT NULL DEFAULT '0',
  `spent_at` int NOT NULL DEFAULT '0' COMMENT '0 = still available',
  PRIMARY KEY (`id`),
  KEY `pending` (`account_id`, `spent_at`)
) ENGINE=InnoDB;
