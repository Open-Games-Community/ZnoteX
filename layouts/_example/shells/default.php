<?php
/**
 * The shell: the frame every page of this theme is rendered inside.
 *
 * This is plain HTML. The only line you cannot remove is theme_content(),
 * which is where the page itself goes. Everything else is yours - move it,
 * delete it, rewrite it.
 *
 * Available to you here:
 *
 *   theme_title()            site title, already escaped
 *   theme_body_class()       "theme-yourtheme page_highscores"
 *   theme_asset('css/x.css') URL of a file in this theme's assets/ folder
 *   theme_content()          the page body                      [required]
 *   theme_menu()             includes this theme's menu.php     [optional]
 *   theme_sidebar()          includes this theme's aside.php    [optional]
 *   widget('login')          one widget from widgets/           [optional]
 *   $config                  everything from config.php
 *   user_logged_in()         true/false
 *
 * This theme writes its menu directly in the markup below rather than calling
 * theme_menu(), to show that nothing is imposed on you.
 */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= theme_title() ?></title>

	<link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
	<link rel="stylesheet" href="assets/fontawesome/css/font-awesome.min.css">
</head>
<body class="<?= theme_body_class() ?>">

	<header class="ex-header">
		<a class="ex-logo" href="index.php"><?= theme_title() ?></a>

		<nav class="ex-nav">
			<a href="index.php">Home</a>
			<a href="highscores.php">Highscores</a>
			<a href="onlinelist.php">Online</a>
			<a href="guilds.php">Guilds</a>
			<a href="shop.php">Shop</a>
			<a href="page.php?p=example">Example page</a>
			<?php if (user_logged_in()): ?>
				<a href="myaccount.php">My account</a>
			<?php else: ?>
				<a href="login.php">Login</a>
			<?php endif; ?>
		</nav>
	</header>

	<main class="ex-main">
		<?php theme_content(); ?>
	</main>

	<footer class="ex-footer">
		&copy; <?= theme_title() ?> &middot; Powered by
		<a href="credits.php">ZnoteX</a> &middot;
		<?= elapsedTime() ?>s
	</footer>

</body>
</html>
