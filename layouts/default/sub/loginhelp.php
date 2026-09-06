<!-- Tell the user to use the aside menu -->
<h1 class="toright"><?= h(t('login.title')) ?></h1>
<p class="toright"><?= h(t('login.help_text')) ?> &rarr;</p>
<style type="text/css">
	/* Align the text to the right of the page. */
	.toright {
		text-align: right;
	}
	div.leftPane {
		box-sizing: border-box;
		padding-right: 30px;
	}
</style>
<script type="text/javascript">
	// Auto focus to login username box
	$(function() {
		$('#login_username').focus();
	});
</script>
