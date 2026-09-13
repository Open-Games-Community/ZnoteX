<h1><?= t('twofa.incompatible') ?></h1>
<p><?= t('twofa.incompatible2') ?> <?= t('twofa.title') ?>.<br>
TFS 1.2 or higher is required to run the legacy in-game two-factor authentication, grab it
<a href="https://github.com/otland/forgottenserver/releases" target="_BLANK">here</a>.
<?php if ($twofa2Enabled): ?>
	<br>You can still use the website's own two-factor authentication below - it does not depend on the game engine.
<?php endif; ?>
</p>
