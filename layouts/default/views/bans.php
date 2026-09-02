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
		return 'Permanent';
	}

	$left = $expires - time();
	if ($left <= 0) {
		return 'Expired';
	}

	$days  = intdiv($left, 86400);
	$hours = intdiv($left % 86400, 3600);

	if ($days > 0) {
		return $days . ' day' . ($days === 1 ? '' : 's') . ' left';
	}
	if ($hours > 0) {
		return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' left';
	}

	return 'Less than an hour';
}
?>
<h1>Bans</h1>

<?php if (!$bansSupported): ?>

	<p>This server engine does not expose a ban list.</p>

<?php else: ?>

	<p class="txt">
		Rule violations handled by the gamemasters.
		<?php if ($ipBanCount > 0): ?>
			<?= (int)$ipBanCount ?> address ban<?= $ipBanCount === 1 ? ' is' : 's are' ?> also in force;
			addresses are not listed.
		<?php endif; ?>
	</p>

	<h2>Banned accounts</h2>
	<?php if ($accountBans): ?>
		<table class="table table-striped">
			<tr class="yellow">
				<td>Character</td>
				<td>Reason</td>
				<td>Banned</td>
				<td>Status</td>
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
		<p>No accounts are currently banned.</p>
	<?php endif; ?>

	<h2>Name locks</h2>
	<?php if ($nameLocks): ?>
		<table class="table table-striped">
			<tr class="yellow">
				<td>Character</td>
				<td>Reason</td>
				<td>Locked</td>
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
		<p>No name locks in force.</p>
	<?php endif; ?>

<?php endif; ?>
