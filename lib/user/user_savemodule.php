<?php
//save module settings.
resurrection_user_module_save((string)httpget('module'), (int)$userid);
$op = "edit";
httpset("op", "edit");
httpset("subop", "module", true);
?>
