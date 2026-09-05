<?php
if (user_logged_in() === true) {
?>

<h1><?= t('protected.stop') ?></h1>
<p><?= t('protected.sniffing') ?></p>

<?php
} else {
?>

<h1><?= t('protected.need_login') ?></h1>
<p><?= t('protected.register') ?></p>

<?php
}
