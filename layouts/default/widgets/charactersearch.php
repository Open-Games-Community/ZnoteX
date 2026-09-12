<div class="well search_widget" id="searchContainer">
	<div class="header">
		<?= t('widget.search.title') ?>
	</div>
	<div class="body">
		<div class="relative">
			<div id="name_suggestion"></div>
		</div>
		<form class="searchForm" action="characterprofile.php" method="get">
			<label for="src_name"><?= t('widget.search.label') ?> </label><input autocomplete="off" type="text" name="name" id="src_name" class="search" placeholder="<?= t('widget.search.placeholder') ?>">
		</form>
		<?php
		$cache = new Cache('engine/cache/characterNames');
		if ($cache->hasExpired()) {
			$names_sql = db()->fetchAll('SELECT `name` FROM `players` ORDER BY `name` ASC;');
			$names = array();
			if ($names_sql !== false): foreach ($names_sql as $name) {
				$names[] = $name['name'];
			} endif;
			$cache->setContent($names);
			$cache->save();
		} else {
			$names = $cache->load();
		}
		?>
		<script type="text/javascript">
			window.searchNames = <?php echo json_encode($names); ?>;
			document.addEventListener('DOMContentLoaded', function () {
				var input = document.getElementById('src_name');
				var suggestion = document.getElementById('name_suggestion');
				if (!input || !suggestion || !Array.isArray(window.searchNames) || window.searchNames.length === 0) {
					return;
				}

				input.addEventListener('keyup', function () {
					suggestion.innerHTML = '';
					var search = input.value.toLowerCase();
					var results = [];

					if (search.length > 0) {
						for (var i = 0; i < window.searchNames.length && results.length < 10; i += 1) {
							if (String(window.searchNames[i]).toLowerCase().indexOf(search) > -1) {
								results.push(window.searchNames[i]);
							}
						}
					}

					if (results.length > 0) {
						results.forEach(function (name) {
							var row = document.createElement('div');
							var link = document.createElement('a');
							row.className = 'sname';
							link.href = 'characterprofile.php?name=' + encodeURIComponent(name);
							link.textContent = name;
							row.appendChild(link);
							suggestion.appendChild(row);
						});
						suggestion.classList.add('show');
					} else {
						suggestion.classList.remove('show');
					}
				});
			});
		</script>
	</div>
</div>
