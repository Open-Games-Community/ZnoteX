<?php require_once 'engine/init.php'; theme_open();

/**
 * Public FAQ.
 *
 * Entries are edited from Admin Panel > Settings > Content > FAQ questions,
 * not on this page - this file only reads config('faq.entries').
 */

$faqEntries = (array)($config['faq']['entries'] ?? array());

view('faq', [
	'faqEntries' => $faqEntries,
]);

theme_close();
