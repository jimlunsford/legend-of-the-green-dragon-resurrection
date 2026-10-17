<?php
require_once 'common.php';
require_once 'lib/titles.php';
require_once 'lib/taunt.php';
require_once 'lib/names.php';
require_once 'lib/dragon_combat.php';

tlschema('dragon');
page_header('The Green Dragon!');
resurrection_dragon_controller();
page_footer();
