<?php
/** Step 1 - requirements. Nothing here touches the database. */

if (!defined('ZNOTE_INSTALL')) { http_response_code(403); die('Direct access denied.'); }

$checks = install_requirements();

$blocking = 0;
foreach ($checks as $check) {
	if (!$check[1] && $check[3]) { $blocking++; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $blocking === 0) {
	install_max_step(2);
	header('Location: ' . install_url(2));
	exit;
}
?>
<h1>Requirements</h1>
<p class="lead">What this server needs before ZnoteX can run.</p>

<ul class="checks">
	<?php foreach ($checks as $check):
		list($label, $ok, $detail, $fatal) = $check;
		$state = $ok ? 'ok' : ($fatal ? 'no' : 'opt');
	?>
		<li>
			<span class="state <?= $state ?>"><?= $ok ? '&#10003;' : ($fatal ? '&#10007;' : '!') ?></span>
			<span class="what">
				<?= ih($label) ?>
				<?php if (!$ok && !$fatal): ?><em>(optional)</em><?php endif; ?>
				<span class="detail"><?= ih($detail) ?></span>
			</span>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ($blocking > 0): ?>
	<p class="bad">
		<?= (int)$blocking ?> requirement<?= $blocking === 1 ? '' : 's' ?> not met. Fix
		<?= $blocking === 1 ? 'it' : 'them' ?> and reload this page.
	</p>
<?php else: ?>
	<p class="good">Everything required is in place.</p>
<?php endif; ?>

<form method="post">
	<div class="actions">
		<button class="btn" type="submit" <?= $blocking > 0 ? 'disabled' : '' ?>>Continue</button>
		<a class="btn ghost" href="index.php?step=1">Re-check</a>
	</div>
</form>
