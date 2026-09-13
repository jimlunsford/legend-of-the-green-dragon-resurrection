<?php
// PDO is the sole active transport. Historical drivers remain in the immutable tag.
require_once __DIR__ . '/errorhandling.php';
require_once __DIR__ . '/datacache.php';
define('DBTYPE', 'pdo');
$dbinfo = ['queriesthishit' => 0, 'querytime' => 0.0, 'affected_rows' => 0, 'error' => ''];
require_once __DIR__ . '/dbwrapper_pdo.php';
