<?php
/**
 * Title: API
 * Icon: fa-code
 * Group: Operations
 * Order: 60
 * Description: Every public JSON endpoint under /api/, discovered automatically.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_api_known_endpoints(): array {
	return array(
		'index.php' => t_default('acp.api.desc_index', 'Site title, account/player counts, players online (with unique IPs), client version, port.'),
		'modules/status/online.php' => t_default('acp.api.desc_online', 'The currently online players: name, level, vocation.'),
		'modules/highscores/topExperience.php' => t_default('acp.api.desc_top_experience', 'Top players by experience. ?rows=N (default 10, max 100).'),
		'modules/towns/getTownNames.php' => t_default('acp.api.desc_town_names', 'Configured town id => name map.'),
		'modules/character/info.php' => t_default('acp.api.desc_character_info', 'Character card by name (?name=). Respects hidden characters.'),
		'modules/highscores/top.php' => t_default('acp.api.desc_highscores_top', 'Highscores by skill type, same data as the site page. ?type=1-9&vocation=id|all&rows=N.'),
	);
}

$root = dirname(__DIR__, 2) . '/api';
$files = array();

if (is_dir($root)) {
	$files[] = 'index.php';

	$modulesDir = $root . '/modules';
	if (is_dir($modulesDir)) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}
			$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
			if (strpos($relative, '/class/') !== false) {
				continue;
			}
			$files[] = $relative;
		}
	}
}

sort($files);

$known = acp_api_known_endpoints();
$siteUrl = rtrim((string)($config['site_url'] ?? ''), '/');
?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.api.title', 'Public JSON endpoints') ?></h2>
		<p><?= t_default('acp.api.sub', 'Everything under /api/ - read-only, no authentication. Point a Discord bot or external tool at these.') ?></p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($files): ?>
			<div class="acp-table-wrap">
				<table class="acp-table" data-sortable>
					<thead>
						<tr>
							<th><?= t_default('acp.api.col_endpoint', 'Endpoint') ?></th>
							<th><?= t_default('acp.api.col_description', 'What it returns') ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($files as $file): $url = $siteUrl . '/api/' . $file; ?>
							<tr>
								<td><code>/api/<?= h($file) ?></code></td>
								<td><?= h($known[$file] ?? '') ?></td>
								<td class="is-num">
									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h($url) ?>" target="_blank" rel="noopener">
										<i class="fa fa-external-link"></i> <?= t_default('acp.api.open_btn', 'Open') ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t_default('acp.api.empty', 'No API endpoints found.'), 'fa-code'); ?>
		<?php endif; ?>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.api.response_title', 'Response shape') ?></h2>
	</header>
	<div class="acp-card-body">
		<p><?= t_default('acp.api.response_text', 'Every endpoint replies with JSON shaped like this - "version" always present, "data" holding the actual payload:') ?></p>
		<pre style="background:var(--acp-panel-2);padding:10px;border-radius:var(--acp-radius);overflow-x:auto;">{
  "version": { "znote": "2.0.1", "ot": "TFS_10", "module": 1 },
  "data": { ... }
}</pre>
	</div>
</section>
