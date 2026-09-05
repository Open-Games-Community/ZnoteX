<?php
/**
 * Public ban list.
 *
 * Prepared by bans.php:
 *   $bansSupported  false when this engine has no readable ban tables
 *   $accountBans    [name, reason, banned_at, expires_at]
 *   $nameLocks      [name, reason, at]
 *   $ipBanCount     number of active address bans, shown as a count only
 */

/** "3 days left", "permanent", or "expired". */
function znote_ban_remaining(int $expires): string {
	if ($expires <= 0) {
		return t('bans.permanent');
	}

	$left = $expires - time();
	if ($left <= 0) {
		return t('bans.expired');
	}

	$days  = intdiv($left, 86400);
	$hours = intdiv($left % 86400, 3600);

	if ($days > 0) {
		return t('bans.days_left', ['n' => $days]);
	}
	if ($hours > 0) {
		return t('bans.hours_left', ['n' => $hours]);
	}

	return t('bans.less_hour');
}
?>
<h1><?= t('bans.title') ?></h1>

<?php if (!$bansSupported): ?>

	<p><?= t('bans.unsupported') ?></p>

<?php else: ?>

	<p class="txt">
		<?= t('bans.intro') ?>
		<?php if ($ipBanCount > 0): ?>
			<?= t('bans.ip_count', ['count' => (int)$ipBanCount]) ?>
		<?php endif; ?>
	</p>

	<h2><?= t('bans.accounts') ?></h2>
	<?php if ($accountBans): ?>
		<table class="table table-striped">
			<tr class="yellow">
				<td><?= t('common.character') ?></td>
				<td><?= t('common.reason') ?></td>
				<td><?= t('bans.banned') ?></td>
				<td><?= t('common.status') ?></td>
			</tr>
			<?php foreach ($accountBans as $ban): ?>
				<tr>
					<td>
						<a href="characterprofile.php?name=<?= urlencode($ban['name']) ?>">
							<?= htmlspecialchars($ban['name'], ENT_QUOTES, 'UTF-8') ?>
						</a>
					</td>
					<td><?= htmlspecialchars($ban['reason'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(getClock($ban['banned_at'], true), ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(znote_ban_remaining($ban['expires_at']), ENT_QUOTES, 'UTF-8') ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p><?= t('bans.none') ?></p>
	<?php endif; ?>

	<h2><?= t('bans.namelocks') ?></h2>
	<?php if ($nameLocks): ?>
		<table class="table table-striped">
			<tr class="yellow">
				<td><?= t('common.character') ?></td>
				<td><?= t('common.reason') ?></td>
				<td><?= t('bans.locked') ?></td>
			</tr>
			<?php foreach ($nameLocks as $lock): ?>
				<tr>
					<td>
						<a href="characterprofile.php?name=<?= urlencode($lock['name']) ?>">
							<?= htmlspecialchars($lock['name'], ENT_QUOTES, 'UTF-8') ?>
						</a>
					</td>
					<td><?= htmlspecialchars($lock['reason'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(getClock($lock['at'], true), ENT_QUOTES, 'UTF-8') ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p><?= t('bans.no_namelocks') ?></p>
	<?php endif; ?>

<?php endif; ?>
