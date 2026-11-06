<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
output("`\$%s`) is impressed with your actions, and grants you the power to haunt a foe.`n`n",$deathoverlord);
$search = translate_inline("Search");
rawoutput("<form action='graveyard.php?op=haunt2' method='POST'>");
addnav("","graveyard.php?op=haunt2");
output("Who would you like to haunt? ");
rawoutput("<input name='name' id='name'>");
rawoutput("<input type='submit' class='button' value='$search'>");
rawoutput("</form>");
rawoutput(resurrection_focus_marker('name'));
addnav("Places");
addnav("S?Land of the Shades","shades.php");
addnav("G?The Graveyard","graveyard.php");
addnav("M?Return to the Mausoleum","graveyard.php?op=enter");
?>