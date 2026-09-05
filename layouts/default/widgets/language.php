<?php
if (function_exists('translate_selector')) {
	$markup = translate_selector();
	if ($markup !== '') {
		echo $markup . translate_selector_assets();
		translate_selector_rendered(true);
	}
}
