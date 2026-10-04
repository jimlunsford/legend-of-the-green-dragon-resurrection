<?php
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
rawoutput("<form action='mail.php?op=write' method='post'>");
output('`b`2Address:`b`nTo: ');
rawoutput('<input name="to" id="to" maxlength="255" value="'.$escape($_GET['prepop'] ?? '').'">');
rawoutput('<button type="submit">'. $escape(translate_inline('Search')).'</button>');
if ($session['user']['superuser'] & SU_IS_GAMEMASTER) {
    output('`nFrom display identity (Game Master): ');
    rawoutput('<input name="from" maxlength="255">');
    output('`nLeave empty to send from your account. Display identities cannot receive replies.');
}
rawoutput('</form>');
