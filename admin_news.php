<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

protect_page();
admin_only($user_data);

// Helpers
function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function esc(string $s): string {
	return mysql_znote_escape_string($s);
}
function rebuild_news_cache(): void {
	$cache = new Cache('engine/cache/news');
	$news = fetchAllNews();
	$cache->setContent($news ?: []);
	$cache->save();
}
function parse_option(?string $opt): array {
	// expected like: "a!0", "e!12", "d!3", "s!5", "i!0"
	if ($opt === null || $opt === '') return ['', 0];
	$parts = explode('!', $opt, 2);
	$action = $parts[0] ?? '';
	$id = isset($parts[1]) ? (int)$parts[1] : 0;
	return [$action, $id];
}

$message = '';

// --------------------
// POST handler
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
	$optRaw = isset($_POST['option']) ? (string)$_POST['option'] : '';
	if (function_exists('sanitize')) {
		// keep your original sanitize behavior
		sanitize($optRaw);
	}

	[$action, $id] = parse_option($optRaw);

	// Delete
	if ($action === 'd' && $id > 0) {
		mysql_delete("DELETE FROM `znote_news` WHERE `id` = {$id} LIMIT 1;");
		rebuild_news_cache();
		$message = 'News deleted!';
		header("Location: admin_news.php?msg=deleted");
		exit;
	}

	// Insert (create)
	if ($action === 'i') {
		$charid = isset($_POST['selected_char']) ? (int)$_POST['selected_char'] : 0;
		$title  = (string)($_POST['title'] ?? '');
		$text   = (string)($_POST['text'] ?? '');

		if ($charid > 0 && $title !== '' && $text !== '') {
			$titleSql = esc($title);
			$textSql  = esc($text);
			$date     = time();

			mysql_insert("
				INSERT INTO `znote_news` (`title`, `text`, `date`, `pid`)
				VALUES ('{$titleSql}', '{$textSql}', {$date}, {$charid});
			");

			rebuild_news_cache();
			header("Location: admin_news.php?msg=created");
			exit;
		} else {
			$message = 'ERROR: Missing character/title/text.';
		}
	}

	// Save (update)
	if ($action === 's' && $id > 0) {
		$title = (string)($_POST['title'] ?? '');
		$text  = (string)($_POST['text'] ?? '');

		$titleSql = esc($title);
		$textSql  = esc($text);

		mysql_update("
			UPDATE `znote_news`
			SET `title`='{$titleSql}', `text`='{$textSql}'
			WHERE `id`={$id}
			LIMIT 1;
		");

		rebuild_news_cache();
		header("Location: admin_news.php?msg=updated");
		exit;
	}

	// For 'a' (add) and 'e' (edit) we will render forms below (no redirect)
}

// Optional message from redirect
if (isset($_GET['msg'])) {
	$map = [
		'deleted' => 'News deleted!',
		'created' => 'News created successfully!',
		'updated' => 'News successfully updated!',
	];
	$key = (string)$_GET['msg'];
	if (isset($map[$key])) {
		$message = $map[$key];
	}
}

if ($message !== '') {
	echo '<p><strong style="color: green;">' . h($message) . '</strong></p>';
}

// Decide current view (forms)
$actionView = '';
$viewId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['option'])) {
	[$actionView, $viewId] = parse_option((string)$_POST['option']);
}

?>
<h1>News admin panel</h1>

<form action="" method="post">
	<input type="hidden" name="option" value="a!0">
	<input type="submit" value="Create new article">
</form>

<?php
// --------------------
// VIEW: Add form
// --------------------
if ($actionView === 'a') {
	$char_array = user_character_list($user_data['id']);
	?>
	<script src="engine/js/nicedit.js" type="text/javascript"></script>
	<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>

	<form action="" method="post">
		<input type="hidden" name="option" value="i!0">

		Select character:
		<select name="selected_char">
			<?php
			$count = 0;
			if (is_array($char_array) && !empty($char_array)) {
				foreach ($char_array as $row) {
					$name = (string)($row['name'] ?? '');
					if ($name === '') continue;

					$cid = (int)user_character_id($name);
					$charD = user_character_data($cid, 'group_id', 'id');

					if (isset($charD['group_id']) && (int)$charD['group_id'] > 1) {
						echo '<option value="' . (int)$cid . '">' . h($name) . '</option>';
						$count++;
					}
				}
			}
			?>
		</select>

		<input type="text" name="title" value="" placeholder="Title">
		[youtube]wK0w0x62PjA[/youtube]
		<br />

		<textarea name="text" id="area1" cols="75" rows="10" placeholder="Contents..." style="width: 100%"></textarea><br />
		<input type="submit" value="Create News">
	</form>

	<?php
	if ($count === 0) {
		echo "<p style='font-size: 24px; color: red;'><b>ERROR: NO GMs or Tutors on this account!</b></p>";
	}
}

// --------------------
// VIEW: Edit form
// --------------------
if ($actionView === 'e' && $viewId > 0) {
	$news = fetchAllNews();
	$edit = null;

	if (is_array($news)) {
		foreach ($news as $n) {
			if ((int)$n['id'] === (int)$viewId) {
				$edit = $n;
				break;
			}
		}
	}

	if (!$edit) {
		echo '<p style="color:red;"><b>ERROR: News not found.</b></p>';
	} else {
		?>
		<script src="engine/js/nicedit.js" type="text/javascript"></script>
		<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>

		<form action="" method="post">
			<input type="hidden" name="option" value="s!<?= (int)$viewId ?>">
			<input type="text" name="title" value="<?= h((string)$edit['title']) ?>"><br />
			<textarea name="text" cols="75" rows="10" style="width: 100%"><?= h((string)$edit['text']) ?></textarea><br />
			<input type="submit" value="Save Changes">
		</form>

		<br>
		<p>
			[b]<b>Bold Text</b>[/b]<br>
			[size=5]Size 5 text[/size]<br>
			[img]<?= h('Direct Image Link') ?>[/img]<br>
			[center]Cented Text[/center]<br>
			[link]<?= h('https://youtube.com/') ?>[/link]<br>
			[link=https://youtube.com/]<?= h('Click to View youtube') ?>[/link]<br>
			[color=GREEN]Green Text![/color]<br>
			[*]* Noted text [/*]
		</p>
		<?php
	}
}

// --------------------
// LIST TABLE
// --------------------
$news = fetchAllNews();
if ($news !== false && is_array($news) && !empty($news)) :
	?>
	<table id="news">
		<tr class="yellow">
			<td>Date</td>
			<td>By</td>
			<td>Title</td>
			<td>Edit</td>
			<td>Delete</td>
		</tr>
		<?php foreach ($news as $n):
			$id = (int)($n['id'] ?? 0);
			$date = (int)($n['date'] ?? 0);
			$name = (string)($n['name'] ?? '');
			$title = (string)($n['title'] ?? '');
		?>
			<tr>
				<td><?= h(getClock($date, true)) ?></td>

				<td>
					<?php
					$urlName = urlencode($name);
					?>
					<a href="characterprofile.php?name=<?= $urlName ?>"><?= h($name) ?></a>
				</td>

				<td><?= h($title) ?></td>

				<td>
					<form action="" method="post">
						<input type="hidden" name="option" value="e!<?= $id ?>">
						<input type="submit" value="Edit">
					</form>
				</td>

				<td>
					<form action="" method="post" onsubmit="return confirm('Delete this news article?');">
						<input type="hidden" name="option" value="d!<?= $id ?>">
						<input type="submit" value="Delete">
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php
else:
	echo '<p>' . h('No news found.') . '</p>';
endif;

include 'layout/overall/footer.php'; ?>
