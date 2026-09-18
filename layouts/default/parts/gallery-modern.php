<?php
$znxGalH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$znxGalDesc = static function (?string $desc): string {
	$desc = str_replace('\\r', '', (string)$desc);
	return str_replace('\\n', '<br>', $desc);
};
?>

<style>
.znx-gal{--gal-surface:var(--box-inner-bg,var(--box-bg,var(--nz-panel,var(--wc-bg-2,var(--z-panel-2,var(--s-panel,var(--primary,rgb(30,33,40))))))));--gal-surface-2:color-mix(in srgb,var(--gal-surface) 82%,#000);--gal-well:color-mix(in srgb,var(--gal-surface) 65%,#000);--gal-text:var(--font-color,var(--text,var(--nz-text,var(--wc-text,var(--z-text,var(--s-text,rgb(155,162,177)))))));--gal-muted:color-mix(in srgb,var(--gal-text) 58%,transparent);--gal-border:var(--border,var(--box-inner-border,var(--box-border,var(--nz-border-soft,var(--wc-line,var(--z-border,var(--s-border2,rgb(19,20,23))))))));--gal-accent:var(--accent,var(--link,var(--nz-accent,#d1a233)));--gal-radius:6px;margin:0 0 18px;color:var(--gal-text)}
.znx-gal *{box-sizing:border-box}
.znx-gal__head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:0 0 12px;padding:14px 16px;border:1px solid var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-surface)}
.znx-gal__head h1{margin:0 0 5px;font-size:24px;line-height:1.1;color:var(--gal-accent)}
.znx-gal__head p{margin:0;color:var(--gal-muted);font-size:13px}
.znx-gal__head form{margin:0}
.znx-gal-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 16px;border:1px solid var(--gal-accent);border-radius:var(--gal-radius);background:var(--gal-accent);color:#1a1510;font-weight:800;font-size:13px;cursor:pointer}
.znx-gal-btn:hover{filter:brightness(1.1)}
.znx-gal-empty{padding:30px 16px;text-align:center;color:var(--gal-muted);border:1px dashed var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-surface-2)}
.znx-gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));align-items:stretch;gap:14px}
.znx-gal-card{display:flex;height:100%;flex-direction:column;overflow:hidden;border:1px solid var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-surface-2)}
.znx-gal-card__media{display:block;aspect-ratio:16/10;overflow:hidden;background:var(--gal-well)}
.znx-gal-card__media img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .2s ease}
.znx-gal-card__media:hover img{transform:scale(1.03)}
.znx-gal-card__body{padding:12px 14px 14px}
.znx-gal-card__title{margin:0 0 6px;font-size:14.5px;font-weight:800;color:var(--gal-text)}
.znx-gal-card__desc{margin:0;font-size:12.5px;line-height:1.5;color:var(--gal-muted)}
.znx-gal-panel{max-width:560px;margin:0 0 18px;padding:18px 20px;border:1px solid var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-surface)}
.znx-gal-panel h1{margin:0 0 4px;font-size:20px;color:var(--gal-accent)}
.znx-gal-panel > p{margin:0 0 16px;color:var(--gal-muted);font-size:13px}
.znx-gal-field{margin:0 0 14px}
.znx-gal-field label{display:block;margin:0 0 6px;font-size:12.5px;font-weight:700;color:var(--gal-muted)}
.znx-gal-field input[type=file],.znx-gal-field input[type=text],.znx-gal-field textarea{width:100%;padding:8px 10px;border:1px solid var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-well);color:var(--gal-text);font:inherit}
.znx-gal-field textarea{resize:vertical}
.znx-gal-notice{max-width:560px;margin:0 0 18px;padding:16px 18px;border:1px solid var(--gal-border);border-radius:var(--gal-radius);background:var(--gal-surface)}
.znx-gal-notice h1{margin:0 0 6px;font-size:19px;color:var(--gal-accent)}
.znx-gal-notice p{margin:0;color:var(--gal-muted);font-size:13px}
.znx-gal-preview{max-width:420px;margin:16px 0 0}
.znx-gal-preview h2{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--gal-muted)}
</style>

<?php if ($logged_in === true && !empty($_POST['new'])): ?>

	<section class="znx-gal">
		<div class="znx-gal-panel">
			<h1><?= $znxGalH(t('gallery.create')) ?></h1>
			<p><?= $znxGalH(t('gallery.powered')) ?></p>
			<form action="" method="post" enctype="multipart/form-data">
				<div class="znx-gal-field">
					<label for="imagefile"><?= $znxGalH(t('gallery.select')) ?></label>
					<input type="file" name="imagefile" id="imagefile">
				</div>
				<div class="znx-gal-field">
					<label for="galleryTitle"><?= $znxGalH(t('gallery.image_title')) ?></label>
					<input type="text" name="title" id="galleryTitle" size="70">
				</div>
				<div class="znx-gal-field">
					<label for="galleryDesc"><?= $znxGalH(t('gallery.image_desc')) ?></label>
					<textarea name="desc" id="galleryDesc" cols="55" rows="8"></textarea>
				</div>
				<button type="submit" class="znx-gal-btn" name="submit"><?= $znxGalH(t('gallery.upload')) ?></button>
			</form>
		</div>
	</section>

<?php endif; ?>

<?php if ($logged_in === true && isset($_FILES['imagefile']) && !empty($_FILES['imagefile'])): ?>
	<?php
	$upload = $_FILES['imagefile'];
	if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
		|| !is_uploaded_file((string)$upload['tmp_name'])
		|| (int)$upload['size'] > 5 * 1024 * 1024
		|| !getimagesize($upload['tmp_name'])
	): ?>
		<section class="znx-gal">
			<div class="znx-gal-notice">
				<h1><?= $znxGalH(t('gallery.failed')) ?></h1>
				<p><?= $znxGalH(t('gallery.failed_text')) ?></p>
			</div>
		</section>
	<?php else:
		$image = file_get_contents($upload['tmp_name']);
		$imgurClientID = $config['gallery']['Client ID'];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image/');
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'type' => 'file',
			'name' => $upload['name'],
			'image' => $image,
		));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Client-ID {$imgurClientID}",
		));
		$response = json_decode(curl_exec($ch));
		$image_url = $response->data->link ?? false;
		$image_delete = $response->data->deletehash ?? '';
		$title = $_POST['title'] ?? '';
		$desc = $_POST['desc'] ?? '';

		if ($image_url !== false):
			$inserted = insertImage((int)$session_user_id, $title, $desc, $image_url, $image_delete);
			if ($inserted === true):
				$safeTitle = $znxGalH($title);
				?>
				<section class="znx-gal">
					<div class="znx-gal-notice">
						<h1><?= $znxGalH(t('gallery.posted')) ?></h1>
						<p><?= $znxGalH(t('gallery.posted_text')) ?></p>
						<div class="znx-gal-preview">
							<h2><?= $znxGalH(t('gallery.preview')) ?></h2>
							<div class="znx-gal-card">
								<a class="znx-gal-card__media" href="<?= $znxGalH($image_url) ?>" target="_blank">
									<img src="<?= $znxGalH($image_url) ?>" alt="<?= $safeTitle ?>">
								</a>
								<div class="znx-gal-card__body">
									<h3 class="znx-gal-card__title"><?= $safeTitle ?></h3>
									<p class="znx-gal-card__desc"><?= $znxGalDesc($znxGalH($desc)) ?></p>
								</div>
							</div>
						</div>
					</div>
				</section>
				<?php
			else: ?>
				<section class="znx-gal">
					<div class="znx-gal-notice">
						<h1><?= $znxGalH(t('gallery.exists')) ?></h1>
						<p><?= $znxGalH(t('gallery.exists_text')) ?></p>
					</div>
				</section>
			<?php endif;
		else: ?>
			<section class="znx-gal">
				<div class="znx-gal-notice">
					<h1><?= $znxGalH(t('gallery.failed')) ?></h1>
					<p><?= $znxGalH(t('gallery.failed_text')) ?></p>
				</div>
			</section>
		<?php endif;
	endif; ?>
<?php endif; ?>

<?php if (empty($_POST)): ?>

	<section class="znx-gal">
		<header class="znx-gal__head">
			<div>
				<h1><?= $znxGalH(t('gallery.title')) ?></h1>
				<?php if ($logged_in === true): ?><p><?= $znxGalH(t('gallery.invite')) ?></p><?php endif; ?>
			</div>
			<?php if ($logged_in === true): ?>
				<form action="" method="post">
					<button type="submit" class="znx-gal-btn" name="new" value="1"><?= $znxGalH(t('gallery.add')) ?></button>
				</form>
			<?php endif; ?>
		</header>

		<?php if (is_array($images) && !empty($images)): ?>
			<div class="znx-gal-grid">
				<?php foreach ($images as $image): ?>
					<article class="znx-gal-card">
						<a class="znx-gal-card__media" href="<?= $znxGalH($image['image']) ?>" target="_blank">
							<img src="<?= $znxGalH($image['image']) ?>" alt="<?= $znxGalH($image['title']) ?>" loading="lazy">
						</a>
						<div class="znx-gal-card__body">
							<h3 class="znx-gal-card__title"><?= $znxGalH($image['title']) ?></h3>
							<p class="znx-gal-card__desc"><?= $znxGalDesc($znxGalH($image['desc'])) ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="znx-gal-empty"><?= $znxGalH(t('gallery.empty')) ?></div>
		<?php endif; ?>

		<?php if ($logged_in === false): ?>
			<p style="margin-top:14px;color:var(--gal-muted,var(--color-default,#968452));"><?= $znxGalH(t('gallery.need_login')) ?></p>
		<?php endif; ?>
	</section>

<?php endif; ?>
