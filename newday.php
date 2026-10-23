<?php
require_once __DIR__ . '/src/Security/ScalarState.php';
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/sanitize.php");
require_once("lib/buffs.php");

// Strictly validate explicit resurrection routing before any prerequisite action.
require_once 'lib/ramius_resurrection.php';
resurrection_ramius_query('newday');

require_once('lib/dragon_points.php');
resurrection_dragon_point_boundary();

require_once('lib/race_onboarding.php');
require_once('lib/specialty_onboarding.php');
if (isset($_GET['setspecialty'])) { http_response_code(403); exit('Specialty selection requires a form.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['setrace']) && (isset($_POST['setspecialty']) || ($_POST['onboarding'] ?? null) === 'specialty')) {
    $resline = httpget('resurrection') === 'true' ? '&resurrection=true' : '';
    resurrection_specialty_onboarding();
}
if (isset($_GET['setrace'])) { http_response_code(403); exit('Race selection requires a form.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['setrace']) || isset($_POST['onboarding']) ||
    !$session['user']['race'] || $session['user']['race'] === RACE_UNKNOWN)) {
    $resline = httpget('resurrection') === 'true' ? '&resurrection=true' : '';
    resurrection_race_onboarding();
}

if (array_key_exists('resurrection', $_GET) && $session['user']['race'] &&
    $session['user']['race'] !== RACE_UNKNOWN && $session['user']['specialty'] !== '') {
    resurrection_ramius('resurrection');
}

// Normal daily reset owns its method boundary after protected prerequisites.
if (httpget('resurrection') !== 'true' && $session['user']['race'] &&
    $session['user']['race'] !== RACE_UNKNOWN && $session['user']['specialty'] !== '') {
    require_once 'lib/newday_daily.php';
    resurrection_newday_daily();
}

// Only protected onboarding presentation remains after the two reset handlers.
$resline = httpget('resurrection') === 'true' ? '&resurrection=true' : '';
if (!$session['user']['race'] || $session['user']['race'] === RACE_UNKNOWN) require_once 'lib/newday/setrace.php';
else require_once 'lib/newday/setspecialty.php';
page_footer();
