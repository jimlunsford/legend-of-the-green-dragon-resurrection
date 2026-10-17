<?php
require_once __DIR__.'/dragon_points.php';
require_once __DIR__.'/newday_daily.php';
require_once __DIR__.'/../src/Game/GraveyardCombatState.php';
require_once __DIR__.'/../src/Game/RamiusState.php';
use Resurrection\Game\NewDayState;
use Resurrection\Game\RamiusState;

/** Reject duplicate/nested/normalized query names before dispatch or prerequisite writes. */
function resurrection_ramius_query(string $route): void {
    try {
        $raw = (string)($_SERVER['QUERY_STRING'] ?? '');
        if ($raw !== '') \Resurrection\Game\DragonPointState::transport($raw, $_GET);
        if ($route === 'newday') {
            if (array_key_exists('resurrection', $_GET) &&
                ($_GET['resurrection'] !== 'true' || array_diff(array_keys($_GET), ['resurrection','continue','c']) !== [])) {
                throw new InvalidArgumentException('Invalid resurrection query.');
            }
        } elseif (array_diff(array_keys($_GET), ['op','c']) !== []) throw new InvalidArgumentException('Invalid favor query.');
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid resurrection route.');
    }
}

/** @return array<string,mixed> */
function resurrection_ramius_sources(bool $lock = false): array {
    global $settings;
    $sources = resurrection_newday_sources($lock);
    $settings += ['resurrectionturns'=>-6, 'deathoverlord'=>'`$Ramius'];
    RamiusState::setting($settings['resurrectionturns']);
    if (!is_string($settings['deathoverlord']) || strlen($settings['deathoverlord']) > 4096) throw new DomainException('Invalid death overlord.');
    $sources['settings'] += ['resurrectionturns'=>$settings['resurrectionturns'], 'deathoverlord'=>$settings['deathoverlord']];
    // The historical ritual visited the Graveyard header before New Day. Its
    // shipped Drinks preference write now belongs to this same transaction.
    $headers = db_query('SELECT h.modulename,h.location,h.`function`,h.whenactive,h.priority FROM '.db_prefix('module_hooks').' h '.
        'INNER JOIN '.db_prefix('modules').' m ON m.modulename=h.modulename WHERE m.active=1 '.
        "AND h.location='header-graveyard' ORDER BY h.priority,h.modulename".($lock ? ' FOR UPDATE' : ''));
    $GLOBALS['modulehook_queries']['header-graveyard'] = $headers;
    $sources['graveyardHeader'] = $headers;
    return $sources;
}

function resurrection_ramius_validate(bool $favor = true): void {
    global $baseaccount, $session, $companions;
    resurrection_newday_validate();
    RamiusState::eligible($baseaccount, gmdate('Y-m-d', gametime()), $favor);
    RamiusState::eligible($session['user'], gmdate('Y-m-d', gametime()), $favor);
    \Resurrection\Game\ForestCompanionState::validate(\Resurrection\Security\ScalarState::read($baseaccount['companions']));
    \Resurrection\Game\ForestCompanionState::validate($companions);
}

function resurrection_ramius_form(string $context): void {
    $url = 'newday.php?resurrection=true'; addnav('', $url);
    rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('ramius-resurrection', $context).
        '<input type="hidden" name="resurrection" value="ramius"><button class="button">'.
        htmlspecialchars(translate_inline('Continue (100 favor)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</button></form>');
}

/** All three explicit surfaces share presentation protection and one settlement authority. */
function resurrection_ramius(string $stage): never {
    global $session, $baseaccount, $playermount, $companions;
    define('RESURRECTION_NEWDAY_PRESENTATION', true);
    define('RESURRECTION_RAMIUS_PRESENTATION', true);
    try {
        $sources = resurrection_ramius_sources();
        resurrection_ramius_validate($stage !== 'question');
        $context = resurrection_newday_context($sources);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            resurrection_consume_action('ramius-resurrection', $context);
            if ($stage !== 'resurrection' || basename($_SERVER['SCRIPT_NAME']) !== 'newday.php' ||
                strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
                throw new InvalidArgumentException('Invalid resurrection transport.');
            }
            \Resurrection\Game\DragonPointState::transport((string)file_get_contents('php://input'), $_POST);
            if (array_diff(array_keys($_POST), ['csrf_token','action_token','resurrection']) !== [] ||
                ($_POST['resurrection'] ?? null) !== 'ramius') throw new InvalidArgumentException('Invalid resurrection fields.');
            resurrection_player_mutation(function () use ($context): void {
                global $session, $baseaccount, $playermount, $companions;
                $sources = resurrection_ramius_sources(true);
                resurrection_ramius_validate();
                if (resurrection_newday_context($sources) !== $context) throw new DomainException('Resurrection state changed.');
                $before = $session['user'];
                $day = gmdate('Y-m-d', gametime());
                $turnsperday = NewDayState::integer(getsetting('turns', 10));
                $mininterest = NewDayState::rate(getsetting('mininterest', 1))/100 + 1;
                $maxinterest = NewDayState::rate(getsetting('maxinterest', 10))/100 + 1;
                tlschema('newday');
                modulehook('header-graveyard');
                modulehook('newday-intercept', []);
                require __DIR__.'/ramius_outcomes.php';
                restore_buff_fields();
                resurrection_newday_validate();
                NewDayState::buffs($session['bufflist'], $session['user']);
                NewDayState::integer($session['user']['deathpower']);
                if ($session['user']['deathpower'] !== (int)$before['deathpower'] - 100 ||
                    (int)$session['user']['resurrections'] !== (int)$before['resurrections'] + 1 ||
                    (int)$session['user']['age'] !== (int)$before['age'] + 1 ||
                    $session['user']['alive'] !== true || (int)$session['user']['hitpoints'] !== (int)$session['user']['maxhitpoints'] ||
                    $session['user']['spirits'] !== -6) throw new DomainException('Invalid resurrection outcome.');
                foreach (['lastnewday','playerfights','soulpoints','gravefights'] as $key) {
                    if ($session['user'][$key] !== $before[$key]) throw new DomainException('Resurrection changed a daily-only field.');
                }
                if (gmdate('Y-m-d', gametime()) !== $day) throw new DomainException('Game day changed during resurrection.');
            });
            page_header('Resurrected!');
            addnav('Continue', 'village.php?c=1');
            page_footer();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Use a resurrection form.'); }
        tlschema('graveyard');
        page_header('The Graveyard');
        $deathoverlord = getsetting('deathoverlord', '`$Ramius');
        if ($stage === 'question') require __DIR__.'/graveyard/case_question.php';
        else require __DIR__.'/graveyard/case_resurrection.php';
        page_footer();
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid resurrection form. Request a fresh form.');
    } catch (DomainException $error) {
        http_response_code(409); exit('Ramius resurrection is unavailable. Complete any due New Day or encounter, or ask an administrator to repair stored state.');
    } catch (Throwable $error) {
        http_response_code(500); exit('Resurrection was not completed. Request a fresh form.');
    }
    exit;
}
