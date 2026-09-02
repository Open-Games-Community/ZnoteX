<?php
if (user_logged_in() === true) {
?>

<h1>STOP!</h1>
<p>Ummh... Why are you sniffing around here?</p>

<?php
} else {
?>

<h1>Sorry, you need to be logged in to do that!</h1>
<p>Please register or log in.</p>

<?php
}
