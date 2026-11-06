<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// Only the fields the editor changed are written, and only if they still hold what the form showed.
resurrection_user_editor_save((int)$userid, $userinfo);
if ($petition!="")
	addnav("","viewpetition.php?op=view&id=$petition");
addnav("","user.php");
$op = "edit";
httpset($op, "edit");
?>
