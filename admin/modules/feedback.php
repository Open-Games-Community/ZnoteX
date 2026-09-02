<?php
/**
 * Title: Feedback Board
 * Icon: fa-comments-o
 * Group: Support
 * Order: 30
 * Description: Forum threads from players awaiting a staff reply.
 * Url: ../forum.php?cat=4
 * Target: _self
 *
 * The Url header turns this into an outbound link: index.php redirects there
 * rather than including this file. The badge still comes from
 * acp_badge_feedback() like any other module.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

// Only reached if the Url header above is removed.
header('Location: ../forum.php?cat=4');
exit;
