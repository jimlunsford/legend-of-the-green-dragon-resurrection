<?php
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
rawoutput("<form action='mail.php?op=write' method='post'>");
// Each input's label is a <label for> (Phase 5 task 9); no color span stays open across it.
output_notl('`0');
rawoutput('<label for="to">');
output('`b`2Address:`b`nTo: ');
output_notl('`0');
rawoutput('</label>');
output_notl('`2');
rawoutput('<input name="to" id="to" maxlength="255" value="'.$escape($_GET['prepop'] ?? '').'">');
rawoutput('<button type="submit">'. $escape(translate_inline('Search')).'</button>');
if ($session['user']['superuser'] & SU_IS_GAMEMASTER) {
    output_notl('`0');
    rawoutput('<label for="from">');
    output('`nFrom display identity (Game Master): ');
    output_notl('`0');
    rawoutput('</label>');
    output_notl('`2');
    rawoutput('<input name="from" id="from" maxlength="255">');
    output('`nLeave empty to send from your account. Display identities cannot receive replies.');
}
rawoutput('</form>');
