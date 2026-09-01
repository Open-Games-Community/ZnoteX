<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

protect_page();
admin_only($user_data);

// Helpers
function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function post_int(string $key): int {
	return isset($_POST[$key]) ? (int)$_POST[$key] : 0;
}

function parse_action_id(string $value): int {
	// Value like "123:Accept Image" or "123:Delete Image"
	$parts = explode(':', $value, 2);
	return (int)($parts[0] ?? 0);
}

function rebuild_gallery_cache(): void {
	$cache = new Cache('engine/cache/gallery');
	$images = fetchImages(2);

	$data = [];
	if (is_array($images)) {
		foreach ($images as $image) {
			$data[] = [
				'title' => $image['title'] ?? '',
				'desc'  => $image['desc'] ?? '',
				'date'  => $image['date'] ?? '',
				'image' => $image['image'] ?? '',
			];
		}
	}
	$cache->setContent($data);
	$cache->save();
}

// --------------------
// Actions
// --------------------
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// DELETE (soft delete -> status 3)
	if (isset($_POST['delete'])) {
		$id = parse_action_id((string)$_POST['delete']);
		if ($id > 0) {
			updateImage($id, 3);
			$message = "Image {$id} deleted.";
			rebuild_gallery_cache();
		}
	}

	// ACCEPT / RECOVER (status 2)
	if (isset($_POST['accept'])) {
		$id = parse_action_id((string)$_POST['accept']);
		if ($id > 0) {
			updateImage($id, 2);
			$message = "Image {$id} accepted and is now public.";
			rebuild_gallery_cache();
		}
	}

	// REMOVE (hard delete -> imgur delete + db delete)
	if (isset($_POST['remove'])) {
		$id = parse_action_id((string)$_POST['remove']);
		$delhash = (string)($_POST['delhash'] ?? '');

		if ($id > 0) {
			// Try delete on imgur only if we have delhash + client id configured
			$imgurClientID = (string)($config['gallery']['Client ID'] ?? '');

			if ($delhash !== '' && $imgurClientID !== '') {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://api.imgur.com/3/image/" . rawurlencode($delhash));
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					"Authorization: Client-ID {$imgurClientID}"
				]);

				$result = curl_exec($ch);

				if ($result === false) {
					error_log('cURL error (imgur delete): ' . curl_error($ch));
				} else {
					$decoded = json_decode($result, true);
					// Optional: you can inspect $decoded['success'] / $decoded['status']
					// but we still continue DB delete.
				}
				curl_close($ch);
			}

			// Hard delete from DB
			mysql_delete("DELETE FROM `znote_images` WHERE `id`={$id} LIMIT 1;");
			$message = "Image {$id} removed.";
			// Cache doesn't include deleted/removed images, but safe to rebuild anyway
			rebuild_gallery_cache();
		}
	}
}

if ($message !== '') {
	echo '<p><strong>' . h($message) . '</strong></p>';
}
?>

<h1>Images in need of moderation:</h1>

<?php
$images = fetchImages(1);

if (is_array($images) && !empty($images)) :
	foreach ($images as $image) :
		$id = (int)($image['id'] ?? 0);

		$titleRaw = (string)($image['title'] ?? '');
		$imageRaw = (string)($image['image'] ?? '');
		$descRaw  = (string)($image['desc'] ?? '');

		$title = h($titleRaw);
		$imageUrl = h($imageRaw);

		$descr = h($descRaw);
		$descr = str_replace(["\\r", "\\n"], ["", "<br />"], $descr);
		?>
		<table>
			<tr class="yellow">
				<td>
					<h2>
						<?= $title ?>
						<form action="" method="post" style="display:inline;">
							<input type="submit" name="accept" value="<?= $id ?>:Accept Image"/>
						</form>
						<form action="" method="post" style="display:inline;">
							<input type="submit" name="delete" value="<?= $id ?>:Delete Image"/>
						</form>
					</h2>
				</td>
			</tr>
			<tr>
				<td>
					<a href="<?= $imageUrl ?>">
						<img src="<?= $imageUrl ?>" alt="<?= $title ?>" style="max-width: 100%;"/>
					</a>
				</td>
			</tr>
			<tr>
				<td><p><?= $descr ?></p></td>
			</tr>
		</table>
	<?php
	endforeach;
else :
	echo '<h2>All good, no new images to moderate.</h2>';
endif;
?>

<h1>Public Images:</h1>

<?php
$images = fetchImages(2);

if (is_array($images) && !empty($images)) :
	foreach ($images as $image) :
		$id = (int)($image['id'] ?? 0);

		$titleRaw = (string)($image['title'] ?? '');
		$imageRaw = (string)($image['image'] ?? '');
		$descRaw  = (string)($image['desc'] ?? '');

		$title = h($titleRaw);
		$imageUrl = h($imageRaw);

		$descr = h($descRaw);
		$descr = str_replace(["\\r", "\\n"], ["", "<br />"], $descr);
		?>
		<table>
			<tr class="yellow">
				<td>
					<h2>
						<?= $title ?>
						<form action="" method="post" style="display:inline;">
							<input type="submit" name="delete" value="<?= $id ?>:Delete Image"/>
						</form>
					</h2>
				</td>
			</tr>
			<tr>
				<td>
					<a href="<?= $imageUrl ?>">
						<img src="<?= $imageUrl ?>" alt="<?= $title ?>" style="max-width: 100%;"/>
					</a>
				</td>
			</tr>
			<tr>
				<td><p><?= $descr ?></p></td>
			</tr>
		</table>
	<?php
	endforeach;
else :
	echo '<h2>There are currently no public images.</h2>';
endif;
?>

<h1>Deleted Images:</h1>

<?php
$images = fetchImages(3);

if (is_array($images) && !empty($images)) :
	foreach ($images as $image) :
		$id = (int)($image['id'] ?? 0);

		$titleRaw = (string)($image['title'] ?? '');
		$imageRaw = (string)($image['image'] ?? '');
		$descRaw  = (string)($image['desc'] ?? '');
		$delhashRaw = (string)($image['delhash'] ?? '');

		$title = h($titleRaw);
		$imageUrl = h($imageRaw);

		$descr = h($descRaw);
		$descr = str_replace(["\\r", "\\n"], ["", "<br />"], $descr);

		$delhashSafe = h($delhashRaw);
		?>
		<table>
			<tr class="yellow">
				<td>
					<h2>
						<?= $title ?>
						<form action="" method="post" style="display:inline;">
							<input type="submit" name="accept" value="<?= $id ?>:Recover Image"/>
							<input type="hidden" name="delhash" value="<?= $delhashSafe ?>">
							<input type="submit" name="remove" value="<?= $id ?>:Remove Image"/>
						</form>
					</h2>
				</td>
			</tr>
			<tr>
				<td>
					<a href="<?= $imageUrl ?>">
						<img src="<?= $imageUrl ?>" alt="<?= $title ?>" style="max-width: 100%;"/>
					</a>
				</td>
			</tr>
			<tr>
				<td><p><?= $descr ?></p></td>
			</tr>
		</table>
	<?php
	endforeach;
else :
	echo '<h2>There are currently no deleted images.</h2>';
endif;

include 'layout/overall/footer.php'; ?>
