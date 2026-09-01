<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

if (!empty($config['Ach'])):
?>

<center><h3>Achievements on <?php echo htmlspecialchars($config['site_title'] ?? '', ENT_QUOTES); ?></h3></center>

<div class="panel-body">
<table class="table table-striped table-bordered table-condensed">
<tr>
    <td width="10%">Grade</td>
    <td width="17%">Name</td>
    <td>Description</td>
    <td width="7%">Secret</td>
    <td width="2%">Points</td>
</tr>

<style>
#wtf {
    margin-left:0px;
}
</style>

<?php
if (!empty($config['achievements']) && is_array($config['achievements'])):
    foreach ($config['achievements'] as $achName):

        $achName['secret'] = $achName['secret'] ?? false;
        $achName['img'] = $achName['img'] ?? 'https://i.imgur.com/ZqWp1TE.png';
        $achName['points'] = (int)($achName['points'] ?? 0);

        $name = $achName[0] ?? '';
        $desc = $achName[1] ?? '';
?>
<tr>
<td>
<?php
if ($achName['points'] >= 1 && $achName['points'] <= 3) {
    echo '<center><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"></center>';
} elseif ($achName['points'] >= 4 && $achName['points'] <= 6) {
    echo '<center><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"></center>';
} elseif ($achName['points'] >= 7) {
    echo '<center><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"><img class="wtf" src="https://i.imgur.com/TUCGsr3.gif"></center>';
} else {
    echo '<img class="wtf" src="' . htmlspecialchars($achName['img'], ENT_QUOTES) . '">';
}
?>
</td>

<td><?php echo htmlspecialchars($name, ENT_QUOTES); ?></td>
<td><?php echo htmlspecialchars($desc, ENT_QUOTES); ?></td>

<td>
<?php if (!empty($achName['secret'])): ?>
    <img class="wtf" src="https://i.imgur.com/NbPRl7b.gif">
<?php endif; ?>
</td>

<td><?php echo $achName['points']; ?></td>
</tr>

<?php
    endforeach;
endif;
?>

</table>
</div>

<?php
include 'layout/overall/footer.php';
else:
    echo 'This page has been disabled, this page can be enabled in config.';
endif;
?>