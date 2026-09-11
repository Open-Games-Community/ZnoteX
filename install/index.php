<?php
/**
 * ZnoteX installer.
 *
 * Six steps, one router. Reachable at /install/ until the site is installed,
 * after which it refuses to run - see install_locked_reason().
 *
 * Delete this folder once you are done. Nothing else depends on it.
 */

define('ZNOTE_INSTALL', true);

if (PHP_VERSION_ID < 80100) {
	die('ZnoteX needs PHP 8.1 or newer. You are on PHP ' . PHP_VERSION . '.');
}

require_once __DIR__ . '/../engine/session.php';
require_once __DIR__ . '/../engine/security.php';
znote_session_start();
znote_security_boot();

require_once __DIR__ . '/bootstrap.php';

$locked = install_locked_reason();

$step = (int)($_GET['step'] ?? 1);
if ($step < 1 || $step > count(INSTALL_STEPS)) {
	$step = 1;
}

// No jumping ahead: the later steps depend on what the earlier ones collected.
if ($locked === '' && $step > install_max_step()) {
	$step = install_max_step();
}

if ($locked === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !install_csrf_validate()) {
	install_error('Invalid or expired form token. Please refresh the page and try again.');
	header('Location: ' . install_url($step));
	exit;
}

$error = install_take_error();

// A step handles its own POST and either advances or sets an error.
$stepFile = __DIR__ . '/steps/' . $step . '.php';

ob_start();
if ($locked === '') {
	if (is_file($stepFile)) {
		include $stepFile;
	} else {
		echo '<p>Missing installer step file.</p>';
	}
}
$content = ob_get_clean();
if ($locked === '') {
	$content = install_csrf_inject($content);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Install ZnoteX</title>
	<link rel="stylesheet" href="assets/install.css">
</head>
<body>

<div class="wrap">

	<header class="head">
		<div class="brand"><span class="mark">ZX</span> Install ZnoteX</div>
		<span class="version">2.0.1</span>
	</header>

	<?php if ($locked === ''): ?>
		<ol class="steps">
			<?php foreach (INSTALL_STEPS as $number => $label): ?>
				<li class="<?= $number === $step ? 'is-current' : ($number < $step ? 'is-done' : '') ?>">
					<span class="num"><?= $number < $step ? '&#10003;' : $number ?></span>
					<span class="lbl"><?= ih($label) ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<main class="card">
		<?php if ($locked !== ''): ?>
			<h1>Already installed</h1>
			<p class="bad"><?= $locked ?></p>
			<p>
				<a class="btn" href="../index.php">Go to the site</a>
				<a class="btn ghost" href="../admin/index.php">Admin panel</a>
			</p>
		<?php else: ?>
			<?php if ($error !== ''): ?>
				<p class="bad"><?= $error ?></p>
			<?php endif; ?>
			<?= $content ?>
		<?php endif; ?>
	</main>

	<footer class="foot">
		Step <?= (int)$step ?> of <?= count(INSTALL_STEPS) ?> &middot;
		Delete the <code>install/</code> folder when you are finished.
	</footer>
</div>

</body>
</html>
