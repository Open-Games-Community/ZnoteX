<?php require_once 'engine/init.php';
znote_csrf_protect_public_post();
theme_open();
protect_page();
error_reporting(E_ALL ^ E_NOTICE);
if (!$config['forum']['enabled']) admin_only($user_data);
znote_forum_style();
echo '<div class="znx-forum">';
/*  -------------------------------
	---		Znote AAC forum 	---
	-------------------------------
	Created by Znote.
	Version 1.4.

	Changelog (1.0 --> 1.2):
	- Updated to the new date/clock time system
	- Bootstrap design support.

	Changelog (1.2 --> 1.3):
	- Show character outfit as avatar
	- Show in-game position

	Changelog (1.3 -> 1.4):
	- Fix SQL query error when editing Board name.
*/
// BBCode rendering now lives in engine/function/bbcode.php as znote_bbcode().

function znote_forum_editor_assets() {
	static $loaded = false;
	if ($loaded) return;
	$loaded = true;
	$v = '3.2.1';
	?>
	<link rel="stylesheet" href="assets/sceditor/themes/defaultdark.min.css?v=<?php echo $v; ?>">
	<link rel="stylesheet" href="assets/sceditor/znote-editor.css?v=<?php echo $v; ?>">
	<script src="assets/sceditor/sceditor.min.js?v=<?php echo $v; ?>"></script>
	<script src="assets/sceditor/formats/bbcode.min.js?v=<?php echo $v; ?>"></script>
	<script src="assets/sceditor/icons/material.min.js?v=<?php echo $v; ?>"></script>
	<script src="assets/sceditor/plugins/undo.min.js?v=<?php echo $v; ?>"></script>
	<script src="assets/sceditor/plugins/autoyoutube.min.js?v=<?php echo $v; ?>"></script>
	<script src="assets/sceditor/znote-editor.js?v=<?php echo $v; ?>"></script>
	<?php
}

function znote_forum_editor($name, $value = '', $height = 260) {
	global $config;
	znote_forum_style();
	$max = (int)($config['forum']['maxImagesPerPost'] ?? 1);
	?>
	<div class="znote-editor-wrap">
		<textarea class="znote-editor" name="<?php echo $name; ?>"
			data-max-images="<?php echo $max; ?>"
			data-height="<?php echo (int)$height; ?>"
			data-asset-base="assets/sceditor/"><?php echo $value; ?></textarea>
	</div>
	<p class="znote-editor-note"><?php
		echo ($max > 0)
			? 'Up to ' . $max . ' image' . ($max === 1 ? '' : 's') . ' per post.'
			: 'Images are not allowed in forum posts.';
	?></p>
	<?php
	znote_forum_editor_assets();
}

/**
 * The "Post as <character>" picker plus the submit button.
 *
 * This used to be a <select multiple>, which browsers draw as a scrolling list
 * box with nothing selected by default - so submitting without first clicking a
 * name posted no character id at all and the action silently did nothing. A
 * plain dropdown always has a value, and when the account has a single
 * character there is nothing to choose, so it is shown as a label instead.
 */
function znote_forum_style() {
	static $done = false;
	if ($done) return;
	$done = true;
	echo '<link rel="stylesheet" href="assets/forum.css?v=4.1.0">' . "\n";
}

/** Short relative time for the board / thread lists. */
function znote_forum_ago($ts) {
	$ts = (int)$ts;
	if ($ts <= 0) return '';
	$d = time() - $ts;
	if ($d < 0) $d = 0;
	if ($d < 60)     return t('forum.ago_now');
	if ($d < 3600)   return t('forum.ago_min', array('n' => (int)floor($d / 60)));
	if ($d < 86400)  return t('forum.ago_hour', array('n' => (int)floor($d / 3600)));
	if ($d < 2592000) return t('forum.ago_day', array('n' => (int)floor($d / 86400)));
	return date('M j, Y', $ts);
}
function znote_forum_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function znote_forum_character_picker(array $chars, string $field, string $submitLabel, string $submitClass = 'btn btn-primary') {
	znote_forum_style();
	$only = (count($chars) === 1) ? reset($chars) : null;
	?>
	<div class="znote-postas">
		<span class="znote-postas-label"><?= t('forum.post_as') ?></span>

		<?php if ($only !== null): ?>
			<input type="hidden" name="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int)$only['id']; ?>">
			<span class="znote-postas-single"><?php echo htmlspecialchars($only['name'], ENT_QUOTES, 'UTF-8'); ?></span>
		<?php else: ?>
			<select class="znote-postas-select form-control" name="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
				<?php foreach ($chars as $char): ?>
					<option value="<?php echo (int)$char['id']; ?>"><?php echo htmlspecialchars($char['name'], ENT_QUOTES, 'UTF-8'); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<button type="submit" class="znote-postas-btn <?php echo htmlspecialchars($submitClass, ENT_QUOTES, 'UTF-8'); ?>">
			<?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?>
		</button>
	</div>
	<?php
}

Function PlayerHaveAccess($yourChars, $playerName){
	$access = false;
	foreach($yourChars as $char) {
		if ($char['name'] == $playerName) $access = true;
	}
	return $access;
}

// Start page init
$admin = is_admin($user_data);
if ($admin) $yourChars = db()->fetchAll("SELECT `id`, `name`, `group_id` FROM `players` WHERE `level` >= 1 AND `account_id` = ?;", [$user_data['id']]);
else $yourChars = db()->fetchAll("SELECT `id`, `name`, `group_id` FROM `players` WHERE `level` >= ? AND `account_id` = ?;", [(int)$config['forum']['level'], $user_data['id']]);
if (!$yourChars) $yourChars = array();
$charCount = count($yourChars);
$yourAccess = accountAccess($user_data['id'], znote_server_adapter()->normalizedEngine());
if ($admin) {
	if (!empty($_POST)) {
		$guilds = db()->fetchAll("SELECT `id`, `name` FROM `guilds` ORDER BY `name`;");
		$guilds[] = array('id' => '0', 'name' => 'No guild');
	}
	$yourAccess = 100;
}

// Your characters, indexed by char_id
$charData = array();
foreach ($yourChars as $char) {
	$charData[$char['id']] = $char;
	if (get_character_guild_rank($char['id']) > 0) {
		$guild = get_player_guild_data($char['id']);
		$charData[$char['id']]['guild'] = $guild['guild_id'];
		$charData[$char['id']]['guild_rank'] = $guild['rank_level'];
	} else $charData[$char['id']]['guild'] = '0';
}
$cooldownw = array(
	$user_znote_data['cooldown'],
	time(),
	$user_znote_data['cooldown'] - time()
	);

/////////////////
// Guild Leader & admin
$leader = false;
foreach($charData as $char) {
	if ($char['guild'] > 0 && $char['guild_rank'] == 3) $leader = true;
}
if ($admin && !empty($_POST) || $leader && !empty($_POST)) {
	$admin_thread_delete = getValue($_POST['admin_thread_delete'] ?? null);
	$admin_thread_close = getValue($_POST['admin_thread_close'] ?? null);
	$admin_thread_open = getValue($_POST['admin_thread_open'] ?? null);
	$admin_thread_sticky = getValue($_POST['admin_thread_sticky'] ?? null);
	$admin_thread_unstick = getValue($_POST['admin_thread_unstick'] ?? null);
	$admin_thread_id = getValue($_POST['admin_thread_id'] ?? null);

	// delete thread
	if ($admin_thread_delete !== false) {
		$admin_thread_id = (int)$admin_thread_id;
		$access = false;
		if (!$admin) {
			$thread = db()->fetchOne("SELECT `forum_id` FROM `znote_forum_threads` WHERE `id` = ?;", [$admin_thread_id]);
			$forum = db()->fetchOne("SELECT `guild_id` FROM `znote_forum` WHERE `id` = ?;", [$thread['forum_id']]);
			foreach($charData as $char) if ($char['guild'] == $forum['guild_id'] && $char['guild_rank'] == 3) $access = true;
		} else $access = true;

		if ($access) {
			// Delete all associated posts
			db()->execute("DELETE FROM `znote_forum_posts` WHERE `thread_id` = ?;", [$admin_thread_id]);
			// Delete thread itself
			db()->execute("DELETE FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$admin_thread_id]);
			acp_log('forum.thread_delete', '#' . $admin_thread_id, ['scope' => $admin ? 'owner' : 'guild_leader']);
			echo '<h1>'. t('forum.thread_deleted'). '</h1>';
		} else echo '<p><b><font color="red">'. t('forum.perm_denied'). '</font></b></p>';
	}

	// Close thread
	if ($admin_thread_close !== false) {
		$admin_thread_id = (int)$admin_thread_id;
		$access = false;
		if (!$admin) {
			$thread = db()->fetchOne("SELECT `forum_id` FROM `znote_forum_threads` WHERE `id` = ?;", [$admin_thread_id]);
			$forum = db()->fetchOne("SELECT `guild_id` FROM `znote_forum` WHERE `id` = ?;", [$thread['forum_id']]);
			foreach($charData as $char) if ($char['guild'] == $forum['guild_id'] && $char['guild_rank'] == 3) $access = true;
		} else $access = true;
		if ($access) {
			db()->execute("UPDATE `znote_forum_threads` SET `closed` = 1 WHERE `id` = ? LIMIT 1;", [$admin_thread_id]);
			acp_log('forum.thread_close', '#' . $admin_thread_id, ['scope' => $admin ? 'owner' : 'guild_leader']);
			echo '<h1>'. t('forum.thread_closed'). '</h1>';
		} else echo '<p><b><font color="red">'. t('forum.perm_denied'). '</font></b></p>';
	}

	// open thread
	if ($admin_thread_open !== false) {
		$admin_thread_id = (int)$admin_thread_id;
		$access = false;
		if (!$admin) {
			$thread = db()->fetchOne("SELECT `forum_id` FROM `znote_forum_threads` WHERE `id` = ?;", [$admin_thread_id]);
			$forum = db()->fetchOne("SELECT `guild_id` FROM `znote_forum` WHERE `id` = ?;", [$thread['forum_id']]);
			foreach($charData as $char) if ($char['guild'] == $forum['guild_id'] && $char['guild_rank'] == 3) $access = true;
		} else $access = true;
		if ($access) {
			db()->execute("UPDATE `znote_forum_threads` SET `closed` = 0 WHERE `id` = ? LIMIT 1;", [$admin_thread_id]);
			acp_log('forum.thread_open', '#' . $admin_thread_id, ['scope' => $admin ? 'owner' : 'guild_leader']);
			echo '<h1>'. t('forum.thread_opened'). '</h1>';
		} else echo '<p><b><font color="red">'. t('forum.perm_denied2') .'</font></b></p>';
	}

	// stick thread
	if ($admin_thread_sticky !== false) {
		$admin_thread_id = (int)$admin_thread_id;
		$access = false;
		if (!$admin) {
			$thread = db()->fetchOne("SELECT `forum_id` FROM `znote_forum_threads` WHERE `id` = ?;", [$admin_thread_id]);
			$forum = db()->fetchOne("SELECT `guild_id` FROM `znote_forum` WHERE `id` = ?;", [$thread['forum_id']]);
			foreach($charData as $char) if ($char['guild'] == $forum['guild_id'] && $char['guild_rank'] == 3) $access = true;
		} else $access = true;
		if ($access) {
			db()->execute("UPDATE `znote_forum_threads` SET `sticky` = 1 WHERE `id` = ? LIMIT 1;", [$admin_thread_id]);
			acp_log('forum.thread_sticky', '#' . $admin_thread_id, ['scope' => $admin ? 'owner' : 'guild_leader']);
			echo '<h1>'. t('forum.thread_stuck2'). '</h1>';
		} else echo '<p><b><font color="red">'. t('forum.perm_denied2') .'</font></b></p>';
	}

	// unstick thread
	if ($admin_thread_unstick !== false) {
		$admin_thread_id = (int)$admin_thread_id;
		$access = false;
		if (!$admin) {
			$thread = db()->fetchOne("SELECT `forum_id` FROM `znote_forum_threads` WHERE `id` = ?;", [$admin_thread_id]);
			$forum = db()->fetchOne("SELECT `guild_id` FROM `znote_forum` WHERE `id` = ?;", [$thread['forum_id']]);
			foreach($charData as $char) if ($char['guild'] == $forum['guild_id'] && $char['guild_rank'] == 3) $access = true;
		} else $access = true;
		if ($access) {
			db()->execute("UPDATE `znote_forum_threads` SET `sticky` = 0 WHERE `id` = ? LIMIT 1;", [$admin_thread_id]);
			acp_log('forum.thread_unsticky', '#' . $admin_thread_id, ['scope' => $admin ? 'owner' : 'guild_leader']);
			echo '<h1>'. t('forum.thread_unstuck2'). '</h1>';
		} else echo '<p><b><font color="red">'. t('forum.perm_denied2') .'</font></b></p>';
	}
}

/////////////////
// ADMIN FUNCT
if ($admin && !empty($_POST)) {
	$admin_post_id = getValue($_POST['admin_post_id'] ?? null);
	$admin_post_delete = getValue($_POST['admin_post_delete'] ?? null);

	$admin_category_delete = getValue($_POST['admin_category_delete'] ?? null);
	$admin_category_edit = getValue($_POST['admin_category_edit'] ?? null);
	$admin_category_id = getValue($_POST['admin_category_id'] ?? null);

	$admin_update_category = getValue($_POST['admin_update_category'] ?? null);
	$admin_category_name = getValue($_POST['admin_category_name'] ?? null);

	$admin_category_access = getValue($_POST['admin_category_access'] ?? null);
	$admin_category_closed = getValue($_POST['admin_category_closed'] ?? null);
	$admin_category_hidden = getValue($_POST['admin_category_hidden'] ?? null);
	$admin_category_guild_id = getValue($_POST['admin_category_guild_id'] ?? null);

	if ($admin_category_access === false) $admin_category_access = 0;
	if ($admin_category_closed === false) $admin_category_closed = 0;
	if ($admin_category_hidden === false) $admin_category_hidden = 0;
	if ($admin_category_guild_id === false) $admin_category_guild_id = 0;

	$admin_board_create_name = getValue($_POST['admin_board_create_name'] ?? null);
	$admin_board_create_access = getValue($_POST['admin_board_create_access'] ?? null);
	$admin_board_create_closed = getValue($_POST['admin_board_create_closed'] ?? null);
	$admin_board_create_hidden = getValue($_POST['admin_board_create_hidden'] ?? null);
	$admin_board_create_guild_id = getValue($_POST['admin_board_create_guild_id'] ?? null);

	if ($admin_board_create_access === false) $admin_board_create_access = 0;
	if ($admin_board_create_closed === false) $admin_board_create_closed = 0;
	if ($admin_board_create_hidden === false) $admin_board_create_hidden = 0;
	if ($admin_board_create_guild_id === false) $admin_board_create_guild_id = 0;

	// Create board
	if ($admin_board_create_name !== false) {

		// Insert data
		db()->execute("INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`)
			VALUES (?, ?, ?, ?, ?);", [
				$admin_board_create_name,
				$admin_board_create_access,
				$admin_board_create_closed,
				$admin_board_create_hidden,
				$admin_board_create_guild_id,
			]);
		acp_log('forum.board_create', (string)$admin_board_create_name, ['access' => $admin_board_create_access, 'guild_id' => $admin_board_create_guild_id]);
		echo '<h1>'. t('forum.board_created'). '</h1>';
	}

	//////////////////
	// update category
	if ($admin_update_category !== false) {
		$admin_category_id = (int)$admin_category_id;

		// Update the category
		db()->execute("UPDATE `znote_forum` SET
			`name` = ?,
			`access` = ?,
			`closed` = ?,
			`hidden` = ?,
			`guild_id` = ?
			WHERE `id` = ? LIMIT 1;", [
				$admin_category_name,
				$admin_category_access,
				$admin_category_closed,
				$admin_category_hidden,
				$admin_category_guild_id,
				$admin_category_id,
			]);
		acp_log('forum.board_update', '#' . $admin_category_id, ['access' => $admin_category_access, 'guild_id' => $admin_category_guild_id]);
		echo '<h1>'. t('forum.board_updated'). '</h1>';
	}

	//////////////////
	// edit category
	if ($admin_category_edit !== false) {
		$admin_category_id = (int)$admin_category_id;
		$category = db()->fetchOne("SELECT `id`, `name`, `access`, `closed`, `hidden`, `guild_id`
			FROM `znote_forum` WHERE `id` = ? LIMIT 1;", [$admin_category_id]);
		if ($category !== false) {
			?>
			<form action="" method="post">
				<input type="hidden" name="admin_category_id" value="<?php echo $category['id']; ?>">
				<table class="updateTable table table-striped">
					<tr>
						<td><label for="admin_category_name"><?= t('forum.board_name') ?></label></td>
						<td><input name="admin_category_name" value="<?php echo $category['name']; ?>" class="span12"></td>

					</tr>
					<tr>
						<td><label for="admin_category_access"><?= t('forum.required_access') ?></label></td>
						<td>
							<select name="admin_category_access" class="span12">
								<?php
								foreach($config['ingame_positions'] as $access => $name) {
									if ($access == $category['access']) echo "<option value='$access' selected>$name</option>";
									else echo "<option value='$access'>$name</option>";
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><label for="admin_category_closed"><?= t('forum.closed') ?></label></td>
						<td>
							<select name="admin_category_closed" class="span12">
								<?php
								if ($category['closed'] == 1) echo '<option value="1" selected>Yes</option>';
								else echo '<option value="1">Yes</option>';
								if ($category['closed'] == 0) echo '<option value="0" selected>No</option>';
								else echo '<option value="0">No</option>';
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><label for="admin_category_hidden"><?= t('forum.hidden') ?></label></td>
						<td>
							<select name="admin_category_hidden" class="span12">
								<?php
								if ($category['hidden'] == 1) echo '<option value="1" selected>Yes</option>';
								else echo '<option value="1">Yes</option>';
								if ($category['hidden'] == 0) echo '<option value="0" selected>No</option>';
								else echo '<option value="0">No</option>';
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><label for="admin_category_guild_id"><?= t('forum.guild_id') ?></label></td>
						<td>
							<select name="admin_category_guild_id" class="span12">
								<?php foreach($guilds as $guild) {
									if ($category['guild_id'] == $guild['id']) echo "<option value='". $guild['id'] ."' selected>". $guild['name'] ."</option>";
									else echo "<option value='". $guild['id'] ."'>". $guild['name'] ."</option>";
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2"><input type="submit" name="admin_update_category" value="<?= t('forum.update_board') ?>" style="width: 100%; height: 30px;" class="btn btn-success"></td>
					</tr>
				</table>
			</form>
			<?php
		} else echo '<h2>'. t('forum.cat_not_found'). '</h2>';

	}

	// delete category
	if ($admin_category_delete !== false) {
		$admin_category_id = (int)$admin_category_id;

		// find all threads in category
		$threads = db()->fetchAll("SELECT `id` FROM `znote_forum_threads` WHERE `forum_id` = ?;", [$admin_category_id]);

		// Then loop through all threads, and delete all associated posts:
		foreach($threads as $thread) {
			db()->execute("DELETE FROM `znote_forum_posts` WHERE `thread_id` = ?;", [$thread['id']]);
		}
		// Then delete all threads
		db()->execute("DELETE FROM `znote_forum_threads` WHERE `forum_id` = ?;", [$admin_category_id]);
		// Then delete the category
		db()->execute("DELETE FROM `znote_forum` WHERE `id` = ? LIMIT 1;", [$admin_category_id]);
		acp_log('forum.board_delete', '#' . $admin_category_id);
		echo '<h1>Board, associated threads and all their associated posts deleted.</h1>';
	}

	// delete post
	if ($admin_post_delete !== false) {
		$admin_post_id = (int)$admin_post_id;

		// Delete the post
		db()->execute("DELETE FROM `znote_forum_posts` WHERE `id` = ? LIMIT 1;", [$admin_post_id]);
		acp_log('forum.post_delete', '#' . $admin_post_id);
		echo '<h1>'. t('forum.post_deleted'). '</h1>';
	}
}
// End admin function

// Fetching get values
if (!empty($_GET)) {
	$getCat = getValue($_GET['cat'] ?? null);
	$getForum = getValue($_GET['forum'] ?? null);
	$getThread = getValue($_GET['thread'] ?? null);

	$new_thread_category = getValue($_POST['new_thread_category'] ?? null);
	$new_thread_cid = getValue($_POST['new_thread_cid'] ?? null);

	$create_thread_cid = getValue($_POST['create_thread_cid'] ?? null);
	$create_thread_title = getValue($_POST['create_thread_title'] ?? null);
	$create_thread_text = getValue($_POST['create_thread_text'] ?? null);
	$create_thread_category = getValue($_POST['create_thread_category'] ?? null);

	$update_thread_id = getValue($_POST['update_thread_id'] ?? null);
	$update_thread_title = getValue($_POST['update_thread_title'] ?? null);
	$update_thread_text = getValue($_POST['update_thread_text'] ?? null);

	$edit_thread = getValue($_POST['edit_thread'] ?? null);
	$edit_thread_id = getValue($_POST['edit_thread_id'] ?? null);

	$reply_thread = getValue($_POST['reply_thread'] ?? null);
	$reply_text = getValue($_POST['reply_text'] ?? null);
	$reply_cid = getValue($_POST['reply_cid'] ?? null);

	$edit_post = getValue($_POST['edit_post'] ?? null);
	$edit_post_id = getValue($_POST['edit_post_id'] ?? null);

	$update_post_id = getValue($_POST['update_post_id'] ?? null);
	$update_post_text = getValue($_POST['update_post_text'] ?? null);

	// Image cap. The editor checks this too, but that check is a convenience -
	// this one is the rule, because a POST does not have to come from the editor.
	// Blanking the field makes the save branches below skip, since each requires
	// its text to be !== false.
	$forumImageLimit = (int)($config['forum']['maxImagesPerPost'] ?? 1);
	$forumImageDenied = false;
	foreach (array('create_thread_text', 'update_thread_text', 'reply_text', 'update_post_text') as $forumField) {
		if ($$forumField !== false && znote_bbcode_count_images($$forumField) > $forumImageLimit) {
			$forumImageDenied = true;
			$$forumField = false;
		}
	}
	if ($forumImageDenied) {
		echo '<p><b><font color="red">'
			. ($forumImageLimit > 0
				? 'Too many images: only ' . $forumImageLimit . ' allowed per post. Nothing was saved.'
				: 'Images are not allowed in forum posts. Nothing was saved.')
			. '</font></b></p>';
	}

	/////////////////////
	// When you are POSTING in an existing thread
	if ($reply_thread !== false && $reply_text !== false && $reply_cid !== false) {
		$reply_cid = (int)$reply_cid;

		if ($user_znote_data['cooldown'] < time()) {
			user_update_znote_account(array('cooldown'=>(time() + $config['forum']['cooldownPost'])));

			$thread = db()->fetchOne("SELECT `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$reply_thread]);

			if (!is_array($thread) || !isset($charData[$reply_cid])) $access = false;
			else if ($thread['closed'] == 1 && $admin === false) $access = false;
			else $access = true;

			if ($access) {
				db()->execute("INSERT INTO `znote_forum_posts` (`thread_id`, `player_id`, `player_name`, `text`, `created`, `updated`) VALUES (?, ?, ?, ?, ?, ?);", [$reply_thread, $reply_cid, $charData[$reply_cid]['name'], $reply_text, time(), time()]);
				if ($config['forum']['newPostsBumpThreads']) db()->execute("UPDATE `znote_forum_threads` SET `updated` = ? WHERE `id` = ?;", [time(), $reply_thread]);
			} else echo '<p><b><font color="red">You don\'t have permission to post on this thread. [Thread: Closed]</font></b></p>';
		} else {
			?>
				<p class="forumCooldown"><?= t('forum.antispam') ?> <?php echo ($user_znote_data['cooldown'] - time()); ?> seconds before you can create or post.</p>
			<?php
		}
	}

	/////////////////////
	// When you ARE creating new thread
	if ($create_thread_cid !== false && $create_thread_title !== false && $create_thread_text !== false && $create_thread_category !== false) {
		if ($user_znote_data['cooldown'] < time()) {
			user_update_znote_account(array('cooldown'=>(time() + $config['forum']['cooldownCreate'])));

			$category = db()->fetchOne("SELECT `access`, `closed`, `guild_id` FROM `znote_forum` WHERE `id` = ? LIMIT 1;", [$create_thread_category]);
			if ($category !== false) {
				$access = true;
				if (!$admin) {
					if ($category['access'] > $yourAccess) $access = false;
					if ($category['guild_id'] > 0) {
						$status = false;
						foreach($charData as $char) {
							if ($char['guild'] == $category['guild_id']) $status = true;
						}
						if (!$status) $access = false;
					}
					if ($category['closed'] > 0) $access = false;
				}

				if ($access) {
					db()->execute("INSERT INTO `znote_forum_threads`
						(`forum_id`, `player_id`, `player_name`, `title`, `text`, `created`, `updated`, `sticky`, `hidden`, `closed`)
						VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0);", [
							$create_thread_category,
							$create_thread_cid,
							$charData[$create_thread_cid]['name'],
							$create_thread_title,
							$create_thread_text,
							time(),
							time(),
						]);
					SendGet(array('cat'=>$create_thread_category), 'forum.php');
				} else echo '<p><b><font color="red">'. t('forum.perm_thread'). '</font></b></p>';
			} else echo t('forum.cat_missing');
		} else {
			?>
				<p class="forumCooldown"><?= t('forum.antispam') ?> <?php echo ($user_znote_data['cooldown'] - time()); ?> seconds before you can create or post.</p>
			<?php
		}
	}

	/////////////////////
	// When you ARE updating post
	if ($update_post_id !== false && $update_post_text !== false) {
		// Fetch the post data
		$post = db()->fetchOne("SELECT `id`, `player_name`, `text`, `thread_id` FROM `znote_forum_posts` WHERE `id` = ? LIMIT 1;", [$update_post_id]);
		if (!is_array($post)) $post = array('id' => 0, 'player_name' => '', 'text' => '', 'thread_id' => 0);
		$thread = db()->fetchOne("SELECT `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$post['thread_id']]);

		// Verify access
		$access = PlayerHaveAccess($yourChars, $post['player_name']);
		if ($thread !== false && $thread['closed'] == 1 && $admin === false) $access = false;
		if ($admin) $access = true;
		//if ($thread === false) $access = false;

		if ($access) {
			db()->execute("UPDATE `znote_forum_posts` SET `text` = ?, `updated` = ? WHERE `id` = ?;", [$update_post_text, time(), $update_post_id]);
			if ($admin) acp_log('forum.post_update', '#' . (int)$update_post_id);
			echo '<h1>post has been updated.</h1>';
		} else echo "<p class='znf-alert'>" . t('forum.edit_post_denied') . "</p>";
	}

	/////////////////////
	// When you ARE updating thread
	if ($update_thread_id !== false && $update_thread_title !== false && $update_thread_text !== false) {
		// Fetch the thread data
		$thread = db()->fetchOne("SELECT `id`, `player_name`, `title`, `text`, `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$update_thread_id]);
		if (!is_array($thread)) $thread = array('id' => 0, 'player_name' => '', 'title' => '', 'text' => '', 'closed' => 0);

		// Verify access
		$access = PlayerHaveAccess($yourChars, $thread['player_name']);
		if ($thread['closed'] == 1 && $admin === false) $access = false;
		if ($admin) $access = true;

		if ($access) {
			db()->execute("UPDATE `znote_forum_threads` SET `title` = ?, `text` = ? WHERE `id` = ?;", [$update_thread_title, $update_thread_text, $update_thread_id]);
			if ($admin) acp_log('forum.thread_update', '#' . (int)$update_thread_id, ['title' => (string)$update_thread_title]);
			echo '<h1>'. t('forum.thread_updated'). '</h1>';
		} else echo "<p class='znf-alert'>" . t('forum.edit_thread_denied') . "</p>";
	}

	/////////////////////
	// When you want to edit a post
	if ($edit_post_id !== false && $edit_post !== false) {
		// Fetch the post data
		$post = db()->fetchOne("SELECT `id`, `thread_id`, `text`, `player_name` FROM `znote_forum_posts` WHERE `id` = ? LIMIT 1;", [$edit_post_id]);
		if (!is_array($post)) $post = array('id' => 0, 'thread_id' => 0, 'text' => '', 'player_name' => '');
		$thread = db()->fetchOne("SELECT `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$post['thread_id']]);
		if (!is_array($thread)) $thread = array('closed' => 0);
		// Verify access
		$access = PlayerHaveAccess($yourChars, $post['player_name']);
		if ($thread['closed'] == 1 && $admin === false) $access = false;
		if ($admin) $access = true;

		if ($access) {
			?>
			<h1><?= t('forum.edit_post') ?></h1>
			<form type="" method="post">
				<input name="update_post_id" type="hidden" value="<?php echo $post['id']; ?>">
				<?php znote_forum_editor('update_post_text', $post['text'], 300); ?>
				<input type="submit" value="<?= t('forum.update_post') ?>" class="btn btn-success">
			</form>
			<?php
		} else echo '<p><b><font color="red">'. t('forum.no_edit_post') .'</font></b></p>';
	} else

	/////////////////////
	// When you want to edit a thread
	if ($edit_thread_id !== false && $edit_thread !== false) {
		// Fetch the thread data
		$thread = db()->fetchOne("SELECT `id`, `title`, `text`, `player_name`, `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$edit_thread_id]);

		$access = PlayerHaveAccess($yourChars, $thread['player_name']);
		if ($thread['closed'] == 1) $access = false;
		if ($admin) $access = true;

		if ($access) {
			?>
			<h1><?= t('forum.edit_thread') ?></h1>
			<form type="" method="post">
				<input name="update_thread_id" type="hidden" value="<?php echo $thread['id']; ?>">
				<input name="update_thread_title" type="text" value="<?php echo $thread['title']; ?>" style="width: 500px;"><br><br>
				<?php znote_forum_editor('update_thread_text', $thread['text'], 300); ?>
				<input type="submit" value="<?= t('forum.update_thread') ?>" class="btn btn-success">
			</form>
			<?php
		} else echo '<p><b><font color="red">'. t('forum.edit_denied'). '</font></b></p>';
	} else

	/////////////////////
	// When you want to view a thread
	if ($getThread !== false) {
		$getThread = (int)$getThread;
		$threadData = db()->fetchOne("SELECT `id`, `forum_id`, `player_id`, `player_name`, `title`, `text`, `created`, `updated`, `sticky`, `hidden`, `closed` FROM `znote_forum_threads` WHERE `id` = ? LIMIT 1;", [$getThread]);

		if ($threadData !== false) {

			$category = db()->fetchOne("SELECT `hidden`, `access`, `guild_id` FROM `znote_forum` WHERE `id` = ? LIMIT 1;", [$threadData['forum_id']]);
			if ($category === false) die("Thread category does not exist.");

			$access = true;
			$leader = false;
			if ($category['hidden'] == 1 || $category['access'] > 1 || $category['guild_id'] > 0) {
				$access = false;
				if ($category['hidden'] == 1) $access = PlayerHaveAccess($yourChars, $threadData['player_name']);
				if ($category['access'] > 1 && $yourAccess >= $category['access']) $access = true;
				foreach($charData as $char) {
					if ($category['guild_id'] == $char['guild']) $access = true;
					if ($char['guild_rank'] == 3) $leader = true;
				}
				if ($admin) $access = true;
			}


			if ($access) {
				$threadPlayer = ($config['forum']['outfit_avatars'] || $config['forum']['player_position']) ? db()->fetchOne("SELECT `id`, `group_id`, `sex`, `lookbody`, `lookfeet`, `lookhead`, `looklegs`, `looktype`, `lookaddons` FROM `players` WHERE `id` = ?;", [$threadData['player_id']]) : false;
				?>
				<nav class="znf-crumbs"><a href="forum.php">Forum</a> <span>/</span> <a href="?cat=<?php echo $getCat; ?>"><?php echo $getForum; ?></a></nav>
				<h1 id="ThreadTitle" class="znf-title"><?php echo "<a href='?forum=". $getForum ."&cat=". $getCat ."&thread=". $threadData['id'] ."'>". $threadData['title'] ."</a>"; ?></h1>
				<article class="znf-post is-op">
					<div class="znf-post__side avatar">
						<a class="znf-post__author" href="characterprofile.php?name=<?php echo $threadData['player_name']; ?>"><?php echo $threadData['player_name']; ?></a>
						<?php if ($threadPlayer !== false && $config['forum']['outfit_avatars']): ?>
						<img class="znf-post__outfit" src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $threadPlayer['looktype']; ?>&addons=<?php echo $threadPlayer['lookaddons']; ?>&head=<?php echo $threadPlayer['lookhead']; ?>&body=<?php echo $threadPlayer['lookbody']; ?>&legs=<?php echo $threadPlayer['looklegs']; ?>&feet=<?php echo $threadPlayer['lookfeet']; ?>" alt="img">
						<?php endif; ?>
						<?php if ($threadPlayer !== false && $config['forum']['player_position']): ?>
						<span class="znf-post__rank"><?php echo group_id_to_name($threadPlayer['group_id']); ?></span>
						<?php endif; ?>
					</div>
					<div class="znf-post__body">
						<div class="znf-post__meta"><span class="znf-tag znf-tag--sticky"><?= t('forum.op_tag') ?></span> <?php echo getClock($threadData['created'], true); ?></div>
						<div class="znf-post__text"><?php echo znote_bbcode($threadData['text']); ?></div>
						<?php if ($charCount > 0 && ($threadData['closed'] == 0 || $yourAccess > 3)): ?>
						<div class="znf-post__actions">
							<button type="button" class="btn btn-info znf-quote-btn" data-author="<?php echo htmlspecialchars($threadData['player_name'], ENT_QUOTES, 'UTF-8'); ?>"><?= t('forum.quote') ?></button>
						</div>
						<?php endif; ?>
						<template class="znf-quote-source"><?php echo htmlspecialchars($threadData['text'], ENT_QUOTES, 'UTF-8'); ?></template>
					</div>
				</article>
				<?php
				if ($admin || $leader) {
					?>
					<div class="znf-mod">
						<form action="" method="post">
							<input type="hidden" name="admin_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="admin_thread_delete" value="<?= t('forum.delete_thread') ?>" class="btn btn-danger">
						</form>
						<?php if ($threadData['closed'] == 0) { ?>
						<form action="" method="post">
							<input type="hidden" name="admin_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="admin_thread_close" value="<?= t('forum.close_thread') ?>" class="btn btn-warning">
						</form>
						<?php } else { ?>
						<form action="" method="post">
							<input type="hidden" name="admin_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="admin_thread_open" value="<?= t('forum.open_thread') ?>" class="btn btn-success">
						</form>
						<?php } ?>
						<?php if ($threadData['sticky'] == 0) { ?>
						<form action="" method="post">
							<input type="hidden" name="admin_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="admin_thread_sticky" value="<?= t('forum.stick') ?>" class="btn btn-info">
						</form>
						<?php } else { ?>
						<form action="" method="post">
							<input type="hidden" name="admin_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="admin_thread_unstick" value="<?= t('forum.unstick') ?>" class="btn btn-primary">
						</form>
						<?php } ?>
						<form action="" method="post">
							<input type="hidden" name="edit_thread_id" value="<?php echo $threadData['id']; ?>">
							<input type="submit" name="edit_thread" value="<?= t('forum.edit_thread') ?>" class="btn btn-warning">
						</form>
					</div>
					<?php
				} else {
					if ($threadData['closed'] == 0 && PlayerHaveAccess($yourChars, $threadData['player_name'])) {
						?>
						<div class="znf-mod">
							<form action="" method="post">
								<input type="hidden" name="edit_thread_id" value="<?php echo $threadData['id']; ?>">
								<input type="submit" name="edit_thread" value="<?= t('forum.edit_thread') ?>" class="btn btn-info">
							</form>
						</div>
						<?php
					}
				}
				?>
				<?php
				// Display replies... (copy table above and edit each post)
				$posts = db()->fetchAll("SELECT `id`, `player_id`, `player_name`, `text`, `created`, `updated` FROM `znote_forum_posts` WHERE `thread_id` = ? ORDER BY `created`;", [$threadData['id']]);
				if ($posts !== false) {
					// Load extra data (like outfit avatars?)
					$players = array();
					$extra = false;
					if ($config['forum']['outfit_avatars'] || $config['forum']['player_position']) {
						$extra = true;

						foreach($posts as $post)
							if (!isset($players[$post['player_id']]))
								$players[$post['player_id']] = array();

						$playerIds = array_keys($players);
						$playerIdPlaceholders = implode(',', array_fill(0, count($playerIds), '?'));
						$sql_players = db()->fetchAll("SELECT `id`, `group_id`, `sex`, `lookbody`, `lookfeet`, `lookhead`, `looklegs`, `looktype`, `lookaddons` FROM `players` WHERE `id` IN ({$playerIdPlaceholders});", $playerIds);

						foreach ($sql_players as $player)
							$players[$player['id']] = $player;

					}

					foreach($posts as $post) {
						?>
						<article class="znf-post<?php echo ($post['player_name'] === $threadData['player_name']) ? ' is-op' : ''; ?>">
							<div class="znf-post__side avatar">
								<a class="znf-post__author" href="characterprofile.php?name=<?php echo $post['player_name']; ?>"><?php echo $post['player_name']; ?></a>
								<?php if ($extra && $config['forum']['outfit_avatars']): ?>
								<img class="znf-post__outfit" src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $players[$post['player_id']]['looktype']; ?>&addons=<?php echo $players[$post['player_id']]['lookaddons']; ?>&head=<?php echo $players[$post['player_id']]['lookhead']; ?>&body=<?php echo $players[$post['player_id']]['lookbody']; ?>&legs=<?php echo $players[$post['player_id']]['looklegs']; ?>&feet=<?php echo $players[$post['player_id']]['lookfeet']; ?>" alt="img">
								<?php endif; ?>
								<?php if ($extra && $config['forum']['player_position']): ?>
								<span class="znf-post__rank"><?php echo group_id_to_name($players[$post['player_id']]['group_id']); ?></span>
								<?php endif; ?>
							</div>
							<div class="znf-post__body">
								<div class="znf-post__meta"><?php echo getClock($post['created'], true); ?></div>
								<div class="znf-post__text"><?php echo znote_bbcode($post['text']); ?></div>
								<div class="znf-post__actions">
						<?php
						if ($charCount > 0 && ($threadData['closed'] == 0 || $yourAccess > 3)) {
							?>
							<button type="button" class="btn btn-info znf-quote-btn" data-author="<?php echo htmlspecialchars($post['player_name'], ENT_QUOTES, 'UTF-8'); ?>"><?= t('forum.quote') ?></button>
							<?php
						}
						if (PlayerHaveAccess($yourChars, $post['player_name']) || $admin) {
							if ($admin) {
								?>
								<form action="" method="post" class="postButton">
									<input type="hidden" name="admin_post_id" value="<?php echo $post['id']; ?>">
									<input type="submit" name="admin_post_delete" value="<?= t('forum.delete_post') ?>" class="btn btn-danger">
								</form>
								<?php
							}
							if ($threadData['closed'] == 0 || $admin) {
								?>
								<form action="" method="post" class="postButton">
									<input type="hidden" name="edit_post_id" value="<?php echo $post['id']; ?>">
									<input type="submit" name="edit_post" value="<?= t('forum.edit_post') ?>" class="btn btn-info">
								</form>
								<?php
							}
						}
						?>
								</div>
								<template class="znf-quote-source"><?php echo htmlspecialchars($post['text'], ENT_QUOTES, 'UTF-8'); ?></template>
							</div>
						</article>
						<?php
					}
				}

				// Quick Reply
				if ($charCount > 0) {
					if ($threadData['closed'] == 0 || $yourAccess > 3) {
						?>
						<div class="znf-reply">
							<h3 class="znf-reply__title"><?= t('forum.reply_title') ?></h3>
							<form action="" method="post">
								<input name="reply_thread" type="hidden" value="<?php echo $threadData['id']; ?>">
								<?php znote_forum_editor('reply_text', '', 200); ?>
								<?php znote_forum_character_picker($yourChars, 'reply_cid', 'Post Reply', 'btn btn-primary'); ?>
							</form>
						</div>
						<?php
					} else echo '<p class="znf-note">You don\'t have permission to post on this thread. [Thread: Closed]</p>';
				} else {
					?><p class="znf-note">You must have a character on your account that is level <?php echo (int)$config['forum']['level']; ?>+ to reply to this thread.</p><?php
				}
			} else echo '<p class="znf-alert">Your permission to access this thread has been denied.</p>';
		} else {
			?>
			<h1><?= t('forum.thread_unavailable') ?></h1>
			<p>Thread is unavailable for you, or do not exist any more.
				<?php
				if ($_GET['cat'] > 0 && !empty($_GET['forum'])) {
					$tmpCat = getValue($_GET['cat'] ?? null);
					$tmpCatName = getValue($_GET['forum'] ?? null);
					?>
					<br><a href="forum.php?forum=<?php echo $tmpCatName; ?>&cat=<?php echo $tmpCat; ?>"><?= t('forum.go_back_to') ?> <?php echo $tmpCatName; ?></a></p>
					<?php
				} else {
					?>
					<br><a href="forum.php"><?= t('forum.go_back') ?></a></p>
					<?php
				}
				?>
			<?php
		}

	} else

	/////////////////////
	// When you want to create a new thread
	if ($new_thread_category !== false && $new_thread_cid !== false) {
		// Verify we got access to this category
		$category = db()->fetchOne("SELECT `access`, `closed`, `guild_id` FROM `znote_forum` WHERE `id` = ? LIMIT 1;", [$new_thread_category]);
		if ($category !== false) {
			$access = true;
			if (!$admin) {
				if ($category['access'] > $yourAccess) $access = false;
				if ($category['guild_id'] > 0) {
					$status = false;
					foreach($charData as $char) {
						if ($char['guild'] == $category['guild_id']) $status = true;
					}
					if (!$status) $access = false;
				}
				if ($category['closed'] > 0) $access = false;
			}

			if ($access) {
				?>
				<h1><?= t('forum.create_thread_new') ?></h1>
				<form type="" method="post" class="znote-newthread">
					<input name="create_thread_cid" type="hidden" value="<?php echo $new_thread_cid; ?>">
					<input name="create_thread_category" type="hidden" value="<?php echo $new_thread_category; ?>">

					<div class="znote-newthread-head">
						<span class="znote-postas-label"><?= t('forum.post_as') ?></span>
						<span class="znote-postas-single"><?php echo htmlspecialchars($charData[$new_thread_cid]['name'], ENT_QUOTES, 'UTF-8'); ?></span>
					</div>

					<input class="znote-newthread-title form-control" name="create_thread_title" type="text" placeholder="Thread title" maxlength="60" required>

					<?php znote_forum_editor('create_thread_text', '', 300); ?>

					<div class="znote-newthread-actions">
						<button type="submit" class="btn btn-success"><?= t('forum.create_thread') ?></button>
						<a class="btn btn-default" href="forum.php?cat=<?php echo (int)$new_thread_category; ?>">Cancel</a>
					</div>
				</form>
				<?php
			} else echo '<p><b><font color="red">'. t('forum.perm_thread'). '</font></b></p>';
		}
	} else

	/////////////////////
	// When category is specified
	if ($getCat !== false) {
		$getCat = (int)$getCat;

		// Fetch category rules
		$category = db()->fetchOne("SELECT `name`, `access`, `closed`, `hidden`, `guild_id` FROM `znote_forum` WHERE `id` = ? AND `access` <= ? LIMIT 1;", [$getCat, $yourAccess]);

		if ($category !== false && $category['guild_id'] > 0 && !$admin) {
			$access = false;
			foreach($charData as $char) if ($category['guild_id'] == $char['guild']) $access = true;
			if ($access !== true) $category = false;
		}

		if ($category !== false) {
			$getCatInt = (int)$getCat;
			echo '<nav class="znf-crumbs"><a href="forum.php">' . t('forum.boards') . '</a> <span>/</span> ' . znote_forum_e($category['name']) . '</nav>';
			echo '<div class="znf-bhead"><h1 class="znf-h1">' . znote_forum_e($category['name']) . '</h1></div>';

			$threads = db()->fetchAll(
				"SELECT `t`.`id`, `t`.`player_name`, `t`.`title`, `t`.`sticky`, `t`.`closed`, `t`.`created`, `t`.`updated`, "
				. "COUNT(`p`.`id`) AS `reply_count`, COALESCE(MAX(`p`.`created`), 0) AS `last_reply_at` "
				. "FROM `znote_forum_threads` `t` LEFT JOIN `znote_forum_posts` `p` ON `p`.`thread_id` = `t`.`id` "
				. "WHERE `t`.`forum_id` = ? "
				. "GROUP BY `t`.`id`, `t`.`player_name`, `t`.`title`, `t`.`sticky`, `t`.`closed`, `t`.`created`, `t`.`updated` "
				. "ORDER BY `t`.`sticky` DESC, `t`.`updated` DESC;",
				[$getCatInt]
			);

			$lastReplyBy = array();
			$lrRows = db()->fetchAll(
				"SELECT `x`.`thread_id`, `x`.`player_name` FROM `znote_forum_posts` `x` JOIN ("
				. "SELECT `p2`.`thread_id`, MAX(`p2`.`created`) AS `mc` FROM `znote_forum_posts` `p2` "
				. "JOIN `znote_forum_threads` `t2` ON `t2`.`id` = `p2`.`thread_id` WHERE `t2`.`forum_id` = ? "
				. "GROUP BY `p2`.`thread_id`) `y` ON `y`.`thread_id` = `x`.`thread_id` AND `y`.`mc` = `x`.`created`;",
				[$getCatInt]
			);
			foreach ((array)$lrRows as $row) if (!isset($lastReplyBy[$row['thread_id']])) $lastReplyBy[$row['thread_id']] = $row['player_name'];

			$visibleThreads = array();
			foreach ((array)$threads as $thread) {
				$access = true;
				if ($category['hidden'] == 1) {
					$access = PlayerHaveAccess($yourChars, $thread['player_name']);
					if ($yourAccess > 3 || $admin) $access = true;
				}
				if ($access) $visibleThreads[] = $thread;
			}
			?>
			<div class="znf-threads">
				<?php if (!$visibleThreads): ?>
					<p class="znf-empty"><?php echo t('forum.board_empty'); ?></p>
				<?php endif; ?>
				<?php foreach ($visibleThreads as $thread):
					$e = 'znote_forum_e';
					$turl = 'forum.php?forum=' . urlencode($category['name']) . '&cat=' . $getCatInt . '&thread=' . (int)$thread['id'];
					$replyBy = $lastReplyBy[$thread['id']] ?? '';
				?>
					<div class="znf-thread<?php echo $thread['sticky'] ? ' is-sticky' : ''; echo $thread['closed'] ? ' is-locked' : ''; ?>" data-href="<?php echo $e($turl); ?>" onclick="if(!event.target.closest('a,button,form,input'))window.location.href=this.dataset.href">
						<span class="znf-thread__ico"><?php echo $thread['sticky'] ? '&#128204;' : ($thread['closed'] ? '&#128274;' : '&#128172;'); ?></span>
						<span class="znf-thread__main">
							<a class="znf-thread__title" href="<?php echo $e($turl); ?>"><?php echo $e($thread['title']); ?></a>
							<span class="znf-thread__meta"><?php echo t('forum.started_by', array('name' => $e($thread['player_name']), 'ago' => znote_forum_ago($thread['created']))); ?></span>
						</span>
						<span class="znf-thread__stats">
							<b><?php echo (int)$thread['reply_count']; ?></b> <?php echo t('forum.replies_word'); ?><br>
							<?php echo (int)$thread['last_reply_at'] > 0
								? t('forum.last_by', array('name' => $e($replyBy), 'ago' => znote_forum_ago($thread['last_reply_at'])))
								: t('forum.no_replies'); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php

			///////////
			// Create thread button
			if ($charCount > 0) {
				if ($category['closed'] == 0  || $admin) {
					?>
					<div class="znf-newwrap">
					<form action="" method="post">
						<input type="hidden" value="<?php echo $getCat; ?>" name="new_thread_category">
						<?php znote_forum_character_picker($yourChars, 'new_thread_cid', t('forum.create_thread_new'), 'btn btn-primary'); ?>
					</form>
					</div>
					<?php
				} else echo '<p class="znf-note">'. t('forum.board_closed'). '</p>';
			} else echo '<p class="znf-note">You must have a character on your account that is level '. (int)$config['forum']['level'] .'+ to create new threads.</p>';
		} else echo '<p class="znf-alert">Your permission to access this board has been denied.<br>If you are trying to access a Guild Board, you need level: '. (int)$config['forum']['level'] .'+</p>';

	}
} else {

	//////////////////////
	// No category specified, show list of available categories
	$boardWhereParams = [];
	if ($admin) {
		$boardWhere = '';
	} else {
		$boardWhere = " WHERE `f`.`access` <= ?";
		$boardWhereParams[] = (int)$yourAccess;
	}
	$categories = db()->fetchAll(
		"SELECT `f`.`id`, `f`.`name`, `f`.`access`, `f`.`closed`, `f`.`hidden`, `f`.`guild_id`, "
		. "COUNT(`t`.`id`) AS `thread_count`, COALESCE(MAX(`t`.`updated`), 0) AS `last_time` "
		. "FROM `znote_forum` `f` LEFT JOIN `znote_forum_threads` `t` ON `t`.`forum_id` = `f`.`id`"
		. $boardWhere
		. " GROUP BY `f`.`id`, `f`.`name`, `f`.`access`, `f`.`closed`, `f`.`hidden`, `f`.`guild_id` ORDER BY `f`.`name`;",
		$boardWhereParams
	);

	$lastThreads = array();
	$ltRows = db()->fetchAll(
		"SELECT `x`.`forum_id`, `x`.`player_name`, `x`.`title`, `x`.`id` FROM `znote_forum_threads` `x` "
		. "JOIN (SELECT `forum_id`, MAX(`updated`) AS `mu` FROM `znote_forum_threads` GROUP BY `forum_id`) `y` "
		. "ON `y`.`forum_id` = `x`.`forum_id` AND `y`.`mu` = `x`.`updated`;"
	);
	foreach ((array)$ltRows as $row) if (!isset($lastThreads[$row['forum_id']])) $lastThreads[$row['forum_id']] = $row;

	$guild = false;
	foreach ($charData as $char) if ($char['guild'] > 0) $guild = true;
	if (!isset($guilds)) {
		$guilds = db()->fetchAll("SELECT `id`, `name` FROM `guilds` ORDER BY `name`;");
		$guilds[] = array('id' => '0', 'name' => 'No guild');
	}
	$guildName = array();
	foreach ((array)$guilds as $g) $guildName[$g['id']] = $g['name'];

	$mainBoards = array();
	$guildboard = array();
	foreach ((array)$categories as $b) {
		$li = $lastThreads[$b['id']] ?? null;
		$b['last_author'] = $li['player_name'] ?? '';
		$b['last_title']  = $li['title'] ?? '';
		if ((int)$b['guild_id'] > 0) {
			$inGuild = $admin;
			foreach ($charData as $char) if ((int)$b['guild_id'] === (int)$char['guild']) $inGuild = true;
			if ($inGuild) $guildboard[] = $b;
		} else {
			$mainBoards[] = $b;
		}
	}

	$znfBoardRow = static function (array $b) use ($admin, $guildName) {
		$e  = 'znote_forum_e';
		$id = (int)$b['id'];
		?>
		<div class="znf-board" data-href="forum.php?cat=<?php echo $id; ?>" onclick="if(!event.target.closest('a,button,form,input'))window.location.href=this.dataset.href">
			<span class="znf-board__icon"><?php echo (int)$b['guild_id'] > 0 ? '&#9876;' : '&#128172;'; ?></span>
			<span class="znf-board__main">
				<span class="znf-board__name">
					<a href="forum.php?cat=<?php echo $id; ?>"><?php echo $e($b['name']); ?></a>
					<?php if ($b['closed']): ?><span class="znf-tag znf-tag--lock"><?php echo t('forum.locked_tag'); ?></span><?php endif; ?>
					<?php if ($b['hidden']): ?><span class="znf-tag znf-tag--hidden"><?php echo t('forum.hidden_tag'); ?></span><?php endif; ?>
					<?php if ((int)$b['guild_id'] > 0): ?><span class="znf-tag znf-tag--guild"><?php echo $e($guildName[$b['guild_id']] ?? ''); ?></span><?php endif; ?>
				</span>
				<?php if (!empty($b['last_time'])): ?>
					<span class="znf-board__last"><?php echo t('forum.lastpost'); ?> <b><?php echo $e($b['last_title']); ?></b> &middot; <?php echo $e($b['last_author']); ?> &middot; <?php echo znote_forum_ago($b['last_time']); ?></span>
				<?php else: ?>
					<span class="znf-board__last"><?php echo t('forum.board_empty'); ?></span>
				<?php endif; ?>
			</span>
			<span class="znf-board__count"><b><?php echo (int)$b['thread_count']; ?></b> <?php echo t('forum.threads_word'); ?></span>
			<?php if ($admin): ?>
			<span class="znf-board__admin">
				<form method="post"><input type="hidden" name="admin_category_id" value="<?php echo $id; ?>"><button class="btn btn-warning btn--sm" type="submit" name="admin_category_edit" value="Edit"><?php echo t('forum.edit'); ?></button></form>
				<form method="post"><input type="hidden" name="admin_category_id" value="<?php echo $id; ?>"><button class="btn btn-danger btn--sm" type="submit" name="admin_category_delete" value="1"><?php echo t('forum.delete_btn'); ?></button></form>
			</span>
			<?php endif; ?>
		</div>
		<?php
	};
	?>

	<?php if (!$mainBoards && !$guildboard): ?>
		<p class="znf-empty"><?php echo t('forum.no_boards'); ?></p>
	<?php endif; ?>

	<?php if ($mainBoards): ?>
	<div class="znf-boards">
		<div class="znf-boards__title"><?php echo t('forum.boards'); ?></div>
		<?php foreach ($mainBoards as $b) $znfBoardRow($b); ?>
	</div>
	<?php endif; ?>

	<?php if ($guildboard || ($guild && $admin)): ?>
	<div class="znf-boards">
		<div class="znf-boards__title"><?php echo t('forum.guild_boards'); ?></div>
		<?php if ($guildboard) { foreach ($guildboard as $b) $znfBoardRow($b); } else { ?>
			<p class="znf-empty"><?php echo t('forum.no_guildboards2'); ?></p>
		<?php } ?>
	</div>
	<?php endif; ?>
	<hr class="bighr">
	<?php
	if ($admin) {
		?>
		<form action="" method="post" class="znf-form">
			<h2 class="znf-form__title"><?= t('forum.create_board') ?></h2>
			<div class="znf-field">
				<label for="admin_board_create_name"><?= t('forum.board_name') ?></label>
				<input type="text" id="admin_board_create_name" name="admin_board_create_name" placeholder="<?= t('forum.board_name') ?>">
			</div>
			<div class="znf-field">
				<label for="admin_board_create_access"><?= t('forum.required_access') ?></label>
				<select id="admin_board_create_access" name="admin_board_create_access">
					<?php foreach($config['ingame_positions'] as $access => $name) echo "<option value='$access'>$name</option>"; ?>
				</select>
			</div>
			<div class="znf-field">
				<label for="admin_board_create_closed"><?= t('forum.closed') ?></label>
				<select id="admin_board_create_closed" name="admin_board_create_closed">
					<option value="0">No</option>
					<option value="1">Yes</option>
				</select>
			</div>
			<div class="znf-field">
				<label for="admin_board_create_hidden"><?= t('forum.hidden') ?></label>
				<select id="admin_board_create_hidden" name="admin_board_create_hidden">
					<option value="0">No</option>
					<option value="1">Yes</option>
				</select>
			</div>
			<div class="znf-field">
				<label for="admin_board_create_guild_id"><?= t('forum.guild_boards') ?></label>
				<select id="admin_board_create_guild_id" name="admin_board_create_guild_id">
					<?php foreach($guilds as $guild) {
						$sel = ($guild['id'] == 0) ? ' selected' : '';
						echo "<option value='". $guild['id'] ."'$sel>". $guild['name'] ."</option>";
					} ?>
				</select>
			</div>
			<div class="znf-field znf-field--submit">
				<input type="submit" value="<?= t('forum.create_board_btn') ?>" class="btn btn-primary">
			</div>
		</form>
		<?php
	}

}


echo '</div>';
theme_close(); ?>
