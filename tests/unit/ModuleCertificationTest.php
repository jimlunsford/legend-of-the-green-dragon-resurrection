<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\Csrf;

/** Runs after FreshInstallTest against its disposable schema. */
final class ModuleCertificationTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RESURRECTION_TEST_DB_HOST') === false) self::markTestSkipped('Requires installed disposable CI database.');
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        self::assertTrue(db_connect(getenv('RESURRECTION_TEST_DB_HOST'), getenv('RESURRECTION_TEST_DB_USER'), getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        foreach (['modules','output','translator','sanitize','holiday_texts','http','datetime','e_rand','buffs','tempstat','pageparts','debuglog','addnews','arrayutil'] as $library) require_once 'lib/' . $library . '.php';
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['settings'] = null;
        $rows = db_query('SELECT * FROM accounts WHERE login=?', true, ['FixtureAdmin']);
        $user = $rows[0];
        $user['prefs'] = [];
        $user['loggedin'] = true;
        $user['superuser'] = SU_MANAGE_MODULES;
        $GLOBALS['session'] = ['loggedin'=>true, 'user'=>$user, 'bufflist'=>[], 'allowednavs'=>[], 'debug'=>''];
        foreach (['module_settings','module_prefs','modulehook_queries','module_preload','navschema','navbysection','translation_namespace_stack','blocked_modules','unblocked_modules'] as $key) $GLOBALS[$key] = [];
        $GLOBALS['block_all_modules'] = false;
        $GLOBALS['injected_modules'] = [0=>[],1=>[]];
        $GLOBALS['translation_namespace'] = '';
        $GLOBALS['REQUEST_URI'] = 'village.php';
        $GLOBALS['SCRIPT_NAME'] = 'village.php';
        $GLOBALS['resline'] = '';
        $GLOBALS['navsection'] = '';
        $GLOBALS['output'] = '';
        $GLOBALS['companions'] = [];
        $GLOBALS['blockednavs'] = ['blockpartial'=>[], 'unblockpartial'=>[], 'blockfull'=>[], 'unblockfull'=>[]];
        $_GET = [];
        $_SESSION = [];
        $_POST = ['csrf_token'=>Csrf::token($_SESSION)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        translator_setup();
    }

    public function testGoldmineDeathWithoutMountHasDefinedOutcome(): void
    {
        self::assertTrue(activate_module('racehuman'));
        self::assertTrue(activate_module('goldmine'));
        $goldLoss=get_module_setting('percentgoldloss','goldmine');
        $gemLoss=get_module_setting('percentgemloss','goldmine');
        $chance=get_module_setting('minedeathchance','racehuman');
        try {
            set_module_setting('percentgoldloss',50,'goldmine');
            set_module_setting('percentgemloss',25,'goldmine');
            set_module_setting('minedeathchance',90,'racehuman');
            $GLOBALS['session']['user']=array_replace($GLOBALS['session']['user'],[
                'hashorse'=>0,'race'=>'Human','alive'=>true,'hitpoints'=>100,'experience'=>100,
                'gold'=>1000,'gems'=>20,'turns'=>10,'specialinc'=>'module:goldmine']);
            injectmodule('goldmine');
            $_GET=['op'=>'mine'];
            // Seed zero gives 45 (entry), 20 (collapse), 34 (human death), with no mount.
            // No production random hook or probability changes are introduced.
            mt_srand(0);
            goldmine_runevent('forest');
            self::assertFalse($GLOBALS['session']['user']['alive']);
            self::assertSame(0,$GLOBALS['session']['user']['hitpoints']);
            self::assertSame(0,$GLOBALS['session']['user']['hashorse']);
            self::assertEquals(500,$GLOBALS['session']['user']['gold']);
            self::assertEquals(15,$GLOBALS['session']['user']['gems']);
            self::assertEquals(110,$GLOBALS['session']['user']['experience']);
            self::assertSame('',$GLOBALS['session']['user']['specialinc']);
            self::assertStringContainsString('crushed under a ton of rock',$GLOBALS['output']);
        } finally {
            mt_srand(); $_GET=[];
            set_module_setting('percentgoldloss',$goldLoss,'goldmine');
            set_module_setting('percentgemloss',$gemLoss,'goldmine');
            set_module_setting('minedeathchance',$chance,'racehuman');
            deactivate_module('goldmine'); deactivate_module('racehuman');
        }
    }

    public function testEveryBundledLifecycleAndMetadataRoundTrip(): void
    {
        $expected = resurrection_bundled_modules();
        $files = array_map(static fn($path) => basename($path, '.php'), glob('modules/*.php'));
        sort($files);
        self::assertSame($expected, $files);
        foreach ($expected as $module) {
            self::assertFalse((bool)is_module_active($module), $module);
            self::assertFalse(injectmodule($module), $module);
            $beforeHooks = db_query('SELECT location,`function`,whenactive,priority FROM module_hooks WHERE modulename=? ORDER BY location,`function`', true, [$module]);
            $beforeEvents = db_query('SELECT event_type,event_chance FROM module_event_hooks WHERE modulename=? ORDER BY event_type', true, [$module]);
            self::assertNotEmpty(array_merge($beforeHooks, $beforeEvents), $module);
            self::assertTrue(activate_module($module), $module);
            self::assertTrue((bool)is_module_active($module), $module);
            self::assertTrue(injectmodule($module), $module);
            $info = get_module_info($module);
            self::assertTrue(module_check_requirements($info['requires'] ?? []), $module);
            foreach ($beforeHooks as $hook) self::assertTrue(is_callable($hook['function']), $module . ':' . $hook['location']);
            // Exercise declared defaults, raw storage, and cache reload for each settings/prefs namespace.
            foreach (['settings','prefs'] as $kind) {
                foreach ($info[$kind] ?? [] as $name=>$descriptor) {
                    if (!is_string($name)) continue;
                    $descriptor = is_array($descriptor) ? $descriptor[0] : $descriptor;
                    $parts = explode('|', $descriptor, 2);
                    $getter = $kind === 'settings' ? 'get_module_setting' : 'get_module_pref';
                    $setter = $kind === 'settings' ? 'set_module_setting' : 'set_module_pref';
                    $default = $getter($name, $module);
                    self::assertEquals($parts[1] ?? null, $default, $module . ':' . $name);
                    $raw = "O'Reilly \\ dragon 🐉";
                    $setter($name, $raw, $module);
                    unset($GLOBALS['module_settings'][$module], $GLOBALS['module_prefs'][(int)$GLOBALS['session']['user']['acctid']][$module]);
                    self::assertSame($raw, $getter($name, $module));
                    $setter($name, $default ?? '', $module);
                }
            }
            set_module_setting('certification-preserved', 'configured', $module);
            // Populate real preload state before transitions. Reinstall must be idempotent for registrations.
            mass_module_prepare(array_column($beforeHooks, 'location'));
            self::assertTrue(deactivate_module($module), $module);
            self::assertFalse(injectmodule($module), $module);
            self::assertSame([], $GLOBALS['modulehook_queries']);
            self::assertTrue(activate_module($module), $module);
            self::assertTrue(injectmodule($module), $module);
            self::assertTrue(install_module($module), $module);
            self::assertFalse(injectmodule($module), $module);
            self::assertSame('configured', get_module_setting('certification-preserved', $module));
            self::assertSame($beforeHooks, db_query('SELECT location,`function`,whenactive,priority FROM module_hooks WHERE modulename=? ORDER BY location,`function`', true, [$module]));
            self::assertSame($beforeEvents, db_query('SELECT event_type,event_chance FROM module_event_hooks WHERE modulename=? ORDER BY event_type', true, [$module]));
            self::assertTrue(activate_module($module), $module);
        }
        self::assertSame('24', db_query('SELECT COUNT(*) AS n FROM modules WHERE active=1')[0]['n']);
        self::assertSame('3', db_query('SELECT COUNT(*) AS n FROM drinks')[0]['n']);
        self::assertSame('1', db_query('SELECT COUNT(*) AS n FROM bounty')[0]['n']);
        self::assertSame([], db_query('SELECT modulename,location,`function`,COUNT(*) AS n FROM module_hooks GROUP BY modulename,location,`function` HAVING COUNT(*)>1'));
        foreach ($expected as $module) self::assertTrue(deactivate_module($module));
    }

    public function testCombinedRaceNamesAndSpecialtyState(): void
    {
        foreach (resurrection_bundled_modules() as $module) self::assertTrue(activate_module($module));
        try {
            self::assertSame(['Dwarf'=>'Dwarf','Elf'=>'Elf','Human'=>'Human','Troll'=>'Troll'], modulehook('racenames', []));
            self::assertSame(['DA'=>'specialtydarkarts','MP'=>'specialtymysticpower','TS'=>'specialtythiefskills'], modulehook('specialtymodules', []));
            $GLOBALS['session']['user']['specialty'] = '';
            modulehook('choose-specialty', []);
            foreach (['DA','MP','TS'] as $spec) self::assertStringContainsString('setspecialty=' . $spec, $GLOBALS['output']);
            foreach (['DA'=>'specialtydarkarts','MP'=>'specialtymysticpower','TS'=>'specialtythiefskills'] as $spec=>$module) {
                $GLOBALS['session']['user']['specialty'] = $spec;
                set_module_pref('skill', 2, $module);
                set_module_pref('uses', 0, $module);
                modulehook('incrementspecialty', ['color'=>'`2']);
                self::assertSame(3, get_module_pref('skill', $module));
                self::assertSame(1, get_module_pref('uses', $module));
                modulehook('newday', ['turnstoday'=>''], false, $module);
                self::assertSame(2, get_module_pref('uses', $module));
                modulehook('fightnav-specialties', ['script'=>'forest.php?'], false, $module);
                self::assertNotEmpty($GLOBALS['navbysection']);
                foreach (['-1','0','4','999999999999999999999', '1 OR 1=1', ['1']] as $invalid) {
                    $_GET = ['skill'=>$spec, 'l'=>$invalid];
                    modulehook('apply-specialties', [], false, $module);
                    self::assertSame(2, get_module_pref('uses', $module));
                }
                foreach (['1','2','3','5'] as $level) {
                    set_module_pref('uses', 10, $module);
                    $_GET = ['skill'=>$spec, 'l'=>$level];
                    modulehook('apply-specialties', [], false, $module);
                    self::assertSame(10 - (int)$level, get_module_pref('uses', $module));
                    if ($spec === 'DA' && $level === '1') {
                        self::assertGreaterThan(0, $GLOBALS['companions']['skeleton_warrior']['hitpoints']);
                    } else {
                        self::assertArrayHasKey(strtolower($spec) . $level, $GLOBALS['session']['bufflist']);
                    }
                }
                $_GET = [];
                modulehook('dragonkill', [], false, $module);
                self::assertSame(0, get_module_pref('skill', $module));
                self::assertSame(0, get_module_pref('uses', $module));
            }
        } finally {
            foreach (resurrection_bundled_modules() as $module) deactivate_module($module);
        }
    }
    public function testRaceStatisticsLocationsAndBundledEvents(): void
    {
        foreach (resurrection_bundled_modules() as $module) self::assertTrue(activate_module($module));
        try {
            $GLOBALS['session']['user']['level'] = 10;
            $GLOBALS['session']['user']['attack'] = 10;
            $GLOBALS['session']['user']['defense'] = 10;
            foreach (['Elf'=>'raceelf', 'Troll'=>'racetroll'] as $race=>$module) {
                $GLOBALS['session']['user']['race'] = $race;
                $GLOBALS['session']['bufflist'] = [];
                modulehook('newday', ['turnstoday'=>''], false, $module);
                $field = $race === 'Elf' ? 'defmod' : 'atkmod';
                self::assertSame(1.3, $GLOBALS['session']['bufflist']['racialbenefit'][$field]);
                self::assertSame(1, $GLOBALS['session']['bufflist']['racialbenefit']['allowinpvp']);
                self::assertSame(1, $GLOBALS['session']['bufflist']['racialbenefit']['allowintrain']);
                $pvpField = $race === 'Elf' ? 'creaturedefense' : 'creatureattack';
                self::assertSame(13.0, modulehook('pvpadjust', ['race'=>$race, 'creaturelevel'=>10, $pvpField=>10], false, $module)[$pvpField]);
                $stat = $race === 'Elf' ? 'defense' : 'attack';
                self::assertSame(13.0, modulehook('adjuststats', ['race'=>$race, 'level'=>10, $stat=>10], false, $module)[$stat]);
            }
            $GLOBALS['session']['user']['race'] = 'Human';
            $GLOBALS['session']['user']['turns'] = 10;
            modulehook('newday', ['turnstoday'=>''], false, 'racehuman');
            self::assertSame(12, $GLOBALS['session']['user']['turns']);
            $GLOBALS['session']['user']['race'] = 'Dwarf';
            self::assertSame(120.0, modulehook('creatureencounter', ['creaturegold'=>100], false, 'racedwarf')['creaturegold']);
            foreach (['Dwarf'=>'racedwarf','Elf'=>'raceelf','Human'=>'racehuman','Troll'=>'racetroll'] as $race=>$module) {
                $GLOBALS['session']['user']['race'] = $race;
                $GLOBALS['output'] = '';
                modulehook('chooserace', [], false, $module);
                self::assertStringContainsString('setrace=' . $race, $GLOBALS['output']);
                modulehook('setrace', [], false, $module);
                $old = get_module_setting('villagename', $module);
                $new = "O'Reilly \\ village 🐉";
                db_query('UPDATE accounts SET location=? WHERE acctid=?', true, [$old, $GLOBALS['session']['user']['acctid']]);
                modulehook('changesetting', ['setting'=>'villagename','module'=>$module,'old'=>$old,'new'=>$new], false, $module);
                self::assertSame($new, db_query('SELECT location FROM accounts WHERE acctid=?', true, [$GLOBALS['session']['user']['acctid']])[0]['location']);
                modulehook('changesetting', ['setting'=>'villagename','module'=>$module,'old'=>$new,'new'=>$old], false, $module);
                self::assertSame([], modulehook('validlocation', [], false, $module)); // Cities is intentionally absent.
                self::assertSame([], modulehook('validforestloc', [], false, $module));
            }
            $GLOBALS['playermount'] = ['mountid'=>1];
            set_module_objpref('mounts', 1, 'findtavern', 0, 'darkhorse');
            $events = module_collect_events('forest');
            self::assertCount(8, $events);
            self::assertSame(100, array_column($events, 'rawchance', 'modulename')['darkhorse']);
            set_module_objpref('mounts', 1, 'findtavern', 1, 'darkhorse');
            self::assertSame(0, array_column(module_collect_events('forest'), 'rawchance', 'modulename')['darkhorse']);
            self::assertCount(4, module_collect_events('travel'));
            self::assertSame(0, resurrection_event_chance('darkhorse', 'require_once("modules/darkhorse.php"); return (darkhorse_tavernmount() ? 0 : 100);'));
            set_module_objpref('mounts', 1, 'findtavern', 0, 'darkhorse');
            modulehook('darkhorsegame', ['return'=>'runmodule.php?module=darkhorse']);
            self::assertNotEmpty($GLOBALS['navbysection']);
            $scripts = db_query("SELECT DISTINCT creatureaiscript FROM creatures WHERE creatureaiscript IS NOT NULL AND creatureaiscript<>''");
            self::assertCount(1, $scripts);
            self::assertTrue(\Resurrection\Game\CreatureAi::supported($scripts[0]['creatureaiscript']));
        } finally {
            db_query('UPDATE accounts SET location=? WHERE acctid=?', true, [LOCATION_FIELDS, $GLOBALS['session']['user']['acctid']]);
            foreach (resurrection_bundled_modules() as $module) deactivate_module($module);
        }
    }

    public function testCombinedInnDayAndForestParticipation(): void
    {
        foreach (resurrection_bundled_modules() as $module) self::assertTrue(activate_module($module));
        try {
            $GLOBALS['session']['user']['race'] = 'Human';
            $GLOBALS['session']['user']['specialty'] = 'DA';
            $GLOBALS['session']['user']['turns'] = 10;
            $GLOBALS['session']['user']['gold'] = 1000;
            $GLOBALS['session']['user']['gems'] = 10;
            $GLOBALS['session']['user']['hashorse'] = 0;
            foreach (['dag'=>['bounties'], 'drinks'=>['harddrinks','drunkeness'], 'lovers'=>['seenlover'], 'sethsong'=>['been'], 'outhouse'=>['usedouthouse'], 'crazyaudrey'=>['played'], 'game_fivesix'=>['playstoday']] as $module=>$preferences) {
                foreach ($preferences as $preference) set_module_pref($preference, 1, $module);
            }
            modulehook('newday', ['turnstoday'=>'', 'resurrection'=>false]);
            foreach (['dag'=>['bounties'], 'drinks'=>['harddrinks','drunkeness'], 'lovers'=>['seenlover'], 'sethsong'=>['been'], 'outhouse'=>['usedouthouse'], 'crazyaudrey'=>['played'], 'game_fivesix'=>['playstoday']] as $module=>$preferences) {
                foreach ($preferences as $preference) self::assertSame(0, get_module_pref($preference, $module));
            }
            modulehook('inn', []);
            modulehook('inn-desc', []);
            modulehook('forest', []);
            self::assertStringContainsString('Dag Durnick', $GLOBALS['output']);
            $navs = json_encode($GLOBALS['navbysection']);
            foreach (['dag','lovers','sethsong','outhouse'] as $module) self::assertStringContainsString('module=' . $module, $navs);
            $_GET = ['op'=>'bartender', 'act'=>''];
            modulehook('header-inn', []);
            self::assertStringContainsString('module=cedrikspotions', json_encode($GLOBALS['navbysection']));
            set_module_pref('extrahps', 3, 'cedrikspotions');
            set_module_pref('extrahps', 2, 'fairy');
            self::assertSame(95, modulehook('hprecalc', ['total'=>100,'extra'=>5])['total']);
            set_module_pref('drunkeness', 80, 'drinks');
            modulehook('header-graveyard', []);
            self::assertSame(0, get_module_pref('drunkeness', 'drinks'));
            set_module_pref('harddrinks', get_module_setting('hardlimit','drinks'), 'drinks');
            modulehook('ale', []); // Exhausted hard-drink quota text/list path.
            $GLOBALS['badguy'] = ['acctid'=>999999, 'creaturename'=>'Synthetic opponent'];
            self::assertSame(['pvpmessageadd'=>''], modulehook('pvpwin', ['pvpmessageadd'=>''], false, 'dag'));
            $_GET = [];
            injectmodule('findgem');
            findgem_runevent('forest', 'forest.php?');
            self::assertSame(11, $GLOBALS['session']['user']['gems']);
            injectmodule('findgold');
            $gold = $GLOBALS['session']['user']['gold'];
            findgold_runevent('forest', 'forest.php?');
            self::assertGreaterThan($gold, $GLOBALS['session']['user']['gold']);
            foreach (['crazyaudrey','fairy','foilwench','glowingstream','goldmine','darkhorse'] as $module) {
                injectmodule($module);
                $GLOBALS['output'] = '';
                $_GET = [];
                ($module . '_runevent')('forest', 'forest.php?');
                self::assertNotEmpty($GLOBALS['output'], $module);
            }
            injectmodule('foilwench');
            $_GET = ['op'=>'give'];
            $before = get_module_pref('skill', 'specialtydarkarts');
            foilwench_runevent('forest');
            self::assertSame($before + 1, get_module_pref('skill', 'specialtydarkarts'));
            self::assertSame(10, $GLOBALS['session']['user']['gems']);
            injectmodule('goldmine');
            $_GET = ['op'=>'no'];
            goldmine_runevent('forest');
            self::assertSame('', $GLOBALS['session']['user']['specialinc']);
        } finally {
            $_GET = [];
            foreach (resurrection_bundled_modules() as $module) deactivate_module($module);
        }
    }

}
