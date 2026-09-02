<?php
/**
 * Title: My New Page
 * Icon: fa-cube
 * Group: Other
 * Order: 100
 * Description: One line shown under the page title.
 */

/*
 * ---------------------------------------------------------------------------
 * How to add an admin page
 * ---------------------------------------------------------------------------
 *
 * 1. Copy this file to admin/modules/<name>.php  (no leading underscore -
 *    files starting with "_" are skipped by the registry).
 * 2. Edit the docblock above. That IS the menu entry: Title, Icon
 *    (Font Awesome 4.7 class), Group and Order. Nothing else to register.
 * 3. Write your logic and markup below. Available to you already:
 *
 *      $config, $user_data, $session_user_id, $version   from engine/init.php
 *      h(), intv(), esc()                                escaping helpers
 *      acp_url(), acp_site(), acp_redirect()             links and redirects
 *      acp_csrf_field()                                  CSRF token input
 *      acp_flash_success/error/info()                    messages across redirects
 *      acp_card_open/close(), acp_empty(), acp_stat()    layout blocks
 *      mysql_select_single/multi(), mysql_insert/update/delete()
 *
 * Notes:
 *   - The working directory is the project root, so 'engine/cache/x' and
 *     other engine-relative paths work exactly as they do on a normal page.
 *   - Links to the public site need acp_site(): acp_site('index.php').
 *   - Do NOT include init.php, the header or the footer - index.php does that.
 *   - POST requests are CSRF-checked centrally, so every form needs
 *     <?= acp_csrf_field() ?> or it will be rejected with a 400.
 *   - Redirect after a successful POST with acp_redirect('<name>') so a
 *     refresh does not resubmit.
 *   - To give the menu entry a counter bubble, add a function named
 *     acp_badge_<name>() to admin/bootstrap.php returning an int.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// ... do the work ...
	acp_flash_success('Saved.');
	acp_redirect('_template');
}
?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Example form</h2>
		<p>Delete everything below and write your own</p>
	</header>
	<div class="acp-card-body">
		<form method="post">
			<?= acp_csrf_field() ?>
			<div class="acp-field">
				<label class="acp-label" for="example">Something</label>
				<input class="acp-input" id="example" name="example" placeholder="Type here">
			</div>
			<div class="acp-actions">
				<button class="acp-btn" type="submit"><i class="fa fa-check"></i> Save</button>
			</div>
		</form>
	</div>
</section>
