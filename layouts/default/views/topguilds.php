<?php

if (!empty($guilds) && $guilds !== false) {
	?>
	<h3><center><?= t('topguilds.title') ?></center></h3>
	<table id="onlinelistTable" class="table table-striped table-hover">
	    <tr class="yellow">
			<th>#</th>
	        <th><?= t('online.label_name') ?></th>
	        <th><?= t('topguilds.frags') ?></th>
	    </tr>
	    <?php
		foreach ($guilds as $guild):
		    $url = url("guilds.php?name=". $guild['name']);
			?>
			<tr class="special" onclick="javascript:window.location.href='<?php echo $url; ?>'">
				<td><?php
					echo convert_number_to_words($count);
					$count++;
				?></td>
		        <td><a href="" onclick="return false"><?php echo $guild['name']; ?></a></td>
		        <td><?php echo $guild['frags']; ?></td>
		    </tr>
	    	<?php
		endforeach; ?>
	</table>
	<?php
} else {
	echo '<h1>'. t('topguilds.no_frags') .'</h1>';
}
