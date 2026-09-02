<?php
/**
 * ZnoteX ACP shell - sidebar panel with ZnoteX styling.
 * Rendered by admin/index.php with $acp_content already built.
 *
 * @var array       $acp_modules
 * @var array|null  $acp_module
 * @var string      $acp_page
 * @var string      $acp_content
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$acp_flashes  = acp_take_flashes();
$acp_groups   = acp_nav_groups();
$acp_admin    = (string)($user_data['name'] ?? 'Admin');
$acp_siteName = (string)($config['site_title'] ?? 'ZnoteX');
$acp_title    = $acp_module['title'] ?? 'Admin Panel';
$acp_engine   = serverEngineReal();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= h($acp_title) ?> &middot; <?= h($acp_siteName) ?> ACP</title>

	<link rel="stylesheet" href="../assets/fontawesome/css/font-awesome.min.css?acp=1">
	<link rel="stylesheet" href="assets/acp.css?acp=1">

	<script>
		// Applied before first paint so the theme never flashes light-then-dark.
		(function () {
			try {
				var d = document.documentElement;
				var t = localStorage.getItem('acp.theme');
				if (t) { d.setAttribute('data-acp-theme', t); }
				if (localStorage.getItem('acp.sidebar_closed') === '1') { d.classList.add('acp-sidebar-closed'); }
			} catch (e) {}
		})();
	</script>
</head>
<body class="acp acp-page-<?= h($acp_page) ?>">

<div class="acp-shell">

	<aside class="acp-sidebar" id="acpSidebar">
		<a class="acp-side-home" href="<?= h(acp_url('dashboard')) ?>">
			<i class="fa fa-home"></i>
			<span><?= h($acp_siteName) ?></span>
		</a>

		<div class="acp-nav-filter">
			<i class="fa fa-search"></i>
			<input type="search" id="acpFilter" placeholder="Search panel..." autocomplete="off" aria-label="Search panel">
		</div>

		<nav class="acp-nav" id="acpNav" aria-label="Admin navigation">
			<?php foreach ($acp_groups as $groupName => $groupModules): ?>
				<div class="acp-nav-group">
					<button type="button" class="acp-nav-group-label" aria-expanded="true">
						<span><?= h($groupName) ?></span>
						<i class="fa fa-angle-down"></i>
					</button>
					<ul>
						<?php foreach ($groupModules as $key => $mod):
							$isExternal = !empty($mod['url']);
							$href       = $isExternal ? $mod['url'] : acp_url($key);
							$badge      = acp_badge($key);
							$active     = (!$isExternal && $key === $acp_page);
						?>
							<li>
								<a class="acp-nav-link<?= $active ? ' is-active' : '' ?>"
								   href="<?= h($href) ?>"
								   data-title="<?= h(strtolower($mod['title'] . ' ' . $groupName)) ?>"
								   <?= $isExternal ? 'target="' . h($mod['target'] ?? '_self') . '"' : '' ?>>
									<i class="fa <?= h($mod['icon']) ?>"></i>
									<span class="acp-nav-text"><?= h($mod['title']) ?></span>
									<?php if ($badge !== null): ?>
										<span class="acp-nav-badge"><?= (int)$badge ?></span>
									<?php elseif ($isExternal): ?>
										<i class="fa fa-external-link acp-nav-ext"></i>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
			<p class="acp-nav-nomatch" hidden>No menu entry matches.</p>
		</nav>
	</aside>

	<div class="acp-backdrop" id="acpBackdrop" hidden></div>

	<section class="acp-panel">
		<header class="acp-topbar">
			<button type="button" class="acp-icon-btn acp-burger" id="acpBurger" aria-label="Toggle menu">
				<i class="fa fa-bars"></i>
			</button>
			<div class="acp-top-title">
				<strong><?= h($acp_siteName) ?></strong>
				<span><?= h($acp_engine) ?> control panel</span>
			</div>
			<div class="acp-top-actions">
				<a class="acp-icon-btn" href="../index.php" title="View site" aria-label="View site">
					<i class="fa fa-globe"></i>
				</a>
				<button type="button" class="acp-theme-toggle" id="acpTheme" title="Toggle day/night mode" aria-label="Toggle day/night mode">
					<i class="fa fa-moon-o"></i>
					<span>Night</span>
				</button>
				<span class="acp-user">
					<i class="fa fa-user-circle-o"></i>
					<span class="acp-user-name"><?= h($acp_admin) ?></span>
				</span>
				<a class="acp-icon-btn" href="../login.php?logout" title="Log out" aria-label="Log out">
					<i class="fa fa-sign-out"></i>
				</a>
			</div>
		</header>

		<main class="acp-content">

			<div class="acp-page-head">
				<h1><?= h($acp_title) ?></h1>
				<?php if (!empty($acp_module['description'])): ?>
					<p><?= h($acp_module['description']) ?></p>
				<?php endif; ?>
			</div>

			<?php foreach ($acp_flashes as $flash): ?>
				<div class="acp-flash acp-flash--<?= h($flash['type']) ?>">
					<i class="fa <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle') ?>"></i>
					<span><?= $flash['message'] ?></span>
				</div>
			<?php endforeach; ?>

			<?= $acp_content ?>
		</main>

		<footer class="acp-footer">
			<span>&copy; <?= h($acp_siteName) ?> &middot; ZnoteX <?= h($version ?? '') ?></span>
			<span>
				Rendered in <?= h(elapsedTime()) ?>s
				&middot; <?= h(getClock(false, true)) ?>
			</span>
		</footer>
	</section>
</div>

<script src="assets/acp.js?acp=1"></script>
</body>
</html>
