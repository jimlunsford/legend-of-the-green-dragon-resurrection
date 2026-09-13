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
        foreach (['modules','output','translator','sanitize','holiday_texts','http','datetime','e_rand','buffs','tempstat','pageparts'] as $library) require_once 'lib/' . $library . '.php';
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['settings'] = null;
        $rows = db_query('SELECT * FROM accounts WHERE login=?', true, ['FixtureAdmin']);
        $user = $rows[0];
        $user['prefs'] = [];
        $user['loggedin'] = true;
        $user['superuser'] = SU_MANAGE_MODULES;
        $GLOBALS['session'] = ['loggedin'=>true, 'user'=>$user, 'bufflist'=>[], 'allowednavs'=>[], 'debug'=>''];
        foreach (['module_settings','module_prefs','modulehook_queries','module_preload','navschema','navbysection','translation_namespace_stack'] as $key) $GLOBALS[$key] = [];
        $GLOBALS['injected_modules'] = [0=>[],1=>[]];
        $GLOBALS['translation_namespace'] = '';
        $GLOBALS['REQUEST_URI'] = 'village.php';
        $GLOBALS['SCRIPT_NAME'] = 'village.php';
        $GLOBALS['resline'] = '';
        $GLOBALS['navsection'] = '';
        $GLOBALS['output'] = '';
        $_GET = [];
        $_SESSION = [];
        $_POST = ['csrf_token'=>Csrf::token($_SESSION)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        translator_setup();
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
                modulehook('dragonkill', [], false, $module);
                self::assertSame(0, get_module_pref('skill', $module));
                self::assertSame(0, get_module_pref('uses', $module));
            }
        } finally {
            foreach (resurrection_bundled_modules() as $module) deactivate_module($module);
        }
    }
}
