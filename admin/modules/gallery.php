<?php
/**
 * Title: Gallery
 * Icon: fa-picture-o
 * Group: Content
 * Order: 20
 * Description: Moderate player screenshot submissions.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_gallery_rebuild_cache(): void {
	$cache  = new Cache('engine/cache/gallery');
	$images = fetchImages(2);

	$data = [];
	if (is_array($images)) {
		foreach ($images as $image) {
			$data[] = [
				'title' => $image['title'] ?? '',
				'desc'  => $image['desc']  ?? '',
				'date'  => $image['date']  ?? '',
				'image' => $image['image'] ?? '',
			];
		}
	}

	$cache->setContent($data);
	$cache->save();
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do = (string)($_POST['do'] ?? '');
	$id = intv($_POST['id'] ?? 0);

	if ($id > 0) {
		// Soft delete: hidden from the public gallery but kept on record.
		if ($do === 'delete') {
			updateImage($id, 3);
			acp_gallery_rebuild_cache();
			acp_flash_success('Image #' . $id . ' hidden from the gallery.');
			acp_redirect('gallery');
		}

		// Accept, or recover a soft-deleted image.
		if ($do === 'accept') {
			updateImage($id, 2);
			acp_gallery_rebuild_cache();
			acp_flash_success('Image #' . $id . ' is now public.');
			acp_redirect('gallery');
		}

		// Hard delete: drop it at imgur too, then remove the row.
		if ($do === 'remove') {
			$delhash        = (string)($_POST['delhash'] ?? '');
			$imgurClientID  = (string)($config['gallery']['Client ID'] ?? '');

			if ($delhash !== '' && $imgurClientID !== '' && function_exists('curl_init')) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image/' . rawurlencode($delhash));
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Client-ID {$imgurClientID}"]);

				$result = curl_exec($ch);
				if ($result === false) {
					error_log('cURL error (imgur delete): ' . curl_error($ch));
				}
				curl_close($ch);
			}

			mysql_delete("DELETE FROM `znote_images` WHERE `id` = {$id} LIMIT 1;");
			acp_gallery_rebuild_cache();
			acp_flash_success('Image #' . $id . ' removed for good.');
			acp_redirect('gallery');
		}
	}

	acp_flash_error('Unknown gallery action.');
	acp_redirect('gallery');
}

/**
 * One moderation section: heading, count, and a card per image.
 *
 * @param array<int, array<string, mixed>>|false $images
 * @param array<int, array{do: string, label: string, icon: string, class: string, confirm?: string}> $actions
 */
function acp_gallery_section($images, string $title, string $subtitle, array $actions, string $emptyText): void {
	$images = is_array($images) ? $images : [];
	?>
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h($title) ?></h2>
			<p><?= h($subtitle) ?></p>
			<span class="acp-pill acp-pill--grey"><?= count($images) ?></span>
		</header>
		<div class="acp-card-body">
			<?php if (!$images): ?>
				<?php acp_empty($emptyText, 'fa-picture-o'); ?>
			<?php else: ?>
				<div class="acp-media">
					<?php foreach ($images as $image):
						$id       = (int)($image['id'] ?? 0);
						$imageUrl = (string)($image['image'] ?? '');
						$delhash  = (string)($image['delhash'] ?? '');
						$desc     = str_replace(['\\r', '\\n'], ['', '<br>'], h((string)($image['desc'] ?? '')));
					?>
						<article class="acp-media-item">
							<a href="<?= h($imageUrl) ?>" target="_blank" rel="noopener">
								<img src="<?= h($imageUrl) ?>" alt="<?= h((string)($image['title'] ?? '')) ?>" loading="lazy">
							</a>
							<div class="acp-media-body">
								<h3><?= h((string)($image['title'] ?? '')) ?></h3>
								<p><?= $desc ?></p>
							</div>
							<div class="acp-media-foot">
								<?php foreach ($actions as $action): ?>
									<form class="acp-inline-form" method="post"
										  <?= isset($action['confirm']) ? 'data-confirm="' . h($action['confirm']) . '"' : '' ?>>
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="<?= h($action['do']) ?>">
										<input type="hidden" name="id" value="<?= $id ?>">
										<?php if ($action['do'] === 'remove'): ?>
											<input type="hidden" name="delhash" value="<?= h($delhash) ?>">
										<?php endif; ?>
										<button class="acp-btn <?= h($action['class']) ?> acp-btn--sm" type="submit">
											<i class="fa <?= h($action['icon']) ?>"></i> <?= h($action['label']) ?>
										</button>
									</form>
								<?php endforeach; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

acp_gallery_section(
	fetchImages(1),
	'Awaiting moderation',
	'Submitted by players, not visible yet',
	[
		['do' => 'accept', 'label' => 'Approve', 'icon' => 'fa-check', 'class' => 'acp-btn--green'],
		['do' => 'delete', 'label' => 'Reject',  'icon' => 'fa-times', 'class' => 'acp-btn--red'],
	],
	'Nothing to moderate right now.'
);

acp_gallery_section(
	fetchImages(2),
	'Public gallery',
	'Live on the site',
	[
		['do' => 'delete', 'label' => 'Hide', 'icon' => 'fa-eye-slash', 'class' => 'acp-btn--amber'],
	],
	'The public gallery is empty.'
);

acp_gallery_section(
	fetchImages(3),
	'Hidden images',
	'Rejected or taken down - recoverable until removed for good',
	[
		['do' => 'accept', 'label' => 'Recover', 'icon' => 'fa-undo',  'class' => 'acp-btn--green'],
		[
			'do'      => 'remove',
			'label'   => 'Delete forever',
			'icon'    => 'fa-trash',
			'class'   => 'acp-btn--red',
			'confirm' => 'Delete this image permanently, including on imgur? This cannot be undone.',
		],
	],
	'No hidden images.'
);
