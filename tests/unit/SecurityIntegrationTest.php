<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\Csrf;

require_once __DIR__ . '/../../lib/commentary_security.php';

final class SecurityIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires installed disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        self::assertTrue(db_connect($host, getenv('RESURRECTION_TEST_DB_USER'), getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
    }

    public function testCommentaryAuthorizationAndRawStorage(): void
    {
        require_once 'lib/commentary.php';
        $text = "O'Reilly \\ dragon 🐉 <script>";
        injectrawcomment('village', 1, $text);
        $id = db_insert_id();
        $rows = db_query('SELECT comment FROM commentary WHERE commentid=?', true, [$id]);
        self::assertSame($text, db_fetch_assoc($rows)['comment']);
        $csrf = []; $token = Csrf::token($csrf);
        $post = ['removecomment' => (string)$id, 'csrf_token' => $token];
        $moderator = ['loggedin' => true, 'user' => ['acctid' => 1, 'superuser' => SU_EDIT_COMMENTS]];
        foreach ([[], ['loggedin' => false, 'user' => ['acctid' => 1, 'superuser' => SU_EDIT_COMMENTS]],
                  ['loggedin' => true, 'user' => ['acctid' => 2, 'superuser' => 0]]] as $actor) {
            try { resurrection_delete_comment($actor, $csrf, 'POST', $post); self::fail('Unauthorized deletion.'); }
            catch (\DomainException $error) { self::assertSame('Comment moderation is not authorized.', $error->getMessage()); }
        }
        foreach ([['GET', $post], ['POST', ['removecomment' => (string)$id]],
                  ['POST', ['removecomment' => (string)$id, 'csrf_token' => [$token]]]] as [$method, $fields]) {
            try { resurrection_delete_comment($moderator, $csrf, $method, $fields); self::fail('Invalid mutation was allowed.'); }
            catch (\DomainException $error) { self::assertSame('Invalid form submission.', $error->getMessage()); }
        }
        foreach (['0', '-1', '1 OR 1=1', '1e0', [$id]] as $invalid) {
            try { resurrection_delete_comment($moderator, $csrf, 'POST', ['removecomment' => $invalid, 'csrf_token' => $token]); self::fail('Invalid ID was allowed.'); }
            catch (\InvalidArgumentException $error) { self::assertNotEmpty($error->getMessage()); }
        }
        self::assertTrue(resurrection_delete_comment($moderator, $csrf, 'POST', $post));
        self::assertFalse(resurrection_delete_comment($moderator, $csrf, 'POST', $post));
        $rows = db_query('SELECT comment FROM moderatedcomments ORDER BY modid DESC LIMIT 1');
        $audit = unserialize(db_fetch_assoc($rows)['comment'], ['allowed_classes' => false]);
        self::assertSame($text, $audit['comment']);
        $auditRows = db_query('SELECT modid FROM moderatedcomments ORDER BY modid DESC LIMIT 1');
        $auditId = db_fetch_assoc($auditRows)['modid'];
        $restore = ['modid' => $auditId, 'csrf_token' => $token];
        try { resurrection_restore_comment($moderator, $csrf, 'POST', $restore); self::fail('Non-auditor restored.'); }
        catch (\DomainException $error) { self::assertSame('Comment auditing is not authorized.', $error->getMessage()); }
        $moderator['user']['superuser'] |= SU_AUDIT_MODERATION;
        self::assertTrue(resurrection_restore_comment($moderator, $csrf, 'POST', $restore));
        self::assertFalse(resurrection_restore_comment($moderator, $csrf, 'POST', $restore));
        self::assertSame([(int)$id], resurrection_comment_ids([$id => '1']));
        foreach (['1', ['1 OR 1=1' => 1], [-1 => 1]] as $invalid) {
            try { resurrection_comment_ids($invalid); self::fail('Invalid selection accepted.'); }
            catch (\InvalidArgumentException $error) { self::assertNotEmpty($error->getMessage()); }
        }

    }

    public function testActualModuleInjectionEnforcesStateAndDependencies(): void
    {
        require_once 'lib/modules.php';
        require_once 'lib/translator.php';
        require_once 'lib/sanitize.php';
        $path = 'modules/resurrectionfixture.php';
        self::assertFileDoesNotExist($path);
        file_put_contents($path, '<?php function resurrectionfixture_getmoduleinfo(){return ["name"=>"Fixture","version"=>"1.0","author"=>"Synthetic","category"=>"Tests","requires"=>$GLOBALS["fixture_requirements"]];} function resurrectionfixture_install(){return true;}');
        $GLOBALS['session'] = ['loggedin' => true, 'user' => ['acctid' => 2, 'superuser' => 0, 'loggedin' => true]];
        $GLOBALS['fixture_requirements'] = [];
        $GLOBALS['translation_namespace_stack'] = [];
        $GLOBALS['translation_namespace'] = '';
        $GLOBALS['REQUEST_URI'] = 'runmodule.php';
        try {
            $clear = static function (): void { $GLOBALS['injected_modules'] = [0 => [], 1 => []]; };
            $clear();
            self::assertFalse(injectmodule('resurrectionfixture', true));
            foreach (['../resurrectionfixture', 'resurrectionfixture.php', "x' OR 1=1", [], null] as $name) {
                self::assertFalse(injectmodule($name, true));
            }
            db_query('INSERT INTO modules (modulename,active,version) VALUES (?,?,?)', true, ['resurrectionfixture', 0, '1.0']);
            $clear(); self::assertFalse(injectmodule('resurrectionfixture', true));
            db_query('UPDATE modules SET active=1 WHERE modulename=?', true, ['resurrectionfixture']);
            $clear(); self::assertTrue(injectmodule('resurrectionfixture', false));
            $raw = "Apostrophe ' and backslash \\ and dragon 🐉";
            set_module_setting('raw_setting', $raw, 'resurrectionfixture');
            unset($GLOBALS['module_settings']['resurrectionfixture']);
            self::assertSame($raw, get_module_setting('raw_setting', 'resurrectionfixture'));
            set_module_pref('user_raw', $raw, 'resurrectionfixture', 2);
            unset($GLOBALS['module_prefs'][2]['resurrectionfixture']);
            self::assertSame($raw, get_module_pref('user_raw', 'resurrectionfixture', 2));

            foreach ([['racehuman' => []], [0 => '1.0'], ['../racehuman' => '1.0'], ['racehuman' => 'not-a-version']] as $bad) {
                self::assertFalse(module_check_requirements($bad));
            }
            self::assertGreaterThan(0, module_compare_versions('1.10', '1.2'));
            self::assertFalse(module_check_requirements(['racehuman' => '1.0|Inactive']));
            db_query('UPDATE modules SET active=1 WHERE modulename=?', true, ['racehuman']);
            self::assertTrue(module_check_requirements(['racehuman' => '1.0|Valid']));
            self::assertFalse(module_check_requirements(['racehuman' => '9.0|Too old']));
            $GLOBALS['fixture_requirements'] = ['resurrectionfixture' => '1.0|Cycle'];
            self::assertFalse(module_check_requirements($GLOBALS['fixture_requirements']));
            db_query('UPDATE modules SET active=0 WHERE modulename=?', true, ['racehuman']);
            $GLOBALS['fixture_requirements'] = 'malformed';
            $clear(); self::assertFalse(injectmodule('resurrectionfixture', false));
            $GLOBALS['fixture_requirements'] = ['missingdependency' => '1.0|Missing dependency'];
            $clear(); self::assertFalse(injectmodule('resurrectionfixture', true));
            $GLOBALS['fixture_requirements'] = ['resurrectionfixture' => '99.0|Too old'];
            $clear(); self::assertFalse(injectmodule('resurrectionfixture', false));
            self::assertFalse(activate_module('resurrectionfixture'));
            self::assertFalse(install_module('resurrectionfixture'));
            self::assertFalse(uninstall_module('resurrectionfixture'));
            $GLOBALS['session']['user']['superuser'] = SU_MANAGE_MODULES;
            $_SESSION = []; $_POST = ['csrf_token' => Csrf::token($_SESSION)];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $clear(); self::assertFalse(activate_module('resurrectionfixture'));
            self::assertFalse(install_module('resurrectionfixture'));
            self::assertSame('1', db_query('SELECT active FROM modules WHERE modulename=?', true, ['resurrectionfixture'])[0]['active']);
            $GLOBALS['fixture_requirements'] = [];
            self::assertTrue(install_module('resurrectionfixture'));
            self::assertSame('0', db_query('SELECT active FROM modules WHERE modulename=?', true, ['resurrectionfixture'])[0]['active']);
            $clear(); self::assertTrue(activate_module('resurrectionfixture'));
            $clear(); self::assertTrue(injectmodule('resurrectionfixture', false));
            self::assertTrue(deactivate_module('resurrectionfixture'));
            self::assertFalse(injectmodule('resurrectionfixture', false));

        } finally {
            unlink($path);
            db_query('DELETE FROM modules WHERE modulename=?', true, ['resurrectionfixture']);
            db_query('DELETE FROM module_settings WHERE modulename=?', true, ['resurrectionfixture']);
            db_query('DELETE FROM module_userprefs WHERE modulename=?', true, ['resurrectionfixture']);
            $GLOBALS['injected_modules'] = [0 => [], 1 => []];
        }
    }
}
