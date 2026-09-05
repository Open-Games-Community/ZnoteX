<?php
/**
 * Top navigation for the default theme.
 *
 * The links come from the database and are managed in Admin Panel > Menus, so
 * adding one no longer means editing this file - and every theme shares the
 * same navigation instead of carrying its own copy.
 *
 * The markup is still entirely this theme's business: theme_menu_items() only
 * hands over the data, already filtered for the current visitor. An entry
 * marked "Admins only" is absent from the array, not hidden with CSS.
 *
 * A theme that would rather hardcode its menu simply does not call this.
 */

$menuItems = theme_menu_items('main');

// A site that has not run the menu migration yet gets the old hardcoded links,
// so upgrading never leaves someone with no navigation at all.
$menuFallback = !$menuItems;
?>
<nav>
	<div class="container">

		<!-- Menu left aligned -->
		<div class="pull-left">
			<?php if (!$menuFallback): ?>
				<ul>
					<?php foreach ($menuItems as $item): ?>
						<li>
							<?php // A category carries no URL: it opens its children, it does not navigate. ?>
							<?php if ($item['url'] === ''): ?>
								<span class="menuCategory">
									<?php if ($item['icon'] !== ''): ?>
										<i class="fa <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
									<?php endif; ?>
									<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
								</span>
							<?php else: ?>
								<a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
								   <?= $item['target'] !== '' ? 'target="' . htmlspecialchars($item['target'], ENT_QUOTES, 'UTF-8') . '" rel="noopener"' : '' ?>>
									<?php if ($item['icon'] !== ''): ?>
										<i class="fa <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
									<?php endif; ?>
									<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
								</a>
							<?php endif; ?>

							<?php if ($item['children']): ?>
								<ul>
									<?php foreach ($item['children'] as $child): ?>
										<li>
											<a href="<?= htmlspecialchars($child['url'], ENT_QUOTES, 'UTF-8') ?>"
											   <?= $child['target'] !== '' ? 'target="' . htmlspecialchars($child['target'], ENT_QUOTES, 'UTF-8') . '" rel="noopener"' : '' ?>>
												<?= htmlspecialchars($child['label'], ENT_QUOTES, 'UTF-8') ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<ul>
					<li><a href="/"><i class="fa fa-home"></i> Home</a></li>
					<li><a id="accountLink" href="myaccount.php"><i class="fa fa-user-circle"></i> Account</a></li>
					<li><a href="onlinelist.php"><i class="fa fa-users"></i> Community</a></li>
					<li><a href="serverinfo.php"><i class="fa fa-book"></i> Library</a></li>
					<li><a href="support.php"><i class="fa fa-info-circle"></i> Support</a></li>
					<li><a href="shop.php"><i class="fa fa-shopping-cart"></i> Shop</a></li>
				</ul>
			<?php endif; ?>
		</div>

		<!-- Menu right aligned -->
		<div class="pull-right">
			<ul>
				<?php if (user_logged_in() === true): ?>
					<li><a href="myaccount.php"><i class="fa fa-user"></i> <?= htmlspecialchars($user_data['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></li>
					<li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
				<?php else: ?>
					<li><a href="#loginContainer" class="modIcon loginBtn"><i class="fa fa-lock"></i><i class="fa fa-unlock"></i> Login</a></li>
					<li><a href="register.php"><i class="fa fa-key"></i> Register</a></li>
				<?php endif; ?>
			</ul>
		</div>

	</div>
</nav>
