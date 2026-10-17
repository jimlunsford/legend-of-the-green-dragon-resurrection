<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/../src/Game/NewDayState.php';
require_once __DIR__ . '/../src/Game/ForestBuffState.php';
require_once __DIR__ . '/../src/Game/ForestCompanionState.php';
require_once __DIR__ . '/../src/Game/TransmutationState.php';
require_once __DIR__ . '/../src/Game/Expression.php';
use Resurrection\Game\NewDayState;

/** Read authoritative sources afresh; never invoke gameplay hooks while issuing a form.
 * @return array<string,mixed>
 */
function resurrection_newday_sources(bool $lock = false): array {
    global $session, $settings, $playermount;
    $suffix = $lock ? ' FOR UPDATE' : '';
    $modules = db_query('SELECT modulename,active FROM '.db_prefix('modules').' ORDER BY modulename'.$suffix);
    $rows = db_query('SELECT setting,value FROM '.db_prefix('settings').' ORDER BY setting'.$suffix);
    $settings = array_column($rows, 'value', 'setting');
    $defaults = ['turns'=>10,'mininterest'=>1,'maxinterest'=>10,'fightsforinterest'=>4,'maxgoldforinterest'=>100000,
        'pvpday'=>3,'gravefightsperday'=>10,'specialtybonus'=>1,'gameoffsetseconds'=>0,'daysperday'=>4,
        'villagename'=>LOCATION_FIELDS,'bard'=>'`^Seth','barmaid'=>'`%Violet',
        'game_epoch'=>gmdate('Y-m-d 00:00:00 O', strtotime('-30 days'))];
    $material = array_intersect_key($settings, $defaults) + $defaults;
    foreach (['turns','fightsforinterest','maxgoldforinterest','pvpday','gravefightsperday','specialtybonus'] as $key) {
        NewDayState::integer($material[$key]);
    }
    NewDayState::integer($material['gameoffsetseconds'], -2147483648, 2147483647);
    NewDayState::integer($material['daysperday'], 1, 86400);
    if (!is_string($material['game_epoch']) || strtotime($material['game_epoch']) === false) throw new DomainException('Invalid game epoch.');
    NewDayState::rate($material['mininterest']); NewDayState::rate($material['maxinterest']);
    // Missing defaults remain request-local on GET and stable across the locked read.
    $settings += $defaults;
    $hooks = db_query('SELECT h.modulename,h.location,h.`function`,h.whenactive,h.priority FROM '.db_prefix('module_hooks').' h '.
        'INNER JOIN '.db_prefix('modules').' m ON m.modulename=h.modulename WHERE m.active=1 '.
        "AND h.location IN ('newday','pre-newday','newday-intercept') ORDER BY h.priority,h.modulename,h.location".$suffix);
    foreach (['newday','pre-newday','newday-intercept'] as $hook) $GLOBALS['modulehook_queries'][$hook] = [];
    foreach ($hooks as $hook) {
        if (!is_file(__DIR__.'/../modules/'.$hook['modulename'].'.php')) throw new DomainException('Missing active hook source.');
        $GLOBALS['modulehook_queries'][$hook['location']][] = $hook;
    }
    $GLOBALS['injected_modules'] = [[], []];
    $moduleSettings = db_query('SELECT modulename,setting,value FROM '.db_prefix('module_settings').' ORDER BY modulename,setting'.$suffix);
    $prefs = db_query('SELECT modulename,setting,value FROM '.db_prefix('module_userprefs').' WHERE userid=? ORDER BY modulename,setting'.$suffix, true, [(int)$session['user']['acctid']]);
    foreach ($prefs as $pref) {
        if ((in_array($pref['modulename'], ['specialtydarkarts','specialtymysticpower','specialtythiefskills'], true) && in_array($pref['setting'], ['skill','uses'], true)) ||
            ($pref['modulename'] === 'drinks' && $pref['setting'] === 'drunkeness')) NewDayState::integer($pref['value'], 0, 999999999);
    }
    foreach ($moduleSettings as $setting) {
        if ($setting['modulename'] === 'racehuman' && $setting['setting'] === 'bonus') NewDayState::integer($setting['value']);
    }
    $GLOBALS['module_settings'] = []; $GLOBALS['module_prefs'] = [];
    $mountId = NewDayState::integer($session['user']['hashorse']);
    $playermount = [];
    if ($mountId) {
        $mounts = db_query('SELECT * FROM '.db_prefix('mounts').' WHERE mountid=?'.$suffix, true, [$mountId]);
        if (count($mounts) !== 1) throw new DomainException('Missing owned mount.');
        $playermount = $mounts[0];
        NewDayState::integer($playermount['mountforestfights'], -2147483648, 2147483647);
        $buff = \Resurrection\Security\ScalarState::read($playermount['mountbuff']);
        NewDayState::buffs(['mount'=>$buff], $session['user']);
    }
    $mountContext = $playermount;
    if ($mountId) $mountContext['mountbuff'] = \Resurrection\Security\ScalarState::read($playermount['mountbuff']);
    return ['settings'=>$material,'modules'=>$modules,'hooks'=>$hooks,'moduleSettings'=>$moduleSettings,'prefs'=>$prefs,'mount'=>$mountContext];
}

/** Validate account and effect authority before display calculation or mutation. */
function resurrection_newday_validate(): void {
    global $session, $baseaccount, $companions;
    if (resurrection_dragon_point_state() !== 0 || !$session['user']['race'] || $session['user']['race'] === RACE_UNKNOWN ||
        $session['user']['specialty'] === '') throw new DomainException('Incomplete onboarding.');
    if (!in_array($session['user']['race'], ['Human','Elf','Dwarf','Troll'], true) ||
        !in_array($session['user']['specialty'], ['DA','MP','TS'], true)) throw new DomainException('Invalid bundled identity.');
    foreach (['acctid','age','resurrections','dragonkills','turns','playerfights','transferredtoday','amountouttoday',
        'soulpoints','gravefights','seenmaster','maxhitpoints','charm','marriedto'] as $key) NewDayState::integer($session['user'][$key]);
    NewDayState::integer($session['user']['level'], 1, 1000000);
    NewDayState::integer($session['user']['hitpoints'], -2147483648, 2147483647);
    NewDayState::integer($session['user']['goldinbank'], -2147483648, 2147483647);
    if (!in_array($session['user']['alive'], [true,false,0,1,'0','1'], true)) throw new DomainException('Invalid life state.');
    foreach (['seendragon','boughtroomtoday'] as $key) NewDayState::integer($session['user'][$key], 0, 1);
    NewDayState::integer($session['user']['fedmount'], 0, 255);
    if (!array_key_exists('lastnewday', $baseaccount) || !is_string($baseaccount['lastnewday']) ||
        ($baseaccount['lastnewday'] !== '' && !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $baseaccount['lastnewday']))) throw new DomainException('Invalid daily marker.');
    if ($baseaccount['lastnewday'] !== '') {
        [$year,$month,$day] = array_map('intval', explode('-', $baseaccount['lastnewday']));
        if (!checkdate($month,$day,$year)) throw new DomainException('Invalid daily marker date.');
    }
    NewDayState::buffs(\Resurrection\Security\ScalarState::read($baseaccount['bufflist']), $session['user']);
    \Resurrection\Game\ForestCompanionState::validate($companions);
}

/** @param array<string,mixed> $sources */
function resurrection_newday_context(array $sources): string {
    global $baseaccount;
    $state = $baseaccount;
    foreach (['password','laston','gentime','gentimecount','gensize','allowednavs','restorepage','lastip','uniqueid'] as $key) unset($state[$key]);
    foreach (['bufflist','companions','dragonpoints','prefs'] as $key) $state[$key] = \Resurrection\Security\ScalarState::read($state[$key]);
    return hash('sha256', json_encode(NewDayState::canonical([$state,$sources,gmdate('Y-m-d',gametime())]), JSON_THROW_ON_ERROR));
}

function resurrection_newday_eligible(): bool {
    global $baseaccount;
    $today = gmdate('Y-m-d',gametime());
    // Durable marker is primary; lasthit bootstraps older/newly created accounts.
    if ($baseaccount['lastnewday'] !== '') return $baseaccount['lastnewday'] !== $today;
    return is_new_day();
}

function resurrection_newday_continue(): void {
    global $session;
    $route = cmd_sanitize((string)$session['user']['restorepage']);
    if ($route === '' || preg_match('/^(?:newday|badnav|graveyard)\.php/', $route)) $route = 'village.php';
    addnav('Continue', $route);
}

function resurrection_newday_daily(): void {
    global $session, $baseaccount, $playermount, $companions;
    define('RESURRECTION_NEWDAY_PRESENTATION', true);
    tlschema('newday');
    try {
        resurrection_newday_validate();
        $sources = resurrection_newday_sources();
        $context = resurrection_newday_context($sources);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            resurrection_consume_action('normal-newday', $context);
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') throw new InvalidArgumentException('Invalid encoding.');
            \Resurrection\Game\DragonPointState::transport((string)file_get_contents('php://input'), $_POST);
            if (array_diff(array_keys($_POST), ['csrf_token','action_token','newday']) !== [] || ($_POST['newday'] ?? null) !== 'normal' ||
                array_diff(array_keys($_GET), ['continue','c']) !== []) throw new InvalidArgumentException('Invalid daily form.');
            resurrection_player_mutation(function () use ($context): void {
                global $session, $baseaccount, $playermount;
                $sources = resurrection_newday_sources(true);
                resurrection_newday_validate();
                if (!resurrection_newday_eligible() || resurrection_newday_context($sources) !== $context) throw new DomainException('Daily state changed.');
                $day = gmdate('Y-m-d', gametime());
                $turnsperday = NewDayState::integer(getsetting('turns',10));
                $mininterest = NewDayState::rate(getsetting('mininterest',1))/100 + 1;
                $maxinterest = NewDayState::rate(getsetting('maxinterest',10))/100 + 1;
                $dailypvpfights = NewDayState::integer(getsetting('pvpday',3));
                modulehook('newday-intercept', []);
                require __DIR__ . '/newday_daily_outcomes.php';
                restore_buff_fields();
                resurrection_newday_validate();
                $session['user']['lastnewday'] = $day;
                if (gmdate('Y-m-d', gametime()) !== $day) throw new DomainException('Game day changed during reset.');
            });
            page_header('It is a new day!');
            resurrection_newday_continue();
            page_footer();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Use a New Day form.'); }
        page_header('It is a new day!');
        if (resurrection_newday_eligible()) {
            $url = 'newday.php?continue=1'; addnav('', $url);
            output('A new day awaits you. Begin your day to restore your daily adventures.`n');
            rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('normal-newday', $context).
                '<input type="hidden" name="newday" value="normal"><button class="button">Begin New Day</button></form>');
        } else {
            output('Your normal New Day is already complete for this game day.`n');
            resurrection_newday_continue();
        }
        page_footer();
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid New Day form. Request a fresh form.');
    } catch (DomainException $error) {
        http_response_code(409); exit('New Day is unavailable. Request a fresh form or ask an administrator to repair stored state.');
    } catch (Throwable $error) {
        http_response_code(500); exit('New Day was not completed. Request a fresh form.');
    }
}
