<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/../src/Game/DragonPointState.php';
use Resurrection\Game\DragonPointState;

/** @return array{desc:array<string,string>,buy:array<string,bool>} */
function resurrection_dragon_point_schema(bool $lock = false): array {
    global $session;
    if ($lock) db_query('SELECT modulename FROM '.db_prefix('modules').' ORDER BY modulename FOR UPDATE');
    // Bypass both per-request and persistent hook caches at this authority boundary.
    $hooks = db_query('SELECT h.modulename,h.location,h.`function`,h.whenactive FROM '.db_prefix('module_hooks').' h '.
        'INNER JOIN '.db_prefix('modules').' m ON m.modulename=h.modulename '.
        'WHERE m.active=1 AND h.location IN (?,?) ORDER BY h.priority,h.modulename'.($lock ? ' FOR UPDATE' : ''), true,
        ['dkpointlabels','pdkpointrecalc']);
    foreach (['dkpointlabels','pdkpointrecalc'] as $name) $GLOBALS['modulehook_queries'][$name] = [];
    foreach ($hooks as $hook) $GLOBALS['modulehook_queries'][$hook['location']][] = $hook;
    $GLOBALS['injected_modules'] = [];
    if ($lock && $hooks !== []) {
        // Extension declarations may depend on game settings, module settings or
        // this player's preferences. Refresh those sources from locked rows.
        $rows = db_query('SELECT setting,value FROM '.db_prefix('settings').' ORDER BY setting FOR UPDATE');
        $GLOBALS['settings'] = [];
        foreach ($rows as $row) $GLOBALS['settings'][$row['setting']] = $row['value'];
        db_query('SELECT modulename,setting FROM '.db_prefix('module_settings').' ORDER BY modulename,setting FOR UPDATE');
        db_query('SELECT modulename,setting FROM '.db_prefix('module_userprefs').' WHERE userid=? ORDER BY modulename,setting FOR UPDATE', true,
            [(int)$session['user']['acctid']]);
        $GLOBALS['module_settings'] = []; $GLOBALS['module_prefs'] = [];
    }
    return DragonPointState::schema(modulehook('dkpointlabels', [
        'desc'=>['hp'=>'Max Hitpoints + 5','ff'=>'Forest Fights + 1','at'=>'Attack + 1','de'=>'Defense + 1',
            'unknown'=>'Unknown Spends (contact an admin to investigate!)'],
        'buy'=>['hp'=>1,'ff'=>1,'at'=>1,'de'=>1,'unknown'=>0],
    ]));
}

/** Validate the persisted source, not forcednavigation's legacy empty-array fallback. */
function resurrection_dragon_point_state(): int {
    global $session, $baseaccount;
    if (empty($session['loggedin']) || empty($session['user']['acctid'])) throw new DomainException('Authentication required.');
    $points = DragonPointState::read($baseaccount['dragonpoints'], $session['user']['dragonkills']);
    if ($points !== $session['user']['dragonpoints']) throw new DomainException('Allocation state changed.');
    return DragonPointState::integer($session['user']['dragonkills']) - count($points);
}

/** @param array{desc:array<string,string>,buy:array<string,bool>} $schema */
function resurrection_dragon_point_context(array $schema): string {
    global $session;
    restore_buff_fields();
    $state = [];
    foreach (['acctid','dragonkills','maxhitpoints','attack','defense'] as $field) {
        $state[$field] = DragonPointState::integer($session['user'][$field]);
    }
    foreach (['race','specialty','alive','specialinc','lasthit','age','location','authversion'] as $field) {
        $state[$field] = (string)$session['user'][$field];
    }
    $state['dragonpoints'] = $session['user']['dragonpoints'];
    $state['unspent'] = resurrection_dragon_point_state();
    $state['resurrection'] = httpget('resurrection') === 'true';
    return hash('sha256', json_encode([$state, DragonPointState::schema($schema)], JSON_THROW_ON_ERROR));
}

/** Runs before race/specialty dispatch and before any New Day reset or intercept hook. */
function resurrection_dragon_point_boundary(): void {
    global $session, $resline, $pdks;
    try {
        $unspent = resurrection_dragon_point_state();
        if (isset($_GET['dk']) || isset($_GET['pdk']) || isset($_POST['dk']) || isset($_POST['pdk'])) {
            http_response_code(403); exit('Dragon Point allocation requires a fresh form.');
        }
        $resline = httpget('resurrection') === 'true' ? '&resurrection=true' : '';
        $posting = array_intersect(array_keys($_POST), ['allocation','type','hp','ff','at','de']) !== [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($posting || $unspent > 0)) {
            $schema = resurrection_dragon_point_schema();
            $context = resurrection_dragon_point_context($schema);
            resurrection_consume_action('dragon-points', $context);
            if ($unspent < 1) throw new DomainException('No unspent points.');
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
                throw new InvalidArgumentException('Unsupported form encoding.');
            }
            DragonPointState::transport((string)file_get_contents('php://input'), $_POST);
            if (array_diff(array_keys($_GET), ['continue','resurrection','c']) !== []) throw new InvalidArgumentException('Unexpected query.');
            $mode = $unspent === 1 ? 'single' : 'bulk';
            if (($_POST['allocation'] ?? null) !== $mode) throw new InvalidArgumentException('Invalid allocation mode.');
            $counts = array_fill_keys(array_keys(array_filter($schema['buy'])), 0);
            if ($mode === 'single') {
                if (array_diff(array_keys($_POST), ['csrf_token','action_token','allocation','type']) !== [] ||
                    !is_string($_POST['type'] ?? null) || !array_key_exists($_POST['type'], $counts)) {
                    throw new InvalidArgumentException('Invalid allocation type.');
                }
                $counts[$_POST['type']] = 1;
            } else {
                $counts = array_diff_key($_POST, array_flip(['csrf_token','action_token','allocation']));
            }
            $counts = DragonPointState::counts($counts, $schema['buy'], $unspent);
            resurrection_player_mutation(function () use ($context, $counts, $mode): void {
                global $session, $pdks;
                $locked = resurrection_dragon_point_schema(true);
                $remaining = resurrection_dragon_point_state();
                if ($remaining < 1 || resurrection_dragon_point_context($locked) !== $context) throw new DomainException('Allocation state changed.');
                $pdks = DragonPointState::counts($counts, $locked['buy'], $remaining);
                // Historical hook uses global $pdks, with no arguments or return contract.
                // Only validated counts reach it; its result is validated again under lock.
                $before = resurrection_dragon_point_context($locked);
                if ($mode === 'bulk') modulehook('pdkpointrecalc');
                if (resurrection_dragon_point_context($locked) !== $before) {
                    throw new DomainException('Hook changed allocation authority.');
                }
                $pdks = DragonPointState::counts($pdks, $locked['buy'], $remaining);
                $changes = DragonPointState::allocate($session['user'], $pdks);
                foreach ($changes as $key => $value) $session['user'][$key] = $value;
            });
            page_header('Dragon Points');
            output('Your Dragon Points have been allocated permanently.`n');
            addnav('Continue', 'newday.php?continue=1'.$resline);
            page_footer();
        }
        if ($unspent > 0) {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') resurrection_require_post();
            $schema = resurrection_dragon_point_schema();
            $context = resurrection_dragon_point_context($schema);
            require __DIR__ . '/newday/dragonpointspend.php';
            page_footer();
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid Dragon Point allocation. Request a fresh form.');
    } catch (DomainException $error) {
        http_response_code(409); exit('Dragon Point allocation is unavailable. Request a fresh form or ask an administrator to repair stored state.');
    } catch (Throwable $error) {
        http_response_code(500); exit('Dragon Point allocation was not completed. Request a fresh form.');
    }
}
