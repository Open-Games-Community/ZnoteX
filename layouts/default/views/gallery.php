<?php
if ($logged_in === true) {
	if (!empty($_POST['new'])) {
		?>
		<h1><?= t('gallery.create') ?></h1>
		<p><?= t('gallery.powered') ?></p>
		<form action="" method="post" enctype="multipart/form-data">
			<?= t('gallery.select') ?><br><input type="file" name="imagefile" id="imagefile"><br>
			<?= t('gallery.image_title') ?><br /><input type="text" name="title" size="70"><br />
			<?= t('gallery.image_desc') ?><br /><textarea name="desc" cols="55" rows="15"></textarea><br />
			<input type="submit" value="<?= t('gallery.upload') ?>" name="submit">
		</form>
		<?php
	}

	if (isset($_FILES['imagefile']) && !empty($_FILES['imagefile'])) {
		$image = file_get_contents($_FILES['imagefile']['tmp_name']);
		$imgurClientID = $config['gallery']['Client ID'];

		// Post image to imgur
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.imgur.com/3/image/");
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			"type" => "file", 
			"name" => $_FILES['imagefile']['name'],
			"image" => $image
		]);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Client-ID {$imgurClientID}"
		));
		$response = json_decode(curl_exec($ch));
		$image_url = $response->data->link;
		$image_delete = $response->data->deletehash;
		$title = $_POST['title'];
		$desc = $_POST['desc'];

		if ($image_url !== false) {

			// Insert to database
			$inserted = insertImage((int)$session_user_id, $title, $desc, $image_url, $image_delete);
			if ($inserted === true) {
				?>
				<h1><?= t('gallery.posted') ?></h1>
				<p><?= t('gallery.posted_text') ?></p>

				<h2><?= t('gallery.preview') ?></h2>
				<table>
					<tr class="yellow">
						<td><h3><?php echo $title; ?></h3></td>
					</tr>
					<tr>
						<td>
							<a href="<?php echo $image_url; ?>" target="_BLANK"><img class="galleryImage" style="max-width: 100%;" src="<?php echo $image_url; ?>" alt="<?php echo $title; ?>"/></a>
						</td>
					</tr>
					<tr>
						<td>
						<?php
						$descr = str_replace("\\r", "", $desc);
						$descr = str_replace("\\n", "<br />", $descr);
						?>
						<p><?php echo $descr; ?></p>
						</td>
					</tr>
				</table>
				<?php
			} else { // Image not inserted because it already exist
				?>
				<h1><?= t('gallery.exists') ?></h1>
				<p><?= t('gallery.exists_text') ?></p>
				<?php
			}

		} else { // Failed to locate imageSrc
			?>
			<h1><?= t('gallery.failed') ?></h1>
			<p><?= t('gallery.failed_text') ?></p>
			<?php
		}
	}
}
if (empty($_POST)) {
	?>
	<h1><?= t('gallery.title') ?></h1>
	<?php if ($logged_in === true) { ?>
	<form action="" method="post">
		<?= t('gallery.invite') ?> <input type="submit" name="new" value="<?= t('gallery.add') ?>">
	</form>
	<?php
	}

	if (is_array($images) && !empty($images)) {
		foreach($images as $image) {
			?>
			<table>
				<tr class="yellow">
					<td><h3><?php echo $image['title']; ?></h3></td>
				</tr>
				<tr>
					<td>
						<a href="<?php echo $image['image']; ?>" target="_BLANK"><img class="galleryImage" style="max-width: 100%;" src="<?php echo $image['image']; ?>" alt="<?php echo $image['title']; ?>"/></a>
					</td>
				</tr>
				<tr>
					<td>
					<?php
					$descr = str_replace("\\r", "", $image['desc']);
					$descr = str_replace("\\n", "<br />", $descr);
					?>
					<p><?php echo $descr; ?></p>
					</td>
				</tr>
			</table>
		<?php }
	} else echo '<h2>'. t('gallery.empty') .'</h2>';

	if ($logged_in === false) echo t('gallery.need_login');
}
