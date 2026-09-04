<?php
/**
 * Default shell - the frame every page is rendered inside.
 *
 * A shell is plain HTML plus a handful of one-line calls. Everything dynamic
 * comes from a function, so you can move any block anywhere, delete what you
 * do not want, or rewrite the whole thing.
 *
 *   theme_title()       the site title, escaped
 *   theme_body_class()  "theme-default page_highscores"
 *   theme_asset('x')    URL of one of your files in assets/
 *   theme_content()     the page itself - this one you must keep
 *   theme_menu()        includes this theme's menu.php   (optional)
 *   theme_sidebar()     includes this theme's aside.php  (optional)
 *   widget('login')     one widget from widgets/         (optional)
 */

$countDown          = $countDown ?? null;
$countDown_hide     = $countDown_hide ?? 0;
$countDown_complete = $countDown_complete ?? '';

$launch_seconds = $countDown ? (strtotime($countDown) - time()) : 0;
$delay_hide     = $launch_seconds + $countDown_hide;

$aacQueries = $aacQueries ?? 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= theme_title() ?></title>

	<link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
	<link rel="stylesheet" href="assets/fontawesome/css/font-awesome.min.css">
	<link rel="stylesheet" href="<?= theme_asset('css/resp.css') ?>">

	<script src="assets/js/jquery.js" charset="utf-8"></script>
	<?php if ($delay_hide > 0): ?>
		<script src="<?= theme_asset('js/countdown.js') ?>" charset="utf-8"></script>
	<?php endif; ?>

	<script>
		$(document).ready(function () {
			<?php if ($delay_hide > 0): ?>
				countDown("countDownTimer", <?= (int)$launch_seconds ?>, <?= json_encode($countDown_complete) ?>);
			<?php endif; ?>

			$('.loginBtn').click(function () {
				$('.loginContainer input:first-of-type').focus();
			});
			$('#accountLink').click(function () {
				if (this.href.indexOf('#') >= 0) {
					$('.loginContainer input:first-of-type').focus();
				}
			});
		});
	</script>
</head>
<body class="<?= theme_body_class() ?>">

	<div class="main">

		<?php theme_menu(); ?>

		<div class="well banner"></div>

		<div class="well feedContainer preventCollapse">

			<?php if ($delay_hide > 0): ?>
				<div class="well topPane preventCollapse">
					<div class="well pull-left">
						<div id="countDownTimer" data-date="<?= htmlspecialchars((string)$countDown, ENT_QUOTES, 'UTF-8') ?>"></div>
					</div>
				</div>
			<?php endif; ?>

			<!-- MAIN FEED -->
			<div class="pull-left leftPane">
				<?php theme_content(); ?>
			</div>
			<!-- MAIN FEED END -->

			<?php theme_sidebar(); ?>
		</div>

		<footer class="well preventCollapse">
			<?php $footerText = theme_option('footer_html'); ?>
			<?php if ($footerText !== ''): ?>
				<div class="well preventCollapse footerCustom"><?= $footerText ?></div>
			<?php endif; ?>
			<div class="pull-left">
				<p>&copy; <?= theme_title() ?>.
					Page generated in <?= elapsedTime() ?> seconds.
					Q: <?= (int)$aacQueries ?>.
					Designed By <a href="https://otland.net/members/snavy.155163/" target="_blank" rel="noopener">Snavy</a>.
					Engine: <a href="credits.php">ZnoteX</a>.
				</p>
			</div>
			<div class="pull-right">
				<p>Server date and clock is: <?= getClock(false, true) ?></p>
			</div>
		</footer>

	</div><!-- .main -->

</body>
</html>
