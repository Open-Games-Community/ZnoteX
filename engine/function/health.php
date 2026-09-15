<?php

function znote_health_issues(): array
{
	$issues = array();

	if (!extension_loaded('mysqli')) {
		$issues[] = array('label' => 'PHP extension', 'detail' => 'mysqli is not loaded.');
	}

	$root = dirname(__DIR__, 2);
	$free = @disk_free_space($root);
	$total = @disk_total_space($root);
	if ($free !== false && $total !== false && $total > 0) {
		$percentFree = ($free / $total) * 100;
		if ($percentFree < 5 || $free < 524288000) {
			$issues[] = array(
				'label'  => 'Disk space',
				'detail' => number_format($free / 1048576, 0) . ' MB free (' . number_format($percentFree, 1) . '%).',
			);
		}
	}

	if (function_exists('znote_migrations_status')) {
		foreach (znote_migrations_status() as $name => $migration) {
			if (($migration['state'] ?? '') === 'changed') {
				$issues[] = array(
					'label'  => 'Migration changed',
					'detail' => (string) $name . ' was applied but the file no longer matches what ran.',
				);
			}
		}
	}

	return $issues;
}
