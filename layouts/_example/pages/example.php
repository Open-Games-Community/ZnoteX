<?php
/**
 * Title: Example page
 *
 * A page this theme adds to the site. It does not exist in ZnoteX - the theme
 * invents it. Nothing to register anywhere: dropping this file in pages/ makes
 * it live at page.php?p=example, wrapped in this theme's shell.
 *
 * Delete it, rename it, add ten more. The filename is the URL.
 */
?>
<h1>An extra page</h1>

<p>
	This file is <code>layouts/_example/pages/example.php</code> and it is
	reachable at <code>page.php?p=example</code>.
</p>

<p>
	Write plain HTML. You have the whole engine available if you want it:
	<code>$config</code>, <code>user_logged_in()</code>,
	<code>mysql_select_multi()</code>, and every function in
	<code>engine/function/</code>.
</p>

<h2>Example: pull something out of the database</h2>

<?php
$players = mysql_select_multi("
	SELECT `name`, `level`, `vocation`
	FROM `players`
	ORDER BY `level` DESC
	LIMIT 5;
");
?>

<?php if (is_array($players) && $players): ?>
	<table class="table table-striped">
		<tr class="yellow">
			<td>Name</td>
			<td>Vocation</td>
			<td>Level</td>
		</tr>
		<?php foreach ($players as $player): ?>
			<tr>
				<td>
					<a href="characterprofile.php?name=<?= urlencode((string)$player['name']) ?>">
						<?= htmlspecialchars((string)$player['name'], ENT_QUOTES, 'UTF-8') ?>
					</a>
				</td>
				<td><?= htmlspecialchars(vocation_id_to_name((int)$player['vocation']), ENT_QUOTES, 'UTF-8') ?></td>
				<td><?= (int)$player['level'] ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php else: ?>
	<p>No characters yet.</p>
<?php endif; ?>

<h2>Want a different frame for this page?</h2>

<p>
	Add <code>shells/wide.php</code> to your theme and put
	<code>&lt;?php theme_shell('wide'); ?&gt;</code> at the top of this file.
	The page then renders inside that frame instead of the default one.
</p>
