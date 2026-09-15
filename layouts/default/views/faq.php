<?php
/**
 * Public FAQ.
 *
 * Prepared by faq.php: $faqEntries, each ['question', 'answer'], in the
 * order set at Admin Panel > Settings > Content > FAQ questions.
 */
?>
<style>
.faq-entry { border: 1px solid #ccc; margin-bottom: 6px; padding: 6px 10px; }
.faq-entry summary { cursor: pointer; font-weight: bold; }
.faq-entry p { margin: 8px 0 2px; }
</style>
<h1><?= t('faq.title') ?></h1>

<?php if ($faqEntries): ?>
	<?php foreach ($faqEntries as $entry):
		$question = trim((string)($entry['question'] ?? ''));
		$answer   = trim((string)($entry['answer'] ?? ''));
		if ($question === '') continue;
	?>
		<details class="faq-entry">
			<summary><?= h($question) ?></summary>
			<p><?= nl2br(h($answer)) ?></p>
		</details>
	<?php endforeach; ?>
<?php else: ?>
	<h2><?= t('faq.none') ?></h2>
<?php endif; ?>
