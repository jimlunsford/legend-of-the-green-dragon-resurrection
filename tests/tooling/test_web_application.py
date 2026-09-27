"""Real loopback application requests against the preceding clean CI install."""
import base64
from contextlib import contextmanager
import html
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import time
import tempfile
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]

class WebApplicationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.config = ROOT / 'dbconnect.php'
        values = {'DB_HOST': host, 'DB_USER': os.environ['RESURRECTION_TEST_DB_USER'],
                  'DB_PASS': os.environ['RESURRECTION_TEST_DB_PASSWORD'],
                  'DB_NAME': 'resurrection_test', 'DB_PREFIX': '',
                  'DB_USEDATACACHE': 0, 'DB_DATACACHEPATH': ''}
        encoded = base64.b64encode(json.dumps(values).encode()).decode()
        with cls.config.open('x') as file:
            file.write("<?php extract(json_decode(base64_decode('" + encoded + "'),true), EXTR_SKIP);\n")
        cls.config.chmod(0o600)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        # Outside the application tree; only the loopback fixture server loads this.
        cls.seed_file = tempfile.NamedTemporaryFile(mode='w', suffix='.php')
        cls.seed_file.write("<?php if (in_array($_SERVER['HTTP_X_RESURRECTION_FIXTURE'] ?? '', ['skeleton-death','specialty-accounting'], true)) mt_srand(12345); if (($_SERVER['HTTP_X_RESURRECTION_FIXTURE'] ?? '') === 'ordinary-flee-failure') mt_srand(3); if (($_SERVER['HTTP_X_RESURRECTION_FIXTURE'] ?? '') === 'graveyard-round') mt_srand(1);\n")
        cls.seed_file.flush()
        cls.server_log = tempfile.TemporaryFile(mode='w+t')
        cls.server = subprocess.Popen([shutil.which('php'), '-d', 'display_errors=1', '-d', 'error_reporting=-1',
                                       '-d', 'zend.exception_ignore_args=1', '-d', 'auto_prepend_file='+cls.seed_file.name, '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT)],
                                      stdout=subprocess.DEVNULL, stderr=cls.server_log, cwd=ROOT)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.1):
                    return
            except OSError:
                if cls.server.poll() is not None:
                    cls.config.unlink()
                    raise RuntimeError('Application test server exited')
                time.sleep(.05)
        cls.server.terminate()
        cls.config.unlink()
        raise RuntimeError('Application test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.server_log.close()
        cls.seed_file.close()
        cls.config.unlink()

    @staticmethod
    def query(sql, parameters=()):
        code = "require 'dbconnect.php'; require 'lib/dbwrapper_pdo.php'; db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); echo json_encode(db_query($input[0],true,$input[1]),JSON_THROW_ON_ERROR);"
        result = subprocess.run([shutil.which('php'), '-r', code], input=json.dumps([sql, parameters]), cwd=ROOT,
                                capture_output=True, text=True, timeout=20)
        if result.returncode != 0:
            raise AssertionError('Fixture database operation failed')
        return json.loads(result.stdout)

    def wager_failure(self, module, player):
        # DDL is fixture setup, before the real request transaction. No SUPER/trigger privilege.
        self.assertIn(module,['game_stones','game_dice','game_fivesix'])
        pattern='%"game":"'+module+'"%"stage":"complete"%'
        self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_wager_failure CHECK (specialmisc NOT LIKE '"+pattern+"')")

    def remove_wager_failure(self):
        if self.query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='accounts' AND CONSTRAINT_NAME='fixture_wager_failure'"):
            self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_wager_failure')

    def test_active_maintenance_failures_retries_and_concurrency(self):
        path = ROOT / 'modules' / 'resurrectionmaintenancefixture.php'
        signal = ROOT / 'tests' / 'fixtures' / 'maintenance-running'
        module = 'resurrectionmaintenancefixture'
        with path.open('x') as file:
            file.write('''<?php
function resurrectionmaintenancefixture_getmoduleinfo() {
    return ['name'=>'Maintenance fixture','version'=>'1.0','author'=>'Synthetic','category'=>'Tests'];
}
function resurrectionmaintenancefixture_dohook($hook, $args) {
    $mode = getsetting('fixture_maint_mode', 'ok');
    db_query("UPDATE settings SET value=value+1 WHERE setting='fixture_maint_count'");
    if ($mode === 'throw') throw new RuntimeException('SYNTHETIC-SECRET-MUST-NOT-LOG');
    if ($mode === 'malformed') return 'invalid';
    if ($mode === 'database') db_query('INSERT INTO nonexistent_fixture_table VALUES (1)');
    if ($mode === 'slow') {
        file_put_contents('tests/fixtures/maintenance-running', 'running');
        usleep(2000000);
    }
    return $args;
}
''')
        def setting(name, value):
            self.query('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [name, str(value)])
        def run():
            return subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        try:
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ?', ['maintenance_day', 'mh:%'])
            self.query('UPDATE modules SET active=1')  # Lifecycle independently exercised by PHPUnit.
            self.query('INSERT INTO modules (modulename,active,version) VALUES (?,1,?)', [module, '1.0'])
            self.query('INSERT INTO module_hooks (modulename,location,`function`,whenactive,priority) VALUES (?,?,?,?,?)',
                       [module, 'newday-runonce', module + '_dohook', '', 100])
            self.query('UPDATE module_settings SET value=3 WHERE modulename=? AND setting=?', ['crazyaudrey', 'gamedaysremaining'])
            setting('fixture_maint_count', 0)
            for mode in ['throw', 'malformed', 'database']:
                setting('fixture_maint_mode', mode)
                failed = run()
                self.assertEqual(1, failed.returncode, failed.stdout)
                self.assertNotIn('SYNTHETIC-SECRET', failed.stdout + failed.stderr)
                self.assertNotIn('nonexistent_fixture_table', failed.stdout + failed.stderr)
                self.assertEqual([{'value': ''}], self.query('SELECT value FROM settings WHERE setting=?', ['maintenance_day']))  # getsetting persists its empty default, never a completed day
                self.assertEqual('0', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maint_count'])[0]['value'])
                # Earlier committed active module is not repeated when a later one fails.
                self.assertEqual('2', self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?', ['crazyaudrey', 'gamedaysremaining'])[0]['value'])
            setting('fixture_maint_mode', 'ok')
            finished = run()
            self.assertEqual(0, finished.returncode, finished.stderr)
            self.assertEqual('complete', json.loads(finished.stdout)['maintenance'])
            self.assertEqual('1', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maint_count'])[0]['value'])
            duplicate = run()
            self.assertEqual('already-complete', json.loads(duplicate.stdout)['maintenance'])
            self.assertEqual('1', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maint_count'])[0]['value'])
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ?', ['maintenance_day', 'mh:%'])
            setting('fixture_maint_mode', 'slow')
            first = subprocess.Popen([shutil.which('php'), 'cron.php'], cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            try:
                for _ in range(100):
                    if signal.exists() or first.poll() is not None:
                        break
                    time.sleep(.05)
                self.assertTrue(signal.exists(), 'First scheduler did not enter its locked active hook')
                second = run()
                self.assertEqual(1, second.returncode)
                out, err = first.communicate(timeout=60)
                self.assertEqual(0, first.returncode, err)
                self.assertEqual('complete', json.loads(out)['maintenance'])
                self.assertEqual('2', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maint_count'])[0]['value'])
            finally:
                if first.poll() is None:
                    first.terminate()
                    first.wait(timeout=5)
        finally:
            path.unlink()
            signal.unlink(missing_ok=True)
            self.query('DELETE FROM module_hooks WHERE modulename=?', [module])
            self.query('DELETE FROM modules WHERE modulename=?', [module])
            self.query('UPDATE modules SET active=0')
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ? OR setting LIKE ?',
                       ['maintenance_day', 'mh:%', 'fixture_maint_%'])

    def test_cli_maintenance_is_once_per_game_day(self):
        first = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, first.returncode, first.stderr)
        self.assertEqual('complete', json.loads(first.stdout)['maintenance'])
        second = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, second.returncode, second.stderr)
        self.assertEqual('already-complete', json.loads(second.stdout)['maintenance'])

    def test_dispatcher_enforces_module_state(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args):
                return None
        client = urllib.request.build_opener(NoRedirect)
        path = ROOT / 'modules' / 'resurrectionhttpfixture.php'
        module = 'resurrectionhttpfixture'
        with path.open('x') as file:
            file.write('''<?php
function resurrectionhttpfixture_getmoduleinfo() {
    return ['name'=>'HTTP fixture','version'=>'1.0','author'=>'Synthetic','category'=>'Tests',
        'allowanonymous'=>true,'override_forced_nav'=>true,
        'requires'=>getsetting('fixture_dependency',0) ? ['missingdependency'=>'1.0|Missing'] : []];
}
function resurrectionhttpfixture_run() { echo 'fixture-executed'; exit; }
''')
        def request(expected_execution=False):
            try:
                response = client.open(f'http://127.0.0.1:{self.port}/runmodule.php?module={module}&admin=1&force=1', timeout=15)
            except urllib.error.HTTPError as error:
                response = error
            body = response.read().decode()
            self.assertNotRegex(body, r'(?i)(fatal error|warning:|deprecated:|notice:|runtime error in)')
            if expected_execution:
                self.assertEqual(200, response.status)
                self.assertEqual('fixture-executed', body.strip())  # historical includes emit a leading newline
            else:
                self.assertIn(response.status, (302, 303, 403, 404))
                self.assertNotIn('fixture-executed', body)
        try:
            request()  # uninstalled, despite both forged force parameters
            self.query('INSERT INTO modules (modulename,active,version) VALUES (?,0,?)', [module, '1.0'])
            request()  # installed but inactive
            self.query('UPDATE modules SET active=1 WHERE modulename=?', [module])
            request(True)
            self.query('UPDATE settings SET value=? WHERE setting=?', ['1', 'fixture_dependency'])  # getsetting persisted its default
            request()  # active but missing a dependency
        finally:
            path.unlink()
            self.query('DELETE FROM modules WHERE modulename=?', [module])
            self.query('DELETE FROM settings WHERE setting=?', ['fixture_dependency'])

    @contextmanager
    def _seeded_module_actions(self, location='header-runmodule'):
        # Installed only in this disposable fixture. Production e_rand remains unchanged.
        name='resurrectionrng'+os.urandom(4).hex(); path=ROOT/'modules'/f'{name}.php'
        with path.open('x') as file:
            file.write("""<?php
function resurrectionrandomfixture_getmoduleinfo() { return ['name'=>'Deterministic test fixture','version'=>'1.0','author'=>'Tests','category'=>'Tests']; }
function resurrectionrandomfixture_dohook($hook,$args) { mt_srand((int)getsetting('fixture_rng_seed',0)); return $args; }
""".replace('resurrectionrandomfixture',name))
        try:
            self.query('INSERT INTO modules(modulename,active,version) VALUES (?,1,?)',[name,'1.0'])
            self.query('INSERT INTO module_hooks(modulename,location,`function`,priority,whenactive) VALUES (?,?,?,100,?)',[name,location,name+'_dohook',''])
            yield lambda seed: self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['fixture_rng_seed',str(seed)])
        finally:
            self.query('DELETE FROM module_hooks WHERE modulename=?',[name])
            self.query('DELETE FROM modules WHERE modulename=?',[name])
            self.query('DELETE FROM settings WHERE setting=?',['fixture_rng_seed'])
            path.unlink()

    def test_specialty_onboarding_http_authority(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        modules=self.query('SELECT modulename,active FROM modules')
        self.query('UPDATE modules SET active=1')
        call=self._security_client(); url='newday.php?continue=1'
        specialties={'DA':'specialtydarkarts','MP':'specialtymysticpower','TS':'specialtythiefskills'}
        def setup():
            self.query('DELETE FROM module_userprefs WHERE userid=? AND modulename IN (?,?,?)',[player,*specialties.values()])
            self.query('UPDATE accounts SET race=?,specialty=?,location=?,specialinc=?,dragonkills=0,dragonpoints=?,alive=1,hitpoints=100,bufflist=? WHERE acctid=?',
                ['Human','','Unselected location','','a:0:{}','a:0:{}',player])
        def state():
            return self.query('SELECT race,location,specialty,gold,gems,turns,age,attack,defense,dragonkills FROM accounts WHERE acctid=?',[player])[0] | {'internal': self.query('SELECT modulename,setting,value FROM module_userprefs WHERE userid=? AND modulename IN (?,?,?) ORDER BY modulename,setting',[player,*specialties.values()])}
        def adversarial(path,data=None): self._security_allow(player,path); return call(path,data)
        def form():
            self._security_allow(player,'newday.php'); before=state()
            status,body=call('newday.php'); self.assertEqual(200,status,body[:1000]); self.assertEqual(before,state())
            found={}
            for action,part in re.findall(r'<form\b[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S):
                if html.unescape(action)!=url: continue
                choice=re.search(r'name="setspecialty" value="([^"]+)"',part)
                if choice: found[choice[1]]=self._security_fields(part)|{'onboarding':'specialty','setspecialty':choice[1]}
            return found,body
        try:
            for spec,module in specialties.items():
                with self.subTest(specialty=spec):
                    setup(); forms,body=form(); self.assertEqual(set(specialties),set(forms))
                    self.assertNotIn('href=\'newday.php?setspecialty=',body)
                    before=state(); self.assertIn(self._security_client(None)(url,forms[spec])[0],[302,303,403]); self.assertEqual(before,state())
                    for patch in [{'csrf_token':None},{'csrf_token':'0'*64}]:
                        data=forms[spec]|patch
                        if data['csrf_token'] is None: del data['csrf_token']
                        self.assertEqual(403,adversarial(url,data)[0]); self.assertEqual(before,state())
                    forms,_=form()
                    self.assertEqual(200,call(url,forms[spec])[0]); after=state()
                    self.assertEqual(spec,after['specialty']); self.assertEqual(before['location'],after['location'])
                    self.assertEqual([{'setting':'skill','value':'0'},{'setting':'uses','value':'0'}], self.query('SELECT setting,value FROM module_userprefs WHERE userid=? AND modulename=? ORDER BY setting',[player,module]))
                    self.assertEqual({k:v for k,v in before.items() if k not in ['specialty','internal']},{k:v for k,v in after.items() if k not in ['specialty','internal']})
                    self.assertEqual(409,adversarial(url,forms[spec])[0]); self.assertEqual(after,state())
                    alternative=next(v for k,v in forms.items() if k!=spec)
                    self.assertEqual(409,adversarial(url,alternative)[0]); self.assertEqual(after,state())
                    # A separate authenticated request reloads the committed identity/preferences.
                    call=self._security_client(); adversarial('inn.php'); self.assertEqual(after,state())
                    # Actual New Day uses the stored skill and historical configured bonus.
                    self.query('UPDATE accounts SET lasthit=? WHERE acctid=?',['2000-01-01 00:00:00',player])
                    self.assertEqual(200,adversarial('newday.php?continue=1')[0])
                    bonus=int(self.query('SELECT value FROM settings WHERE setting=?',['specialtybonus'])[0]['value']) if self.query('SELECT value FROM settings WHERE setting=?',['specialtybonus']) else 1
                    self.assertEqual(spec,state()['specialty'])
                    self.assertEqual(str(bonus),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,module,'uses'])[0]['value'])
                    # Legitimately cleared specialty retains earned state, never client input.
                    setup()
                    for key,value in [('skill','9'),('uses','2')]:
                        self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[module,key,player,value])
                    forms,_=form(); self.assertEqual(200,call(url,forms[spec])[0])
                    self.assertEqual([{'setting':'skill','value':'9'},{'setting':'uses','value':'2'}],self.query('SELECT setting,value FROM module_userprefs WHERE userid=? AND modulename=? ORDER BY setting',[player,module]))
                    self.query('UPDATE accounts SET lasthit=? WHERE acctid=?',['2000-01-01 00:00:00',player])
                    self.assertEqual(200,adversarial('newday.php?continue=1')[0])
                    self.assertEqual(str(3+bonus),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,module,'uses'])[0]['value'])
                    setup(); forms,_=form(); self.query('UPDATE modules SET active=0 WHERE modulename=?',[module]); before=state()
                    self.assertEqual(409,call(url,forms[spec])[0]); self.assertEqual(before,state())
                    offered,_=form(); self.assertNotIn(spec,offered)
                    self.query('UPDATE modules SET active=1 WHERE modulename=?',[module])
                    setup(); forms,_=form()
                    self.query('UPDATE modules SET modulename=? WHERE modulename=?',['fixture_missing_specialty',module])
                    try:
                        before=state(); self.assertEqual(409,call(url,forms[spec])[0]); self.assertEqual(before,state())
                        offered,_=form(); self.assertNotIn(spec,offered)
                    finally: self.query('UPDATE modules SET modulename=? WHERE modulename=?',[module,'fixture_missing_specialty'])
                    setup(); forms,_=form()
                    path=ROOT/'modules'/(module+'.php'); hidden=path.with_suffix('.fixture-hidden')
                    path.rename(hidden)
                    try:
                        before=state(); self.assertEqual(409,call(url,forms[spec])[0]); self.assertEqual(before,state())
                    finally: hidden.rename(path)
                    for malformed in ['-1','1.5','garbage','a:0:{}']:
                        setup(); forms,_=form()
                        self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[module,'uses',player,malformed])
                        before=state(); self.assertEqual(409,call(url,forms[spec])[0]); self.assertEqual(before,state())
                    setup(); forms,_=form()
                    self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[module,'skill',player,'3'])
                    before=state(); self.assertEqual(409,call(url,forms[spec])[0]); self.assertEqual(before,state())
                    setup(); forms,_=form(); before=state()
                    self.assertEqual(403,adversarial('newday.php?setspecialty='+spec)[0]); self.assertEqual(before,state())
                    # Fail the final account write after preference writes; all must roll back.
                    forms,_=form()
                    self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_specialty_failure CHECK (login <> 'WebPlayer' OR specialty='')")
                    try: self.assertEqual(500,call(url,forms[spec])[0])
                    finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_specialty_failure')
                    self.assertEqual(before,state()); self.assertEqual(409,adversarial(url,forms[spec])[0]); self.assertEqual(before,state())
                    forms,_=form(); self.assertEqual(200,call(url,forms[spec])[0]); self.assertEqual(spec,state()['specialty'])
            for patch in [{'setspecialty':'Unknown'},{'setspecialty':'../modules/specialtydarkarts.php'},{'setspecialty':'specialtydarkarts'},{'setspecialty':''},
                          {'setspecialty[]':'DA'},{'module':'specialtydarkarts'},{'module':'../common.php'},{'location':'Forged'},
                          {'skill':'999'},{'uses':'999'},{'prefs[skill]':'999'},{'setspecialty':'drinks'},{'attack':'999'},{'defense':'999'},{'name':'DA'},{'onboarding':'race'}]:
                setup(); forms,_=form(); before=state()
                self.assertEqual(400,call(url,forms['DA']|patch)[0]); self.assertEqual(before,state())
            setup(); forms,_=form(); missing=forms['DA'].copy(); del missing['setspecialty']; before=state()
            self.assertEqual(400,call(url,missing)[0]); self.assertEqual(before,state())
            for key,value in [('race','Horrible Gelatinous Blob'),('specialinc','module:goldmine'),('dragonkills',1),('specialty','MP'),('age',999)]:
                setup(); forms,_=form(); self.query('UPDATE accounts SET '+key+'=? WHERE acctid=?',[value,player]); before=state()
                self.assertEqual(409,call(url,forms['DA'])[0]); self.assertEqual(before,state())
            # No implicit GET fallback when every bundled specialty is inactive.
            setup()
            for module in specialties.values(): self.query('UPDATE modules SET active=0 WHERE modulename=?',[module])
            before=state(); offered,body=form(); self.assertEqual({},offered); self.assertIn('No active bundled specialties',body); self.assertEqual(before,state())
        finally:
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],row['userid'],row['value']])
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            for row in modules: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])

    def test_race_onboarding_http_authority(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        modules=self.query('SELECT modulename,active FROM modules')
        self.query('UPDATE modules SET active=1')
        call=self._security_client(); url='newday.php?continue=1'
        races={'Human':'racehuman','Elf':'raceelf','Dwarf':'racedwarf','Troll':'racetroll'}
        village=self.query('SELECT value FROM settings WHERE setting=?',['villagename'])[0]['value']
        def setup():
            self.query('UPDATE accounts SET race=?,specialty=?,location=?,specialinc=?,dragonkills=0,dragonpoints=?,alive=1,hitpoints=100,bufflist=? WHERE acctid=?',
                ['Horrible Gelatinous Blob','DA','Unselected location','','a:0:{}','a:0:{}',player])
        def state():
            return self.query('SELECT race,location,specialty,gold,gems,turns,age,attack,defense,dragonkills FROM accounts WHERE acctid=?',[player])[0]
        def adversarial(path,data=None): self._security_allow(player,path); return call(path,data)
        def form():
            self._security_allow(player,'newday.php'); before=state()
            status,body=call('newday.php'); self.assertEqual(200,status,body[:1000]); self.assertEqual(before,state())
            found={}
            for action,part in re.findall(r'<form\b[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S):
                if html.unescape(action)!=url: continue
                choice=re.search(r'name="setrace" value="([^"]+)"',part)
                if choice: found[choice[1]]=self._security_fields(part)|{'onboarding':'race','setrace':choice[1]}
            return found,body
        try:
            for race,module in races.items():
                with self.subTest(race=race):
                    setup(); forms,body=form(); self.assertEqual(set(races),set(forms))
                    self.assertNotIn('href=\'newday.php?setrace=',body)
                    before=state(); self.assertIn(self._security_client(None)(url,forms[race])[0],[302,303,403]); self.assertEqual(before,state())
                    for patch in [{'csrf_token':None},{'csrf_token':'0'*64}]:
                        data=forms[race]|patch
                        if data['csrf_token'] is None: del data['csrf_token']
                        self.assertEqual(403,adversarial(url,data)[0]); self.assertEqual(before,state())
                    forms,_=form()
                    self.assertEqual(200,call(url,forms[race])[0]); after=state()
                    self.assertEqual(race,after['race']); self.assertEqual(village,after['location'])
                    self.assertEqual({k:v for k,v in before.items() if k not in ['race','location']},{k:v for k,v in after.items() if k not in ['race','location']})
                    self.assertEqual(409,adversarial(url,forms[race])[0]); self.assertEqual(after,state())
                    alternative=next(v for k,v in forms.items() if k!=race)
                    self.assertEqual(409,adversarial(url,alternative)[0]); self.assertEqual(after,state())
                    # A separate authenticated request reloads the committed identity/location.
                    call=self._security_client(); adversarial('inn.php'); self.assertEqual(after,state())
                    setup(); forms,_=form(); self.query('UPDATE modules SET active=0 WHERE modulename=?',[module]); before=state()
                    self.assertEqual(409,call(url,forms[race])[0]); self.assertEqual(before,state())
                    offered,_=form(); self.assertNotIn(race,offered)
                    self.query('UPDATE modules SET active=1 WHERE modulename=?',[module])
                    setup(); forms,_=form(); before=state()
                    self.assertEqual(403,adversarial('newday.php?setrace='+race)[0]); self.assertEqual(before,state())
                    # One atomic account UPDATE persists race and location. A database CHECK
                    # at that write proves failed selections leave both fields unchanged.
                    forms,_=form()
                    self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_race_failure CHECK (login <> 'WebPlayer' OR race='Horrible Gelatinous Blob')")
                    try: self.assertEqual(500,call(url,forms[race])[0])
                    finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_race_failure')
                    self.assertEqual(before,state()); self.assertEqual(409,adversarial(url,forms[race])[0]); self.assertEqual(before,state())
                    forms,_=form(); self.assertEqual(200,call(url,forms[race])[0]); self.assertEqual(race,state()['race'])
            for patch in [{'setrace':'Unknown'},{'setrace':'../modules/racehuman.php'},{'setrace':'racehuman'},{'setrace':''},
                          {'setrace[]':'Human'},{'module':'racehuman'},{'module':'../common.php'},{'location':'Forged'},
                          {'attack':'999'},{'defense':'999'},{'name':'Human'},{'onboarding':'specialty'}]:
                setup(); forms,_=form(); before=state()
                self.assertEqual(400,call(url,forms['Human']|patch)[0]); self.assertEqual(before,state())
            setup(); forms,_=form(); missing=forms['Human'].copy(); del missing['setrace']; before=state()
            self.assertEqual(400,call(url,missing)[0]); self.assertEqual(before,state())
            for key,value in [('race','Elf'),('specialinc','module:goldmine'),('dragonkills',1),('specialty','MP'),('age',999)]:
                setup(); forms,_=form(); self.query('UPDATE accounts SET '+key+'=? WHERE acctid=?',[value,player]); before=state()
                self.assertEqual(409,call(url,forms['Human'])[0]); self.assertEqual(before,state())
            # No implicit GET fallback is allowed when every bundled race is inactive.
            setup()
            for module in races.values(): self.query('UPDATE modules SET active=0 WHERE modulename=?',[module])
            before=state(); offered,body=form(); self.assertEqual({},offered); self.assertIn('No active bundled races',body); self.assertEqual(before,state())
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            for row in modules: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])

    def test_lovers_alternative_choices_daily_authority_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        settings=self.query('SELECT * FROM settings WHERE setting IN (?,?,?)',['bard','barmaid','innname'])
        modules=self.query('SELECT modulename,active FROM modules')
        self.query('UPDATE modules SET active=1')
        call=self._security_client(); url='runmodule.php?module=lovers&op=flirt'
        def pref(value):
            self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['lovers','seenlover',player,str(value)])
        def setup(sex=0,charm=1,married=0,turns=10):
            self.query('UPDATE accounts SET sex=?,charm=?,marriedto=?,turns=?,alive=1,hitpoints=100,maxhitpoints=100,specialinc=?,bufflist=?,race=?,specialty=? WHERE acctid=?',[sex,charm,married,turns,'','a:0:{}','Human','DA',player]); pref(0)
        def state():
            return [self.query('SELECT gold,gems,turns,charm,marriedto,hitpoints,bufflist FROM accounts WHERE acctid=?',[player])[0],
                self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['lovers','seenlover',player]),
                self.query('SELECT newsid FROM news WHERE accountid=? ORDER BY newsid',[player]),
                self.query('SELECT id FROM debuglog WHERE actor=? ORDER BY id',[player])]
        def adversarial(path,data=None):
            # Permit transport through the legacy nav filter only for negative tests, so
            # the new business boundary must itself reject a forged or replayed action.
            self._security_allow(player,path); return call(path,data)
        def forms():
            # Enter via the real Inn link and submit the real rendered form. Neither
            # Lovers entry nor its valid POST receives fixture navigation authorization.
            self._security_allow(player,'inn.php')
            status,body=call('inn.php'); self.assertEqual(200,status)
            self.assertIn('runmodule.php?module=lovers',html.unescape(body))
            entry=next(html.unescape(link) for link in re.findall(r'href=[\'\"]([^\'\"]+)',body) if html.unescape(link).startswith(url))
            before=state(); status,body=call(entry); self.assertEqual(200,status,body[:1500]); self.assertEqual(before,state())
            found=[]
            for action,part in re.findall(r'<form\b[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S):
                if html.unescape(action)!=url: continue
                data=self._security_fields(part)
                for key,value in re.findall(r'name="(action|flirt)" value="([^"]+)"',part): data[key]=value
                found.append(data)
            self.assertEqual(1 if int(self.query('SELECT marriedto FROM accounts WHERE acctid=?',[player])[0]['marriedto'])==4294967295 else 7,len(found))
            return found,body
        try:
            with self._seeded_module_actions() as seed:
                # Each choice is independently available on both NPC paths. Low/high
                # random branches use charm=1 and fixed seeds, never a stage counter.
                lows=[0,2,1,1,24,2]; highs=[1,5,14,0,19,5]
                for sex in [0,1]:
                    for choice in range(1,8):
                        for positive in [False,True]:
                            with self.subTest(sex=sex,choice=choice,positive=positive):
                                setup(sex,22 if choice==7 and positive else 1)
                                seed((highs if positive else lows)[choice-1] if choice<7 else 0)
                                available,body=forms(); data=available[choice-1]
                                self.assertEqual(str(choice),data['flirt'])
                                status,body=call(url,data); self.assertEqual(200,status,body[:2000])
                                self.assertIn('show in 5 minutes' if sex==1 and choice==6 and not positive else ('Violet' if sex==0 else 'Seth'),body)
                                saved=state(); account=saved[0]
                                expected_charm=22 if choice==7 and positive else (2 if positive and choice<7 else (0 if choice in [4,5,6] else 1))
                                self.assertEqual(expected_charm,int(account['charm']))
                                self.assertEqual(8 if choice==6 and positive else (0 if choice==7 and not positive else 10),int(account['turns']))
                                self.assertEqual(4294967295 if choice==7 and positive else 0,int(account['marriedto']))
                                self.assertEqual([{'value':'1'}],saved[1])
                                self.assertEqual(choice==7 and positive,'lover' in account['bufflist'])
                                self.assertIn(adversarial(url,data)[0],[409]); self.assertEqual(saved,state())
                                self.assertEqual(409,adversarial(url,available[0 if choice!=1 else 1])[0]); self.assertEqual(saved,state())
                                self.assertEqual(409,adversarial(url)[0]); self.assertEqual(saved,state())
                    # Married visit historically has no choice, and both +/- charm outcomes.
                    for rng,delta in [(0,-1),(1,1)]:
                        setup(sex,10,4294967295); seed(rng)
                        available,_=forms(); self.assertNotIn('flirt',available[0])
                        status,body=call(url,available[0]); self.assertEqual(200,status,body[:1500])
                        saved=state(); self.assertEqual(10+delta,int(saved[0]['charm']))
                        self.assertEqual(delta==1,'lover' in saved[0]['bufflist']); self.assertEqual([{'value':'1'}],saved[1])
                        self.assertEqual(409,adversarial(url,available[0])[0]); self.assertEqual(saved,state())
                    # Capped positive charm and exhaustion floors preserve old formulas.
                    for charm,turns,choice,expected_charm,expected_turns in [(30,10,1,30,10),(18,1,6,19,0),(18,0,6,19,0)]:
                        setup(sex,charm,turns=turns); seed(1); available,_=forms()
                        self.assertEqual(200,call(url,available[choice-1])[0]); saved=state()[0]
                        self.assertEqual((expected_charm,expected_turns),(int(saved['charm']),int(saved['turns'])))
                setup(); available,_=forms(); before=state()
                self.assertIn(self._security_client(None)(url,available[0])[0],[302,303,403]); self.assertEqual(before,state())
                for patch in [{'csrf_token':None},{'csrf_token':'0'*64}]:
                    data=available[0]|patch
                    if data['csrf_token'] is None: del data['csrf_token']
                    self.assertEqual(403,adversarial(url,data)[0]); self.assertEqual(before,state())
                for bad in [None,'0','-1','8','999999999999999999999','x','1.0']:
                    setup(); available,_=forms(); data=available[0].copy(); before=state()
                    if bad is None: del data['flirt']
                    else: data['flirt']=bad
                    self.assertEqual(400,call(url,data)[0]); self.assertEqual(before,state())
                for patch in [{'flirt[]':'1'},{'action':'visit'},{'marriedto':'4294967295'},{'sex':'1'},
                              {'effect':'charm'},{'charm':'999'},{'gold':'999'},{'gems':'999'},{'turns':'999'},
                              {'hitpoints':'999'},{'seenlover':'0'},{'partner':'forged'},{'op':'chat'}]:
                    setup(); available,_=forms(); before=state()
                    self.assertEqual(400,call(url,available[0]|patch)[0]); self.assertEqual(before,state())
                setup(); available,_=forms(); before=state()
                duplicated=list(available[0].items())+[('flirt','7')]
                self.assertEqual(400,call(url,duplicated)[0]); self.assertEqual(before,state())
                setup(married=4294967295); available,_=forms(); before=state()
                self.assertEqual(400,call(url,available[0]|{'flirt':'1'})[0]); self.assertEqual(before,state())
                for key,value in [('sex',1),('marriedto',4294967295),('charm',5),('turns',9),('alive',0),('hitpoints',0),('specialinc','module:goldmine')]:
                    setup(); available,_=forms(); self.query('UPDATE accounts SET '+key+'=? WHERE acctid=?',[value,player]); before=state()
                    self.assertEqual(409,call(url,available[0])[0]); self.assertEqual(before,state())
                for value in ['1','-1','garbage','2']:
                    setup(); available,_=forms(); pref(value); before=state()
                    self.assertEqual(409,call(url,available[0])[0]); self.assertEqual(before,state())
                setup(); available,_=forms(); self.query('UPDATE modules SET active=0 WHERE modulename=?',['lovers']); before=state()
                status,body=call(url,available[0]); self.assertIn('no longer active',body); self.assertEqual(before,state())
                self.query('UPDATE modules SET active=1 WHERE modulename=?',['lovers'])
                for married in [0,4294967295]:
                    setup(married=married); before=state()
                    self.assertEqual(400,adversarial(url+'&flirt=1')[0]); self.assertEqual(before,state())
                # Absence of the default row is not a GET write.
                setup(); self.query('DELETE FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['lovers','seenlover',player]); forms()
                self.assertEqual([],state()[1])
                # Real final-account CHECK failures roll back news/debug and the preference.
                for choice,charm in [(6,18),(7,1),(7,22)]:
                    setup(charm=charm); available,_=forms(); before=state()
                    self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_lovers_failure CHECK (login <> 'WebPlayer' OR (turns=10 AND marriedto=0))")
                    try: self.assertEqual(500,call(url,available[choice-1])[0])
                    finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_lovers_failure')
                    self.assertEqual(before,state())
                    self.assertEqual(409,adversarial(url,available[choice-1])[0]); self.assertEqual(before,state())
                    available,_=forms(); self.assertEqual(200,call(url,available[choice-1])[0])
                # Normal New Day resets availability; the previous day's form stays stale.
                setup(); seed(1); available,_=forms(); old=available[1]
                self.assertEqual(200,call(url,available[0])[0]); self.assertEqual(409,adversarial(url)[0])
                self.query('UPDATE accounts SET lasthit=? WHERE acctid=?',['2000-01-01 00:00:00',player])
                self.assertEqual(200,adversarial('newday.php?continue=1')[0]); self.assertEqual([{'value':'0'}],state()[1])
                self.assertEqual(409,adversarial(url,old)[0])
                available,_=forms(); self.assertEqual(200,call(url,available[0])[0]); persisted=state()
                call=self._security_client(); adversarial('inn.php'); self.assertEqual(persisted,state())
                # NPC-married New Day attrition and divorce are historical daily rules.
                for sex in [0,1]:
                    for charm,rng,after_day,married_after in [(10,1,10,4294967295),(1,0,0,0)]:
                        setup(sex,charm,4294967295); seed(rng); available,_=forms()
                        self.assertEqual(200,call(url,available[0])[0])
                        self.query('UPDATE accounts SET dragonkills=0,lasthit=? WHERE acctid=?',['2000-01-01 00:00:00',player])
                        self.assertEqual(200,adversarial('newday.php?continue=1')[0])
                        saved=state(); self.assertEqual(after_day,int(saved[0]['charm']))
                        self.assertEqual(married_after,int(saved[0]['marriedto'])); self.assertEqual([{'value':'0'}],saved[1])
                        available,_=forms(); self.assertEqual(200,call(url,available[0])[0])
                # Configured names keep apostrophes, slashes, UTF-8 and color, while markup escapes.
                for setting in ['bard','barmaid','innname']:
                    self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[setting,"`2O'Reilly \\ 雪 <img src=x>"])
                for sex in [0,1]:
                    setup(sex); available,body=forms(); self.assertNotIn('<img src=x>',body); self.assertIn('&lt;img',body); self.assertIn('雪',body)
                    self.assertEqual(200,call(url,available[0])[0])
                    for act in ['', 'armor' if sex==0 else 'fat', 'sports' if sex==0 else 'gossip']:
                        before=state(); status,body=adversarial('runmodule.php?module=lovers&op=chat&act='+act)
                        self.assertEqual(200,status); self.assertEqual(before,state()); self.assertNotIn('<img src=x>',body)
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            for key in ['bard','barmaid','innname']: self.query('DELETE FROM settings WHERE setting=?',[key])
            for row in settings: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])
            for row in modules: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])

    def test_goldmine_deterministic_http_authority(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        saved=self.query('SELECT * FROM module_settings WHERE modulename IN (?,?,?,?,?)',['goldmine','racehuman','raceelf','racedwarf','racetroll'])
        self.query('UPDATE modules SET active=1')
        self.query('INSERT INTO mounts(mountname,mountcategory,mountbuff) VALUES (?,?,?)',['Mine fixture','Fixture','a:0:{}'])
        mount=int(self.query('SELECT mountid FROM mounts WHERE mountname=?',['Mine fixture'])[0]['mountid'])
        call=self._security_client()
        def request(url,form=None): self._security_allow(player,url); return call(url,form)
        def setting(key,value,module='goldmine'):
            self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[module,key,str(value)])
        def pref(key,value):
            self.query('INSERT INTO module_objprefs(modulename,objtype,objid,setting,value) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['goldmine','mounts',mount,key,str(value)])
        def state(): return self.query('SELECT gold,gems,turns,alive,hitpoints,experience,hashorse,specialinc,bufflist FROM accounts WHERE acctid=?',[player])[0]
        def reset(horse=0,race='Human',turns=10):
            self.query('UPDATE accounts SET gold=1000,gems=20,turns=?,alive=1,hitpoints=100,maxhitpoints=100,experience=100,level=7,hashorse=?,race=?,specialty=?,specialinc=?,bufflist=? WHERE acctid=?',[turns,horse,race,'DA','module:goldmine','a:0:{}',player])
        url='forest.php?op=mine'
        def form():
            before=state(); status,body=request(url); self.assertEqual(200,status,body[:1000]); self.assertEqual(before,state())
            fields=self._security_fields(body)
            self.assertEqual(403,request(url,{})[0]); self.assertEqual(before,state())
            return fields
        def mine():
            fields=form(); status,body=request(url,{**fields,'gold':999999,'gems':99999,'outcome':18,'racesave':1,'hashorse':0,'percentgoldloss':0})
            self.assertEqual(200,status,body[:2000]); after=state()
            request(url,fields); self.assertEqual(after,state())
            return after,body
        try:
            with self._seeded_module_actions('header-forest') as seed:
                setting('alwaystether',0); setting('percentgoldloss',50); setting('percentgemloss',25)
                for race in ['human','elf','dwarf','troll']: setting('minedeathchance',90,'race'+race)
                # Every historical mining roll is deterministic, including both collapse rolls.
                seeds=[75,6,91,12,27,10,23,2,3,15,20,48,7,49,4,24,22,30,16,0]
                for roll,rng in enumerate(seeds,1):
                    reset(); seed(rng); after,body=mine()
                    gold,gems,turns=[int(after[k]) for k in ['gold','gems','turns']]
                    if roll<=5: self.assertEqual((1000,20,9),(gold,gems,turns))
                    elif roll<=10:
                        self.assertTrue(1035<=gold<=1140); self.assertEqual((20,9),(gems,turns))
                    elif roll<=15:
                        self.assertEqual((1000,9),(gold,turns)); self.assertTrue(21<=gems<=22)
                    elif roll<=18:
                        self.assertTrue(1070<=gold<=1280); self.assertTrue(21<=gems<=23); self.assertEqual(9,turns)
                    else: self.assertIn('massive cave in',body)
                    self.assertEqual('',after['specialinc'])
                # No mount death and exact configured percentage loss, including endpoints.
                for loss,expectedGold,expectedGems in [(0,1000,20),(25,750,15),(100,0,0)]:
                    setting('percentgoldloss',loss); setting('percentgemloss',loss); reset(); seed(0)
                    after,_=mine(); self.assertEqual([0,0,110,expectedGold,expectedGems],[int(after[k]) for k in ['alive','hitpoints','experience','gold','gems']])
                setting('percentgoldloss',50); setting('percentgemloss',25)
                # Real shipped rescue hooks, no archive race dependency.
                for race in ['Human','Elf','Dwarf','Troll']:
                    setting('minedeathchance',0,'race'+race.lower()); reset(race=race); seed(0)
                    after,_=mine(); self.assertEqual([1,100,0,1000,20],[int(after[k]) for k in ['alive','hitpoints','turns','gold','gems']])
                    setting('minedeathchance',90,'race'+race.lower())
                # Mount seed 47 collapses after automatic-tether and entrance rolls.
                for enter,auto,die,save,alive,kept,text in [
                    (0,0,100,100,0,True,'tether'),(100,100,100,100,0,True,'tether'),
                    (100,0,0,0,0,True,'managed to escape'),(100,0,100,0,0,False,'bones'),
                    (100,0,100,100,1,True,'drag you to safety')]:
                    setting('alwaystether',auto)
                    for key,value in [('entermine',enter),('dieinmine',die),('saveplayer',save)]: pref(key,value)
                    reset(mount); seed(47); after,body=mine()
                    self.assertEqual(alive,int(after['alive'])); self.assertEqual(mount if kept else 0,int(after['hashorse'])); self.assertIn(text,body)
                # Preferences preserve LoGD color text while HTML is escaped by output().
                pref('savemsg','`2SAFE <img src=x onerror=alert(1)>'); reset(mount); seed(47)
                after,body=mine(); self.assertIn('SAFE',body); self.assertNotIn('<img src=x onerror=',body)
                # Stale forms bind the mount record, preference values, settings and race chance.
                for change in [lambda:pref('saveplayer',0),lambda:setting('percentgoldloss',51),
                               lambda:setting('minedeathchance',89,'racehuman'),
                               lambda:self.query('UPDATE mounts SET mountname=? WHERE mountid=?',['Changed fixture',mount])]:
                    reset(mount); fields=form(); change(); before=state()
                    self.assertEqual(409,request(url,fields)[0]); self.assertEqual(before,state())
                for key,bad in [('entermine','101'),('dieinmine','-1'),('saveplayer','bad')]:
                    old=self.query('SELECT value FROM module_objprefs WHERE modulename=? AND objid=? AND setting=?',['goldmine',mount,key])[0]['value']
                    pref(key,bad); reset(mount); before=state(); self.assertEqual(409,request(url)[0]); self.assertEqual(before,state()); pref(key,old)
                setting('percentgoldloss','101'); reset(); before=state(); self.assertEqual(409,request(url)[0]); self.assertEqual(before,state()); setting('percentgoldloss',50)
                reset(254); before=state(); self.assertEqual(409,request(url)[0]); self.assertEqual(before,state())
                reset(); before=state()
                for bad in ['forest.php?op[]=mine','forest.php?op=forged']:
                    self.assertEqual(400,request(bad)[0]); self.assertEqual(before,state())
                fields=form(); self.query('UPDATE modules SET active=0 WHERE modulename=?',['goldmine']); before=state()
                request(url,fields); self.assertEqual(before,state()); self.query('UPDATE modules SET active=1 WHERE modulename=?',['goldmine'])
                self.assertIn(self._security_client(None)(url,fields)[0],[302,303,403]); self.assertEqual(before,state())
                # Final-account failure rolls back debug/news and currency together; fresh retry works.
                reset(); seed(0); fields=form(); before=state()
                counts=[self.query('SELECT COUNT(*) AS n FROM '+table)[0]['n'] for table in ['debuglog','news']]
                self.query('ALTER TABLE accounts ADD CONSTRAINT fixture_mine_failure CHECK (alive=1)')
                try: self.assertEqual(500,request(url,fields)[0])
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_mine_failure')
                self.assertEqual(before,state()); self.assertEqual(counts,[self.query('SELECT COUNT(*) AS n FROM '+table)[0]['n'] for table in ['debuglog','news']])
                self.assertEqual(409,request(url,fields)[0]); self.assertEqual(before,state())
                after,_=mine(); self.assertEqual(0,int(after['alive']))
                reset(turns=0); seed(0); after,_=mine(); self.assertEqual([1000,20,1],[int(after[k]) for k in ['gold','gems','alive']])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_objprefs WHERE modulename=? AND objid=?',['goldmine',mount]); self.query('DELETE FROM mounts WHERE mountid=?',[mount])
            for module in ['goldmine','racehuman','raceelf','racedwarf','racetroll']: self.query('DELETE FROM module_settings WHERE modulename=?',[module])
            for row in saved: setting(row['setting'],row['value'],row['modulename'])
            self.query('UPDATE modules SET active=0')

    def test_outhouse_configured_outcomes_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        saved=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['outhouse'])
        self.query('UPDATE modules SET active=1'); call=self._security_client()
        def request(url,form=None): self._security_allow(player,url); return call(url,form)
        def pref(key,value): self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['outhouse',key,player,str(value)])
        def setting(key,value): self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['outhouse',key,str(value)])
        def state(): return self.query('SELECT gold,gems,turns FROM accounts WHERE acctid=?',[player])[0]
        def action(op,expected=200):
            url='runmodule.php?module=outhouse&op='+op
            before=state(); status,body=request(url); self.assertEqual(200,status); self.assertEqual(before,state())
            form=self._security_fields(body); self.assertEqual(403,request(url,{})[0]); self.assertEqual(before,state())
            status,body=request(url,form); self.assertEqual(expected,status,body[:1500]); after=state()
            self.assertEqual(409,request(url,form)[0]); self.assertEqual(after,state())
            return after
        try:
            with self._seeded_module_actions() as seed:
                for key,value in {'cost':7,'giveback':3,'goldinhand':0,'takeback':20,'badmusthit':0}.items(): setting(key,value)
                # paid/free, rewards, no reward, penalty and lower bound. Seeds only choose historical outcomes.
                cases=[('pay','washpay',100,100,100,0,100,96,6,11),('pay','washpay',0,0,0,0,100,93,5,10),
                       ('pay','washpay',100,0,0,0,100,96,5,10),('free','washfree',100,0,0,0,100,103,5,10),
                       ('free','washfree',100,0,0,1,100,100,5,10),('free','nowash',0,0,0,0,3,0,5,10)]
                for visit,finish,good,gem,turn,rng,gold,expectedGold,expectedGems,expectedTurns in cases:
                    seed(rng); pref('usedouthouse',0); pref('stage',0)
                    self.query('UPDATE accounts SET gold=?,gems=5,turns=10,alive=1,specialinc=? WHERE acctid=?',[gold,'',player])
                    for key,value in {'goodmusthit':good,'givegempercent':gem,'giveturnchance':turn}.items(): setting(key,value)
                    action(visit); after=action(finish)
                    self.assertEqual([expectedGold,expectedGems,expectedTurns],[int(after[k]) for k in ['gold','gems','turns']])
                    before=state(); action(finish,409); action(visit,409); self.assertEqual(before,state())
                for key,bad in [('cost','-1'),('goodmusthit','101'),('givegempercent','bad'),('takeback','21')]:
                    old=self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['outhouse',key])[0]['value']
                    pref('usedouthouse',0); pref('stage',0); setting(key,bad); before=state()
                    action('free',409); self.assertEqual(before,state()); setting(key,old)
                pref('usedouthouse','invalid'); before=state(); action('free',409); self.assertEqual(before,state())
                pref('usedouthouse',1); pref('stage',2)
                self.query('UPDATE accounts SET lasthit=?,race=?,specialty=? WHERE acctid=?',['2000-01-01 00:00:00','Human','DA',player])
                self.assertEqual(200,request('newday.php?continue=1')[0])
                actual={r['setting']:r['value'] for r in self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=?',['outhouse',player])}
                self.assertEqual('0',actual['usedouthouse']); self.assertEqual('0',actual['stage'])
                action('free')
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            self.query('DELETE FROM module_settings WHERE modulename=?',['outhouse'])
            for row in saved: setting(row['setting'],row['value'])
            self.query('UPDATE modules SET active=0')

    def test_sethsong_all_configured_effects_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        saved=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['sethsong'])
        self.query('UPDATE modules SET active=1'); call=self._security_client(); url='runmodule.php?module=sethsong'
        def request(path,form=None): self._security_allow(player,path); return call(path,form)
        def pref(value): self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['sethsong','been',player,str(value)])
        def setting(key,value): self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['sethsong',key,str(value)])
        def state(): return self.query('SELECT gold,gems,turns,hitpoints,charm FROM accounts WHERE acctid=?',[player])[0]
        def listen():
            before=state(); status,body=request(url); self.assertEqual(200,status); self.assertEqual(before,state())
            form=self._security_fields(body)
            self.assertEqual(403,request(url,{})[0]); self.assertEqual(before,state())
            status,body=request(url,form); self.assertEqual(200,status,body[:1500]); after=state()
            self.assertEqual(409,request(url,form)[0]); self.assertEqual(after,state())
            return after
        try:
            with self._seeded_module_actions() as seed:
                config={'visits':1,'mingold':13,'maxgold':13,'mingems':2,'maxgems':2,'hpgain':20,'bhploss':10,'shploss':20,'goldloss':7}
                for key,value in config.items(): setting(key,value)
                seeds=[44,39,71,22,15,4,47,1,23,5,10,17,2,3,8,0,28,9,6]
                for outcome,rng in enumerate(seeds):
                    pref(0); seed(rng)
                    self.query('UPDATE accounts SET gold=100,gems=5,turns=10,hitpoints=50,maxhitpoints=100,charm=5,sex=0,alive=1,specialinc=? WHERE acctid=?',['',player])
                    expected={'gold':100,'gems':5,'turns':10,'hitpoints':50,'charm':5}
                    if outcome==0: expected['turns']=12
                    if outcome in [1,2,6,13,14,15]: expected['turns']=11
                    if outcome in [5,11]: expected['turns']=9
                    if outcome==3: expected['gold']=113
                    if outcome==4: expected['hitpoints']=120
                    if outcome==7: expected['hitpoints']=40
                    if outcome==8: expected['gold']=93
                    if outcome==9: expected['gems']=7
                    if outcome in [10,12]: expected['hitpoints']=100
                    if outcome==16: expected['hitpoints']=30
                    if outcome==18: expected['charm']=4
                    actual=listen(); self.assertEqual(expected,{k:int(v) for k,v in actual.items()},'song '+str(outcome))
                    self.assertEqual('1',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['sethsong','been',player])[0]['value'])
                    status,body=request(url); self.assertEqual(200,status); self.assertNotIn('name="action_token"',body); self.assertEqual(actual,state())
                # Female charm, HP floor, insufficient gold, zero gem reward and overfull HP.
                for rng,patch,configPatch,field,expected in [(0,{'sex':1},{},'charm',6),(1,{'hitpoints':1},{},'hitpoints',1),
                    (23,{'gold':6},{},'gold',6),(5,{}, {'mingems':0,'maxgems':0},'gems',5),
                    (15,{'hitpoints':150},{},'hitpoints',180)]:
                    pref(0); seed(rng)
                    self.query('UPDATE accounts SET gold=100,gems=5,turns=10,hitpoints=50,maxhitpoints=100,charm=5,sex=0 WHERE acctid=?',[player])
                    for key,value in config.items(): setting(key,value)
                    for key,value in configPatch.items(): setting(key,value)
                    self.query('UPDATE accounts SET '+','.join(k+'=?' for k in patch)+' WHERE acctid=?',list(patch.values())+[player]) if patch else None
                    self.assertEqual(expected,int(listen()[field]))
                for key,value in config.items(): setting(key,value)
                setting('visits',2); pref(0); seed(9)
                listen(); listen()
                before=state(); self.assertNotIn('name="action_token"',request(url)[1]); self.assertEqual(before,state())
                for key,bad in [('visits','-1'),('hpgain','101'),('mingold','14'),('goldloss','-1')]:
                    pref(0); _,body=request(url); form=self._security_fields(body); old=self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['sethsong',key])[0]['value']
                    setting(key,bad); before=state(); self.assertEqual(409,request(url,form)[0]); self.assertEqual(before,state()); setting(key,old)
                pref('garbage'); self.assertEqual(409,request(url)[0]); pref(2)
                self.query('UPDATE accounts SET lasthit=?,race=?,specialty=? WHERE acctid=?',['2000-01-01 00:00:00','Human','DA',player])
                self.assertEqual(200,request('newday.php?continue=1')[0])
                self.assertEqual('0',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['sethsong','been',player])[0]['value'])
                listen()
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            self.query('DELETE FROM module_settings WHERE modulename=?',['sethsong'])
            for row in saved: setting(row['setting'],row['value'])
            self.query('UPDATE modules SET active=0')

    def test_shared_typed_settings_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT superuser FROM accounts WHERE acctid=?',[player])[0]['superuser']
        core_saved=self.query('SELECT value FROM settings WHERE setting=?',['autofightfull'])
        modules=['cedrikspotions','darkhorse']
        saved={m:self.query('SELECT setting,value FROM module_settings WHERE modulename=?',[m]) for m in modules}
        self.query('UPDATE modules SET active=1')
        call=self._security_client()
        def request(url,fields=None):
            self._security_allow(player,url); return call(url,fields)
        def urls(module):
            base='configuration.php?op=modulesettings&module='+module
            return base,base+'&save=1'
        def values(module):
            return self.query('SELECT setting,value FROM module_settings WHERE modulename=? ORDER BY setting',[module])
        try:
            base,save=urls('cedrikspotions')
            self.assertIn(self._security_client(login=None)(base)[0],[302,303,403])
            for role in [0,2]:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[role,player]); call=self._security_client()
                self.assertEqual(403,request(base)[0]); self.assertEqual(403,request(save,{'transcost':'3'})[0])
            self.query('UPDATE accounts SET superuser=128 WHERE acctid=?',[player]); call=self._security_client()
            before=values('cedrikspotions')
            _,body=request(base); form=self._security_fields(body,save)
            for fields in [{},{**form,'csrf_token':'bad','transcost':'3'}]: self.assertEqual(403,request(save,fields)[0])
            self.assertEqual(403,request(save)[0]); self.assertEqual(before,values('cedrikspotions'))
            for invalid in [{'namespace':'core'},{'undeclared':'1'},{'transcost[]':'2'},{'transcost':'0'},{'transcost':'11'},{'transcost':'1e1'},{'transmuteturns':'2147483648'},{'survive':'true'},{'atkmod':'nan'},{'minrand':'9','maxrand':'2'},{'charmgain':'-1'}]:
                _,body=request(base); form=self._security_fields(body,save)
                self.assertEqual(400,request(save,{**form,**invalid})[0]); self.assertEqual(before,values('cedrikspotions'))
            for module in ['missing','../darkhorse','core']:
                self.assertIn(request(urls(module)[0])[0],[400,404])
            for patch in [{'transcost':'1','transmuteturns':'1','atkmod':'.1','defmod':'2','survive':'0'}, {'transcost':'10','transmuteturns':'20','atkmod':'2','defmod':'.1','survive':'1'}]:
                _,body=request(base); form={**self._security_fields(body,save),**patch}
                self.assertEqual(200,request(save,form)[0]); self.assertEqual(409,request(save,form)[0])
                actual={x['setting']:x['value'] for x in values('cedrikspotions')}
                for key,value in patch.items(): self.assertEqual(value,actual[key])
            # A second tab cannot overwrite an intervening editor change.
            _,body=request(base); stale={**self._security_fields(body,save),'transcost':'3'}
            self.query('UPDATE module_settings SET value=4 WHERE modulename=? AND setting=?',['cedrikspotions','transcost'])
            self.assertEqual(409,request(save,stale)[0])
            # Failure after the first setting and its audit but before the second setting rolls back the entire patch.
            before=values('cedrikspotions')
            audit_before=self.query('SELECT count(*) AS n FROM gamelog WHERE who=?',[player])
            player_before=self.query('SELECT gold,gems,bufflist FROM accounts WHERE acctid=?',[player])
            self.query("ALTER TABLE module_settings ADD CONSTRAINT fixture_editor_log CHECK (modulename <> 'cedrikspotions' OR setting <> 'transmuteturns' OR value <> '5')")
            try:
                _,body=request(base); form={**self._security_fields(body,save),'transcost':'5','transmuteturns':'5'}
                self.assertEqual(500,request(save,form)[0]); self.assertEqual(before,values('cedrikspotions'))
                self.assertEqual(audit_before,self.query('SELECT count(*) AS n FROM gamelog WHERE who=?',[player]))
                self.assertEqual(player_before,self.query('SELECT gold,gems,bufflist FROM accounts WHERE acctid=?',[player]))
                self.assertEqual(409,request(save,form)[0])
            finally: self.query('ALTER TABLE module_settings DROP CONSTRAINT fixture_editor_log')
            _,body=request(base); self.assertEqual(200,request(save,{**self._security_fields(body,save),'transcost':'5','transmuteturns':'5'})[0])
            # Consume the exact configuration written through HTTP, as an ordinary player.
            potion_before=self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player])[0]
            try:
                self.query("UPDATE accounts SET superuser=0,gems=100,race='Human',bufflist='a:0:{}' WHERE acctid=?",[player]); call=self._security_client()
                potion='runmodule.php?module=cedrikspotions&op=gems'; _,body=request(potion)
                purchase=html.unescape(re.search(r'<form action=[\'\"]([^\'\"]+)',body).group(1))
                self.assertEqual(200,request(purchase,dict(self._security_fields(body),wish='5',gemcount='10'))[0])
                actual=self.query('SELECT gems,bufflist FROM accounts WHERE acctid=?',[player])[0]
                self.assertEqual('95',actual['gems']); self.assertIn('s:6:"rounds";i:5;',actual['bufflist'])
            finally:
                self.query('UPDATE accounts SET gems=?,race=?,bufflist=?,superuser=128 WHERE acctid=?',[potion_before['gems'],potion_before['race'],potion_before['bufflist'],player]); call=self._security_client()
            base,save=urls('darkhorse'); text='Tavern " onfocus="alert(1) <script>é</script> \\ O\'Reilly'
            _,body=request(base); self.assertEqual(200,request(save,{**self._security_fields(body,save),'tavernname':text})[0])
            _,body=request(base); self.assertNotIn('<script>é</script>',body); self.assertIn('&lt;script&gt;é&lt;/script&gt;',body)
            self.assertEqual(text,{x['setting']:x['value'] for x in values('darkhorse')}['tavernname'])
            # Core uses the same declared namespace and enum contract.
            base='configuration.php'; save=base+'?op=save'
            _,body=request(base); self.assertEqual(400,request(save,{**self._security_fields(body,save),'autofightfull':'99'})[0])
            _,body=request(base); self.assertEqual(400,request(save,{**self._security_fields(body,save),'resurrection_install':'0'})[0])
            _,body=request(base); form=dict(self._security_fields(body,save),autofightfull='1')
            result=request(save,form); self.assertEqual(200,result[0],result[1][:1500]); self.assertEqual(409,request(save,form)[0])
            self.assertEqual('1',self.query('SELECT value FROM settings WHERE setting=?',['autofightfull'])[0]['value'])
        finally:
            self.query('DELETE FROM settings WHERE setting=?',['autofightfull'])
            if core_saved: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',['autofightfull',core_saved[0]['value']])
            for m,rows in saved.items():
                self.query('DELETE FROM module_settings WHERE modulename=?',[m])
                for row in rows: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',[m,row['setting'],row['value']])
            self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[original,player]); self.query('UPDATE modules SET active=0')

    def test_shared_mount_preferences_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT superuser FROM accounts WHERE acctid=?',[player])[0]['superuser']
        self.query('UPDATE modules SET active=1'); ids=[]
        call=self._security_client()
        def request(url,fields=None): self._security_allow(player,url); return call(url,fields)
        def urls(ident,module='darkhorse'):
            suffix=f'&subop=module&id={ident}&module={module}'
            return 'mounts.php?op=edit'+suffix,'mounts.php?op=save'+suffix
        def snapshot(): return self.query('SELECT * FROM module_objprefs WHERE objtype=? ORDER BY modulename,objid,setting',['mounts'])
        try:
            for name in ['Editor fixture one','Editor fixture two']:
                self.query('INSERT INTO mounts(mountname,mountcategory) VALUES (?,?)',[name,'Fixture'])
                ids.append(self.query('SELECT mountid FROM mounts WHERE mountname=?',[name])[0]['mountid'])
            base,save=urls(ids[0]); before=snapshot()
            self.assertIn(self._security_client(login=None)(base)[0],[302,303,403])
            for role in [0,128]:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[role,player]); call=self._security_client()
                self.assertEqual(403,request(base)[0]); self.assertEqual(403,request(save,{'findtavern':'1'})[0])
            self.query('UPDATE accounts SET superuser=2 WHERE acctid=?',[player]); call=self._security_client()
            _,body=request(base); form=self._security_fields(body,save)
            self.assertEqual(before,snapshot())
            for data in [None,{},dict(form,csrf_token='bad',findtavern='1')]: self.assertEqual(403,request(save,data)[0])
            for patch in [{'findtavern':'2'},{'findtavern':'2147483648'},{'findtavern[]':'1'},{'forged':'1'},{'objtype':'accounts'},{'objid':ids[1]},{'module':'goldmine'}]:
                _,body=request(base); self.assertEqual(400,request(save,{**self._security_fields(body,save),**patch})[0]); self.assertEqual(before,snapshot())
            _,body=request(base); form=dict(self._security_fields(body,save),findtavern='1')
            self.assertEqual(409,request(urls(ids[1])[1],form)[0]); self.assertEqual(409,request(urls(ids[0],'goldmine')[1],form)[0])
            for ident in ['0','-1','999999999','1e2',str(ids[0])+'%20OR%201=1']:
                self.assertEqual(400,request(urls(ident)[0])[0])
            self.assertEqual(400,request(base+'&objtype=accounts')[0])
            self.assertEqual(400,request(urls(ids[0],'cedrikspotions')[0])[0])
            _,body=request(base); form=dict(self._security_fields(body,save),findtavern='1')
            result=request(save,form); self.assertEqual(200,result[0],result[1]); self.assertEqual(409,request(save,form)[0])
            self.assertEqual('1',self.query('SELECT value FROM module_objprefs WHERE modulename=? AND objid=? AND setting=?',['darkhorse',ids[0],'findtavern'])[0]['value'])
            _,body=request(base); form=dict(self._security_fields(body,save),findtavern='0')
            self.query('UPDATE mounts SET mountname=? WHERE mountid=?',['Changed fixture',ids[0]])
            self.assertEqual(409,request(save,form)[0])
            # Configured range/text consumer and actual multi-write rollback.
            base,save=urls(ids[0],'goldmine'); before=snapshot()
            audit_before=self.query('SELECT count(*) AS n FROM gamelog WHERE who=?',[player])
            player_before=self.query('SELECT gold,gems,bufflist FROM accounts WHERE acctid=?',[player])
            _,body=request(base); form=dict(self._security_fields(body,save),entermine='100',dieinmine='0',tethermsg='<script>fixture</script>')
            self.query("ALTER TABLE module_objprefs ADD CONSTRAINT fixture_object_log CHECK (value <> '<script>fixture</script>')")
            try:
                self.assertEqual(500,request(save,form)[0]); self.assertEqual(before,snapshot()); self.assertEqual(409,request(save,form)[0])
                self.assertEqual(audit_before,self.query('SELECT count(*) AS n FROM gamelog WHERE who=?',[player]))
                self.assertEqual(player_before,self.query('SELECT gold,gems,bufflist FROM accounts WHERE acctid=?',[player]))
            finally: self.query('ALTER TABLE module_objprefs DROP CONSTRAINT fixture_object_log')
            _,body=request(base); form=dict(self._security_fields(body,save),entermine='100',dieinmine='0',tethermsg='<script>fixture</script>')
            self.assertEqual(200,request(save,form)[0]); _,body=request(base); self.assertIn('&lt;script&gt;fixture&lt;/script&gt;',body)
            _,body=request(base); form=dict(self._security_fields(body,save),entermine='101'); self.assertEqual(400,request(save,form)[0])
            _,body=request(base); form=dict(self._security_fields(body,save),entermine='0'); self.query('DELETE FROM mounts WHERE mountid=?',[ids[0]])
            self.assertEqual(400,request(save,form)[0])
        finally:
            for ident in ids:
                self.query('DELETE FROM module_objprefs WHERE objtype=? AND objid=?',['mounts',ident]); self.query('DELETE FROM mounts WHERE mountid=?',[ident])
            self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[original,player]); self.query('UPDATE modules SET active=0')

    def test_transmutation_persistence_and_failure_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        columns='gems,race,specialty,bufflist,maxhitpoints,hitpoints,attack,defense,turns,specialinc,badguy,dragonkills,dragonpoints,age,superuser'
        original=self.query(f'SELECT {columns} FROM accounts WHERE acctid=?',[player])[0]
        saved=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['cedrikspotions'])
        self.query('UPDATE modules SET active=1'); call=self._security_client()
        base='runmodule.php?module=cedrikspotions&op=gems'
        def request(url,form=None): self._security_allow(player,url); return call(url,form)
        def setting(key,value): self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['cedrikspotions',key,str(value)])
        def buffs():
            value=self.query('SELECT bufflist FROM accounts WHERE acctid=?',[player])[0]['bufflist']
            code="require 'vendor/autoload.php'; echo json_encode(\\Resurrection\\Security\\ScalarState::read(stream_get_contents(STDIN)),JSON_THROW_ON_ERROR);"
            result=subprocess.run([shutil.which('php'),'-r',code],input=value,text=True,capture_output=True,cwd=ROOT,check=True)
            return json.loads(result.stdout)
        def stored(value):
            code='echo serialize(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR));'
            data=subprocess.run([shutil.which('php'),'-r',code],input=json.dumps(value),text=True,capture_output=True,cwd=ROOT,check=True).stdout
            self.query('UPDATE accounts SET bufflist=? WHERE acctid=?',[data,player])
        def buy():
            status,body=request(base); self.assertEqual(200,status,body[:1500])
            url=html.unescape(re.search(r'<form action=[\'"]([^\'"]+)',body).group(1))
            form=dict(self._security_fields(body),wish='5',gemcount='10',rounds='999',atkmod='999',race='Elf')
            return url,form,request(url,form)
        try:
            for key,value in {'random':0,'transcost':2,'transmuteturns':2,'atkmod':'.5','defmod':'.75','survive':1}.items(): setting(key,value)
            self.query("UPDATE accounts SET gems=100,race='Human',specialty='DA',bufflist='a:0:{}',dragonkills=0,dragonpoints='a:0:{}',specialinc='' WHERE acctid=?",[player])
            url,form,result=buy(); self.assertEqual(200,result[0],result[1][:1500])
            self.assertEqual('98',self.query('SELECT gems FROM accounts WHERE acctid=?',[player])[0]['gems'])
            first=buffs()['transmute']; self.assertEqual(2,first['rounds']); self.assertEqual(.5,first['atkmod']); self.assertEqual(.75,first['defmod']); self.assertEqual(1,first['survivenewday'])
            self.assertEqual('Horrible Gelatinous Blob',self.query('SELECT race FROM accounts WHERE acctid=?',[player])[0]['race'])
            self.assertNotIn('racialbenefit',buffs()); self.assertEqual(409,request(url,form)[0]); self.assertEqual(first,buffs()['transmute'])
            # A fresh login hydrates the committed buff; navigation does not spend rounds.
            call=self._security_client(); self.assertEqual(200,request(base)[0]); self.assertEqual(first['rounds'],buffs()['transmute']['rounds'])
            # Repeated legitimate purchase adds duration, retaining the original effect/carry snapshot.
            setting('atkmod','2'); setting('survive',0)
            self.assertEqual(200,buy()[2][0]); self.assertEqual(4,buffs()['transmute']['rounds']); self.assertEqual(.5,buffs()['transmute']['atkmod']); self.assertEqual(1,buffs()['transmute']['survivenewday'])
            # Exercise the shipped race-selection and actual New Day route. This is persistence
            # evidence only, not certification of onboarding authority or New Day replay.
            status,body=request('newday.php'); self.assertEqual(200,status)
            raceform=self._security_fields(body)|{'onboarding':'race','setrace':'Human'}
            self.assertEqual(200,call('newday.php?continue=1',raceform)[0])
            self.assertEqual(200,request('newday.php?continue=1')[0]); self.assertEqual(4,buffs()['transmute']['rounds'])
            self.assertEqual(200,request('newday.php?continue=1')[0]); self.assertEqual(4,buffs()['transmute']['rounds'])
            # Failure on final account write must undo the preceding real potion debug log.
            before=self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player])
            logs=self.query('SELECT count(*) AS n FROM debuglog WHERE actor=?',[player])
            balance=int(before[0]['gems'])-2
            self.query(f"ALTER TABLE accounts ADD CONSTRAINT fixture_transmute CHECK (login <> 'WebPlayer' OR gems <> {balance})")
            try:
                url,form,result=buy(); self.assertEqual(500,result[0]); self.assertEqual(before,self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player])); self.assertEqual(logs,self.query('SELECT count(*) AS n FROM debuglog WHERE actor=?',[player])); self.assertEqual(409,request(url,form)[0])
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_transmute')
            self.assertEqual(200,buy()[2][0]); self.assertEqual(6,buffs()['transmute']['rounds'])
            # Actual Forest HTTP combat persists one spent sickness round per fight request.
            enemy=self.query('SELECT * FROM creatures ORDER BY creatureid LIMIT 1')[0]
            enemy.update(creaturehealth=1000000,creatureattack=1,creaturedefense=1,creaturelevel=1,playerstarthp=10000,diddamage=0)
            combat={'enemies':[enemy],'options':{'type':'forest'}}
            encoded=subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(combat),text=True,capture_output=True,cwd=ROOT,check=True).stdout
            self.query("UPDATE accounts SET badguy=?,hitpoints=10000,maxhitpoints=10000,attack=1,defense=100,specialinc='' WHERE acctid=?",[encoded,player])
            for remaining in range(5,-1,-1):
                status,body=self._ordinary_attack(request); self.assertEqual(200,status,body[:1500])
                if remaining: self.assertEqual(remaining,buffs()['transmute']['rounds'])
                else: self.assertNotIn('transmute',buffs())
            self.query("UPDATE accounts SET badguy='' WHERE acctid=?",[player])
            # A new potion without carry expires at the next real New Day.
            stored({}); self.assertEqual(200,buy()[2][0]); self.assertEqual(0,buffs()['transmute']['survivenewday'])
            status,body=request('newday.php'); self.assertEqual(200,status)
            raceform=self._security_fields(body)|{'onboarding':'race','setrace':'Human'}
            self.assertEqual(200,call('newday.php?continue=1',raceform)[0]); self.assertEqual(200,request('newday.php?continue=1')[0]); self.assertNotIn('transmute',buffs())
            for invalid in [None,{},dict(first,rounds=0),dict(first,rounds=-1),dict(first,rounds=2147483648),dict(first,atkmod='<attack>'),dict(first,defmod=[]),dict(first,forged=1)]:
                stored({'transmute':invalid}); before=self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player])
                self.assertEqual(400,request(base)[0]); self.assertEqual(before,self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player]))
            stored({}); self.assertEqual(200,request(base)[0])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_settings WHERE modulename=?',['cedrikspotions'])
            for row in saved: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',['cedrikspotions',row['setting'],row['value']])
            self.query('UPDATE modules SET active=0')


    def test_forest_specialty_post_authority_and_rollback(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        registry=self.query('SELECT modulename,active FROM modules')
        companion_setting=self.query("SELECT value FROM settings WHERE setting='enablecompanions'")
        self.query('UPDATE modules SET active=0')
        self.query("UPDATE modules SET active=1 WHERE modulename IN ('specialtydarkarts','specialtymysticpower','specialtythiefskills')")
        call=self._security_client(); url='forest.php?op=specialty'
        def request(path,data=None): self._security_allow(player,path); return call(path,data)
        def encode(value):
            return subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(value),text=True,capture_output=True,cwd=ROOT,check=True).stdout
        enemy=self.query('SELECT * FROM creatures ORDER BY creatureid LIMIT 1')[0]
        enemy.update(creaturehealth=1000000,creatureattack=1,creaturedefense=1,creaturelevel=10,playerstarthp=10000,diddamage=0)
        combat={'enemies':[enemy],'options':{'type':'forest'}}
        def prepare(spec='DA',uses='9',skill='15',state=None):
            self.query("UPDATE accounts SET level=10,alive=1,race='Human',specialty=?,dragonkills=0,dragonpoints='a:0:{}',badguy=?,companions='a:0:{}',bufflist='a:0:{}',hitpoints=10000,maxhitpoints=10000,attack=10,defense=10000,specialinc='' WHERE acctid=?",[spec,encode(combat if state is None else state),player])
            for module in ['specialtydarkarts','specialtymysticpower','specialtythiefskills']:
                for key,value in [('uses',uses),('skill',skill)]:
                    self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[module,key,player,value])
        def state():
            return self.query('SELECT specialty,gold,gems,experience,hitpoints,maxhitpoints,alive,turns,age,attack,defense,badguy,companions,bufflist FROM accounts WHERE acctid=?',[player])+self.query("SELECT modulename,setting,value FROM module_userprefs WHERE userid=? AND modulename IN ('specialtydarkarts','specialtymysticpower','specialtythiefskills') ORDER BY modulename,setting",[player])
        def form(level='1'):
            status,body=request(url); self.assertEqual(200,status,body[:1800]); return self._security_fields(body)|{'level':level}
        def decoded(encoded):
            return json.loads(subprocess.run([shutil.which('php'),'-r',"require 'src/Security/ScalarState.php'; echo json_encode(\\Resurrection\\Security\\ScalarState::read(stream_get_contents(STDIN)),JSON_THROW_ON_ERROR);"],input=encoded,text=True,capture_output=True,cwd=ROOT,check=True).stdout)
        def rejected(data,expected=409):
            before=state(); status,body=request(url,data); self.assertEqual(expected,status,body[:2000]); self.assertEqual(before,state())
        try:
            for spec,module in [('DA','specialtydarkarts'),('MP','specialtymysticpower'),('TS','specialtythiefskills')]:
                for level in ['1','2','3','5']:
                    with self.subTest(spec=spec,level=level):
                        prepare(spec); data=form(level); before=state()
                        self.assertEqual(200,request(url,data)[0])
                        after=state(); self.assertNotEqual(before[0]['badguy'],after[0]['badguy'])
                        effects={('DA','3'):{'badguydmgmod':.5},('DA','5'):{'badguyatkmod':0,'badguydefmod':0},
                            ('MP','1'):{'regen':'10','aura':True},('MP','2'):{'minioncount':1,'minbadguydamage':1,'maxbadguydamage':30,'areadamage':True},
                            ('MP','3'):{'lifetap':1},('MP','5'):{'damageshield':2},('TS','1'):{'badguyatkmod':.5},
                            ('TS','2'):{'atkmod':2},('TS','3'):{'badguyatkmod':0},('TS','5'):{'atkmod':3,'defmod':3}}
                        if (spec,level) in effects:
                            buff=decoded(after[0]['bufflist'])[spec.lower()+level]
                            self.assertEqual(4,buff['rounds'])
                            for key,value in effects[spec,level].items(): self.assertEqual(value,buff[key])
                        self.assertEqual(str(9-int(level)),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,module,'uses'])[0]['value'])
                        self.assertEqual(200,request('village.php')[0]); self.assertEqual(after,state())
                        rejected(data); rejected(data)
                        # A late account-write constraint rejects after the preference write.
                        prepare(spec); data=form(level); before=state()
                        self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_specialty_failure CHECK (login <> 'WebPlayer' OR badguy = '"+before[0]['badguy'].replace("'","''")+"')")
                        try: rejected(data,500)
                        finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_specialty_failure')
                        rejected(data); self.assertEqual(200,request(url,form(level))[0])
                # Real consumption followed by the shipped New Day restoration.
                prepare(spec); self.assertEqual(200,request(url,form('5'))[0])
                self.assertEqual('4',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,module,'uses'])[0]['value'])
                self.query("UPDATE accounts SET badguy='' WHERE acctid=?",[player])
                self.assertEqual(200,request('newday.php?continue=1')[0])
                bonus=self.query("SELECT value FROM settings WHERE setting='specialtybonus'")
                expected=5+int(bonus[0]['value'] if bonus else 1)
                self.assertEqual(str(expected),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,module,'uses'])[0]['value'])
                self.assertEqual(spec,self.query('SELECT specialty FROM accounts WHERE acctid=?',[player])[0]['specialty'])
                # Stored authority failures, including unchanged state on missing/invalid form.
                for uses in ['0','-1','broken','9999999999','a:0:{}']:
                    prepare(spec,uses=uses)
                    rejected({'level':'1'},409 if uses not in ['0'] else 403)
                prepare(spec,uses='4'); rejected(form('5'))
                prepare(spec); data=form('5')
                self.query("UPDATE module_userprefs SET value='4' WHERE userid=? AND modulename=? AND setting='uses'",[player,module]); rejected(data)
                prepare(spec); data=form()
                self.query("UPDATE module_userprefs SET value='0' WHERE userid=? AND modulename=? AND setting='skill'",[player,module]); rejected(data)
                prepare(spec); data=form()
                self.query('UPDATE modules SET active=0 WHERE modulename=?',[module]); rejected(data)
                self.query('UPDATE modules SET active=1 WHERE modulename=?',[module])
            prepare('DA')
            self.query("INSERT INTO settings(setting,value) VALUES ('enablecompanions','0') ON DUPLICATE KEY UPDATE value='0'")
            data=form(); self.assertEqual(200,request(url,data)[0])
            result=state(); buff=decoded(result[0]['bufflist'])['da1']
            self.assertEqual(4,buff['rounds']); self.assertEqual(4,buff['minioncount']); self.assertEqual(6,buff['maxbadguydamage'])
            self.assertEqual([],decoded(result[0]['companions'])); rejected(data)
            self.assertEqual('8',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])[0]['value'])
            self.query("DELETE FROM settings WHERE setting='enablecompanions'")
            for row in companion_setting: self.query("INSERT INTO settings(setting,value) VALUES ('enablecompanions',?)",[row['value']])
            for level in ['', '0','-1','2.0','six','4','9999999999']:
                prepare(); data=form(); data['level']=level; rejected(data,400)
            prepare(); data=form(); del data['level']; rejected(data,400)
            prepare(); data=form(); del data['level']; data['level[]']='1'; rejected(data,400)
            for field in ['damage','healing','companion','uses','skill','specialty','target','buff']:
                prepare(); data=form(); data[field]='999'; rejected(data,400)
            for token in ['csrf_token','action_token']:
                prepare(); data=form(); del data[token]; rejected(data,403 if token=='csrf_token' else 409)
                prepare(); data=form(); data[token]='bad'; rejected(data,403 if token=='csrf_token' else 409)
            for spec in ['', 'MP']:
                prepare(); data=form(); self.query('UPDATE accounts SET specialty=? WHERE acctid=?',[spec,player]); rejected(data)
            malformed=[{},[],{'enemies':[],'options':{'type':'forest'}}]
            for key,value in [('creaturehealth',0),('creaturehealth',-1),('creaturehealth','invalid'),('creaturehealth',2147483648),('creatureattack',[]),('creaturedefense',-1),('creatureid',0),('dead',True),('istarget',[]),('terminal',True)]:
                malformed.append({'enemies':[dict(enemy,**{key:value})],'options':{'type':'forest'}})
            for malformed_state in malformed:
                prepare(state=malformed_state); rejected({'level':'1'})
            for encoded in ['', 'broken','O:8:"stdClass":0:{}']:
                prepare(); self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encoded,player]); rejected({'level':'1'})
            prepare(); data=form(); self.query("UPDATE accounts SET badguy='' WHERE acctid=?",[player]); rejected(data)
            prepare(); data=form(); changed={'enemies':[dict(enemy,creaturehealth=999999)],'options':{'type':'forest'}}
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encode(changed),player]); rejected(data)
            prepare(); before=state(); self.assertEqual(200,request(url)[0]); self.assertEqual(before,state())
            for path in ['forest.php?op=fight&skill=DA&l=1','forest.php?op=fight&skill=MP&l=5','forest.php?op=fight&skill=TS&l=3']:
                self.assertEqual(400,request(path)[0]); self.assertEqual(before,state())
            anonymous=self._security_client(login=None); self.assertIn(anonymous(url,{'level':'1'})[0],[302,303,403]); self.assertEqual(before,state())
            for path in ['battle.php','battle.php?op=fight&skill=DA&l=1','battle.php/extra?op=fight&skill=DA&l=1']:
                for data in [None,{'level':'1','skill':'DA'}]:
                    self.assertEqual(404,request(path,data)[0]); self.assertEqual(before,state())
                    self.assertEqual(404,anonymous(path,data)[0]); self.assertEqual(before,state())
        finally:
            self.query("DELETE FROM settings WHERE setting='enablecompanions'")
            for row in companion_setting: self.query("INSERT INTO settings(setting,value) VALUES ('enablecompanions',?)",[row['value']])
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            for row in registry: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])

    @contextmanager
    def _specialty_accounting_fixture(self, route='forest'):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        registry=self.query('SELECT * FROM modules')
        hooks=self.query('SELECT * FROM module_hooks WHERE location=?',['apply-specialties'])
        setting_values={'enablecompanions':'1','dropmingold':'0','forestgemchance':'25','instantexp':'0'}
        settings=self.query("SELECT * FROM settings WHERE setting IN ('enablecompanions','dropmingold','forestgemchance','instantexp')")
        modules={'DA':'specialtydarkarts','MP':'specialtymysticpower','TS':'specialtythiefskills'}
        self.query('UPDATE modules SET active=0')
        self.query("UPDATE modules SET active=1 WHERE modulename IN ('specialtydarkarts','specialtymysticpower','specialtythiefskills')")
        for key,value in setting_values.items():
            self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[key,value])
        call=self._security_client(); url=route+'.php?op=specialty'
        def request(path=url,data=None):
            self._security_allow(player,path)
            return call(path,data,fixture='specialty-accounting')
        def encode(value):
            return subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(value),text=True,capture_output=True,cwd=ROOT,check=True).stdout
        def decode(value):
            return json.loads(subprocess.run([shutil.which('php'),'-r',"require 'src/Security/ScalarState.php'; echo json_encode(\\Resurrection\\Security\\ScalarState::read(stream_get_contents(STDIN)),JSON_THROW_ON_ERROR);"],input=value,text=True,capture_output=True,cwd=ROOT,check=True).stdout)
        enemy=self.query('SELECT * FROM creatures ORDER BY creatureid LIMIT 1')[0]
        enemy.update(creaturename='Accounting Target',creaturehealth=100000,creatureattack=120,creaturedefense=80,creaturelevel=10,playerstarthp=500,diddamage=0)
        if route=='dragon':
            enemy={k:v for k,v in enemy.items() if k in ['creaturename','creatureweapon','creaturelevel','creatureattack','creaturedefense','creaturehealth','diddamage','playerstarthp']}
            enemy.update(type='dragon',creaturelevel=18)
        def prepare(spec,**changes):
            values=dict(level=10,alive=1,race='Human',specialty=spec,dragonkills=0,dragonpoints='a:0:{}',badguy=encode({'enemies':[enemy],'options':{'type':route,'didsurprise':1}}),companions='a:0:{}',bufflist='a:0:{}',hitpoints=500,maxhitpoints=1000,attack=100,defense=50,specialinc='',superuser=0)
            if route=='dragon': values['level']=15
            values.update(changes)
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in values)+' WHERE acctid=?',[*values.values(),player])
            for module in modules.values():
                for key,value in [('uses','9'),('skill','15')]:
                    self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[module,key,player,value])
        def snapshot():
            return self.query('SELECT specialty,gold,gems,experience,hitpoints,maxhitpoints,alive,turns,age,attack,defense,badguy,companions,bufflist FROM accounts WHERE acctid=?',[player])+self.query("SELECT modulename,setting,value FROM module_userprefs WHERE userid=? ORDER BY modulename,setting",[player])
        def form(level):
            status,body=request(); self.assertEqual(200,status,body[:2000])
            if route=='dragon':
                forms=[part for action,part in re.findall(r'<form[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S) if html.unescape(action)==url]
                self.assertTrue(forms,body[:2000]); body=forms[0]
            return self._security_fields(body)|{'level':str(level)}
        def rejected(data,expected=409):
            before=snapshot(); status,body=request(data=data)
            self.assertEqual(expected,status,body[:2000]); self.assertEqual(before,snapshot())
        try:
            yield dict(player=player,modules=modules,enemy=enemy,prepare=prepare,request=request,encode=encode,decode=decode,snapshot=snapshot,form=form,rejected=rejected)
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            for row in registry:
                self.query('REPLACE INTO modules ('+','.join(row)+') VALUES ('+','.join('?' for _ in row)+')',list(row.values()))
            self.query('DELETE FROM module_hooks WHERE location=?',['apply-specialties'])
            for row in hooks:
                self.query('INSERT INTO module_hooks ('+','.join('`'+k+'`' for k in row)+') VALUES ('+','.join('?' for _ in row)+')',list(row.values()))
            self.query("DELETE FROM settings WHERE setting IN ('enablecompanions','dropmingold','forestgemchance','instantexp')")
            for row in settings: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])

    def _ordinary_attack(self, request):
        url='forest.php?op=fight'
        status,body=request(url); self.assertEqual(200,status,body[:2000])
        return request(url,self._security_fields(body,url))

    @contextmanager
    def _graveyard_fixture(self):
        with self._specialty_accounting_fixture() as f:
            settings=self.query("SELECT * FROM settings WHERE setting IN ('gravechance','autofight','autofightfull')")
            creatures=self.query('SELECT * FROM creatures WHERE graveyard=1')
            self.query('UPDATE creatures SET graveyard=0 WHERE graveyard=1')
            enemy=dict(f['enemy']);enemy.pop('creatureid',None)
            for key in ['playerstarthp','diddamage']: enemy.pop(key,None)
            enemy.update(creaturename='Torment Fixture',graveyard=1)
            self.query('INSERT INTO creatures ('+','.join(enemy)+') VALUES ('+','.join('?' for _ in enemy)+')',list(enemy.values()))
            enemyid=self.query("SELECT creatureid FROM creatures WHERE creaturename='Torment Fixture'")[0]['creatureid']
            for key in ['gravechance','autofight','autofightfull']:
                self.query('INSERT INTO settings(setting,value) VALUES (?,0) ON DUPLICATE KEY UPDATE value=0',[key])
            call=self._security_client()
            def request(path='graveyard.php?op=fight',data=None,seed=None):
                self._security_allow(f['player'],path); return call(path,data,fixture=seed or ('graveyard-round' if path=='graveyard.php?op=fight' else 'specialty-accounting'))
            def patch(**values): self.query('UPDATE accounts SET '+','.join(k+'=?' for k in values)+' WHERE acctid=?',[*values.values(),f['player']])
            def prepare(**changes):
                f['prepare']('',alive=0,hitpoints=0,badguy='',soulpoints=100,gravefights=5,deathpower=100)
                patch(**changes) if changes else None
            def snapshot():
                return self.query('SELECT alive,hitpoints,soulpoints,gravefights,deathpower,attack,defense,level,gold,gems,experience,badguy,companions,bufflist,specialinc,specialmisc FROM accounts WHERE acctid=?',[f['player']])[0]
            def form(op='fight'):
                url='graveyard.php?op='+op;code,body=request(url);self.assertEqual(200,code,body[:3000]);return self._security_fields(body,url)
            def reject(data,op='fight',status=409):
                before=snapshot();news=self.query('SELECT * FROM news'); code,body=request('graveyard.php?op='+op,data)
                self.assertEqual(status,code,body[:3000]);self.assertEqual(before,snapshot());self.assertEqual(news,self.query('SELECT * FROM news'))
                self.assertNotIn('Stack trace',body);self.assertNotIn('SQLSTATE',body)
            def enter():
                data=form('search'); code,body=request('graveyard.php?op=search',data);self.assertEqual(200,code,body[:3000]);return data,body
            try: yield f|dict(request=request,patch=patch,gprepare=prepare,gsnapshot=snapshot,gform=form,greject=reject,enter=enter,enemyid=enemyid)
            finally:
                self.query('DELETE FROM creatures WHERE creatureid=?',[enemyid])
                for row in creatures: self.query('UPDATE creatures SET graveyard=1 WHERE creatureid=?',[row['creatureid']])
                self.query("DELETE FROM settings WHERE setting IN ('gravechance','autofight','autofightfull')")
                for row in settings: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])

    def test_graveyard_search_round_authority_and_replay(self):
        with self._graveyard_fixture() as f:
            buff={'proof':dict(name='Preserve on GET',schema='fixture',rounds=5,atkmod=2)}
            f['gprepare'](bufflist=f['encode'](buff)); before=f['gsnapshot']()
            for op in ['','search','fight','run']:
                code,body=f['request']('graveyard.php'+('?op='+op if op else ''));self.assertEqual(200,code,body[:2000]);self.assertEqual(before,f['gsnapshot']())
            data=f['gform']('search'); f['greject'](data|dict(csrf_token='bad'),'search',403)
            for field in ['creatureid','creatureattack','creatureexp','victory','alive','soulpoints','deathpower','type','auto','newtarget']:
                f['greject'](f['gform']('search')|{field:'999'},'search',400)
            data,body=f['enter']();after=f['gsnapshot']();state=f['decode'](after['badguy']);enemy=state['enemies'][0]
            self.assertEqual('4',after['gravefights']);self.assertEqual([],f['decode'](after['bufflist']));self.assertEqual('100',after['deathpower'])
            self.assertEqual(f['enemyid'],enemy['creatureid']);self.assertEqual(22,enemy['creatureattack']);self.assertEqual(22*.7,enemy['creaturedefense']);self.assertEqual(10,enemy['creaturelevel'])
            self.assertRegex(state['options']['encounter'],r'^[a-f0-9]{32}$');self.assertEqual('0',after['hitpoints']);self.assertEqual(before['attack'],after['attack']);self.assertEqual(before['defense'],after['defense'])
            f['greject'](data,'search');old=f['gform']();data=f['gform']();code,body=f['request'](data=data);self.assertEqual(200,code,body[:3000]);roundstate=f['gsnapshot']()
            self.assertEqual('4',roundstate['gravefights']);self.assertEqual('0',roundstate['hitpoints']);self.assertEqual(state['options']['encounter'],f['decode'](roundstate['badguy'])['options']['encounter'])
            self.assertEqual('95',roundstate['soulpoints']);self.assertEqual(94,f['decode'](roundstate['badguy'])['enemies'][0]['creaturehealth']);self.assertEqual(96,enemy['creaturehealth']);self.assertEqual(13,enemy['creatureexp'])
            f['greject'](old);f['greject'](data)

    def test_graveyard_search_rollback_and_eligibility(self):
        with self._graveyard_fixture() as f:
            for changes in [dict(alive=1,hitpoints=100),dict(alive=1,hitpoints=0),dict(alive=0,hitpoints=1),dict(specialinc='module:goldmine'),dict(badguy='broken')]:
                f['gprepare'](); data=f['gform']('search');f['patch'](**changes);f['greject'](data,'search')
            f['gprepare']();data=f['gform']('search');f['patch'](gravefights=0);f['greject'](data,'search');code,body=f['request']('graveyard.php?op=search');self.assertEqual(200,code);self.assertNotIn('action_token',body)
            f['gprepare']();data=f['gform']('search');self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_search CHECK (login <> 'WebPlayer' OR gravefights=5)")
            try: f['greject'](data,'search',500)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_search')
            f['greject'](data,'search');f['enter']();self.assertEqual('4',f['gsnapshot']()['gravefights'])

    def test_graveyard_victory_defeat_and_late_rollback(self):
        with self._graveyard_fixture() as f, self._specialty_terminal_capture() as terminal:
            for outcome,hp in [('victory',2),('victory',1),('defeat',1)]:
                f['gprepare']();f['enter']();row=f['gsnapshot']();state=f['decode'](row['badguy'])
                if outcome=='victory': state['enemies'][0]['creaturehealth']=hp;f['patch'](badguy=f['encode'](state))
                else: f['patch'](soulpoints=hp)
                before=f['gsnapshot']();news_before=len(self.query('SELECT * FROM news'));self.query('DELETE FROM fixture_specialty_terminal');data=f['gform']();old=f['gform']()
                check='deathpower=100' if outcome=='victory' else 'gravefights=4'
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_terminal CHECK (login <> 'WebPlayer' OR "+check+")")
                try: f['greject'](data,status=500);self.assertEqual([],terminal())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_terminal')
                f['greject'](data);data=f['gform']();code,body=f['request'](data=data);self.assertEqual(200,code,body[:3000]);after=f['gsnapshot']()
                self.assertEqual('',after['badguy']);self.assertEqual('0',after['alive']);self.assertEqual('0',after['hitpoints']);self.assertEqual(before['attack'],after['attack']);self.assertEqual(before['defense'],after['defense'])
                self.assertEqual(1,len(terminal()));self.assertEqual('battle-'+outcome,terminal()[0]['hook']);self.assertEqual(news_before+(outcome=='defeat'),len(self.query('SELECT * FROM news')))
                self.assertEqual(before['experience'],after['experience']);self.assertEqual(before['gold'],after['gold'])
                if outcome=='victory':
                    self.assertEqual(113,int(after['deathpower']));self.assertEqual('4',after['gravefights']);self.assertEqual(hp-2,terminal()[0]['enemy']['creaturehealth'])
                else: self.assertEqual('100',after['deathpower']);self.assertEqual('0',after['gravefights']);self.assertEqual('0',after['soulpoints'])
                f['greject'](data);f['greject'](old)

    def test_graveyard_malformed_state_and_explicit_repair(self):
        with self._graveyard_fixture() as f:
            f['gprepare']();f['enter']();good=f['gsnapshot']()['badguy'];base=f['decode'](good)
            cases=['broken','O:8:"stdClass":0:{}','b:0;','a:0:{}',good+'tail']
            for key,value in dict(creatureid=0,creaturename=[],creaturehealth=0,creatureattack=-1,creaturedefense=99,creaturelevel=11,creatureexp=999,dead=True,istarget=False,playerstarthp=-1,creaturegold=100).items():
                state=json.loads(json.dumps(base));state['enemies'][0][key]=value;cases.append(f['encode'](state))
            for key,value in dict(type='forest',encounter='bad',maxattacks=0).items():
                state=json.loads(json.dumps(base));state['options'][key]=value;cases.append(f['encode'](state))
            state=json.loads(json.dumps(base));state['enemies'].append(state['enemies'][0]);cases.append(f['encode'](state))
            for encoded in cases:
                data=f['gform']();f['patch'](badguy=encoded);f['greject'](data);before=f['gsnapshot']();self.assertEqual(409,f['request']()[0]);self.assertEqual(before,f['gsnapshot']());f['patch'](badguy=good)
            for field in ['companions','bufflist']:
                for encoded in ['b:0;','a:1:{s:3:"bad";a:0:{}}']:
                    data=f['gform']();f['patch'](**{field:encoded});f['greject'](data);f['patch'](**{field:'a:0:{}'})
            self.assertEqual(200,f['request'](data=f['gform']())[0])

    def test_graveyard_event_handoff_is_atomic_and_does_not_consume_fight(self):
        with self._graveyard_fixture() as f:
            module='resurrectiongravefixture';path=ROOT/'modules'/(module+'.php')
            path.write_text("<?php\nfunction resurrectiongravefixture_getmoduleinfo(){return ['name'=>'Grave fixture','version'=>'1','author'=>'Tests','category'=>'Tests'];}\nfunction resurrectiongravefixture_runevent($type,$link){throw new RuntimeException('Event must be deferred');}\n")
            self.query('INSERT INTO modules(modulename,active,version) VALUES (?,1,?)',[module,'1'])
            self.query('INSERT INTO module_event_hooks(event_type,modulename,event_chance) VALUES (?,?,?)',['graveyard',module,'100'])
            self.query("UPDATE settings SET value=100 WHERE setting='gravechance'")
            try:
                f['gprepare']();data=f['gform']('search');self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_event CHECK (login <> 'WebPlayer' OR specialinc='')")
                try: f['greject'](data,'search',500)
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_event')
                f['greject'](data,'search');data,body=f['enter']();row=f['gsnapshot']()
                self.assertEqual('module:'+module,row['specialinc']);self.assertEqual('5',row['gravefights']);self.assertEqual('',row['badguy']);self.assertEqual('100',row['soulpoints']);self.assertEqual('100',row['deathpower'])
                self.assertIn('graveyard.php',body);self.assertNotIn('forest.php',body);f['greject'](data,'search')
            finally:
                self.query('DELETE FROM module_event_hooks WHERE modulename=?',[module]);self.query('DELETE FROM modules WHERE modulename=?',[module]);path.unlink()

    def test_graveyard_companion_suspension_and_skeleton_exclusion(self):
        with self._graveyard_fixture() as f:
            skeleton=dict(name='`4Skeleton Warrior',hitpoints=43,maxhitpoints=43,attack=26.5,defense=14.5,dyingtext='`$Your skeleton warrior crumbles to dust.`n',abilities=dict(fight=True),ignorelimit=True)
            helper=dict(name='Shade Helper',hitpoints=100,maxhitpoints=100,attack=20,defense=10,abilities=dict(fight=True),allowinshades=True,dyingtext='Helper falls',schema='fixture')
            excluded=helper|dict(name='Excluded Helper',allowinshades=False)
            f['gprepare'](companions=f['encode'](dict(helper=helper,excluded=excluded,skeleton_warrior=skeleton)))
            data=f['gform']('search');self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_companion CHECK (login <> 'WebPlayer' OR gravefights=5)")
            try: f['greject'](data,'search',500)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_companion')
            _,body=f['enter']();row=f['gsnapshot']();companions=f['decode'](row['companions'])
            self.assertEqual(skeleton|dict(suspended=True),companions['skeleton_warrior']);self.assertEqual(excluded|dict(suspended=True),companions['excluded'])
            self.assertEqual(94,companions['helper']['hitpoints']);self.assertEqual(85,f['decode'](row['badguy'])['enemies'][0]['creaturehealth']);self.assertTrue(companions['helper']['used']);self.assertNotIn('Skeleton Warrior hits',body);self.assertNotIn('Excluded Helper hits',body)
            text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
            self.assertLess(text.index('RIPOSTE for 4'),text.index('Shade Helper hits Torment Fixture for 11'));self.assertLess(text.index('Shade Helper hits Torment Fixture for 11'),text.index('hits Shade Helper for 6'))
            # Suspension is retained at terminal combat because the player is still dead.
            state=f['decode'](row['badguy']);state['enemies'][0]['creaturehealth']=1;f['patch'](badguy=f['encode'](state));code,body=f['request'](data=f['gform']());self.assertEqual(200,code,body[:3000])
            self.assertEqual('',f['gsnapshot']()['badguy']);companions=f['decode'](f['gsnapshot']()['companions'])
            self.assertEqual(skeleton|dict(suspended=True),companions['skeleton_warrior']);self.assertTrue(companions['excluded']['suspended'])
            f['gprepare'](companions=f['encode'](dict(helper=helper|dict(hitpoints=1))));_,body=f['enter']();row=f['gsnapshot']();self.assertEqual([],f['decode'](row['companions']));self.assertEqual(85,f['decode'](row['badguy'])['enemies'][0]['creaturehealth']);self.assertIn('Helper falls',body)

    def test_graveyard_flee_success_failure_caps_and_rollback(self):
        with self._graveyard_fixture() as f:
            for favor,expected in [(100,87),(5,0),(0,0)]:
                f['gprepare'](deathpower=favor);f['enter']();before=f['gsnapshot']();data=f['gform']('run');old=f['gform']()
                # Seed 3 belongs only to the loopback fixture prepend, never request authority in production.
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_flee CHECK (login <> 'WebPlayer' OR badguy<>'')")
                try:
                    code,body=f['request']('graveyard.php?op=run',data,seed='ordinary-flee-failure');self.assertEqual(500,code,body[:3000]);self.assertEqual(before,f['gsnapshot']())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_flee')
                f['greject'](data,'run');data=f['gform']('run');code,body=f['request']('graveyard.php?op=run',data,seed='ordinary-flee-failure');self.assertEqual(200,code,body[:3000]);after=f['gsnapshot']()
                self.assertEqual(str(expected),after['deathpower']);self.assertEqual('',after['badguy']);self.assertEqual('4',after['gravefights']);self.assertEqual(before['soulpoints'],after['soulpoints']);self.assertEqual('0',after['hitpoints']);f['greject'](data,'run');f['greject'](old)
            f['gprepare']();f['enter']();data=f['gform']('run');self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_run CHECK (login <> 'WebPlayer' OR soulpoints=100)")
            try: f['greject'](data,'run',500)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_run')
            f['greject'](data,'run');data=f['gform']('run');old=f['gform']();code,body=f['request']('graveyard.php?op=run',data);self.assertEqual(200,code,body[:3000]);row=f['gsnapshot']()
            self.assertEqual('92',row['soulpoints']);self.assertEqual(96,f['decode'](row['badguy'])['enemies'][0]['creaturehealth']);self.assertEqual('100',row['deathpower']);self.assertEqual('4',row['gravefights']);self.assertIn('summoned back',body);f['greject'](data,'run');f['greject'](old)

    def test_graveyard_unsigned_favor_overflow_and_zero_soul(self):
        with self._graveyard_fixture() as f:
            f['gprepare'](deathpower=4294967295);f['enter']();state=f['decode'](f['gsnapshot']()['badguy']);state['enemies'][0]['creaturehealth']=1;f['patch'](badguy=f['encode'](state));f['greject'](f['gform']())
            f['patch'](deathpower=4294967282);code,body=f['request'](data=f['gform']());self.assertEqual(200,code,body[:3000]);self.assertEqual('4294967295',f['gsnapshot']()['deathpower'])
            f['gprepare'](soulpoints=0);data,body=f['enter']();row=f['gsnapshot']();self.assertEqual('0',row['gravefights']);self.assertEqual('0',row['soulpoints']);self.assertEqual('100',row['deathpower']);self.assertEqual('',row['badguy']);f['greject'](data,'search')

    def test_graveyard_header_buff_transaction_and_new_encounter_binding(self):
        with self._graveyard_fixture() as f:
            self.query("UPDATE modules SET active=1 WHERE modulename='drinks'")
            self.query("INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES ('drinks','drunkeness',?,'75') ON DUPLICATE KEY UPDATE value='75'",[f['player']])
            def drunk():return self.query("SELECT value FROM module_userprefs WHERE modulename='drinks' AND setting='drunkeness' AND userid=?",[f['player']])[0]['value']
            buff={'proof':dict(name='Temporary attack',schema='fixture',rounds=5,**{'tempstat-attack':20})}
            f['gprepare'](bufflist=f['encode'](buff));before=f['gsnapshot']()
            code,body=f['request']('graveyard.php');self.assertEqual(200,code,body[:3000]);self.assertEqual(before,f['gsnapshot']());self.assertEqual('75',drunk())
            data=f['gform']('search');self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_grave_header CHECK (login <> 'WebPlayer' OR gravefights=5)")
            try: f['greject'](data,'search',500);self.assertEqual('75',drunk())
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_grave_header')
            f['enter']();self.assertEqual('0',drunk());self.assertEqual('100',f['gsnapshot']()['attack']);self.assertEqual([],f['decode'](f['gsnapshot']()['bufflist']))
            old=f['gform']();combat=f['decode'](f['gsnapshot']()['badguy']);combat['options']['encounter']='b'*32;f['patch'](badguy=f['encode'](combat));f['greject'](old)
            f['patch'](gravefights=0);data=f['gform']();code,body=f['request'](data=data);self.assertEqual(200,code,body[:3000]);self.assertEqual('0',f['gsnapshot']()['gravefights'])

    @contextmanager
    def _training_fixture(self):
        with self._specialty_accounting_fixture() as f:
            masters=self.query('SELECT * FROM masters ORDER BY creatureid')
            keys=['automaster','multimaster','companionslevelup','displaymasternews','referminlevel','refereraward','autofight','autofightfull']
            saved={k:self.query('SELECT * FROM settings WHERE setting=?',[k]) for k in keys}
            def setting(k,v): self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[k,str(v)])
            for k,v in dict(automaster=0,multimaster=1,companionslevelup=1,displaymasternews=1,referminlevel=4,refereraward=25,autofight=1,autofightfull=1).items(): setting(k,v)
            def prepare(**changes):
                values=dict(level=10,experience=15143,hitpoints=500,maxhitpoints=100,attack=100,defense=50,
                    seenmaster=0,badguy='',specialty='',soulpoints=50,turns=20,gold=1000,gems=10,referer=0,refererawarded=0)
                values.update(changes); f['prepare']('TS',**values)
            def master(hp=100000,attack=120,defense=80):
                self.query('UPDATE masters SET creaturehealth=?,creatureattack=?,creaturedefense=? WHERE creaturelevel=10',[hp,attack,defense])
            def snapshot():
                row=self.query('SELECT * FROM accounts WHERE acctid=?',[f['player']])[0]
                ignore=['allowednavs','restorepage','laston','lastip','gentime','gentimecount','gensize','uniqueid','loggedin']
                return [{k:v for k,v in row.items() if k not in ignore}]+self.query('SELECT modulename,setting,value FROM module_userprefs WHERE userid=? ORDER BY modulename,setting',[f['player']])
            def form(op='fight',**fields):
                code,body=f['request']('train.php?op='+op); self.assertEqual(200,code,body[:3000])
                parts=[part for url,part in re.findall(r'<form[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S) if html.unescape(url)=='train.php?op='+op]
                self.assertTrue(parts,body[:2500]); return self._security_fields(parts[0])|fields
            def action(data,op='fight'): return f['request']('train.php?op='+op,data)
            def reject(data,op='fight',status=409):
                before=snapshot(); code,body=action(data,op); self.assertEqual(status,code,body[:3000]); self.assertEqual(before,snapshot())
            def enter(**changes):
                prepare(**changes); data=form('challenge'); code,body=action(data,'challenge'); self.assertEqual(200,code,body[:3000]); return data
            f.update(prepare_training=prepare,training_master=master,training_snapshot=snapshot,training_form=form,
                training_action=action,training_reject=reject,enter=enter,setting=setting,masters=masters)
            try: yield f
            finally:
                for row in masters: self.query('REPLACE INTO masters ('+','.join(row)+') VALUES ('+','.join('?' for _ in row)+')',list(row.values()))
                for k in keys:
                    self.query('DELETE FROM settings WHERE setting=?',[k])
                    for row in saved[k]: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[k,row['value']])

    def test_training_entry_authority_and_stale_round(self):
        with self._training_fixture() as f:
            f['training_master'](attack=1000); f['prepare_training'](hitpoints=5000)
            snap=f['training_snapshot']; form=f['training_form']; action=f['training_action']; reject=f['training_reject']
            before=snap()
            for path in ['train.php','train.php?op=question','train.php?op=challenge','train.php?op=autochallenge','train.php?op=fight','train.php?op=run']:
                self.assertEqual(200,f['request'](path)[0]); self.assertEqual(before,snap())
            for field in ['victory','master','level','experience','eligible','advancement','target','creaturehealth','attack','defense','reward','skill','l']:
                data=form('challenge'); reject(dict(data,**{field:'1'}),'challenge',400)
                self.assertEqual(400,f['request']('train.php?op=challenge&'+field+'=1')[0]); self.assertEqual(before,snap())
            data=form('challenge')
            reject({},'challenge',403); reject(dict(data,csrf_token='bad'),'challenge',403)
            reject({k:v for k,v in data.items() if k!='action_token'},'challenge',409)
            self.assertIn(self._security_client(None)('train.php?op=challenge',data)[0],[302,303,403])
            self.assertEqual(before,snap())
            stale=form('challenge'); code,body=action(data,'challenge'); self.assertEqual(200,code,body[:3000]); reject(data,'challenge'); reject(stale,'challenge')
            state=f['decode'](snap()[0]['badguy']); self.assertEqual('10',str(state['enemies'][0]['creatureid'])); self.assertEqual(10,state['options']['traininglevel']); self.assertEqual('1',snap()[0]['seenmaster'])
            before=snap(); stale=form(); data=form(); self.assertEqual(before,snap()); code,body=action(data); self.assertEqual(200,code,body[:3000])
            after=snap(); enemy=f['decode'](after[0]['badguy'])['enemies'][0]
            self.assertEqual(('5000',100000),(before[0]['hitpoints'],f['decode'](before[0]['badguy'])['enemies'][0]['creaturehealth'])); self.assertEqual(('4823',99960),(after[0]['hitpoints'],enemy['creaturehealth']))
            self.assertTrue(enemy['istarget']); reject(data); reject(stale)
            self.assertEqual(200,f['request']('train.php?op=fight')[0]); self.assertEqual(after,snap())

    def test_training_eligibility_shipped_masters_and_dragon_scale(self):
        with self._training_fixture() as f:
            prepare=f['prepare_training']; form=f['training_form']; action=f['training_action']; snap=f['training_snapshot']
            # Actual preserved master row, no requested identity or stat substitution.
            for level,exp,ident in [(1,100,1),(10,15143,10),(12,23840,12),(14,36071,14)]:
                prepare(level=level,experience=exp,maxhitpoints=level*10,hitpoints=1000,attack=1,defense=1000,race='Elf')
                code,body=action(form('challenge'),'challenge'); self.assertEqual(200,code,body[:3000])
                state=f['decode'](snap()[0]['badguy']); enemy=state['enemies'][0]
                self.assertEqual(str(ident),str(enemy['creatureid'])); self.assertEqual(str(level),str(enemy['creaturelevel']))
                row=next(x for x in f['masters'] if int(x['creatureid'])==ident)
                for key in ['creatureattack','creaturedefense']: self.assertEqual(float(row[key]),float(enemy[key]))
                self.assertEqual(int(row['creaturehealth']),enemy['trainingmaxhp'])
                if level==12: self.assertIn('another Elf',enemy['creaturelose'])
            prepare(experience=15142); code,body=action(form('challenge'),'challenge'); self.assertEqual(200,code,body[:2000]); after=snap()[0]
            self.assertEqual('',after['badguy']); self.assertEqual('10',after['level']); self.assertEqual('1',after['seenmaster']); self.assertEqual('15142',after['experience'])
            f['training_reject']({},'challenge',403)
            for patch in [dict(level=0),dict(level=15),dict(level=65535),dict(hitpoints=0,alive=0),dict(alive=0),dict(specialinc='event')]:
                prepare(**patch); before=snap(); code,_=f['request']('train.php?op=challenge'); self.assertIn(code,[409,200]); self.assertEqual(before,snap())
                self.assertIn(action({},'challenge')[0],[409,403]); self.assertEqual(before,snap())
            prepare(level=1,experience=100,dragonkills=4,maxhitpoints=10,hitpoints=10)
            self.assertEqual(200,action(form('challenge'),'challenge')[0]); self.assertEqual('',snap()[0]['badguy']) # DK requirement is 200.
            f['training_master'](); prepare(dragonkills=4,dragonpoints=f['encode'](['at','de','hp']),experience=16143,maxhitpoints=110)
            self.assertEqual(200,action(form('challenge'),'challenge')[0]); enemy=f['decode'](snap()[0]['badguy'])['enemies'][0]
            self.assertEqual(100005,enemy['trainingmaxhp']); self.assertEqual(120,enemy['creatureattack']); self.assertEqual(80,enemy['creaturedefense'])
            # Missing nearest master cannot create combat; fixture removal is explicitly restored.
            self.query('DELETE FROM masters'); prepare(); before=snap(); self.assertEqual(409,f['request']('train.php?op=challenge')[0]); self.assertEqual(before,snap())

    def test_training_victory_zero_negative_replay_and_next_level(self):
        with self._training_fixture() as f, self._specialty_terminal_capture() as terminal:
            f['training_master'](hp=10000,attack=0,defense=0)
            for hp in [10,9]:
                f['enter'](attack=0,defense=1000,specialty='TS')
                row=f['training_snapshot']()[0]; state=f['decode'](row['badguy']); state['enemies'][0]['creaturehealth']=hp
                buff={'proof':{'name':'Fixture','schema':'train','effectmsg':'','effectnodmgmsg':'','effectfailmsg':'','rounds':3,'allowintrain':1,'minioncount':1,'minbadguydamage':10,'maxbadguydamage':10}}
                self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[f['encode'](state),f['encode'](buff),f['player']])
                self.query('DELETE FROM fixture_specialty_terminal')
                before=f['training_snapshot'](); stale=f['training_form'](); data=f['training_form']()
                code,body=f['training_action'](data); self.assertEqual(200,code,body[:3000]); after=f['training_snapshot']()
                observed=terminal(); self.assertEqual(1,len(observed)); self.assertEqual(hp-10,observed[0]['enemy']['creaturehealth'])
                expected=before[0]|dict(level='11',maxhitpoints='110',soulpoints='55',attack='1',defense='1001',seenmaster='0',badguy='')
                # Overfull HP remains overfull; historical heal is a floor, never a cap.
                for key in ['level','maxhitpoints','soulpoints','attack','defense','seenmaster','badguy','hitpoints','experience','gold','gems','turns','location','dragonkills','dragonpoints','authversion','race','specialty','alive']:
                    self.assertEqual(expected[key],after[0][key],key)
                self.assertEqual('16',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='skill'",[f['player']])[0]['value'])
                f['training_reject'](data); f['training_reject'](stale); f['training_reject'](data,'challenge')
                for path in ['train.php?op=challenge&victory=1','train.php?op=challenge&master=10']:
                    self.assertEqual(400,f['request'](path)[0]); self.assertEqual(after,f['training_snapshot']())
                self.assertEqual(200,f['request']('train.php?op=question')[0]); self.assertEqual(after,f['training_snapshot']())
                # Experience was not spent, but is below level 11's 19121 requirement.
                self.assertEqual(200,f['training_action'](f['training_form']('challenge'),'challenge')[0]); self.assertEqual('11',f['training_snapshot']()[0]['level']); self.assertEqual('',f['training_snapshot']()[0]['badguy'])

    def test_training_settlement_rollback_and_defeat(self):
        with self._training_fixture() as f, self._specialty_terminal_capture() as terminal:
            for win in [True,False]:
                f['training_master'](hp=10000,attack=0 if win else 10000,defense=0)
                f['enter'](attack=0,defense=1000,hitpoints=1000)
                row=f['training_snapshot']()[0]; state=f['decode'](row['badguy']); state['enemies'][0]['creaturehealth']=10 if win else 10000
                buffs={'proof':{'name':'Fixture','schema':'train','effectmsg':'','effectnodmgmsg':'','effectfailmsg':'','rounds':3,'allowintrain':1,'minioncount':1,'minbadguydamage':10,'maxbadguydamage':10}} if win else {}
                if not win:
                    buffs={'retained':dict(name='Retained',schema='train',rounds=3,atkmod=2),'expires':dict(name='Expires',schema='train',rounds=3,atkmod=2,expireafterfight=1)}
                    skeleton=dict(name='`4Skeleton Warrior',hitpoints=20,maxhitpoints=43,attack=26.5,defense=14.5,dyingtext='`$Your skeleton warrior crumbles to dust.`n',abilities=dict(fight=True),ignorelimit=True)
                    self.query('UPDATE accounts SET companions=? WHERE acctid=?',[f['encode']({'skeleton_warrior':skeleton}),f['player']])
                self.query('UPDATE accounts SET badguy=?,bufflist=?,hitpoints=1,defense=0 WHERE acctid=?',[f['encode'](state),f['encode'](buffs),f['player']])
                self.query('DELETE FROM fixture_specialty_terminal')
                before=f['training_snapshot'](); news=self.query('SELECT count(*) AS n FROM news WHERE accountid=?',[f['player']]); data=f['training_form']()
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_training_failure CHECK (login <> 'WebPlayer' OR badguy <> '')")
                try:
                    f['training_reject'](data,status=500); self.assertEqual([],terminal()); self.assertEqual(news,self.query('SELECT count(*) AS n FROM news WHERE accountid=?',[f['player']]))
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_training_failure')
                f['training_reject'](data); code,body=f['training_action'](f['training_form']()); self.assertEqual(200,code,body[:3000]); after=f['training_snapshot']()[0]
                self.assertEqual('11' if win else '10',after['level']); self.assertEqual('110' if win else '100',after['hitpoints']); self.assertEqual('1',after['alive']); self.assertEqual('',after['badguy'])
                self.assertEqual('0' if win else '1',after['seenmaster']); self.assertEqual(1,len(terminal()))
                for key in ['experience','gold','gems','turns','location','dragonkills','authversion']:
                    self.assertEqual(before[0][key],after[key],key)
                if not win:
                    for key in ['maxhitpoints','attack','defense','soulpoints']: self.assertEqual(before[0][key],after[key])
                    buffs=f['decode'](after['bufflist']); self.assertNotIn('expires',buffs); self.assertEqual(3,buffs['retained']['rounds']); self.assertFalse(buffs['retained']['suspended'])
                    companion=f['decode'](after['companions'])['skeleton_warrior']; self.assertEqual(20,companion['hitpoints']); self.assertFalse(companion['suspended'])
                    self.assertNotIn('name="action_token"',f['request']('train.php?op=challenge')[1]); f['training_reject'](data,'challenge')

    def test_training_corrupt_state_preserved_and_repaired(self):
        with self._training_fixture() as f:
            f['training_master'](); f['enter'](); base=f['training_snapshot']()[0]; valid=f['decode'](base['badguy']); encode=f['encode']
            cases=['broken','O:8:"stdClass":0:{}',encode(False),encode([]),encode({'enemies':[],'options':{'type':'train'}})]
            for key,value in [('creatureid',999),('creatureid',[]),('creaturelevel',11),('creaturehealth',0),('creaturehealth',-1),('creaturehealth',99999999),('creatureattack',[]),('creatureattack',121),('creaturedefense',-1),('dead',True),('istarget',False),('killedplayer',True),('reward',999),('trainingmaxhp',1)]:
                state=json.loads(json.dumps(valid)); state['enemies'][0][key]=value; cases.append(encode(state))
            for options in [dict(type='forest'),dict(traininglevel=11),dict(encounter='bad'),dict(experience=[999])]:
                state=json.loads(json.dumps(valid)); state['options'].update(options); cases.append(encode(state))
            state=json.loads(json.dumps(valid)); state['enemies']*=2; cases.append(encode(state))
            for value in cases:
                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[value,f['player']]); before=f['training_snapshot']()
                for data in [None,{'csrf_token':'bad'}]:
                    code,body=f['request']('train.php?op=fight',data); self.assertEqual(409,code,body[:2500]); self.assertNotRegex(body,r'Warning:|Fatal error:|SQLSTATE'); self.assertEqual(before,f['training_snapshot']())
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[base['badguy'],f['player']])
            for column,value in [('bufflist',encode(False)),('bufflist','broken'),('bufflist','O:8:"stdClass":0:{}'),('companions','O:8:"stdClass":0:{}'),('bufflist',encode({'bad':{'rounds':1,'atkmod':[]}})),('companions',encode({'bad':{'hitpoints':-1}}))]:
                self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[value,f['player']]); before=f['training_snapshot'](); code,body=f['request']('train.php?op=fight'); self.assertIn(code,[400,409]); self.assertEqual(before,f['training_snapshot']()); self.assertNotRegex(body,r'Warning:|Fatal error:|SQLSTATE')
                self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[base[column],f['player']])
            self.assertEqual(200,f['training_action'](f['training_form']())[0])

    def test_training_buffs_companions_and_context_canonicalization(self):
        with self._training_fixture() as f:
            f['training_master'](); encode=f['encode']; decode=f['decode']; snap=f['training_snapshot']
            guard=dict(name='Training guard',hitpoints=1000,maxhitpoints=1000,attack=2,defense=3,abilities=dict(fight=True),
                attackperlevel=1,defenseperlevel=2,maxhitpointsperlevel=3,allowintrain=1)
            skeleton=dict(name='`4Skeleton Warrior',hitpoints=43,maxhitpoints=43,attack=26.5,defense=14.5,
                dyingtext='`$Your skeleton warrior crumbles to dust.`n',abilities=dict(fight=True),ignorelimit=True)
            buffs={'excluded':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=5,regen=10),'allowed':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=5,regen=3,allowintrain=1)}
            f['enter'](companions=encode(dict(skeleton_warrior=skeleton,guard=guard)),bufflist=encode(buffs))
            before=snap()[0]; data=f['training_form'](); state=decode(before['badguy'])
            # Reorder each record, preserving collection execution order, then use the existing intent.
            state['enemies'][0]=dict(reversed(list(state['enemies'][0].items())))
            buffstate=decode(before['bufflist']); companions=decode(before['companions'])
            buffstate={k:dict(reversed(list(v.items()))) for k,v in buffstate.items()}
            companions={k:dict(reversed(list(v.items()))) for k,v in companions.items()}
            self.query('UPDATE accounts SET badguy=?,bufflist=?,companions=? WHERE acctid=?',[encode(state),encode(buffstate),encode(companions),f['player']])
            code,body=f['training_action'](data); self.assertEqual(200,code,body[:3000]); after=snap()[0]
            actual=decode(after['companions']); self.assertEqual(43,actual['skeleton_warrior']['hitpoints']); self.assertTrue(actual['skeleton_warrior']['suspended'])
            self.assertNotIn('Skeleton Warrior hits',body); self.assertIn('Training guard',body)
            plain=html.unescape(re.sub(r'<[^>]*>','',body)); companion_move=re.search(r'Training guard (?:hits|tries to hit)',plain); player_move=re.search(r'You (?:hit|try to hit)',plain)
            master_move=re.search(r'Sensei Noetha (?:hits you|tries to hit you)',plain)
            self.assertIsNotNone(companion_move); self.assertIsNotNone(player_move); self.assertIsNotNone(master_move)
            self.assertLess(player_move.start(),master_move.start()); self.assertLess(master_move.start(),companion_move.start())
            actualbuffs=decode(after['bufflist']); self.assertEqual(5,actualbuffs['excluded']['rounds']); self.assertTrue(actualbuffs['excluded']['suspended']); self.assertLess(actualbuffs['allowed']['rounds'],buffstate['allowed']['rounds'])
            # Every material participant/state change invalidates a form without another round.
            for column,value in [('experience',15144),('hitpoints',400),('attack',99),('seenmaster',0),('level',11),('dragonkills',1),('location','Elsewhere')]:
                old=snap()[0][column]; data=f['training_form'](); self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[value,f['player']]); f['training_reject'](data)
                self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[old,f['player']])
            for column in ['bufflist','companions']:
                old=snap()[0][column]; data=f['training_form'](); changed=decode(old)
                if column=='bufflist': changed['allowed']['rounds']+=1
                else: changed['guard']['hitpoints']-=1
                self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[encode(changed),f['player']]); f['training_reject'](data)
                self.query('UPDATE accounts SET '+column+'=? WHERE acctid=?',[old,f['player']])
            # Winning phase excludes the skeleton, then historical advancement heals/grows surviving companions.
            state=decode(snap()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
            self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[encode(state),encode({'finish':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=2,allowintrain=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)}),f['player']])
            self.assertEqual(200,f['training_action'](f['training_form']())[0]); after=decode(snap()[0]['companions'])
            self.assertEqual(43,after['skeleton_warrior']['hitpoints']); self.assertFalse(after['skeleton_warrior']['suspended'])
            self.assertEqual(3,after['guard']['attack']); self.assertEqual(5,after['guard']['defense']); self.assertEqual(1003,after['guard']['maxhitpoints']); self.assertEqual(1003,after['guard']['hitpoints'])

    def test_training_autochallenge_maximum_and_multimaster(self):
        with self._training_fixture() as f:
            f['setting']('automaster',1); f['training_master'](attack=0); f['prepare_training'](experience=19121,hitpoints=50)
            before=f['training_snapshot'](); data=f['training_form']('autochallenge'); self.assertEqual(before,f['training_snapshot']()); f['training_reject'](data,'autochallenge')
            f['prepare_training'](experience=19122,hitpoints=50)
            before=f['training_snapshot'](); data=f['training_form']('autochallenge'); self.assertEqual(before,f['training_snapshot']())
            self.assertEqual(200,f['training_action'](data,'autochallenge')[0]); self.assertEqual('100',f['training_snapshot']()[0]['hitpoints']); f['training_reject'](data,'autochallenge')
            # Level 14 is last training master. Level 15 cannot recreate combat or bypass Dragon progression.
            for multi in [0,1]:
                f['setting']('automaster',0); f['setting']('multimaster',multi)
                f['enter'](level=14,experience=36071,maxhitpoints=140,hitpoints=1000,attack=0,defense=1000)
                state=f['decode'](f['training_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
                self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[f['encode'](state),f['encode']({'finish':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=2,allowintrain=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)}),f['player']])
                data=f['training_form'](); self.assertEqual(200,f['training_action'](data)[0]); after=f['training_snapshot']()
                self.assertEqual('15',after[0]['level']); self.assertEqual('150',after[0]['maxhitpoints']); self.assertEqual(str(1-multi),after[0]['seenmaster'])
                self.assertIn('memories',f['request']('train.php')[1]); self.assertEqual(after,f['training_snapshot']()); f['training_reject'](data); f['training_reject'](data,'challenge')
            # Multimaster permits a fresh earned next level when experience already satisfies it.
            f['setting']('multimaster',1); f['enter'](experience=19121,attack=0,defense=1000)
            state=f['decode'](f['training_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
            self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[f['encode'](state),f['encode']({'finish':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=2,allowintrain=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)}),f['player']])
            self.assertEqual(200,f['training_action'](f['training_form']())[0]); self.assertEqual('11',f['training_snapshot']()[0]['level'])
            self.assertEqual(200,f['training_action'](f['training_form']('challenge'),'challenge')[0])
            state=f['decode'](f['training_snapshot']()[0]['badguy']); self.assertEqual('11',str(state['enemies'][0]['creatureid']))

    def test_training_referral_and_specialty_rollback(self):
        with self._training_fixture() as f:
            ref=self.query("SELECT acctid,donation FROM accounts WHERE login='FixtureAdmin'")[0]
            f['training_master'](attack=0); f['enter'](attack=0,defense=1000,specialty='TS',referer=ref['acctid'])
            state=f['decode'](f['training_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
            self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[f['encode'](state),f['encode']({'finish':dict(name='Fixture',schema='train',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=2,allowintrain=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)}),f['player']])
            data=f['training_form'](); before=f['training_snapshot'](); mail=self.query('SELECT count(*) AS n FROM mail WHERE msgto=?',[ref['acctid']])
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_training_referral CHECK (login <> 'WebPlayer' OR level <> 11)")
            try:
                f['training_reject'](data,status=500); self.assertEqual(before,f['training_snapshot']())
                self.assertEqual(ref['donation'],self.query('SELECT donation FROM accounts WHERE acctid=?',[ref['acctid']])[0]['donation'])
                self.assertEqual(mail,self.query('SELECT count(*) AS n FROM mail WHERE msgto=?',[ref['acctid']]))
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_training_referral')
            try:
                self.assertEqual(200,f['training_action'](f['training_form']())[0]); f['training_reject'](data)
                self.assertEqual(int(ref['donation'])+25,int(self.query('SELECT donation FROM accounts WHERE acctid=?',[ref['acctid']])[0]['donation']))
                self.assertEqual('1',f['training_snapshot']()[0]['refererawarded']); self.assertEqual(int(mail[0]['n'])+1,int(self.query('SELECT count(*) AS n FROM mail WHERE msgto=?',[ref['acctid']])[0]['n']))
            finally: self.query('UPDATE accounts SET donation=? WHERE acctid=?',[ref['donation'],ref['acctid']])

    def test_training_entry_rollback_settings_and_autofight(self):
        with self._training_fixture() as f:
            f['training_master'](attack=0,defense=0); f['prepare_training'](attack=1,defense=1000)
            before=f['training_snapshot'](); data=f['training_form']('challenge')
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_training_entry CHECK (login <> 'WebPlayer' OR seenmaster <> 1)")
            try: f['training_reject'](data,'challenge',500)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_training_entry')
            self.assertEqual(before,f['training_snapshot']()); f['training_reject'](data,'challenge')
            self.assertEqual(200,f['training_action'](f['training_form']('challenge'),'challenge')[0])
            for rounds in ['five','ten','full']:
                data=f['training_form'](rounds=rounds)
                if rounds=='full':
                    # Fully supported until-end action uses a small remaining live target.
                    state=f['decode'](f['training_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
                    self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode'](state),f['player']]); data=f['training_form'](rounds=rounds)
                code,body=f['training_action'](data); self.assertEqual(200,code,body[:2500]); f['training_reject'](data)
            f['enter'](attack=1,defense=1000)
            data=f['training_form'](); f['setting']('multimaster',0); f['training_reject'](data); f['setting']('multimaster',1)
            data=f['training_form'](); self.query('UPDATE masters SET creatureattack=1 WHERE creaturelevel=10'); f['training_reject'](data); self.query('UPDATE masters SET creatureattack=0 WHERE creaturelevel=10')
            data=f['training_form'](); self.query("UPDATE module_userprefs SET value='16' WHERE userid=? AND modulename='specialtythiefskills' AND setting='skill'",[f['player']]); f['training_reject'](data)
            for rounds in ['one','999','0']:
                f['training_reject'](f['training_form'](rounds=rounds),status=400)
            f['setting']('autofight',0); f['training_reject'](f['training_form'](rounds='five'),status=400)

    def test_training_specialty_growth_and_bundled_racial_buffs(self):
        with self._training_fixture() as f:
            for spec,race,stat,expression in [('DA','Elf','defmod','(<defense>?(1+((1+floor(<level>/5))/<defense>)):0)'),
                ('MP','Troll','atkmod','(<attack>?(1+((1+floor(<level>/5))/<attack>)):0)'),
                ('TS','Elf','defmod','(<defense>?(1+((1+floor(<level>/5))/<defense>)):0)')]:
                f['training_master'](attack=0)
                racial=dict(name='Racial benefit',schema='module-race'+race.lower(),rounds=-1,allowintrain=1,allowinpvp=1,**{stat:expression})
                f['enter'](attack=100,defense=100,specialty=spec,race=race,bufflist=f['encode']({'racialbenefit':racial}))
                self.query("UPDATE module_userprefs SET value='17' WHERE userid=? AND modulename=? AND setting='skill'",[f['player'],f['modules'][spec]])
                state=f['decode'](f['training_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=1
                finish=dict(name='Finish',schema='train',rounds=2,allowintrain=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10,effectmsg='',effectnodmgmsg='',effectfailmsg='')
                self.query('UPDATE accounts SET badguy=?,bufflist=? WHERE acctid=?',[f['encode'](state),f['encode']({'racialbenefit':racial,'finish':finish}),f['player']])
                data=f['training_form'](); code,body=f['training_action'](data); self.assertEqual(200,code,body[:2500]); after=f['training_snapshot']()[0]
                self.assertEqual(('11','101','101'),(after['level'],after['attack'],after['defense']))
                prefs=self.query('SELECT setting,value FROM module_userprefs WHERE userid=? AND modulename=? ORDER BY setting',[f['player'],f['modules'][spec]])
                self.assertEqual([{'setting':'skill','value':'18'},{'setting':'uses','value':'10'}],prefs)
                buff=f['decode'](after['bufflist'])['racialbenefit']; self.assertEqual(expression,buff[stat]); self.assertEqual(-1,buff['rounds']); self.assertFalse(buff.get('suspended',False)); f['training_reject'](data)

    @contextmanager
    def _ordinary_forest_fixture(self):
        with self._specialty_accounting_fixture() as f:
            request=f['request']; url='forest.php?op=fight'
            def form(op='fight',**fields):
                status,body=request('forest.php?op='+op)
                self.assertEqual(200,status,body[:2000])
                parts=[part for url,part in re.findall(r'<form[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S) if html.unescape(url)=='forest.php?op='+op]
                self.assertTrue(parts,body[:2000])
                return self._security_fields(parts[0])|fields
            def action(data,op='fight'): return request('forest.php?op='+op,data)
            def reject(data,op='fight',status=409):
                before=f['snapshot'](); code,body=action(data,op)
                self.assertEqual(status,code,body[:2000]); self.assertEqual(before,f['snapshot']())
            f.update(ordinary_form=form,action=action,reject=reject)
            yield f

    def test_ordinary_forest_http_authority_and_context(self):
        with self._ordinary_forest_fixture() as f:
            f['prepare']('TS',specialty='',badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1000)],'options':{'type':'forest','didsurprise':1}}),gold=1000,gems=10,experience=1000,turns=20)
            before=f['snapshot']()
            for path in ['forest.php?op=fight','forest.php?op=run','forest.php?op=newtarget&newtarget=1','forest.php?op=fight&auto=full','forest.php']:
                status,body=f['request'](path); self.assertEqual(200,status,body[:2000]); self.assertEqual(before,f['snapshot']())
            form=f['ordinary_form']; action=f['action']; reject=f['reject']
            data=form(); reject({k:v for k,v in data.items() if k!='csrf_token'},status=403)
            reject(dict(data,csrf_token='0'*64),status=403)
            reject({k:v for k,v in data.items() if k!='action_token'})
            for key in ['target','newtarget','targetid','index','creaturehealth','creatureattack','creaturedefense','creaturelevel','creatureexp','creaturegold','gems','reward','multiplier','victory','dead','istarget','skill','l']:
                reject(dict(form(),**{key:'1'}),status=400)
            old=form(); data=form(); code,body=action(data); self.assertEqual(200,code,body[:2000])
            after=f['snapshot']()[0]; combat=f['decode'](after['badguy'])
            self.assertEqual('323',after['hitpoints']); self.assertEqual(99960,combat['enemies'][0]['creaturehealth'])
            self.assertTrue(combat['enemies'][0]['istarget']); self.assertEqual([],f['decode'](after['companions']))
            self.assertEqual(['1000','1000','10'],[after[k] for k in ['gold','experience','gems']])
            reject(data); reject(old)
            persisted=f['snapshot'](); form(); self.assertEqual(persisted,f['snapshot']())
            # Equivalent associative key ordering leaves the current intent valid.
            data=form(); reordered=dict(reversed(list(combat.items())))
            reordered['enemies']=[dict(reversed(list(combat['enemies'][0].items())))]
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode'](reordered),f['player']])
            code,body=action(data); self.assertEqual(200,code,body[:2000])
            # A restart with otherwise identical stats has a new server encounter identity.
            data=form(); combat=f['decode'](f['snapshot']()[0]['badguy']); combat['options']['encounter']='a'*32
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode'](combat),f['player']]); reject(data)
            anonymous=self._security_client(login=None)
            self.assertIn(anonymous('forest.php?op=fight')[0],[302,303,403])
            self.assertIn(anonymous('forest.php?op=fight',data)[0],[302,303,403])

    def test_ordinary_forest_progression_rewards_and_rollback(self):
        with self._ordinary_forest_fixture() as f, self._specialty_terminal_capture() as terminal:
            encode=f['encode']; decode=f['decode']; form=f['ordinary_form']; action=f['action']; reject=f['reject']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for instant in [0,1]:
                self.query("UPDATE settings SET value=? WHERE setting='instantexp'",[str(instant)])
                for hp in [40,39]:
                    a=dict(f['enemy'],creaturehealth=hp,creatureexp=100,creaturegold=40,istarget=True)
                    b=dict(f['enemy'],creatureid=2,creaturename='Second Target',creatureattack=1000,creaturehealth=100000,creatureexp=200,creaturegold=60,istarget=False)
                    f['prepare']('TS',specialty='',badguy=encode({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}),gold=1000,gems=10,experience=1000,turns=20)
                    stale=form(); first=form(); code,body=action(first); self.assertEqual(200,code,body[:2000])
                    after=f['snapshot']()[0]; combat=decode(after['badguy']); corpse=combat['enemies'][0]
                    self.assertEqual(hp-40,corpse['creaturehealth']); self.assertTrue(corpse['dead']); self.assertFalse(corpse['istarget'])
                    self.assertTrue(combat['enemies'][1]['istarget']); self.assertEqual(100000,combat['enemies'][1]['creaturehealth'])
                    self.assertEqual(['500',str(1000+50*instant),'1000','10'],[after[k] for k in ['hitpoints','experience','gold','gems']])
                    reject(first); reject(stale); reject(dict(form(),newtarget='0'),status=400)
                    fresh=form(); self.assertEqual(200,action(fresh)[0]); progressed=decode(f['snapshot']()[0]['badguy'])
                    self.assertEqual(corpse,progressed['enemies'][0]); self.assertEqual(99960,progressed['enemies'][1]['creaturehealth']); reject(fresh)
                    stale=form(); progressed['enemies'][1]['creaturehealth']=40
                    self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encode(progressed),f['player']]); reject(stale)
                    self.query('DELETE FROM fixture_specialty_terminal'); data=form(); before=f['snapshot']()
                    self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_ordinary_terminal CHECK (login <> 'WebPlayer' OR badguy <> '')")
                    try:
                        code,body=action(data); self.assertEqual(500,code,body[:2000]); self.assertEqual(before,f['snapshot']()); self.assertEqual([],terminal()); reject(data)
                    finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_ordinary_terminal')
                    final=form(); code,body=action(final); self.assertEqual(200,code,body[:2000]); after=f['snapshot']()[0]
                    self.assertEqual('',after['badguy']); self.assertEqual('1150',after['experience']); self.assertEqual('11',after['gems'])
                    self.assertEqual('1044',after['gold']); self.assertEqual(2,len(terminal())); self.assertEqual(['battle-victory']*2,[x['hook'] for x in terminal()])
                    self.assertEqual(['323','1'],[after[k] for k in ['hitpoints','alive']]); reject(final); reject(first)

    def test_ordinary_forest_defeat_and_rollback(self):
        with self._ordinary_forest_fixture() as f, self._specialty_terminal_capture() as terminal:
            enemy=dict(f['enemy'],creatureattack=1000,creaturegold=100,creatureexp=100)
            f['prepare']('TS',specialty='',hitpoints=177,badguy=f['encode']({'enemies':[enemy],'options':{'type':'forest','didsurprise':1}}),gold=1000,gems=10,experience=1000,turns=20)
            form=f['ordinary_form']; action=f['action']; reject=f['reject']; stale=form(); data=form(); before=f['snapshot']()
            news=self.query('SELECT * FROM news ORDER BY newsid')
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_ordinary_terminal CHECK (login <> 'WebPlayer' OR badguy <> '')")
            try:
                code,body=action(data); self.assertEqual(500,code,body[:2000]); self.assertEqual(before,f['snapshot']()); self.assertEqual(news,self.query('SELECT * FROM news ORDER BY newsid')); self.assertEqual([],terminal()); reject(data)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_ordinary_terminal')
            final=form(); code,body=action(final); self.assertEqual(200,code,body[:2000]); after=f['snapshot']()[0]
            self.assertEqual(['0','0','0','10','900',''],[after[k] for k in ['alive','hitpoints','gold','gems','experience','badguy']])
            self.assertEqual([],f['decode'](after['bufflist'])); self.assertEqual([],f['decode'](after['companions']))
            self.assertEqual('battle-defeat',terminal()[0]['hook']); self.assertEqual(99960,terminal()[0]['enemy']['creaturehealth'])
            self.assertEqual(len(news)+1,len(self.query('SELECT * FROM news'))); reject(final); reject(stale)

    def test_ordinary_forest_malformed_preserved_and_repair(self):
        with self._ordinary_forest_fixture() as f:
            import copy
            f['prepare']('TS',specialty=''); valid=f['snapshot']()[0]['badguy']; base=f['decode'](valid)
            cases=['','a:0:{}','broken','O:8:"stdClass":0:{}',f['encode'](True),f['encode']({'enemies':[],'options':{'type':'forest'}})]
            patches=[('creatureid',0),('creatureid',1.5),('creaturehealth',True),('creaturehealth',-1),('creatureattack',-1),('creaturedefense',[]),('creaturelevel','1e2'),('creatureexp',-1),('creaturegold',{}),('dead',True),('istarget',[]),('cannotbetarget',True),('unknown',1)]
            for key,value in patches:
                state=copy.deepcopy(base); state['enemies'][0][key]=value; cases.append(f['encode'](state))
            for options in [{'type':'pvp'},{'type':'forest','maxattacks':0},{'type':'forest','didsurprise':[]},{'type':'forest','experience':[99999]},{'type':'forest','unknown':True}]:
                state=copy.deepcopy(base); state['options']=options; cases.append(f['encode'](state))
            state=copy.deepcopy(base); state['enemies']=[dict(f['enemy'],istarget=True),dict(f['enemy'],istarget=True)]; cases.append(f['encode'](state))
            state=copy.deepcopy(base); state['enemies']=[f['enemy']]*101; cases.append(f['encode'](state))
            for bad in cases:
                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[bad,f['player']]); before=f['snapshot']()
                code,body=f['request']('forest.php?op=fight'); self.assertEqual(409,code,body[:2000]); self.assertEqual(before,f['snapshot']())
                code,body=f['action']({'csrf_token':'0'*64,'action_token':'0'*64}); self.assertEqual(409,code,body[:2000]); self.assertEqual(before,f['snapshot']())
            # Explicit operator repair restores the original valid encounter, never a silent reset.
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[valid,f['player']]); self.assertEqual(200,f['action'](f['ordinary_form']())[0])
            for field,value in [('bufflist','a:1:{s:1:"x";i:2;}'),('bufflist',f['encode']({'unknown':{'rounds':1,'atkmod':[]}})),('companions','a:1:{s:1:"x";i:2;}'),('companions',f['encode']({'unknown':{'hitpoints':1,'attack':[]}}))]:
                f['prepare']('TS',specialty='',**{field:value}); before=f['snapshot'](); code,body=f['request']('forest.php?op=fight')
                self.assertEqual(409,code,body[:2000]); self.assertEqual(before,f['snapshot']())

    def test_ordinary_forest_search_escape_and_event_handoff(self):
        with self._ordinary_forest_fixture() as f:
            saved=self.query("SELECT * FROM settings WHERE setting IN ('forestchance','autofight','autofightfull')")
            try:
                for key,value in [('forestchance','0'),('autofight','1'),('autofightfull','1')]:
                    self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[key,value])
                f['prepare']('TS',specialty='',badguy='',gold=1000,gems=10,experience=1000,turns=20)
                before=f['snapshot'](); data=f['ordinary_form']('search'); self.assertEqual(before,f['snapshot']())
                f['reject']({},'search',403)
                stale=f['ordinary_form']('search'); code,body=f['action'](data,'search'); self.assertEqual(200,code,body[:3000])
                after=f['snapshot']()[0]; state=f['decode'](after['badguy']); self.assertEqual('19',after['turns'])
                self.assertRegex(state['options']['encounter'],r'^[a-f0-9]{32}$'); f['reject'](data,'search'); f['reject'](stale,'search')
                # The form emitted by the mutating response must work immediately after DB hydration.
                data=self._security_fields(body,'forest.php?op=run'); code,body=f['action'](data,'run'); self.assertEqual(200,code,body[:3000]); f['reject'](data,'run')
                # Independently establish the deterministic successful escape.
                f['prepare']('TS',specialty='',gold=1000,gems=10,experience=1000,turns=20)
                data=f['ordinary_form']('run'); code,body=f['action'](data,'run'); self.assertEqual(200,code,body[:2000]); f['reject'](data,'run')
                after=f['snapshot']()[0]
                self.assertEqual('',after['badguy']); self.assertEqual(['500','1000','1000','10'],[after[k] for k in ['hitpoints','experience','gold','gems']])
                # Search failure is late enough to roll back turn, encounter and surprise HP.
                f['prepare']('TS',specialty='',badguy='',turns=20); data=f['ordinary_form']('search'); before=f['snapshot']()
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_ordinary_search CHECK (login <> 'WebPlayer' OR turns <> 19)")
                try:
                    code,body=f['action'](data,'search'); self.assertEqual(500,code,body[:2000]); self.assertEqual(before,f['snapshot']()); f['reject'](data,'search')
                finally:self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_ordinary_search')
                self.assertEqual(200,f['action'](f['ordinary_form']('search'),'search')[0])
                # Cave discovery used to mutate seendragon on GET. Its own scoped POST is replay-safe.
                f['prepare']('TS',specialty='',badguy='',level=15,seendragon=0)
                data=f['ordinary_form']('dragon'); self.assertEqual('0',self.query('SELECT seendragon FROM accounts WHERE acctid=?',[f['player']])[0]['seendragon'])
                self.assertEqual(200,f['action'](data,'dragon')[0]); self.assertEqual('1',self.query('SELECT seendragon FROM accounts WHERE acctid=?',[f['player']])[0]['seendragon']); f['reject'](data,'dragon')
                # One POST may select the historically configured five/ten/full action, never a GET.
                for rounds in ['five','ten','full']:
                    hp=45 if rounds=='full' else 100000
                    enemy=dict(f['enemy'],creaturehealth=hp,creaturegold=0,creatureexp=100)
                    f['prepare']('TS',specialty='',badguy=f['encode']({'enemies':[enemy],'options':{'type':'forest','didsurprise':1}}))
                    data=f['ordinary_form'](rounds=rounds); code,body=f['action'](data); self.assertEqual(200,code,body[:2000]); f['reject'](data)
                    if rounds=='full': self.assertEqual('',f['snapshot']()[0]['badguy'])
                    else: self.assertLess(f['decode'](f['snapshot']()[0]['badguy'])['enemies'][0]['creaturehealth'],99955)
                # Legacy target and automatic query parameters cannot authorize mutations.
                f['prepare']('TS',specialty='')
                for query in ['&newtarget=0','&auto=full','&type=thrill']:
                    data=f['ordinary_form'](); before=f['snapshot'](); code,body=f['request']('forest.php?op=fight'+query,data)
                    self.assertEqual(400,code,body[:2000]); self.assertEqual(before,f['snapshot']())
                # The failed-escape seed retains the target and executes enemy retaliation only.
                enemy=dict(f['enemy'],creatureattack=1000)
                f['prepare']('TS',specialty='',hitpoints=5000,maxhitpoints=10000,badguy=f['encode']({'enemies':[enemy],'options':{'type':'forest','didsurprise':1}}))
                call=self._security_client()
                def failure_request(data=None):
                    self._security_allow(f['player'],'forest.php?op=run')
                    return call('forest.php?op=run',data,fixture='ordinary-flee-failure')
                code,body=failure_request(); data=self._security_fields(body,'forest.php?op=run')
                code,body=failure_request(data); self.assertEqual(200,code,body[:2000]); after=f['snapshot']()[0]
                self.assertEqual(100000,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
                self.assertEqual('3936',after['hitpoints']); before=f['snapshot'](); self.assertEqual(409,failure_request(data)[0]); self.assertEqual(before,f['snapshot']())
            finally:
                self.query("DELETE FROM settings WHERE setting IN ('forestchance','autofight','autofightfull')")
                for row in saved:self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])

    def test_ordinary_forest_shield_simultaneous_terminal_and_companion(self):
        with self._ordinary_forest_fixture() as f, self._specialty_terminal_capture() as terminal:
            e,d=f['encode'],f['decode']; player=f['player']
            # Obtain the actual certified shield producer, then consume it through ordinary attack.
            f['prepare']('MP',hitpoints=5000,maxhitpoints=10000)
            self.assertEqual(200,f['request'](data=f['form'](5))[0]); shield=d(f['snapshot']()[0]['bufflist'])
            for targethp in [394,393,395]:
                enemy=dict(f['enemy'],creatureattack=1000,creaturehealth=targethp,creaturegold=0,creatureexp=100)
                f['prepare']('MP',hitpoints=177,bufflist=e(shield),badguy=e({'enemies':[enemy],'options':{'type':'forest','didsurprise':1}}),gold=1000,gems=10,experience=1000,turns=20)
                self.query('DELETE FROM fixture_specialty_terminal'); data=f['ordinary_form'](); code,body=f['action'](data); self.assertEqual(200,code,body[:2000])
                after=f['snapshot']()[0]; victory=targethp<=394
                self.assertEqual('',after['badguy']); self.assertEqual('battle-victory' if victory else 'battle-defeat',terminal()[0]['hook'])
                self.assertEqual(targethp-394,terminal()[0]['enemy']['creaturehealth']); self.assertEqual(0,terminal()[0]['hp'])
                self.assertEqual('1' if victory else '0',after['hitpoints']); self.assertEqual('1' if victory else '0',after['alive'])
                self.assertEqual(3,d(after['bufflist'])['mp5']['rounds']); f['reject'](data)
            # Real skeleton plus real regeneration: ordinary action uses existing validation and ordering.
            f['prepare']('DA',hitpoints=5000,maxhitpoints=10000,badguy=e({'enemies':[dict(f['enemy'],creatureattack=40,creaturedefense=1)],'options':{'type':'forest','didsurprise':1}}))
            self.assertEqual(200,f['request'](data=f['form'](1))[0]); skeleton=d(f['snapshot']()[0]['companions'])
            skeleton['skeleton_warrior']['hitpoints']=30
            weak=e({'enemies':[dict(f['enemy'],creatureattack=1,creaturedefense=1)],'options':{'type':'forest','didsurprise':1}})
            f['prepare']('MP',companions=e(skeleton),hitpoints=5000,maxhitpoints=10000,badguy=weak)
            self.assertEqual(200,f['request'](data=f['form'](1))[0]); buffs=d(f['snapshot']()[0]['bufflist']); skeleton=d(f['snapshot']()[0]['companions'])
            f['prepare']('MP',hitpoints=500,companions=e(skeleton),bufflist=e(buffs),badguy=weak); stale=f['ordinary_form'](); data=f['ordinary_form']()
            before=f['snapshot']()
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_ordinary_companion CHECK (login <> 'WebPlayer' OR hitpoints <> 510)")
            try:
                code,body=f['action'](data); self.assertEqual(500,code,body[:2000]); self.assertEqual(before,f['snapshot']()); f['reject'](data)
            finally:self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_ordinary_companion')
            data=f['ordinary_form']()
            code,body=f['action'](data); self.assertEqual(200,code,body[:2000]); after=f['snapshot']()[0]
            self.assertEqual(3,d(after['bufflist'])['mp1']['rounds']); self.assertEqual('510',after['hitpoints'])
            expected=dict(skeleton['skeleton_warrior'],hitpoints=36,used=True)
            self.assertEqual({'skeleton_warrior':expected},d(after['companions'])); f['reject'](data); f['reject'](stale)
            fresh=f['ordinary_form'](); skeleton=d(after['companions']); key=next(iter(skeleton)); skeleton[key]['hitpoints']-=1
            self.query('UPDATE accounts SET companions=? WHERE acctid=?',[e(skeleton),player]); f['reject'](fresh)

    def test_defeated_target_progression_exact_zero_negative_and_terminal(self):
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            player=f['player']; encode=f['encode']; decode=f['decode']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for starting_hp in [88,87]:
                for outcome in ['victory','defeat']:
                    with self.subTest(first_target_hp=starting_hp,outcome=outcome):
                        self.query('DELETE FROM fixture_specialty_terminal')
                        a=dict(f['enemy'],creaturehealth=starting_hp,creatureexp=100,creaturegold=0,istarget=True)
                        b=dict(f['enemy'],creatureid=2,creaturename='Second Target',creatureattack=1000,creatureexp=200,creaturegold=0,istarget=False)
                        f['prepare']('TS',badguy=encode({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}),gold=1000,gems=10,experience=1000,turns=20)
                        stale=f['form'](2); first=f['form'](2)
                        status,body=f['request'](data=first); self.assertEqual(200,status,body[:2000])
                        state=f['snapshot']()[0]; combat=decode(state['badguy']); corpse=combat['enemies'][0]
                        self.assertEqual(starting_hp-88,corpse['creaturehealth']); self.assertTrue(corpse['dead']); self.assertFalse(corpse['istarget'])
                        self.assertTrue(combat['enemies'][1]['istarget']); self.assertFalse(combat['enemies'][1]['dead'])
                        self.assertEqual(100000,combat['enemies'][1]['creaturehealth'])
                        self.assertEqual(['500','1000','1000','10'],[state[k] for k in ['hitpoints','experience','gold','gems']])
                        self.assertEqual(4,decode(state['bufflist'])['ts2']['rounds']); self.assertEqual([],terminal())
                        f['rejected'](first); f['rejected'](stale)
                        # Every request-side target/stat override rejects against the actual progressed state.
                        for patch in [{'newtarget':'0'},{'newtarget':'2'},{'creaturehealth':'100000'},{'enemy':'0'}]:
                            f['rejected'](dict(f['form'](2),**patch),400)
                        fresh=f['form'](2); status,body=f['request'](data=fresh); self.assertEqual(200,status,body[:2000])
                        state=f['snapshot']()[0]; progressed=decode(state['badguy'])
                        self.assertEqual(corpse,progressed['enemies'][0])
                        self.assertEqual(99912,progressed['enemies'][1]['creaturehealth'])
                        self.assertEqual('323',state['hitpoints']); self.assertEqual('1000',state['experience'])
                        self.assertEqual(4,decode(state['bufflist'])['ts2']['rounds'])
                        self.assertEqual('5',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                        f['rejected'](fresh); f['rejected'](first)
                        stale=f['form'](2)
                        if outcome=='victory':
                            progressed['enemies'][1]['creaturehealth']=88
                            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encode(progressed),player])
                        else: self.query('UPDATE accounts SET hitpoints=177 WHERE acctid=?',[player])
                        f['rejected'](stale)
                        final=f['form'](2); status,body=f['request'](data=final); self.assertEqual(200,status,body[:2000])
                        after=f['snapshot']()[0]
                        self.assertEqual('',after['badguy']); self.assertEqual([],decode(after['companions']))
                        self.assertEqual('3',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                        self.assertEqual(2,len(terminal()))
                        self.assertEqual(['battle-'+outcome]*2,[event['hook'] for event in terminal()])
                        self.assertEqual(starting_hp-88,terminal()[0]['enemy']['creaturehealth'])
                        self.assertEqual(0 if outcome=='victory' else 99824,terminal()[1]['enemy']['creaturehealth'])
                        expected=['1','323','1000','1150','11'] if outcome=='victory' else ['0','0','0','900','10']
                        self.assertEqual(expected,[after[k] for k in ['alive','hitpoints','gold','experience','gems']])
                        before=terminal(); f['rejected'](final); f['rejected'](fresh); f['rejected'](first); self.assertEqual(before,terminal())

    def test_defeated_target_thieving_remaining_progression_and_terminal(self):
        # Weapon kills skip defense-only activation; TS1/TS3 retain five
        # rounds on A, while TS5 spends an offense round. No formula changes.
        cases=[(1,40,40,70,5),(3,40,58,0,5),(5,135,135,104,4)]
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            encode=f['encode']; decode=f['decode']; player=f['player']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for level,weapon_damage,round_damage,incoming,first_rounds in cases:
                for overkill in [0,1]:
                    for outcome in ['victory','defeat']:
                        with self.subTest(level=level,overkill=overkill,outcome=outcome):
                            self.query('DELETE FROM fixture_specialty_terminal')
                            a=dict(f['enemy'],creaturehealth=weapon_damage-overkill,creatureexp=100,creaturegold=0,istarget=True)
                            b=dict(f['enemy'],creatureid=2,creaturename='Second Target',creatureattack=1000,creatureexp=200,creaturegold=0,istarget=False)
                            f['prepare']('TS',badguy=encode({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}),gold=1000,gems=10,experience=1000,turns=20)
                            # Earned skill 45 supports fifteen remaining uses, enough
                            # for three legitimate Backstabs without replenishment.
                            self.query("UPDATE module_userprefs SET value=CASE setting WHEN 'skill' THEN '45' ELSE '15' END WHERE userid=? AND modulename='specialtythiefskills' AND setting IN ('skill','uses')",[player])
                            stale=f['form'](level); first=f['form'](level)
                            status,body=f['request'](data=first); self.assertEqual(200,status,body[:2000])
                            state=f['snapshot']()[0]; combat=decode(state['badguy']); corpse=combat['enemies'][0]; key='ts'+str(level)
                            self.assertEqual(-overkill,corpse['creaturehealth']); self.assertTrue(corpse['dead']); self.assertFalse(corpse['istarget'])
                            self.assertTrue(combat['enemies'][1]['istarget']); self.assertFalse(combat['enemies'][1]['dead'])
                            self.assertEqual(100000,combat['enemies'][1]['creaturehealth'])
                            self.assertEqual(['500','1000','1000','10'],[state[k] for k in ['hitpoints','experience','gold','gems']])
                            self.assertEqual(first_rounds,decode(state['bufflist'])[key]['rounds']); self.assertEqual([],terminal())
                            self.assertEqual(str(15-level),self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                            f['rejected'](first); f['rejected'](stale)
                            f['rejected'](dict(f['form'](level),newtarget='0'),400)
                            fresh=f['form'](level); status,body=f['request'](data=fresh); self.assertEqual(200,status,body[:2000])
                            state=f['snapshot']()[0]; progressed=decode(state['badguy'])
                            self.assertEqual(corpse,progressed['enemies'][0]); self.assertEqual(100000-round_damage,progressed['enemies'][1]['creaturehealth'])
                            self.assertEqual(500-incoming,int(state['hitpoints'])); self.assertEqual(4,decode(state['bufflist'])[key]['rounds'])
                            self.assertEqual(['1000','1000','10'],[state[k] for k in ['experience','gold','gems']]); self.assertEqual([],terminal())
                            self.assertEqual(str(15-2*level),self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                            f['rejected'](fresh); f['rejected'](first)
                            stale_b=f['form'](level)
                            if outcome=='victory':
                                progressed['enemies'][1]['creaturehealth']=weapon_damage-overkill
                            elif level==3:
                                # Hidden Attack stops enemy attacks, not the
                                # historical weapon riposte against high defense.
                                progressed['enemies'][1]['creaturedefense']=1000
                            self.query('UPDATE accounts SET badguy=?,hitpoints=? WHERE acctid=?',[encode(progressed),500-incoming if outcome=='victory' else (22 if level==3 else incoming),player])
                            f['rejected'](stale_b)
                            final=f['form'](level); status,body=f['request'](data=final); self.assertEqual(200,status,body[:2000])
                            after=f['snapshot']()[0]; events=terminal()
                            self.assertEqual('',after['badguy']); self.assertEqual([],decode(after['companions']))
                            self.assertEqual(str(15-3*level),self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                            self.assertEqual(['battle-'+outcome]*2,[event['hook'] for event in events]); self.assertEqual(-overkill,events[0]['enemy']['creaturehealth'])
                            final_target=-overkill if outcome=='victory' else 100000-round_damage-(0 if level==3 else weapon_damage)
                            self.assertEqual(final_target,events[1]['enemy']['creaturehealth'])
                            expected=['1',str(500-incoming),'1000','1150','11'] if outcome=='victory' else ['0','0','0','900','10']
                            self.assertEqual(expected,[after[k] for k in ['alive','hitpoints','gold','experience','gems']])
                            retained=first_rounds if outcome=='victory' else (5 if level==3 else 4)
                            self.assertEqual(retained,decode(after['bufflist'])[key]['rounds'])
                            self.assertEqual('21' if outcome=='victory' and level==3 else '20',after['turns'])
                            f['rejected'](final); f['rejected'](fresh); f['rejected'](first); self.assertEqual(events,terminal())

    def test_dragon_and_forest_thieving_combined_modifiers_and_natural_expiration(self):
        # All four effects can coexist through normal same-specialty casts.
        # Values include historical power-move and riposte damage, not just
        # raw multipliers. Each request starts from the same fixture RNG seed.
        rounds=[
            (1,4930,99960,14,{'ts1':4}),
            (5,4930,99824,9,{'ts1':3,'ts5':4}),
            (2,4930,99447,7,{'ts1':2,'ts5':3,'ts2':4}),
            (3,4930,99006,4,{'ts1':1,'ts5':2,'ts2':3,'ts3':4}),
            (None,4930,98565,4,{'ts5':1,'ts2':2,'ts3':3}),
            (None,4930,98124,4,{'ts2':1,'ts3':2}),
            (None,4930,98018,4,{'ts3':1}),
            (None,4930,97960,4,{}),
            (None,4753,97920,4,{}),
        ]
        for route in ['forest','dragon']:
            with self.subTest(route=route), self._specialty_accounting_fixture(route) as f:
                enemy=dict(f['enemy'],creatureattack=1000)
                f['prepare']('TS',badguy=f['encode']({'enemies':[enemy],'options':{'type':route,'didsurprise':1}}),hitpoints=5000,maxhitpoints=10000)
                self.query("UPDATE module_userprefs SET value=CASE setting WHEN 'skill' THEN '45' ELSE '15' END WHERE userid=? AND modulename='specialtythiefskills' AND setting IN ('skill','uses')",[f['player']])
                before=f['snapshot']()[0]
                for level,hp,targethp,uses,remaining in rounds:
                    stale=f['form'](1)
                    if level is not None:
                        data=f['form'](level); status,body=f['request'](data=data)
                    elif route=='dragon':
                        path='dragon.php?op=fight'; status,body=f['request'](path); self.assertEqual(200,status,body[:2000])
                        status,body=f['request'](path,self._security_fields(body,path))
                    else:
                        status,body=self._ordinary_attack(f['request'])
                    self.assertEqual(200,status,body[:2000]); state=f['snapshot']()[0]
                    self.assertEqual(hp,int(state['hitpoints'])); self.assertEqual(targethp,f['decode'](state['badguy'])['enemies'][0]['creaturehealth'])
                    buffs=f['decode'](state['bufflist'])
                    self.assertEqual(remaining,{k:v['rounds'] for k,v in (buffs.items() if isinstance(buffs,dict) else [])})
                    self.assertEqual(str(uses),self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[f['player']])[0]['value'])
                    for key in ['gold','gems','experience','alive','attack','defense','turns']: self.assertEqual(before[key],state[key])
                    self.assertEqual([],f['decode'](state['companions'])); f['rejected'](stale)
                    if level is not None: f['rejected'](data)


    @contextmanager
    def _darkarts_companion_fixture(self, route):
        with self._specialty_accounting_fixture(route) as f:
            encode,decode,player=f['encode'],f['decode'],f['player']
            def combat(attack=40, defense=1, hp=100000):
                return encode({'enemies':[dict(f['enemy'],creatureattack=attack,
                    creaturedefense=defense,creaturehealth=hp)],
                    'options':{'type':route,'didsurprise':1}})
            def prepare(**changes):
                f['prepare']('DA',badguy=combat(),gold=1000,gems=10,experience=1000,turns=20,**changes)
                # A trained DA player can afford the real combined sequence.
                for key,value in [('uses','40'),('skill','120')]:
                    self.query("UPDATE module_userprefs SET value=? WHERE userid=? AND modulename='specialtydarkarts' AND setting=?",[value,player,key])
            def uses():
                return int(self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])[0]['value'])
            def cast(level):
                data=f['form'](level); before=uses()
                status,body=f['request'](data=data); self.assertEqual(200,status,body[:2500])
                self.assertEqual(before-level,uses())
                after=f['snapshot'](); f['rejected'](data)
                # A separate request rehydrates the exact committed business state.
                if after[0]['badguy']:
                    path='dragon.php?op=prologue1' if route=='dragon' and 'dragonVictory' in after[0]['badguy'] else route+'.php?op=specialty'
                    self.assertEqual(200,f['request'](path)[0])
                else: self.assertEqual(302 if after[0]['alive']=='0' else 200,f['request']('village.php')[0])
                self.assertEqual(after,f['snapshot']())
                return body,data
            def patch(**values):
                self.query('UPDATE accounts SET '+','.join(k+'=?' for k in values)+' WHERE acctid=?',[*values.values(),player])
            def state():
                a=f['snapshot']()[0]
                return a,decode(a['companions']),decode(a['bufflist'])
            def ordered(body,*messages):
                text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                position=-1
                for message in messages:
                    found=text.find(message,position+1); self.assertGreater(found,position,text)
                    position=found
            yield f|dict(combat=combat,prepare_da=prepare,cast=cast,patch=patch,uses=uses,state=state,ordered=ordered)

    def test_darkarts_companion_injury_death_defeat_and_rollback(self):
        for route,maxhp,injured,hurt in [('forest',43,17,13),('dragon',60,37,12)]:
            with self.subTest(route=route), self._darkarts_companion_fixture(route) as f, self._specialty_terminal_capture() as terminal:
                d,e=f['decode'],f['encode']; f['prepare_da']()
                body,created=f['cast'](1); a,c,b=f['state']()
                self.assertEqual(injured,c['skeleton_warrior']['hitpoints'])
                self.assertEqual(maxhp,c['skeleton_warrior']['maxhitpoints'])
                self.assertEqual('500',a['hitpoints']); self.assertEqual(99929 if route=='forest' else 99923,d(a['badguy'])['enemies'][0]['creaturehealth'])
                f['ordered'](body,'You hit Accounting Target for 47 points','you RIPOSTE for 14 points',
                    'Skeleton Warrior hits Accounting Target','hits Skeleton Warrior for '+str(maxhp-injured)+' points')
                # Curse halves companion retaliation with historical integer rounding.
                body,_=f['cast'](3); a,c,b=f['state'](); wounded=c
                self.assertEqual(injured-hurt,c['skeleton_warrior']['hitpoints'])
                self.assertEqual(4,b['da3']['rounds']); self.assertEqual('500',a['hitpoints'])
                self.assertEqual(99858 if route=='forest' else 99846,d(a['badguy'])['enemies'][0]['creaturehealth'])
                f['ordered'](body,'You hit Accounting Target for 47 points','deals only half damage',
                    'Skeleton Warrior hits Accounting Target','hits Skeleton Warrior for '+str(hurt)+' points')
                # Lethal companion riposte/retaliation is a multi-write transition.
                f['patch'](badguy=f['combat'](120,80)); failed=f['form'](3); before=f['snapshot']()
                escaped=before[0]['companions'].replace("'","''")
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_da_companion CHECK (login <> 'WebPlayer' OR companions='"+escaped+"')")
                try:
                    f['rejected'](failed,500); self.assertEqual([],terminal())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_da_companion')
                f['rejected'](failed); self.assertEqual(before,f['snapshot']())
                body,death=f['cast'](3); a,c,b=f['state']()
                self.assertEqual([],c); self.assertNotIn('da1',b); self.assertEqual(4,b['da3']['rounds'])
                self.assertEqual('500',a['hitpoints']); self.assertEqual(99955,d(a['badguy'])['enemies'][0]['creaturehealth'])
                self.assertEqual(1,body.count('Your skeleton warrior crumbles to dust.'))
                f['ordered'](body,'You hit Accounting Target','Skeleton Warrior tries to hit Accounting Target',
                    'hits Skeleton Warrior','Your skeleton warrior crumbles to dust.')
                body,_=f['cast'](3); self.assertNotIn('Skeleton Warrior',body); self.assertEqual([],f['state']()[1])
                self.assertEqual(99910,d(f['state']()[0]['badguy'])['enemies'][0]['creaturehealth']); f['rejected'](death)
                # Death never summons fallback minions; a fresh cast explicitly replaces it.
                f['patch'](badguy=f['combat'](1)); body,_=f['cast'](1)
                self.assertEqual(maxhp,f['state']()[1]['skeleton_warrior']['hitpoints']); self.assertNotIn('da1',f['state']()[2])
                healthy=f['state']()[1]
                f['patch'](companions=e(wounded)); f['cast'](1)
                self.assertEqual(1,len(f['state']()[1])); self.assertEqual(healthy,f['state']()[1])
                for companion in [healthy,wounded]:
                    with self.subTest(companion_hp=companion['skeleton_warrior']['hitpoints']):
                        f['prepare_da'](companions=e(companion),hitpoints=89)
                        f['patch'](badguy=f['combat'](1000,80)); self.query('DELETE FROM fixture_specialty_terminal')
                        body,dead=f['cast'](3); a,c,b=f['state'](); events=terminal()
                        self.assertEqual(['0','0','0','900' if route=='forest' else '1000'],[a[k] for k in ['alive','hitpoints','gold','experience']])
                        self.assertEqual('',a['badguy']); self.assertEqual(dict(companion['skeleton_warrior'],used=False),c['skeleton_warrior'])
                        self.assertEqual(4,b['da3']['rounds']); self.assertEqual(['battle-defeat'],[x['hook'] for x in events])
                        self.assertEqual(99960,events[0]['enemy']['creaturehealth']); self.assertEqual(c,events[0]['companions'])
                        self.assertNotIn('Skeleton Warrior hits',body); self.assertNotIn('crumbles to dust',body)
                        f['rejected'](dead); self.assertEqual(events,terminal())

    def test_darkarts_companion_combined_natural_expiration(self):
        cases=[('forest',[99929,99858,99787,99475,99163,98851,98539,98258],[17,4,4,4,4,4,4,4]),
               ('dragon',[99923,99846,99758,99430,99102,98774,98446,98153],[37,25,25,25,25,25,25,25])]
        rounds=[{}, {'da3':4}, {'da3':3,'da5':4}, {'da3':2,'da5':3},
                {'da3':1,'da5':2}, {'da5':1}, {}, {}]
        for route,targets,companions in cases:
            with self.subTest(route=route), self._darkarts_companion_fixture(route) as f:
                f['prepare_da']()
                for n,level in enumerate([1,3,5,2,2,2,2,2]):
                    body,_=f['cast'](level); a,c,b=f['state']()
                    self.assertEqual(targets[n],f['decode'](a['badguy'])['enemies'][0]['creaturehealth'])
                    self.assertEqual(companions[n],c['skeleton_warrior']['hitpoints'])
                    self.assertEqual(487 if n==7 else 500,int(a['hitpoints']))
                    self.assertEqual(rounds[n],{k:v['rounds'] for k,v in b.items()} if b else {})
                    self.assertNotIn('da2',b)
                    if n in [5,6]:
                        f['ordered'](body,'doll hurting it for 263 points','You hit Accounting Target',
                            'Skeleton Warrior hits Accounting Target','Your curse has faded.' if n==5 else "Your victim's soul has been restored.")
                self.assertEqual(21,f['uses']())

    def test_darkarts_companion_target_progression_and_terminal_order(self):
        with self._darkarts_companion_fixture('forest') as f, self._specialty_terminal_capture() as terminal:
            d,e=f['decode'],f['encode']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for overkill in [0,1]:
                with self.subTest(overkill=overkill):
                    f['prepare_da'](); f['cast'](1); f['cast'](3)
                    self.assertEqual(4,f['state']()[1]['skeleton_warrior']['hitpoints'])
                    a=dict(f['enemy'],creatureattack=40,creaturedefense=1,creaturehealth=71-overkill,
                           creatureexp=100,creaturegold=0,istarget=True)
                    b=dict(a,creatureid=2,creaturename='Second Target',creaturehealth=100000,
                           creatureexp=200,istarget=False)
                    f['patch'](badguy=e({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}))
                    self.query('DELETE FROM fixture_specialty_terminal'); stale=f['form'](3)
                    body,first=f['cast'](3); account,c,buffs=f['state'](); state=d(account['badguy'])
                    corpse=state['enemies'][0]
                    self.assertEqual(-overkill,corpse['creaturehealth']); self.assertTrue(corpse['dead']); self.assertFalse(corpse['istarget'])
                    self.assertTrue(state['enemies'][1]['istarget']); self.assertEqual(100000,state['enemies'][1]['creaturehealth'])
                    self.assertEqual(['500','1000','10','1000'],[account[k] for k in ['hitpoints','gold','gems','experience']])
                    self.assertEqual([],terminal()); self.assertEqual(4,buffs['da3']['rounds']); f['rejected'](stale)
                    if overkill:
                        self.assertEqual(4,c['skeleton_warrior']['hitpoints']); self.assertNotIn('crumbles to dust',body)
                    else:
                        self.assertEqual([],c); self.assertEqual(1,body.count('Your skeleton warrior crumbles to dust.'))
                    # A fresh action uses B; Wither Soul protects the injured survivor.
                    body,_=f['cast'](5); account,c,buffs=f['state'](); state=d(account['badguy'])
                    self.assertEqual(corpse,state['enemies'][0])
                    self.assertEqual(99929 if overkill else 99951,state['enemies'][1]['creaturehealth'])
                    self.assertEqual({'da3':3,'da5':4},{k:v['rounds'] for k,v in buffs.items()})
                    if overkill:
                        self.assertEqual(4,c['skeleton_warrior']['hitpoints'])
                        f['ordered'](body,'You hit Second Target','Skeleton Warrior hits Second Target')
                    else: self.assertNotIn('Skeleton Warrior',body)
                    state['enemies'][1]['creaturehealth']=263-overkill
                    f['patch'](badguy=e(state)); body,final=f['cast'](2); account,finalc,buffs=f['state']()
                    self.assertEqual('',account['badguy']); self.assertEqual(['1','500','1000','11','1150'],[account[k] for k in ['alive','hitpoints','gold','gems','experience']])
                    self.assertEqual(26,f['uses']()); self.assertEqual(2,len(terminal()))
                    self.assertEqual(['battle-victory']*2,[x['hook'] for x in terminal()])
                    self.assertEqual([-overkill,-overkill],[x['enemy']['creaturehealth'] for x in terminal()])
                    self.assertEqual({'da3':3,'da5':4},{k:v['rounds'] for k,v in buffs.items()})
                    if overkill:
                        self.assertEqual(dict(c['skeleton_warrior'],used=False),finalc['skeleton_warrior'])
                    else: self.assertEqual([],finalc)
                    events=terminal(); f['rejected'](first); f['rejected'](final); self.assertEqual(events,terminal())

    def test_darkarts_dragon_companion_simultaneous_victory_and_expiration(self):
        with self._darkarts_companion_fixture('dragon') as f, self._specialty_terminal_capture() as terminal:
            d,e=f['decode'],f['encode']; request=f['request']
            for overkill in [0,1]:
                with self.subTest(overkill=overkill):
                    f['prepare_da'](); f['cast'](1)
                    for _ in range(3): f['cast'](3)
                    account,c,b=f['state'](); self.assertEqual(1,c['skeleton_warrior']['hitpoints'])
                    b['da3']['rounds']=1 # Valid last-active-round state of the real producer.
                    f['patch'](badguy=f['combat'](hp=77-overkill),bufflist=e(b))
                    self.query('DELETE FROM fixture_specialty_terminal')
                    path='dragon.php?op=fight'; status,body=request(path); self.assertEqual(200,status)
                    action=self._security_fields(body,path); beforeuses=f['uses']()
                    status,body=request(path,action); self.assertEqual(200,status,body[:2500])
                    account,c,b=f['state'](); events=terminal(); pending=d(account['badguy'])
                    self.assertTrue(pending['dragonVictory']); self.assertEqual(0,pending['dragonkills'])
                    self.assertEqual('500',account['hitpoints']); self.assertEqual(beforeuses,f['uses']()); self.assertNotIn('da3',b)
                    self.assertEqual(['battle-victory'],[x['hook'] for x in events]); self.assertEqual(-overkill,events[0]['enemy']['creaturehealth'])
                    self.assertEqual(c,events[0]['companions'])
                    if overkill:
                        self.assertEqual(1,c['skeleton_warrior']['hitpoints']); self.assertNotIn('crumbles to dust',body)
                        f['ordered'](body,'Skeleton Warrior hits Accounting Target','Your curse has faded.')
                    else:
                        self.assertEqual([],c); self.assertEqual(1,body.count('Your skeleton warrior crumbles to dust.'))
                        f['ordered'](body,'Skeleton Warrior hits Accounting Target','hits Skeleton Warrior for 12 points',
                            'Your skeleton warrior crumbles to dust.','Your curse has faded.')
                    continuation=self._security_fields(body,'dragon.php?op=prologue1')
                    before=f['snapshot'](); self.assertEqual(409,request(path,action)[0]); self.assertEqual(before,f['snapshot']())
                    self.assertEqual(200,request('dragon.php?op=prologue1')[0]); self.assertEqual(before,f['snapshot']())
                    status,body=request('dragon.php?op=prologue1',continuation); self.assertEqual(200,status,body[:2500])
                    self.assertEqual([],f['state']()[1]); self.assertEqual('',f['state']()[0]['badguy'])
                    self.assertEqual(['1','1'],list(self.query('SELECT dragonkills,level FROM accounts WHERE acctid=?',[f['player']])[0].values()))
                    after=f['snapshot'](); self.assertEqual(409,request('dragon.php?op=prologue1',continuation)[0]); self.assertEqual(after,f['snapshot']()); self.assertEqual(events,terminal())

    def test_darkarts_fallback_minions_combined_and_terminal(self):
        cases=[('forest',4,6,8,[99921,99842,99758,99474,99190,98900,98610,98339]),
               ('dragon',6,9,20,[99901,99802,99697,99377,99057,98767,98477,98206])]
        rounds=[{'da1':4},{'da1':3,'da3':4},{'da1':2,'da3':3,'da5':4},
                {'da1':1,'da3':2,'da5':3},{'da3':1,'da5':2},{'da5':1},{},{}]
        for route,count,damage,minionhit,targets in cases:
            with self.subTest(route=route), self._darkarts_companion_fixture(route) as f, self._specialty_terminal_capture() as terminal:
                d,e=f['decode'],f['encode'];f['prepare_da']();stale=f['form'](1)
                self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'");f['rejected'](stale)
                for field in ['enablecompanions','companion','minioncount']:
                    f['rejected'](f['form'](1)|{field:'1'},400)
                for n,level in enumerate([1,3,5,2,2,2,2,2]):
                    body,_=f['cast'](level); a,c,b=f['state']()
                    self.assertEqual([],c);self.assertNotIn('Skeleton Warrior',body)
                    self.assertEqual(targets[n],d(a['badguy'])['enemies'][0]['creaturehealth'])
                    self.assertEqual(487 if n==7 else 500,int(a['hitpoints']))
                    self.assertEqual(rounds[n],{k:v['rounds'] for k,v in b.items()} if b else {})
                    if n==0:
                        self.assertEqual(count,b['da1']['minioncount']);self.assertEqual(damage,b['da1']['maxbadguydamage'])
                        fallback=b['da1']
                    if n==4:f['ordered'](body,'An undead minion','doll hurting it','You hit Accounting Target','Your skeleton minions crumble to dust.')
                # Historical setting selects the DA1 producer; it does not suspend
                # a previously created warrior. Prove coexistence instead of
                # inventing a disabled-companion cleanup policy.
                self.query("UPDATE settings SET value='1' WHERE setting='enablecompanions'")
                f['prepare_da']();f['cast'](1);old=f['form'](1)
                self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'");f['rejected'](old)
                body,_=f['cast'](1);account,c,buffs=f['state']()
                self.assertEqual(99845 if route=='forest' else 99803,d(account['badguy'])['enemies'][0]['creaturehealth'])
                self.assertEqual('500',account['hitpoints']);self.assertEqual(4,buffs['da1']['rounds']);self.assertEqual(38,f['uses']())
                f['ordered'](body,'An undead minion','You hit Accounting Target','Skeleton Warrior hits Accounting Target')
                if route=='forest':
                    self.assertEqual([],c);self.assertEqual(1,body.count('Your skeleton warrior crumbles to dust.'))
                else:self.assertEqual(37,c['skeleton_warrior']['hitpoints'])
                if route=='forest':
                    f['prepare_da'](bufflist=e({'da1':dict(fallback,rounds=1)}))
                    a=dict(f['enemy'],creatureattack=40,creaturedefense=1,creaturehealth=minionhit,istarget=True)
                    b=dict(a,creatureid=2,creaturename='Second Target',creaturehealth=100000,istarget=False)
                    f['patch'](badguy=e({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}))
                    self.query('DELETE FROM fixture_specialty_terminal');old=f['form'](3)
                    f['cast'](3);account,c,buffs=f['state']();progressed=d(account['badguy'])
                    self.assertEqual(0,progressed['enemies'][0]['creaturehealth']);self.assertTrue(progressed['enemies'][0]['dead'])
                    self.assertTrue(progressed['enemies'][1]['istarget']);self.assertEqual(100000,progressed['enemies'][1]['creaturehealth'])
                    self.assertNotIn('da1',buffs);self.assertEqual(5,buffs['da3']['rounds']);self.assertEqual([],c);self.assertEqual([],terminal());f['rejected'](old)
                    f['cast'](2);account,c,buffs=f['state']();progressed=d(account['badguy'])
                    self.assertEqual(99729,progressed['enemies'][1]['creaturehealth']);self.assertEqual('493',account['hitpoints'])
                    self.assertEqual([],c);self.assertNotIn('da1',buffs);self.assertEqual(4,buffs['da3']['rounds']);self.assertEqual([],terminal())
                # Last-round fallback can itself end the encounter before player,
                # companion or defensive effects. The schema is a real cast's output.
                for outcome in ['victory','defeat']:
                    f['prepare_da'](bufflist=e({'da1':dict(fallback,rounds=1)}),hitpoints=1 if outcome=='defeat' else 500)
                    f['patch'](badguy=f['combat'](1000 if outcome=='defeat' else 40,1000 if outcome=='defeat' else 1,
                                                100000 if outcome=='defeat' else minionhit))
                    self.query('DELETE FROM fixture_specialty_terminal')
                    body,action=f['cast'](3);a,c,b=f['state']();events=terminal()
                    self.assertEqual([],c);self.assertNotIn('da1',b);self.assertEqual(5,b['da3']['rounds'])
                    self.assertEqual(['battle-'+outcome],[x['hook'] for x in events])
                    self.assertEqual(100000-minionhit if outcome=='defeat' else 0,events[0]['enemy']['creaturehealth'])
                    f['ordered'](body,'An undead minion','Your skeleton minions crumble to dust.')
                    self.assertNotIn('Skeleton Warrior',body)
                    if outcome=='defeat':
                        self.assertEqual(['0','0',''],[a[k] for k in ['alive','hitpoints','badguy']])
                    elif route=='dragon':
                        self.assertTrue(d(a['badguy'])['dragonVictory'])
                        continuation=self._security_fields(body,'dragon.php?op=prologue1')
                        before=f['snapshot']();self.assertEqual(200,f['request']('dragon.php?op=prologue1')[0]);self.assertEqual(before,f['snapshot']())
                        self.assertEqual(200,f['request']('dragon.php?op=prologue1',continuation)[0])
                        after=f['snapshot']();self.assertEqual([],f['state']()[1]);self.assertEqual([],f['state']()[2])
                        self.assertEqual(409,f['request']('dragon.php?op=prologue1',continuation)[0]);self.assertEqual(after,f['snapshot']())
                    else:self.assertEqual('',a['badguy'])
                    f['rejected'](action);self.assertEqual(events,terminal())

    def test_darkarts_injured_companion_target_transition_and_terminal(self):
        # Voodoo kills A before the companion phase. The injured, real-produced
        # skeleton must carry to B, including when B kills the player first.
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            player=f['player']; encode=f['encode']; decode=f['decode']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for overkill in [0,1]:
                for outcome in ['victory','defeat']:
                    with self.subTest(overkill=overkill,outcome=outcome):
                        weak=dict(f['enemy'],creatureattack=1,creaturedefense=1)
                        f['prepare']('DA',badguy=encode({'enemies':[weak],'options':{'type':'forest','didsurprise':1}}))
                        self.assertEqual(200,f['request'](data=f['form'](1))[0])
                        skeleton=decode(f['snapshot']()[0]['companions'])['skeleton_warrior']
                        self.assertEqual(43,skeleton['hitpoints'])
                        skeleton=dict(skeleton,hitpoints=30)
                        a=dict(f['enemy'],creaturehealth=263-overkill,creatureexp=100,creaturegold=0,istarget=True)
                        b=dict(f['enemy'],creatureid=2,creaturename='Second Target',creatureexp=200,creaturegold=0,istarget=False)
                        self.query('UPDATE accounts SET badguy=?,companions=?,hitpoints=500,gold=1000,gems=10,experience=1000,turns=20 WHERE acctid=?',
                            [encode({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}),encode({'skeleton_warrior':skeleton}),player])
                        self.query('DELETE FROM fixture_specialty_terminal')
                        stale=f['form'](2); first=f['form'](2)
                        status,body=f['request'](data=first); self.assertEqual(200,status,body[:2000])
                        after=f['snapshot']()[0]; progressed=decode(after['badguy']); corpse=progressed['enemies'][0]
                        self.assertEqual(-overkill,corpse['creaturehealth']); self.assertTrue(corpse['dead']); self.assertFalse(corpse['istarget'])
                        self.assertTrue(progressed['enemies'][1]['istarget']); self.assertEqual(100000,progressed['enemies'][1]['creaturehealth'])
                        self.assertEqual(dict(skeleton,used=False),decode(after['companions'])['skeleton_warrior'])
                        self.assertEqual(['500','1000','10','1000'],[after[k] for k in ['hitpoints','gold','gems','experience']])
                        self.assertEqual([],terminal()); f['rejected'](first); f['rejected'](stale)
                        # Malformation after actual progression cannot silently heal or
                        # discard the companion, spend uses, or change either target.
                        valid=after['companions']; fresh=f['form'](2)
                        self.query('UPDATE accounts SET companions=? WHERE acctid=?',[encode({'skeleton_warrior':dict(skeleton,hitpoints=0)}),player])
                        f['rejected'](fresh)
                        self.query('UPDATE accounts SET companions=? WHERE acctid=?',[valid,player])
                        if outcome=='victory':
                            progressed['enemies'][1]['creaturehealth']=263-overkill
                        else:
                            progressed['enemies'][1].update(creatureattack=1000,creaturedefense=1000)
                        self.query('UPDATE accounts SET badguy=?,hitpoints=? WHERE acctid=?',[encode(progressed),500 if outcome=='victory' else 11,player])
                        f['rejected'](fresh)
                        final=f['form'](2 if outcome=='victory' else 3)
                        status,body=f['request'](data=final); self.assertEqual(200,status,body[:2000])
                        after=f['snapshot']()[0]; events=terminal()
                        self.assertEqual('',after['badguy']); self.assertEqual(['battle-'+outcome]*2,[e['hook'] for e in events])
                        self.assertEqual(-overkill,events[0]['enemy']['creaturehealth'])
                        self.assertEqual(-overkill if outcome=='victory' else 100000,events[1]['enemy']['creaturehealth'])
                        self.assertEqual(30,decode(after['companions'])['skeleton_warrior']['hitpoints'])
                        expected=['1','500','1000','11','1150'] if outcome=='victory' else ['0','0','0','10','900']
                        self.assertEqual(expected,[after[k] for k in ['alive','hitpoints','gold','gems','experience']])
                        self.assertEqual('4' if outcome=='victory' else '3',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])[0]['value'])
                        f['rejected'](final); f['rejected'](first); self.assertEqual(events,terminal())

    @contextmanager
    def _mystic_progression_fixture(self):
        with self._specialty_accounting_fixture() as f:
            e,d,player=f['encode'],f['decode'],f['player']
            def patch(**values):
                self.query('UPDATE accounts SET '+','.join(k+'=?' for k in values)+' WHERE acctid=?',[*values.values(),player])
            def combat(hp,attack=1000,defense=80):
                a=dict(f['enemy'],creaturehealth=hp,creatureattack=attack,creaturedefense=defense,creatureexp=100,creaturegold=0,istarget=True)
                b=dict(a,creatureid=2,creaturename='Second Target',creaturehealth=100000,creatureexp=200,istarget=False)
                return e({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}})
            def prepare(**values):
                defaults=dict(badguy=combat(100000),hitpoints=5000,maxhitpoints=10000,gold=1000,gems=10,experience=1000,turns=20)
                defaults.update(values); f['prepare']('MP',**defaults)
                for key,value in [('uses','40'),('skill','120')]:
                    self.query("UPDATE module_userprefs SET value=? WHERE userid=? AND modulename='specialtymysticpower' AND setting=?",[value,player,key])
            def uses():
                return int(self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtymysticpower' AND setting='uses'",[player])[0]['value'])
            def cast(level):
                stale=f['form'](1); data=f['form'](level); before=uses()
                status,body=f['request'](data=data); self.assertEqual(200,status,body[:2500])
                self.assertEqual(before-level,uses()); f['rejected'](data); f['rejected'](stale)
                saved=f['snapshot']()
                if saved[0]['badguy']: self.assertEqual(200,f['request']()[0])
                self.assertEqual(saved,f['snapshot']())
                return body,data
            def fight():
                stale=f['form'](1); before=uses()
                status,body=self._ordinary_attack(f['request']); self.assertEqual(200,status,body[:2500])
                self.assertEqual(before,uses()); f['rejected'](stale)
                return body
            def state():
                a=f['snapshot']()[0]
                return a,d(a['badguy']) if a['badguy'] else {},d(a['bufflist'])
            yield f|dict(patch=patch,combat=combat,prepare_mp=prepare,uses=uses,cast=cast,fight=fight,state=state)

    def test_mystic_effect_target_progression_and_fresh_recast(self):
        # Earth Fist rolls 16 at player level 15; Lifetap heals the 40-point
        # weapon hit; Lightning Aura reflects 177*2 after that weapon hit.
        with self._mystic_progression_fixture() as f, self._specialty_terminal_capture() as terminal:
            for level,damage,attack,playerlevel,hp in [(2,16,120,15,4914),(3,40,1000,10,4863),(5,394,1000,10,4823)]:
                for delta in [1,0,-1]:
                    with self.subTest(level=level,delta=delta):
                        f['prepare_mp'](level=playerlevel,badguy=f['combat'](damage+delta,attack))
                        body,first=f['cast'](level); a,c,b=f['state']()
                        self.assertEqual(delta,c['enemies'][0]['creaturehealth'])
                        self.assertEqual(100000,c['enemies'][1]['creaturehealth'])
                        self.assertEqual(4,b['mp'+str(level)]['rounds'])
                        self.assertEqual(hp if delta>0 or level==5 else (5040 if level==3 else 5000),int(a['hitpoints']))
                        self.assertEqual(delta<=0,bool(c['enemies'][0]['dead']))
                        self.assertEqual(delta<=0,bool(c['enemies'][1]['istarget']))
                        self.assertEqual([],terminal())
                        self.assertEqual(['1000','1000','10'],[a[k] for k in ['gold','experience','gems']])
                        if delta>0:
                            f['fight'](); a,c,b=f['state']()
                            self.assertTrue(c['enemies'][0]['dead']); self.assertTrue(c['enemies'][1]['istarget'])
                        corpse=c['enemies'][0]
                        for patch in [{'newtarget':'0'},{'newtarget':'2'},{'enemy':'0'}]:
                            f['rejected'](dict(f['form'](level),**patch),400)
                        before=int(a['hitpoints']); body,second=f['cast'](level); a,c,b=f['state']()
                        self.assertEqual(corpse,c['enemies'][0]); self.assertEqual(100000-damage,c['enemies'][1]['creaturehealth'])
                        self.assertEqual(before+({2:-86,3:-137,5:-177}[level]),int(a['hitpoints']))
                        self.assertEqual(4,b['mp'+str(level)]['rounds']); self.assertEqual(40-2*level,f['uses']())
                        self.assertEqual([],terminal()); self.assertEqual('1000',a['experience'])
                        f['rejected'](first); f['rejected'](second)

    def test_mystic_expiration_at_target_transition(self):
        with self._mystic_progression_fixture() as f:
            for level,damage,attack,playerlevel in [(1,40,1000,10),(2,16,120,15),(3,40,1000,10),(5,394,1000,10)]:
                for rounds in [3,1]:
                    with self.subTest(level=level,rounds=rounds):
                        f['prepare_mp'](level=playerlevel); f['cast'](level)
                        a,c,b=f['state'](); key='mp'+str(level); b[key]['rounds']=rounds
                        old=f['form'](1)
                        f['patch'](badguy=f['combat'](damage,attack),bufflist=f['encode'](b),hitpoints=5000)
                        f['rejected'](old); f['fight'](); a,c,b=f['state']()
                        self.assertEqual(0,c['enemies'][0]['creaturehealth']); self.assertTrue(c['enemies'][1]['istarget'])
                        self.assertEqual({1:5010,2:5000,3:5040,5:4823}[level],int(a['hitpoints']))
                        if rounds==1: self.assertNotIn(key,b)
                        else: self.assertEqual(rounds-1,b[key]['rounds'])
                        corpse=c['enemies'][0]; before=int(a['hitpoints'])
                        f['fight'](); a,c,b=f['state']()
                        active=rounds>1
                        self.assertEqual(corpse,c['enemies'][0])
                        self.assertEqual(100000-(damage if active and level in [2,5] else (45 if level==2 else 40)),c['enemies'][1]['creaturehealth'])
                        self.assertEqual(before+({1:-167,2:-86,3:-137,5:-177}[level] if active else (0 if level==2 else -177)),int(a['hitpoints']))
                        if active: self.assertEqual(1,b[key]['rounds'])
                        else: self.assertNotIn(key,b)
                        self.assertEqual(40-level,f['uses']())

    def test_mystic_progressed_terminal_healing_shield_and_rollback(self):
        with self._mystic_progression_fixture() as f, self._specialty_terminal_capture() as terminal:
            # A is already legitimately defeated before each distinct B outcome.
            for level,firsthp in [(1,40),(3,40),(5,394)]:
                for outcome in ['victory','defeat']:
                    with self.subTest(level=level,outcome=outcome):
                        self.query('DELETE FROM fixture_specialty_terminal')
                        f['prepare_mp'](badguy=f['combat'](firsthp)); f['cast'](level)
                        a,c,b=f['state'](); self.assertTrue(c['enemies'][0]['dead'])
                        corpse=c['enemies'][0]; b['mp'+str(level)]['rounds']=1
                        c['enemies'][1]['creaturehealth']=(394 if level==5 else 40) if outcome=='victory' else 100000
                        # Lifetap heals before death is assessed. Shield continues
                        # reflecting even when the incoming hit is lethal.
                        hp=167 if level==5 else (127 if outcome=='defeat' else 1)
                        f['patch'](badguy=f['encode'](c),bufflist=f['encode'](b),hitpoints=hp)
                        stale=f['form'](1); f['cast'](3 if level==1 else 1); a,c,b=f['state'](); events=terminal()
                        self.assertEqual('',a['badguy']); self.assertEqual(2,len(events))
                        self.assertEqual(['battle-'+outcome]*2,[x['hook'] for x in events])
                        self.assertEqual(corpse['creaturehealth'],events[0]['enemy']['creaturehealth'])
                        self.assertEqual(0 if outcome=='victory' else (99606 if level==5 else 99960),events[1]['enemy']['creaturehealth'])
                        self.assertEqual(({1:51,3:51,5:1}[level] if outcome=='victory' else 0),int(a['hitpoints']))
                        self.assertEqual('1' if outcome=='victory' else '0',a['alive'])
                        self.assertNotIn('mp'+str(level),b); self.assertEqual(40-level-(3 if level==1 else 1),f['uses']())
                        f['rejected'](stale); self.assertEqual(events,terminal())
            # Real regeneration + Lifetap + shield, with A killed by reflection.
            # B still living makes simultaneous A/player death a defeat.
            for hp,outcome in [(127,'defeat'),(128,None)]:
                self.query('DELETE FROM fixture_specialty_terminal')
                f['prepare_mp'](); f['cast'](1); f['cast'](3)
                a,c,b=f['state']()
                for key in ['mp1','mp3']: b[key]['rounds']=1
                f['patch'](badguy=f['combat'](394),bufflist=f['encode'](b),hitpoints=hp)
                failed=f['form'](5); before=f['snapshot']()
                # This CHECK is reached after preference, buff, HP, target and
                # (in defeat) observer writes. Every write must roll back.
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_mp_progression CHECK (login <> 'WebPlayer' OR hitpoints="+str(hp)+")")
                try:
                    f['rejected'](failed,500); self.assertEqual(before,f['snapshot']()); self.assertEqual([],terminal())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_mp_progression')
                f['rejected'](failed); body,data=f['cast'](5); a,c,b=f['state']()
                self.assertEqual(31,f['uses']()); self.assertNotIn('mp1',b); self.assertNotIn('mp3',b); self.assertEqual(4,b['mp5']['rounds'])
                if outcome:
                    self.assertEqual(['0','0','0','900'],[a[k] for k in ['alive','hitpoints','gold','experience']])
                    self.assertEqual(['battle-defeat']*2,[x['hook'] for x in terminal()])
                    self.assertEqual([0,100000],[x['enemy']['creaturehealth'] for x in terminal()])
                else:
                    self.assertEqual('1',a['hitpoints']); self.assertEqual(0,c['enemies'][0]['creaturehealth'])
                    self.assertTrue(c['enemies'][1]['istarget']); self.assertEqual(100000,c['enemies'][1]['creaturehealth'])
                    self.assertEqual([],terminal()); self.assertEqual('1000',a['experience'])
                saved=terminal(); f['rejected'](data); self.assertEqual(saved,terminal())

    def test_mystic_aura_companion_progression_natural_expiration(self):
        with self._mystic_progression_fixture() as f:
            # The bundled forgetfulness potion resets specialty without clearing
            # companions; onboarding can select MP. Retain real DA producer state,
            # as in the existing healing-aura test, without casting DA as MP.
            f['prepare']('DA',badguy=f['combat'](100000,1,1))
            self.assertEqual(200,f['request'](data=f['form'](1))[0])
            companion=f['decode'](f['snapshot']()[0]['companions']); companion['skeleton_warrior']['hitpoints']=30
            for transition in [1,5]:
                with self.subTest(transition_round=transition):
                    f['prepare_mp'](badguy=f['combat'](74 if transition==1 else 100000,1,1),companions=f['encode'](companion),hitpoints=995,maxhitpoints=1000)
                    for n in range(1,7):
                        if n==5 and transition==5:
                            a,c,b=f['state'](); c['enemies'][0]['creaturehealth']=74
                            f['patch'](badguy=f['encode'](c))
                        if n==6: f['patch'](hitpoints=990)
                        body,_=f['cast'](1) if n==1 else (f['fight'](),None)
                        a,c,b=f['state'](); comp=f['decode'](a['companions'])['skeleton_warrior']
                        self.assertEqual(min(43,30+3*min(n,5)),comp['hitpoints'])
                        self.assertEqual(990 if n==6 else 1000,int(a['hitpoints']))
                        if n<5: self.assertEqual(5-n,b['mp1']['rounds'])
                        else: self.assertNotIn('mp1',b)
                        text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                        if n<=5:
                            self.assertIn('regenerates for '+str(3 if n<5 else 1)+' health',text)
                            self.assertLess(text.index('healing aura'),text.index('You hit'))
                        else: self.assertNotIn('healing aura',text)
                        self.assertLess(text.index('You hit'),text.index('RIPOSTE'))
                        self.assertLess(text.index('RIPOSTE'),text.index('Skeleton Warrior hits'))
                        if n>=transition:
                            self.assertEqual(-1,c['enemies'][0]['creaturehealth']); self.assertTrue(c['enemies'][1]['istarget'])
                            self.assertEqual(100000-76*(n-transition),c['enemies'][1]['creaturehealth'])
                        self.assertEqual(39,f['uses']()); self.assertEqual('1000',a['experience'])

            # Aura heals before the companion's exact-zero killing strike and
            # the historical retaliation that still occurs at zero enemy HP.
            f['prepare_mp'](badguy=f['combat'](71,40,1),companions=f['encode'](companion),hitpoints=995,maxhitpoints=1000)
            body,_=f['cast'](1); a,c,b=f['state']()
            self.assertEqual(7,f['decode'](a['companions'])['skeleton_warrior']['hitpoints']) # 30+3-26
            self.assertEqual(0,c['enemies'][0]['creaturehealth']); self.assertTrue(c['enemies'][1]['istarget'])
            self.assertEqual('1000',a['hitpoints']); self.assertEqual(4,b['mp1']['rounds'])
            text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
            positions=[text.index(x) for x in ['healing aura','You hit Accounting Target for 47',
                'you RIPOSTE for 14','Skeleton Warrior hits Accounting Target for 10','hits Skeleton Warrior for 26']]
            self.assertEqual(sorted(positions),positions)
            c['enemies'][1]['creatureattack']=1; f['patch'](badguy=f['encode'](c))
            f['fight'](); a,c,b=f['state']()
            self.assertEqual(10,f['decode'](a['companions'])['skeleton_warrior']['hitpoints'])
            self.assertEqual([0,99924],[x['creaturehealth'] for x in c['enemies']]); self.assertEqual(3,b['mp1']['rounds'])

    def test_mystic_all_effects_cross_target_and_expire_naturally(self):
        # All four effects come from real MP casts. Earth Fist consumes an RNG
        # draw, producing a 24-point weapon riposte and 62-point enemy hit;
        # shield returns 48+124, Lifetap never heals negative damage.
        with self._mystic_progression_fixture() as f:
            f['prepare_mp'](badguy=f['combat'](100000,120))
            expected=[(1,5010,99955,{'mp1':4}),
                (3,5065,99910,{'mp1':3,'mp3':4}),
                (5,5120,99865,{'mp1':2,'mp3':3,'mp5':4}),
                (2,5044,100000,{'mp1':1,'mp3':2,'mp5':3,'mp2':4}),
                (None,4968,99827,{'mp3':1,'mp5':2,'mp2':3}),
                (None,4882,99654,{'mp5':1,'mp2':2}),
                (None,4796,99481,{'mp2':1}),
                (None,4710,99480,{}),(None,4710,99435,{})]
            for n,(level,hp,targethp,rounds) in enumerate(expected,1):
                if n==4:
                    a,c,b=f['state'](); c['enemies'][0]['creaturehealth']=173
                    f['patch'](badguy=f['encode'](c))
                body,_=f['cast'](level) if level else (f['fight'](),None)
                a,c,b=f['state']()
                self.assertEqual(hp,int(a['hitpoints']))
                self.assertEqual(targethp,c['enemies'][1 if n>=4 else 0]['creaturehealth'])
                self.assertEqual(rounds,{k:v['rounds'] for k,v in (b.items() if isinstance(b,dict) else [])})
                if n>=4:
                    self.assertEqual(0,c['enemies'][0]['creaturehealth']); self.assertTrue(c['enemies'][0]['dead'])
                    self.assertTrue(c['enemies'][1]['istarget'])
                self.assertEqual('1000',a['experience']); self.assertEqual('1000',a['gold'])
            self.assertEqual(29,f['uses']())

    def test_mystic_aura_companion_and_earth_fist_terminal(self):
        with self._mystic_progression_fixture() as f, self._specialty_terminal_capture() as terminal:
            f['prepare']('DA',badguy=f['combat'](100000,1,1))
            self.assertEqual(200,f['request'](data=f['form'](1))[0])
            companion=f['decode'](f['snapshot']()[0]['companions']); companion['skeleton_warrior']['hitpoints']=30
            for effect in ['aura','earth']:
                for outcome in ['victory','defeat']:
                    with self.subTest(effect=effect,outcome=outcome):
                        self.query('DELETE FROM fixture_specialty_terminal')
                        f['prepare_mp'](level=15,badguy=f['combat'](47,1,1),companions=f['encode'](companion),hitpoints=995,maxhitpoints=1000)
                        f['cast'](1); a,c,b=f['state']()
                        self.assertEqual(0,c['enemies'][0]['creaturehealth']); self.assertEqual(35,f['decode'](a['companions'])['skeleton_warrior']['hitpoints'])
                        b['mp1']['rounds']=1
                        if effect=='earth':
                            # Obtain the exact real producer before selecting a
                            # final-active-round boundary, as retained tests do.
                            f['cast'](2); a,c,produced=f['state'](); b['mp2']=produced['mp2']; b['mp2']['rounds']=1
                        c['enemies'][1].update(creatureattack=120 if effect=='earth' else 1000,creaturedefense=80,
                            creaturehealth=(16 if effect=='earth' else 40) if outcome=='victory' else 100000)
                        current=f['decode'](a['companions']); current['skeleton_warrior']['hitpoints']=30
                        f['patch'](badguy=f['encode'](c),bufflist=f['encode'](b),companions=f['encode'](current),hitpoints=1)
                        f['cast'](3); a,c,b=f['state'](); events=terminal()
                        self.assertEqual('',a['badguy']); self.assertEqual(['battle-'+outcome]*2,[x['hook'] for x in events])
                        self.assertEqual((16 if effect=='earth' else 56) if outcome=='victory' else 0,int(a['hitpoints']))
                        self.assertEqual(35,f['decode'](a['companions'])['skeleton_warrior']['hitpoints'])
                        self.assertNotIn('mp1',b); self.assertNotIn('mp2',b)
                        self.assertEqual(0 if outcome=='victory' else (99984 if effect=='earth' else 99960),events[1]['enemy']['creaturehealth'])
                        self.assertEqual(False,f['decode'](a['companions'])['skeleton_warrior']['used'])
                        self.assertEqual(34 if effect=='earth' else 36,f['uses']())

    def test_mystic_combined_final_round_simultaneous_terminal(self):
        # Regeneration then Lifetap adds exactly level+40 HP before 177 damage.
        # Shield returns 354. The one-HP target difference distinguishes defeat
        # from simultaneous victory without changing the historical formulas.
        for route in ['forest','dragon']:
            with self._specialty_accounting_fixture(route) as f, self._specialty_terminal_capture() as terminal:
                player=f['player']; encode=f['encode']; decode=f['decode']
                for targethp in [393,394,395]:
                    with self.subTest(route=route,targethp=targethp):
                        f['prepare']('MP',hitpoints=5000,maxhitpoints=10000)
                        for level in [1,3]: self.assertEqual(200,f['request'](data=f['form'](level))[0])
                        buffs=decode(f['snapshot']()[0]['bufflist'])
                        for key in ['mp1','mp3']: buffs[key]['rounds']=1
                        enemy=dict(f['enemy'],creaturehealth=targethp,creatureattack=1000)
                        if route=='forest': enemy.update(creaturegold=0,creatureexp=100)
                        self.query('UPDATE accounts SET badguy=?,bufflist=?,hitpoints=?,gold=1000,gems=10,experience=1000 WHERE acctid=?',
                            [encode({'enemies':[enemy],'options':{'type':route,'didsurprise':1}}),encode(buffs),127 if route=='forest' else 122,player])
                        self.query('DELETE FROM fixture_specialty_terminal')
                        old=f['form'](1); data=f['form'](5)
                        status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
                        after=f['snapshot']()[0]; events=terminal(); victory=targethp<=394
                        self.assertEqual(1,len(events)); self.assertEqual('battle-victory' if victory else 'battle-defeat',events[0]['hook'])
                        self.assertEqual(0,events[0]['hp']); self.assertEqual(targethp-394,events[0]['enemy']['creaturehealth'])
                        remaining=decode(after['bufflist']); self.assertNotIn('mp1',remaining); self.assertNotIn('mp3',remaining)
                        self.assertEqual(4,remaining['mp5']['rounds']); self.assertEqual([],decode(after['companions']))
                        self.assertEqual('0',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtymysticpower' AND setting='uses'",[player])[0]['value'])
                        self.assertEqual('1' if route=='forest' and victory else '0',after['hitpoints'])
                        self.assertEqual('1' if route=='forest' and victory else '0',after['alive'])
                        if route=='dragon' and victory:
                            self.assertIs(False,decode(after['badguy'])['dragonVictory'])
                            self.assertEqual(['1000','10','1000'],[after[k] for k in ['gold','gems','experience']])
                        else:
                            self.assertEqual('',after['badguy'])
                            self.assertEqual('1000' if victory else '0',after['gold'])
                            if not victory: self.assertEqual('900' if route=='forest' else '1000',after['experience'])
                        f['rejected'](data); f['rejected'](old); self.assertEqual(events,terminal())

    def test_thieving_combined_target_terminal_rollback(self):
        # Insult survives A's early weapon kill; Poison spends its offense
        # round. Its final remaining round then expires during B settlement.
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            player=f['player']; encode=f['encode']; decode=f['decode']
            self.query("UPDATE settings SET value='1' WHERE setting='forestgemchance'")
            for outcome in ['victory','defeat']:
                with self.subTest(outcome=outcome):
                    f['prepare']('TS',hitpoints=5000,maxhitpoints=10000)
                    self.assertEqual(200,f['request'](data=f['form'](1))[0])
                    a=dict(f['enemy'],creaturehealth=88,creatureexp=100,creaturegold=0,istarget=True)
                    b=dict(f['enemy'],creatureid=2,creaturename='Second Target',creatureattack=1000,creatureexp=200,creaturegold=0,istarget=False)
                    self.query('UPDATE accounts SET badguy=?,hitpoints=500,gold=1000,gems=10,experience=1000,turns=20 WHERE acctid=?',
                        [encode({'enemies':[a,b],'options':{'type':'forest','didsurprise':1,'maxattacks':1}}),player])
                    self.query('DELETE FROM fixture_specialty_terminal')
                    first=f['form'](2); self.assertEqual(200,f['request'](data=first)[0])
                    after=f['snapshot']()[0]; progressed=decode(after['badguy']); corpse=progressed['enemies'][0]; buffs=decode(after['bufflist'])
                    self.assertEqual(0,corpse['creaturehealth']); self.assertFalse(corpse['istarget']); self.assertTrue(corpse['dead'])
                    self.assertEqual(100000,progressed['enemies'][1]['creaturehealth']); self.assertTrue(progressed['enemies'][1]['istarget'])
                    self.assertEqual({'ts1':4,'ts2':4},{k:v['rounds'] for k,v in buffs.items()}); self.assertEqual([],terminal())
                    f['rejected'](first); stale=f['form'](1)
                    buffs['ts2']['rounds']=1
                    if outcome=='victory': progressed['enemies'][1]['creaturehealth']=88
                    self.query('UPDATE accounts SET badguy=?,bufflist=?,hitpoints=? WHERE acctid=?',[encode(progressed),encode(buffs),500 if outcome=='victory' else 70,player])
                    f['rejected'](stale); data=f['form'](1); before=f['snapshot']()
                    # Force failure after both target hooks and preference writes.
                    self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_combined_terminal CHECK (login <> 'WebPlayer' OR badguy <> '')")
                    try:
                        self.assertEqual(500,f['request'](data=data)[0]); self.assertEqual(before,f['snapshot']()); self.assertEqual([],terminal())
                        f['rejected'](data)
                    finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_combined_terminal')
                    final=f['form'](1); status,body=f['request'](data=final); self.assertEqual(200,status,body[:2000])
                    after=f['snapshot']()[0]; events=terminal(); remaining=decode(after['bufflist'])
                    self.assertEqual('',after['badguy']); self.assertEqual(['battle-'+outcome]*2,[e['hook'] for e in events])
                    self.assertEqual(0,events[0]['enemy']['creaturehealth']); self.assertEqual(0 if outcome=='victory' else 99912,events[1]['enemy']['creaturehealth'])
                    self.assertNotIn('ts2',remaining); self.assertEqual(5 if outcome=='victory' else 4,remaining['ts1']['rounds'])
                    expected=['1','500','1000','11','1150'] if outcome=='victory' else ['0','0','0','10','900']
                    self.assertEqual(expected,[after[k] for k in ['alive','hitpoints','gold','gems','experience']])
                    self.assertEqual('5',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtythiefskills' AND setting='uses'",[player])[0]['value'])
                    f['rejected'](final); f['rejected'](first); self.assertEqual(events,terminal())

    def test_dragon_effect_accounting_duration_and_corruption(self):
        cases=[('DA',2,120,4914,99737),('DA',3,1000,4911,99960),('DA',5,1000,5000,99951),
            ('MP',1,120,5015,99955),('MP',2,120,4914,99984),('MP',3,120,5045,99955),
            ('MP',5,1000,4823,99606),('TS',1,1000,4930,99960),('TS',2,120,5000,99907),
            ('TS',3,1000,5000,99942),('TS',5,1000,4896,99865)]
        with self._specialty_accounting_fixture('dragon') as f:
            def fight():
                status,body=f['request']('dragon.php?op=fight'); self.assertEqual(200,status,body[:2000])
                status,body=f['request']('dragon.php?op=fight',self._security_fields(body,'dragon.php?op=fight')); self.assertEqual(200,status,body[:2000])
            for spec,level,attack,hp,targethp in cases:
                with self.subTest(spec=spec,level=level):
                    state={'enemies':[dict(f['enemy'],creatureattack=attack)],'options':{'type':'dragon','didsurprise':1}}
                    f['prepare'](spec,badguy=f['encode'](state),hitpoints=5000,maxhitpoints=10000)
                    status,body=f['request'](data=f['form'](level)); self.assertEqual(200,status,body[:2000])
                    after=f['snapshot']()[0]
                    self.assertEqual(hp,int(after['hitpoints']),body[-2000:]); self.assertEqual(targethp,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
                    key=spec.lower()+str(level)
                    if key=='da2': self.assertNotIn(key,f['decode'](after['bufflist'])); continue
                    self.assertEqual(4,f['decode'](after['bufflist'])[key]['rounds'])
                    for remaining in [3,2,1,0,0]:
                        fight(); buffs=f['decode'](f['snapshot']()[0]['bufflist'])
                        if remaining: self.assertEqual(remaining,buffs[key]['rounds'])
                        else: self.assertNotIn(key,buffs)
                    self.assertEqual(str(9-level),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],f['modules'][spec],'uses'])[0]['value'])
            # Genuine persisted producers supply the starting schema for corruption tests.
            for spec,level in [('DA',3),('MP',1),('MP',2),('MP',3),('MP',5),('TS',1),('TS',2),('TS',3),('TS',5)]:
                f['prepare'](spec); self.assertEqual(200,f['request'](data=f['form'](level))[0])
                buffs=f['decode'](f['snapshot']()[0]['bufflist']); key=spec.lower()+str(level)
                for patch in [{'rounds':0},{'rounds':6},{'forged':1},{'schema':'forged'}]:
                    corrupt=dict(buffs); corrupt[key]=dict(buffs[key],**patch)
                    self.query('UPDATE accounts SET bufflist=? WHERE acctid=?',[f['encode'](corrupt),f['player']]); before=f['snapshot']()
                    for path,data in [('dragon.php?op=fight',None),('dragon.php?op=specialty',None),('dragon.php?op=specialty',{'level':str(level)})]:
                        self.assertEqual(409,f['request'](path,data)[0]); self.assertEqual(before,f['snapshot']())
                self.query('UPDATE accounts SET bufflist=? WHERE acctid=?',[f['encode'](buffs),f['player']])
            f['prepare']('DA'); self.assertEqual(200,f['request'](data=f['form'](1))[0])
            for encoded in ['broken',f['encode']({'skeleton_warrior':{'hitpoints':1}})]:
                self.query('UPDATE accounts SET companions=? WHERE acctid=?',[encoded,f['player']]); before=f['snapshot']()
                self.assertEqual(409,f['request']()[0]); self.assertEqual(before,f['snapshot']())

    def test_dragon_specialty_authority_matrix(self):
        with self._specialty_accounting_fixture('dragon') as f:
            anonymous=self._security_client(login=None); before=f['snapshot']()
            for op in ['begin','fight','specialty','prologue1','godmode','restart']:
                for fields in [None,{'level':'2','csrf_token':'forged','action_token':'forged'}]:
                    self.assertIn(anonymous('dragon.php?op='+op,fields)[0],[302,303,403]); self.assertEqual(before,f['snapshot']())
            for spec,module in f['modules'].items():
                for level in [1,2,3,5]:
                    with self.subTest(specialty=spec,level=level):
                        f['prepare'](spec); data=f['form'](level); stale=f['form'](level)
                        before=f['snapshot'](); status,body=f['request'](data=data)
                        self.assertEqual(200,status,body[:2000]); after=f['snapshot']()
                        self.assertNotEqual(before[0]['badguy'],after[0]['badguy'])
                        self.assertEqual(str(9-level),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],module,'uses'])[0]['value'])
                        buffs=f['decode'](after[0]['bufflist']); companions=f['decode'](after[0]['companions'])
                        if spec=='DA' and level==1:
                            self.assertTrue(companions==[] or companions['skeleton_warrior']['maxhitpoints']==60)
                        elif spec=='DA' and level==2: self.assertNotIn('da2',buffs)
                        else: self.assertEqual(4,buffs[spec.lower()+str(level)]['rounds'])
                        f['rejected'](data); f['rejected'](data); f['rejected'](stale)
                        self.assertEqual(200,f['request']('dragon.php?op=fight')[0]); self.assertEqual(after,f['snapshot']())
                    for case in ['wrong','none','inactive','skill','uses','zero','malformed','negative','excessive','missing','malformed-combat','zero-hp','negative-hp','dead','terminal','stale','csrf','invalid-csrf','unsupported','malformed-level','handler']:
                        with self.subTest(specialty=spec,level=level,case=case):
                            f['prepare'](spec); data=f['form'](level); expected=409
                            if case in ['wrong','none']:
                                self.query('UPDATE accounts SET specialty=? WHERE acctid=?',['' if case=='none' else ('MP' if spec=='DA' else 'DA'),f['player']])
                            elif case=='inactive': self.query('UPDATE modules SET active=0 WHERE modulename=?',[module])
                            elif case=='handler': self.query("UPDATE module_hooks SET `function`='missing_handler' WHERE modulename=? AND location='apply-specialties'",[module])
                            elif case in ['skill','uses','zero','malformed','negative','excessive']:
                                value={'skill':str(level-1),'uses':str(level-1),'zero':'0','malformed':'broken','negative':'-1','excessive':'9999999999'}[case]
                                self.query('UPDATE module_userprefs SET value=? WHERE userid=? AND modulename=? AND setting=?',[value,f['player'],module,'skill' if case=='skill' else 'uses'])
                            elif case in ['missing','malformed-combat']:
                                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',['' if case=='missing' else 'broken',f['player']])
                            elif case in ['zero-hp','negative-hp','dead','terminal','stale']:
                                change={'zero-hp':{'creaturehealth':0},'negative-hp':{'creaturehealth':-1},'dead':{'dead':True},'terminal':{'terminal':True},'stale':{'creaturename':'Another Dragon'}}[case]
                                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode']({'enemies':[dict(f['enemy'],**change)],'options':{'type':'dragon','didsurprise':1}}),f['player']])
                            elif case in ['csrf','invalid-csrf']:
                                if case=='csrf': del data['csrf_token']
                                else: data['csrf_token']='invalid'
                                expected=403
                            else: data['level']='4' if case=='unsupported' else '2.0'; expected=400
                            try: f['rejected'](data,expected)
                            finally:
                                self.query('UPDATE modules SET active=1 WHERE modulename=?',[module])
                                self.query("UPDATE module_hooks SET `function`=? WHERE modulename=? AND location='apply-specialties'",[module+'_dohook',module])
                    # Independent insufficient authority, using a currently valid lower-level form.
                    if level>1:
                        for key in ['skill','uses']:
                            f['prepare'](spec)
                            self.query('UPDATE module_userprefs SET value=? WHERE userid=? AND modulename=? AND setting=?',[str(level-1),f['player'],module,key])
                            f['rejected'](f['form'](level))

    def test_dragon_transitions_rewards_and_rollback(self):
        with self._specialty_accounting_fixture('dragon') as f, self._specialty_terminal_capture() as terminal:
            player=f['player']; request=f['request']; encode=f['encode']; decode=f['decode']
            def extra():
                return [f['snapshot'](),self.query('SELECT dragonkills,slaydragon,level,charm,dragonpoints,authversion FROM accounts WHERE acctid=?',[player]),
                    self.query('SELECT newsid FROM news WHERE accountid=? ORDER BY newsid',[player]),
                    self.query('SELECT id FROM debuglog WHERE actor=? ORDER BY id',[player]),terminal()]
            def combat(**changes): return encode({'enemies':[dict(f['enemy'],**changes)],'options':{'type':'dragon','didsurprise':1}})
            def ordinary(path='dragon.php?op=fight'):
                status,body=request(path); self.assertEqual(200,status,body[:2000])
                return self._security_fields(body,path)
            # Entry GET cannot replace combat or start the surprise round.
            f['prepare']('DA',badguy='',hitpoints=150,maxhitpoints=150,attack=100,defense=100)
            before=extra(); status,body=request('dragon.php'); self.assertEqual(200,status,body[:2000]); self.assertEqual(before,extra())
            begin=self._security_fields(body,'dragon.php?op=begin')
            for fields in [{},dict(begin,csrf_token='bad')]: self.assertEqual(403,request('dragon.php?op=begin',fields)[0]); self.assertEqual(before,extra())
            status,body=request('dragon.php?op=begin',begin); self.assertEqual(200,status,body[:2000])
            live=extra(); self.assertEqual('dragon',decode(f['snapshot']()[0]['badguy'])['options']['type'])
            self.assertEqual(409,request('dragon.php?op=begin',begin)[0]); self.assertEqual(live,extra())
            for op in ['','fight','run','specialty']:
                before=extra(); self.assertEqual(200,request('dragon.php'+('?op='+op if op else ''))[0]); self.assertEqual(before,extra())
            for query in ['op=fight&skill=DA&l=2','op=prologue1&flawless=1','op=fight&newtarget=0','op=fight&auto=full']:
                before=extra(); self.assertEqual(400,request('dragon.php?'+query)[0]); self.assertEqual(before,extra())
            # Stale identity includes prologue, completed kill, and different combat family.
            for changed in ['',combat(creaturehealth=0),encode({'dragonVictory':True,'dragonkills':0}),
                            encode({'enemies':[f['enemy']],'options':{'type':'forest'}})]:
                f['prepare']('DA'); data=f['form'](2)
                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[changed,player]); f['rejected'](data)
            f['prepare']('DA'); data=f['form'](2)
            self.query('UPDATE accounts SET dragonkills=1 WHERE acctid=?',[player]); f['rejected'](data)
            # Developer controls retain their historical capability behind POST and role checks.
            f['prepare']('DA',hitpoints=150,maxhitpoints=150,defense=100000)
            for op in ['godmode','restart']:
                before=extra(); self.assertEqual(409,request('dragon.php?op='+op)[0]); self.assertEqual(before,extra())
            self.query('UPDATE accounts SET superuser=2048 WHERE acctid=?',[player])
            status,body=request('dragon.php?op=fight'); self.assertEqual(200,status,body[:2000])
            restart=self._security_fields(body,'dragon.php?op=restart'); god=self._security_fields(body,'dragon.php?op=godmode')
            # Role removal invalidates a previously offered developer action.
            self.query('UPDATE accounts SET superuser=0 WHERE acctid=?',[player])
            before=extra(); self.assertEqual(409,request('dragon.php?op=godmode',god)[0]); self.assertEqual(before,extra())
            self.query('UPDATE accounts SET superuser=2048 WHERE acctid=?',[player])
            status,body=request('dragon.php?op=fight'); self.assertEqual(200,status,body[:2000])
            # Privilege changes rotate CSRF; obtain a current form after regrant.
            restart=self._security_fields(body,'dragon.php?op=restart')
            status,body=request('dragon.php?op=restart',restart); self.assertEqual(200,status,body[:2000])
            one=decode(f['snapshot']()[0]['badguy'])['options']['dragonEncounter']
            stale=f['form'](2); restart=self._security_fields(body,'dragon.php?op=restart')
            status,body=request('dragon.php?op=restart',restart); self.assertEqual(200,status,body[:2000])
            two=decode(f['snapshot']()[0]['badguy'])['options']['dragonEncounter']; self.assertNotEqual(one,two); f['rejected'](stale)
            # Forced failures after specialty preference writes and terminal news writes.
            for spec,level,terminal_round in [('DA',1,False),('MP',1,False),('TS',2,False),('DA',2,False),('DA',2,True)]:
                f['prepare'](spec,badguy=combat(creaturehealth=1 if terminal_round else 100000),hitpoints=150,maxhitpoints=150)
                data=f['form'](level); before=extra()
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_dragon_failure CHECK (login <> 'WebPlayer' OR badguy NOT LIKE '%"+('dragonVictory' if terminal_round else 'istarget')+"%')")
                try:
                    status,body=request(data=data); self.assertEqual(500,status,body[:2000]); self.assertEqual(before,extra())
                    f['rejected'](data)
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_dragon_failure')
                status,body=request(data=f['form'](level)); self.assertEqual(200,status,body[:2000])
            # Exact persisted, server-derived flawless outcome. Continuation is a separate one-use transaction.
            self.query('DELETE FROM fixture_specialty_terminal')
            f['prepare']('DA',badguy=combat(creaturehealth=1,creatureattack=1,creaturedefense=1),hitpoints=150,maxhitpoints=150,gold=1000,gems=10,charm=0)
            # Generate a real companion before the lethal Voodoo round.
            f['prepare']('DA',badguy=combat(creatureattack=1,creaturedefense=1),hitpoints=150,maxhitpoints=150,gold=1000,gems=10,charm=0)
            self.assertEqual(200,request(data=f['form'](1))[0]); skeleton=decode(f['snapshot']()[0]['companions'])
            self.assertIn('skeleton_warrior',skeleton)
            self.assertEqual(60,skeleton['skeleton_warrior']['hitpoints']); self.assertEqual(60,skeleton['skeleton_warrior']['maxhitpoints'])
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[combat(creaturehealth=1,creatureattack=1,creaturedefense=1),player])
            stale_fight=ordinary(); data=f['form'](2)
            status,body=request(data=data); self.assertEqual(200,status,body[:2000])
            outcome=decode(f['snapshot']()[0]['badguy']); self.assertEqual({'dragonVictory':True,'dragonkills':0}, {k:v for k,v in outcome.items() if k!='dragonEncounter'}); self.assertRegex(outcome['dragonEncounter'],r'^[a-f0-9]{32}$')
            self.assertEqual('6',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])[0]['value'])
            self.assertEqual(dict(skeleton['skeleton_warrior'],used=False),decode(f['snapshot']()[0]['companions'])['skeleton_warrior'])
            self.assertEqual('battle-victory',terminal()[-1]['hook'])
            continuation=self._security_fields(body,'dragon.php?op=prologue1'); before=extra()
            f['rejected'](data); self.assertEqual(409,request('dragon.php?op=fight',stale_fight)[0]); self.assertEqual(before,extra())
            self.assertEqual(200,request('dragon.php?op=prologue1')[0]); self.assertEqual(before,extra())
            for fields,code in [({},403),(dict(continuation,csrf_token='bad'),403),(dict(continuation,flawless='1'),400)]:
                self.assertEqual(code,request('dragon.php?op=prologue1',fields)[0]); self.assertEqual(before,extra())
            continuation=ordinary('dragon.php?op=prologue1')
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_dragon_reset CHECK (login <> 'WebPlayer' OR dragonkills <> 1)")
            try:
                status,body=request('dragon.php?op=prologue1',continuation); self.assertEqual(500,status,body[:2000]); self.assertEqual(before,extra())
                self.assertEqual(409,request('dragon.php?op=prologue1',continuation)[0]); self.assertEqual(before,extra())
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_dragon_reset')
            continuation=ordinary('dragon.php?op=prologue1')
            authversion=self.query('SELECT authversion FROM accounts WHERE acctid=?',[player])
            status,body=request('dragon.php?op=prologue1',continuation); self.assertEqual(200,status,body[:2000])
            self.assertEqual(authversion,self.query('SELECT authversion FROM accounts WHERE acctid=?',[player]))
            after=self.query('SELECT dragonkills,level,gold,gems,charm,specialty,badguy,bufflist,companions FROM accounts WHERE acctid=?',[player])[0]
            self.assertEqual(['1','1','250','11','5','',''],[after[k] for k in ['dragonkills','level','gold','gems','charm','specialty','badguy']])
            self.assertEqual([],decode(after['bufflist'])); self.assertEqual([],decode(after['companions']))
            for row in self.query("SELECT value FROM module_userprefs WHERE userid=? AND setting IN ('skill','uses') AND modulename IN ('specialtydarkarts','specialtymysticpower','specialtythiefskills')",[player]): self.assertEqual('0',row['value'])
            before=extra()
            for path,fields in [('dragon.php?op=prologue1',continuation),('dragon.php?op=specialty',data),('dragon.php?op=fight',stale_fight),('dragon.php?op=begin',begin)]:
                self.assertEqual(409,request(path,fields)[0]); self.assertEqual(before,extra())
            # Dragon defeat retains XP, loses all gold and clears combat exactly once.
            f['prepare']('MP',badguy=combat(creatureattack=100000,creaturedefense=100000),hitpoints=1,maxhitpoints=150,gold=1000,experience=1000)
            data=f['form'](3); status,body=request(data=data); self.assertEqual(200,status,body[:2000])
            after=f['snapshot']()[0]
            self.assertEqual(['0','0','0','1000',''],[after[k] for k in ['alive','hitpoints','gold','experience','badguy']])
            self.assertEqual('6',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtymysticpower' AND setting='uses'",[player])[0]['value'])
            self.assertEqual('battle-defeat',terminal()[-1]['hook']); before=extra(); f['rejected'](data); self.assertEqual(before,extra())
            # Historical Dragon simultaneous lethal shield victory still reaches
            # prologue at zero player HP. No Forest mushroom recovery is invented.
            for dragon_hp in [394,393]:
                self.query('DELETE FROM fixture_specialty_terminal')
                f['prepare']('MP',badguy=combat(creaturehealth=dragon_hp,creatureattack=1000),hitpoints=177,maxhitpoints=150,gold=1000,gems=10,charm=0)
                data=f['form'](5); status,body=request(data=data); self.assertEqual(200,status,body[:2000])
                after=f['snapshot']()[0]; self.assertEqual('0',after['hitpoints']); self.assertEqual('0',after['alive'])
                self.assertEqual(False,decode(after['badguy'])['dragonVictory'])
                self.assertEqual('battle-victory',terminal()[0]['hook']); self.assertEqual(dragon_hp-394,terminal()[0]['enemy']['creaturehealth'])
                continuation=self._security_fields(body,'dragon.php?op=prologue1')
                before=extra(); self.assertEqual(200,request('dragon.php?op=prologue1')[0]); self.assertEqual(before,extra())
                status,body=request('dragon.php?op=prologue1',continuation); self.assertEqual(200,status,body[:2000])
                result=self.query('SELECT dragonkills,hitpoints,alive,gold,gems,badguy,bufflist FROM accounts WHERE acctid=?',[player])[0]
                self.assertEqual(['1','10','1','100','10',''],[result[k] for k in ['dragonkills','hitpoints','alive','gold','gems','badguy']])
                self.assertEqual([],decode(result['bufflist'])); before=extra()
                self.assertEqual(409,request('dragon.php?op=prologue1',continuation)[0]); self.assertEqual(before,extra())


    def test_specialty_independent_level_authority(self):
        with self._specialty_accounting_fixture() as f:
            for spec,module in f['modules'].items():
                for level in [1,2,3,5]:
                    for case in ['wrong','none','inactive','skill','uses','zero','malformed','negative','excessive','missing','malformed-combat','zero-hp','negative-hp','dead','terminal','stale','csrf','invalid-csrf','unsupported','malformed-level']:
                        with self.subTest(specialty=spec,level=level,case=case):
                            f['prepare'](spec); data=f['form'](level); expected=409
                            if case in ['wrong','none']:
                                self.query('UPDATE accounts SET specialty=? WHERE acctid=?',['' if case=='none' else ('MP' if spec=='DA' else 'DA'),f['player']])
                            elif case=='inactive': self.query('UPDATE modules SET active=0 WHERE modulename=?',[module])
                            elif case in ['skill','uses','zero','malformed','negative','excessive']:
                                value={'skill':str(level-1),'uses':str(level-1),'zero':'0','malformed':'broken','negative':'-1','excessive':'9999999999'}[case]
                                self.query('UPDATE module_userprefs SET value=? WHERE userid=? AND modulename=? AND setting=?',[value,f['player'],module,'skill' if case=='skill' else 'uses'])
                            elif case in ['missing','malformed-combat']:
                                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',['' if case=='missing' else 'broken',f['player']])
                            elif case in ['zero-hp','negative-hp','dead','terminal','stale']:
                                change={'zero-hp':{'creaturehealth':0},'negative-hp':{'creaturehealth':-1},'dead':{'dead':True},'terminal':{'terminal':True},'stale':{'creaturehealth':99999}}[case]
                                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode']({'enemies':[dict(f['enemy'],**change)],'options':{'type':'forest','didsurprise':1}}),f['player']])
                            elif case in ['csrf','invalid-csrf']:
                                if case=='csrf': del data['csrf_token']
                                else: data['csrf_token']='invalid'
                                expected=403
                            else:
                                data['level']='4' if case=='unsupported' else '2.0'; expected=400
                            try: f['rejected'](data,expected)
                            finally: self.query('UPDATE modules SET active=1 WHERE modulename=?',[module])
                    # A valid intent against independently insufficient authority must
                    # fail too, not merely an intent made stale by a changed preference.
                    for key in ['skill','uses']:
                        f['prepare'](spec)
                        self.query('UPDATE module_userprefs SET value=? WHERE userid=? AND modulename=? AND setting=?',[str(level-1),f['player'],module,key])
                        if level==1:
                            status,body=f['request'](); self.assertEqual(200,status)
                            self.assertNotIn('name="action_token"',body)
                            continue
                        f['rejected'](f['form'](level))

    def test_specialty_module_availability_changes(self):
        with self._specialty_accounting_fixture() as f:
            for spec,module in f['modules'].items():
                registry=self.query('SELECT * FROM modules WHERE modulename=?',[module])[0]
                hook=self.query('SELECT * FROM module_hooks WHERE modulename=? AND location=?',[module,'apply-specialties'])[0]
                for level in [1,2,3,5]:
                    for case in ['uninstalled','inactive','file','handler','missing-hook','conditional-hook']:
                        with self.subTest(specialty=spec,level=level,case=case):
                            f['prepare'](spec); data=f['form'](level)
                            path=ROOT/'modules'/(module+'.php'); hidden=path.with_suffix('.fixture-unavailable')
                            try:
                                if case=='uninstalled': self.query('DELETE FROM modules WHERE modulename=?',[module])
                                elif case=='inactive': self.query('UPDATE modules SET active=0 WHERE modulename=?',[module])
                                elif case=='file': path.rename(hidden)
                                elif case=='missing-hook': self.query('DELETE FROM module_hooks WHERE modulename=? AND location=?',[module,'apply-specialties'])
                                else:
                                    field='function' if case=='handler' else 'whenactive'
                                    self.query('UPDATE module_hooks SET `'+field+'`=? WHERE modulename=? AND location=?',['invalid_handler' if case=='handler' else 'false',module,'apply-specialties'])
                                f['rejected'](data)
                                before=f['snapshot'](); status,body=f['request']()
                                self.assertEqual(409,status,body[:2000]); self.assertEqual(before,f['snapshot']())
                            finally:
                                if hidden.exists(): hidden.rename(path)
                                self.query('REPLACE INTO modules ('+','.join(registry)+') VALUES ('+','.join('?' for _ in registry)+')',list(registry.values()))
                                self.query('DELETE FROM module_hooks WHERE modulename=? AND location=?',[module,'apply-specialties'])
                                self.query('INSERT INTO module_hooks ('+','.join('`'+k+'`' for k in hook)+') VALUES ('+','.join('?' for _ in hook)+')',list(hook.values()))

    def test_specialty_nonexposing_callers_reject_injection(self):
        with self._specialty_accounting_fixture() as f:
            anonymous=self._security_client(login=None)
            for spec in f['modules']:
                f['prepare'](spec)
                for level in [1,2,3,5]:
                    for route in ['train.php','graveyard.php']:
                        for params in [{'skill':spec},{'l':str(level)},{'skill':spec,'l':str(level)},{'skill[]':spec,'l[]':str(level)}]:
                            for method in ['GET','POST']:
                                url=route+'?op=fight'+('&'+urllib.parse.urlencode(params) if method=='GET' else '')
                                data=None if method=='GET' else params
                                before=f['snapshot']()
                                self.assertEqual(400,f['request'](url,data)[0])
                                self.assertEqual(before,f['snapshot']())
                                self.assertEqual(400,anonymous(url,data)[0])
                                self.assertEqual(before,f['snapshot']())

    @contextmanager
    def _specialty_terminal_capture(self):
        # Observe the real terminal hook inside the same transaction, before the
        # caller clears combat. No outcome, RNG, HP or formula is substituted.
        module='resurrectionspecialtyobserver'
        path=ROOT/'modules'/(module+'.php')
        path.write_text('''<?php
function resurrectionspecialtyobserver_getmoduleinfo() { return ['name'=>'Fixture observer','version'=>'1','author'=>'Tests','category'=>'Tests']; }
function resurrectionspecialtyobserver_dohook($hook,$args) {
    global $session, $companions;
    db_query('INSERT INTO fixture_specialty_terminal (payload) VALUES (?)',true,
        [json_encode(['hook'=>$hook,'enemy'=>$args,'hp'=>$session['user']['hitpoints'],'buffs'=>$session['bufflist'],'companions'=>$companions],JSON_THROW_ON_ERROR)]);
    return $args;
}
''')
        self.query('CREATE TABLE fixture_specialty_terminal (payload LONGTEXT NOT NULL) ENGINE=InnoDB')
        self.query('INSERT INTO modules(modulename,active,version) VALUES (?,1,?)',[module,'1'])
        for hook in ['battle-victory','battle-defeat']:
            self.query('INSERT INTO module_hooks(modulename,location,`function`,whenactive,priority) VALUES (?,?,?,?,?)',[module,hook,module+'_dohook','',100])
        try:
            yield lambda: [json.loads(row['payload']) for row in self.query('SELECT payload FROM fixture_specialty_terminal')]
        finally:
            self.query('DELETE FROM module_hooks WHERE modulename=?',[module])
            self.query('DELETE FROM modules WHERE modulename=?',[module])
            self.query('DROP TABLE fixture_specialty_terminal')
            self.assertEqual([],self.query("SHOW TABLES LIKE 'fixture_specialty_terminal'"))
            path.unlink()
            self.assertFalse(path.exists())

    def test_specialty_adverse_defeat_accounting(self):
        # spec, level, enemy attack/defense, starting HP, exact terminal enemy
        # HP, retained rounds, and ordered historical combat messages.
        cases=[
            ('DA',1,1000,1000,1,100000,None,['RIPOSTED for 22 points']),
            ('DA',2,120,80,24,99737,None,['doll hurting it for 263 points','RIPOSTED for 24 points']),
            ('DA',3,1000,1000,11,100000,5,['RIPOSTED for 11 points']),
            ('DA',3,1000,80,89,99960,4,['You hit Accounting Target for 40 points','hits you for 89 points']),
            ('MP',1,1000,80,167,99960,4,['regenerate for 10 health','You hit Accounting Target for 40 points','hits you for 177 points']),
            ('MP',2,120,80,24,99999,4,['earth pummels Accounting Target for 1 points','RIPOSTED for 24 points']),
            ('MP',3,1000,1000,22,100000,4,['RIPOSTED for 22 points','weapon wails as you deal no damage']),
            ('MP',5,1000,80,177,99606,4,['You hit Accounting Target for 40 points','hits you for 177 points','hitting for 354 damage']),
            ('TS',1,1000,80,70,99960,4,['You hit Accounting Target for 40 points','hits you for 70 points']),
            ('TS',2,1000,80,177,99912,4,['You hit Accounting Target for 88 points','hits you for 177 points']),
            ('TS',3,1000,1000,22,100000,5,['RIPOSTED for 22 points']),
            ('TS',5,1000,80,104,99865,4,['You hit Accounting Target for 135 points','hits you for 104 points']),
        ]
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            for spec,level,attack,defense,hp,targethp,rounds,messages in cases:
                with self.subTest(specialty=spec,level=level,defense=defense):
                    self.query('DELETE FROM fixture_specialty_terminal')
                    combat={'enemies':[dict(f['enemy'],creatureattack=attack,creaturedefense=defense)],'options':{'type':'forest','didsurprise':1}}
                    f['prepare'](spec,hitpoints=hp,gold=1000,experience=1000,badguy=f['encode'](combat))
                    before=f['snapshot']()[0]; stale=f['form'](1); data=f['form'](level)
                    status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
                    after=f['snapshot']()[0]; events=terminal(); self.assertEqual(1,len(events)); event=events[0]
                    self.assertEqual('battle-defeat',event['hook']); self.assertEqual(0,event['hp'])
                    self.assertEqual(targethp,event['enemy']['creaturehealth']); self.assertTrue(event['enemy']['killedplayer'])
                    self.assertEqual(['0','0','0','900',''],[after[k] for k in ['hitpoints','alive','gold','experience','badguy']])
                    for key in ['gems','turns','attack','defense']: self.assertEqual(before[key],after[key])
                    self.assertEqual(str(9-level),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],f['modules'][spec],'uses'])[0]['value'])
                    buffs=f['decode'](after['bufflist']); key=spec.lower()+str(level)
                    if rounds is None: self.assertNotIn(key,buffs)
                    else: self.assertEqual(rounds,buffs[key]['rounds'])
                    companions=f['decode'](after['companions'])
                    if spec=='DA' and level==1:
                        self.assertEqual(43,companions['skeleton_warrior']['hitpoints'])
                        self.assertEqual(event['companions'],companions)
                    else: self.assertEqual([],companions)
                    text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                    offsets=[text.index(message) for message in messages]
                    self.assertEqual(sorted(offsets),offsets)
                    self.assertNotIn('You have slain',text)
                    if 'RIPOSTED' in messages[-1]: self.assertNotIn('Accounting Target hits you for',text)
                    f['rejected'](data); f['rejected'](data); f['rejected'](stale)
                    self.assertEqual(events,terminal())
                    self.assertEqual(200,f['request']('news.php')[0]); read=f['snapshot']()[0]
                    if spec=='DA' and level==1:
                        suspended=f['decode'](read['companions'])
                        self.assertEqual(dict(companions['skeleton_warrior'],suspended=True),suspended['skeleton_warrior'])
                        read['companions']=after['companions']
                    self.assertEqual(after,read)

    def test_specialty_shield_terminal_and_lifetap_adversity(self):
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            # A one-HP difference chooses live combat, real defeat, or historical
            # simultaneous lethal victory with the Forest mushroom recovery.
            for hp,targethp,endinghp,endingtarget,outcome in [
                (178,395,1,1,None),(177,395,0,1,'battle-defeat'),
                (178,394,1,0,'battle-victory'),(177,394,1,0,'battle-victory'),
                (1,393,1,-1,'battle-victory')]:
                with self.subTest(hp=hp,targethp=targethp):
                    self.query('DELETE FROM fixture_specialty_terminal')
                    f['prepare']('MP',hitpoints=hp,gold=1000,gems=0,experience=1000,badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1000,creaturehealth=targethp)],'options':{'type':'forest','didsurprise':1}}))
                    before=f['snapshot']()[0]; old=f['form'](1); data=f['form'](5)
                    status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000]); after=f['snapshot']()[0]
                    self.assertEqual(endinghp,int(after['hitpoints']))
                    self.assertEqual(4,f['decode'](after['bufflist'])['mp5']['rounds'])
                    self.assertEqual('4',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtymysticpower' AND setting='uses'",[f['player']])[0]['value'])
                    text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                    self.assertLess(text.index('hits you for 177 points'),text.index('hitting for 354 damage'))
                    events=terminal()
                    if outcome is None:
                        self.assertEqual([],events); self.assertEqual(endingtarget,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
                        for key in ['alive','gold','gems','experience','turns']: self.assertEqual(before[key],after[key])
                    else:
                        self.assertEqual(1,len(events)); self.assertEqual(outcome,events[0]['hook'])
                        self.assertEqual(endingtarget,events[0]['enemy']['creaturehealth']); self.assertEqual('',after['badguy'])
                        if outcome=='battle-victory':
                            self.assertEqual(['1','1008','1','1014'],[after[k] for k in ['alive','gold','gems','experience']])
                            self.assertEqual(1 if hp>177 else 0,events[0]['hp'])
                            self.assertEqual(hp<=177,'restorative properties' in text)
                        else: self.assertEqual(['0','0','0','900'],[after[k] for k in ['alive','gold','gems','experience']])
                    f['rejected'](data); f['rejected'](old); self.assertEqual(events,terminal())
            # Unsuccessful Lifetap cannot heal negative damage or compound it.
            f['prepare']('MP',hitpoints=500,badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1000,creaturedefense=1000)],'options':{'type':'forest','didsurprise':1}}))
            data=f['form'](3); status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
            after=f['snapshot']()[0]
            self.assertEqual(301,int(after['hitpoints'])) # 500 - 22 riposte - 177 attack
            self.assertEqual(100000,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
            self.assertEqual(4,f['decode'](after['bufflist'])['mp3']['rounds']); f['rejected'](data)

    def test_specialty_duration_consumes_active_phases(self):
        with self._specialty_accounting_fixture() as f:
            # Every persistent specialty buff through its natural last round.
            # DA1 uses the supported minion fallback; skeleton has no round TTL.
            self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'")
            for spec,levels in [('DA',[1,3,5]),('MP',[1,2,3,5]),('TS',[1,2,3,5])]:
                for level in levels:
                    with self.subTest(specialty=spec,level=level):
                        f['prepare'](spec,hitpoints=5000,maxhitpoints=10000)
                        data=f['form'](level); key=spec.lower()+str(level)
                        for round_number in range(1,6):
                            status,body=f['request'](data=data) if round_number==1 else self._ordinary_attack(f['request'])
                            self.assertEqual(200,status,body[:2000]); buffs=f['decode'](f['snapshot']()[0]['bufflist'])
                            if round_number<5: self.assertEqual(5-round_number,buffs[key]['rounds'])
                            else: self.assertNotIn(key,buffs)
                            self.assertEqual(str(9-level),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],f['modules'][spec],'uses'])[0]['value'])
                        self.assertEqual(200,self._ordinary_attack(f['request'])[0]); self.assertNotIn(key,f['decode'](f['snapshot']()[0]['bufflist']))
                        f['rejected'](data)
            # Wither Soul cannot cause ordinary enemy retaliation while active.
            # At one HP the player survives five rounds, then dies on round six.
            f['prepare']('DA',hitpoints=1,badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1000)],'options':{'type':'forest','didsurprise':1}}))
            data=f['form'](5)
            for n in range(5):
                self.assertEqual(200,(f['request'](data=data) if n==0 else self._ordinary_attack(f['request']))[0])
                self.assertEqual('1',f['snapshot']()[0]['hitpoints'])
            self.assertNotIn('da5',f['decode'](f['snapshot']()[0]['bufflist']))
            self.assertEqual(200,self._ordinary_attack(f['request'])[0]); self.assertEqual('0',f['snapshot']()[0]['alive']); f['rejected'](data)

    def test_specialty_buff_business_schema_rejects_corruption(self):
        with self._specialty_accounting_fixture() as f:
            self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'")
            for spec,levels in [('DA',[1,3,5]),('MP',[1,2,3,5]),('TS',[1,2,3,5])]:
                for level in levels:
                    f['prepare'](spec,hitpoints=5000,maxhitpoints=10000)
                    self.assertEqual(200,f['request'](data=f['form'](level))[0])
                    valid=f['decode'](f['snapshot']()[0]['bufflist']); key=spec.lower()+str(level)
                    old=f['form'](1)
                    missing=dict(valid[key]); del missing['rounds']
                    corrupt=[[],missing]
                    for field,value in [('rounds',0),('rounds',-1),('rounds',6),('atkmod','NaN'),('lifetap',999),('used',2),('suspended','yes'),('schema','forged'),('tempstat-attack',100),('effectmsg','<img src=x onerror=alert(1)>')]:
                        corrupt.append(dict(valid[key],**{field:value}))
                    encoded=[f['encode']({key:value}) for value in corrupt]
                    encoded += ['broken','O:8:"stdClass":0:{}',f['encode']('not a map'),f['encode']({key:None})]
                    for state in encoded:
                        with self.subTest(specialty=spec,level=level,state=state[:90]):
                            self.query('UPDATE accounts SET bufflist=? WHERE acctid=?',[state,f['player']])
                            before=f['snapshot']()
                            for path,data in [('forest.php?op=specialty',None),('forest.php?op=specialty',old),('forest.php?op=fight',None)]:
                                status,body=f['request'](path,data)
                                self.assertEqual(409,status,body[:2000]); self.assertIn('Invalid stored buff state',body)
                                self.assertEqual(before,f['snapshot']())
                    self.query('UPDATE accounts SET bufflist=? WHERE acctid=?',[f['encode'](valid),f['player']])
                    self.assertEqual(200,f['request']()[0])

    def test_specialty_final_round_victory_and_defeat(self):
        with self._specialty_accounting_fixture() as f, self._specialty_terminal_capture() as terminal:
            self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'")
            for spec,levels in [('DA',[1,3,5]),('MP',[1,2,3,5]),('TS',[1,2,3,5])]:
                for level in levels:
                    for outcome in ['victory','defeat']:
                        if spec=='DA' and level==5 and outcome=='defeat': continue # zero attack AND defense
                        with self.subTest(specialty=spec,level=level,outcome=outcome):
                            f['prepare'](spec,hitpoints=5000,maxhitpoints=10000)
                            data=f['form'](level); self.assertEqual(200,f['request'](data=data)[0])
                            buffs=f['decode'](f['snapshot']()[0]['bufflist']); key=spec.lower()+str(level)
                            buffs[key]['rounds']=1
                            target=dict(f['enemy'],creaturehealth=1 if outcome=='victory' else 100000,creatureattack=1 if outcome=='victory' else 1000,creaturedefense=1 if outcome=='victory' else 1000)
                            f['prepare'](spec,bufflist=f['encode'](buffs),hitpoints=500 if outcome=='victory' else 1,badguy=f['encode']({'enemies':[target],'options':{'type':'forest','didsurprise':1}}))
                            old=f['form'](1); before=f['snapshot']()[0]
                            status,body=self._ordinary_attack(f['request']); self.assertEqual(200,status,body[:2000])
                            after=f['snapshot']()[0]; remaining=f['decode'](after['bufflist'])
                            # Defense-only activation is skipped on early weapon victory
                            # or lethal riposte, though its modifier already affected the roll.
                            retained=(spec,level) in [('DA',3),('TS',1),('TS',3)]
                            if retained: self.assertEqual(1,remaining[key]['rounds'])
                            else: self.assertNotIn(key,remaining)
                            self.assertEqual('1' if outcome=='victory' else '0',after['alive'])
                            self.assertEqual('9',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],f['modules'][spec],'uses'])[0]['value'])
                            f['rejected'](data); f['rejected'](old)

    def test_specialty_gameplay_accounting(self):
        # Fixed RNG, actual producer + battle + committed HTTP result. These
        # explicit totals account for hits/ripostes as well as the named effect.
        cases=[
            ('DA',1,120,80,5000,99955,['Your skeleton warrior crumbles to dust.']),
            ('DA',2,120,80,4914,99737,['doll hurting it for 263 points!','RIPOSTED for 24 points','hits you for 62 points']),
            ('DA',3,1000,80,4911,99960,['You hit Accounting Target for 40 points','hits you for 89 points']),
            ('DA',5,1000,80,5000,99951,['You hit Accounting Target for 47 points','RIPOSTE for 2 points']),
            ('MP',1,120,80,5010,99955,['You regenerate for 10 health.']),
            ('MP',2,120,80,4914,99999,['earth pummels Accounting Target for 1 points.','RIPOSTED for 24 points','hits you for 62 points']),
            ('MP',3,120,80,5045,99955,['You are healed for 40 health.','You are healed for 5 health.']),
            ('MP',5,120,80,5000,99955,['slightly singed by your lightning, but otherwise unharmed.']),
            ('MP',5,1000,80,4823,99606,['hits you for 177 points','hitting for 354 damage.']),
            ('TS',1,1000,80,4930,99960,['hits you for 70 points']),
            ('TS',2,120,80,5000,99907,['You hit Accounting Target for 88 points','RIPOSTE for 5 points']),
            ('TS',3,1000,80,5000,99942,['You hit Accounting Target for 40 points','RIPOSTE for 18 points']),
            ('TS',5,120,80,5000,99823,['You hit Accounting Target for 135 points','RIPOSTE for 42 points']),
            ('TS',5,1000,80,4896,99865,['You hit Accounting Target for 135 points','hits you for 104 points']),
            ('DA',1,1,1,5000,99924,['Skeleton Warrior hits Accounting Target for 10 points','Skeleton Warrior RIPOSTES for 1 points']),
        ]
        with self._specialty_accounting_fixture() as f:
            for spec,level,attack,defense,hp,targethp,messages in cases:
                with self.subTest(specialty=spec,level=level,enemy_attack=attack,enemy_defense=defense):
                    combat={'enemies':[dict(f['enemy'],creatureattack=attack,creaturedefense=defense)],'options':{'type':'forest','didsurprise':1}}
                    f['prepare'](spec,badguy=f['encode'](combat),hitpoints=5000,maxhitpoints=10000)
                    before=f['snapshot']()[0]; data=f['form'](level)
                    status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
                    after=f['snapshot']()[0]; text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                    self.assertEqual(hp,int(after['hitpoints'])); self.assertEqual(targethp,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
                    for message in messages: self.assertIn(message,text)
                    for key in ['gold','gems','experience','attack','defense','alive']: self.assertEqual(before[key],after[key])
                    self.assertEqual(str(9-level),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],f['modules'][spec],'uses'])[0]['value'])
                    if spec=='DA' and level==1:
                        companions=f['decode'](after['companions'])
                        if attack==1: self.assertEqual(43,companions['skeleton_warrior']['hitpoints'])
                        else: self.assertEqual([],companions)
                    elif spec=='DA' and level==2: self.assertNotIn('da2',f['decode'](after['bufflist']))
                    else: self.assertEqual(4,f['decode'](after['bufflist'])[spec.lower()+str(level)]['rounds'])
                    f['rejected'](data); f['rejected'](data)
                    self.assertEqual(200,f['request']('village.php')[0]); self.assertEqual(after,f['snapshot']()[0])
            # Same deterministic unmodified damage roll: 177 incoming damage.
            # Curse rounds 177*0.5 to 89; shield reflects 177*2 = 354.
            f['prepare']('DA',badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1000)],'options':{'type':'forest','didsurprise':1}}),hitpoints=5000,maxhitpoints=10000)
            self.assertEqual(200,self._ordinary_attack(f['request'])[0])
            self.assertEqual(4823,int(f['snapshot']()[0]['hitpoints']))
            self.assertEqual(99960,f['decode'](f['snapshot']()[0]['badguy'])['enemies'][0]['creaturehealth'])

    def test_specialty_healing_aura_and_expiration(self):
        with self._specialty_accounting_fixture() as f:
            weak={'enemies':[dict(f['enemy'],creatureattack=1,creaturedefense=1)],'options':{'type':'forest','didsurprise':1}}
            f['prepare']('DA',badguy=f['encode'](weak))
            self.assertEqual(200,f['request'](data=f['form'](1))[0])
            skeleton=f['decode'](f['snapshot']()[0]['companions'])['skeleton_warrior']
            skeleton['hitpoints']=30
            f['prepare']('MP',badguy=f['encode'](weak),hitpoints=995,maxhitpoints=1000,companions=f['encode']({'skeleton_warrior':skeleton}))
            data=f['form'](1)
            for round_number in range(1,6):
                status,body=f['request'](data=data) if round_number==1 else self._ordinary_attack(f['request'])
                self.assertEqual(200,status,body[:2000]); after=f['snapshot']()[0]
                text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                self.assertLess(text.index('regenerate'),text.index('You hit Accounting Target'))
                self.assertIn('regenerates for '+str(3 if round_number<5 else 1)+' health due to your healing aura.',text)
                self.assertIn('You regenerate for 5 health.' if round_number==1 else 'You have no wounds to regenerate.',text)
                self.assertEqual('8',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],'specialtymysticpower','uses'])[0]['value'])
                self.assertEqual(1000,int(after['hitpoints']))
                self.assertEqual(min(43,30+3*round_number),f['decode'](after['companions'])['skeleton_warrior']['hitpoints'])
                buffs=f['decode'](after['bufflist'])
                if round_number<5: self.assertEqual(5-round_number,buffs['mp1']['rounds'])
                else: self.assertNotIn('mp1',buffs)
            self.query('UPDATE accounts SET hitpoints=990 WHERE acctid=?',[f['player']])
            self.assertEqual(200,self._ordinary_attack(f['request'])[0]); self.assertEqual(990,int(f['snapshot']()[0]['hitpoints']))
            f['rejected'](data)
            # Lifetap cannot heal above max HP or remove HP from a full player.
            for hp in [995,1000]:
                f['prepare']('MP',hitpoints=hp,maxhitpoints=1000)
                self.assertEqual(200,f['request'](data=f['form'](3))[0])
                self.assertEqual(1000,int(f['snapshot']()[0]['hitpoints']))

    def test_specialty_terminal_rewards_area_and_fallback(self):
        with self._specialty_accounting_fixture() as f:
            for hp in [263,262]:
                f['prepare']('DA',badguy=f['encode']({'enemies':[dict(f['enemy'],creaturehealth=hp)],'options':{'type':'forest','didsurprise':1}}))
                before=f['snapshot']()[0]; old=f['form'](1); data=f['form'](2)
                status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
                after=f['snapshot']()[0]; text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
                self.assertIn('doll hurting it for 263 points!',text)
                self.assertNotIn('You hit Accounting Target',text)
                self.assertEqual(14,int(after['experience'])-int(before['experience']))
                self.assertEqual(44,int(after['gold'])-int(before['gold']))
                self.assertEqual(1,int(after['turns'])-int(before['turns']))
                self.assertEqual(before['gems'],after['gems'])
                self.assertEqual('7',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],'specialtydarkarts','uses'])[0]['value'])
                self.assertEqual('',after['badguy']); self.assertEqual(before['hitpoints'],after['hitpoints'])
                f['rejected'](data); f['rejected'](old)
            f['prepare']('DA')
            self.query("UPDATE settings SET value='0' WHERE setting='enablecompanions'")
            data=f['form'](1); status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
            after=f['snapshot']()[0]
            self.assertEqual(491,int(after['hitpoints']))
            self.assertEqual(99985,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
            self.assertEqual([],f['decode'](after['companions']))
            self.assertEqual(4,f['decode'](after['bufflist'])['da1']['rounds'])
            f['rejected'](data)
            f['prepare']('MP',badguy=f['encode']({'enemies':[dict(f['enemy'],creatureattack=1,creaturedefense=1),dict(f['enemy'],creatureid=2,creaturename='Accounting Second',creatureattack=1,creaturedefense=1)],'options':{'type':'forest','didsurprise':1}}))
            old=f['form'](1); data=f['form'](2)
            status,body=f['request'](data=data); self.assertEqual(200,status,body[:2000])
            after=f['snapshot']()[0]; enemies=f['decode'](after['badguy'])['enemies']
            self.assertEqual([99986,99973],[e['creaturehealth'] for e in enemies])
            self.assertEqual([True,False],[e['istarget'] for e in enemies])
            self.assertEqual(500,int(after['hitpoints']))
            self.assertEqual(4,f['decode'](after['bufflist'])['mp2']['rounds'])
            self.assertEqual('7',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[f['player'],'specialtymysticpower','uses'])[0]['value'])
            text=re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',body)))
            self.assertIn('earth pummels Accounting Target for 1 points.',text)
            self.assertIn('earth pummels Accounting Second for 22 points.',text)
            f['rejected'](data); f['rejected'](old)
            # A server-side target change invalidates the complete state-bound form.
            data=f['form'](2); combat=f['decode'](after['badguy'])
            combat['enemies'][0]['istarget']=False; combat['enemies'][1]['istarget']=True
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[f['encode'](combat),f['player']])
            f['rejected'](data)
            # This proves live-target binding, not dead-target progression.


    def test_darkarts_companion_business_state_http(self):
        # Real Forest POST action proves the producer/consumer representation.
        # Broader route and complete module certification remain separate blockers.
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        settings=self.query('SELECT * FROM settings WHERE setting=?',['enablecompanions'])
        registry=self.query('SELECT modulename,active FROM modules')
        self.query('UPDATE modules SET active=0')
        self.query("UPDATE modules SET active=1 WHERE modulename='specialtydarkarts'")
        call=self._security_client()
        def request(url,form=None,fixture=None): self._security_allow(player,url); return call(url,form,fixture=fixture)
        def encode(value):
            return subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(value),text=True,capture_output=True,cwd=ROOT,check=True).stdout
        def companions():
            encoded=self.query('SELECT companions FROM accounts WHERE acctid=?',[player])[0]['companions']
            code="require 'src/Security/ScalarState.php'; echo json_encode(\\Resurrection\\Security\\ScalarState::read(stream_get_contents(STDIN)),JSON_THROW_ON_ERROR);"
            return json.loads(subprocess.run([shutil.which('php'),'-r',code],input=encoded,text=True,capture_output=True,cwd=ROOT,check=True).stdout)
        def snapshot():
            return self.query('SELECT gold,gems,experience,hitpoints,badguy,companions,bufflist FROM accounts WHERE acctid=?',[player]) + self.query("SELECT setting,value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' ORDER BY setting",[player])
        try:
            self.query("INSERT INTO settings(setting,value) VALUES ('enablecompanions','1') ON DUPLICATE KEY UPDATE value='1'")
            enemy=self.query('SELECT * FROM creatures ORDER BY creatureid LIMIT 1')[0]
            enemy.update(creaturehealth=1000000,creatureattack=1,creaturedefense=0,creaturelevel=10,playerstarthp=10000,diddamage=0)
            self.query("UPDATE accounts SET level=10,alive=1,race='Human',specialty='DA',dragonkills=0,dragonpoints='a:0:{}',badguy=?,companions='a:0:{}',bufflist='a:0:{}',hitpoints=10000,maxhitpoints=10000,attack=1,defense=10000,specialinc='' WHERE acctid=?",[encode({'enemies':[enemy],'options':{'type':'forest'}}),player])
            for key,value in [('skill','15'),('uses','5')]:
                self.query("INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES ('specialtydarkarts',?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)",[key,player,value])
            status,body=request('forest.php?op=specialty'); self.assertEqual(200,status,body[:2000])
            form=self._security_fields(body)|{'level':'1'}
            status,body=request('forest.php?op=specialty',form); self.assertEqual(200,status,body[:2000])
            skeleton=companions()['skeleton_warrior']
            consumed=snapshot()
            self.assertEqual(409,request('forest.php?op=specialty',form)[0]); self.assertEqual(consumed,snapshot())
            self.assertEqual(43,skeleton['maxhitpoints']); self.assertGreater(skeleton['hitpoints'],0)
            self.assertEqual(26.5,skeleton['attack']); self.assertEqual(14.5,skeleton['defense'])
            self.assertEqual({'fight':True},skeleton['abilities']); self.assertIs(skeleton['used'],True)
            self.assertEqual('4',self.query("SELECT value FROM module_userprefs WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])[0]['value'])
            call=self._security_client(); self.assertEqual(200,request('village.php')[0]); self.assertEqual(skeleton,companions()['skeleton_warrior'])
            # Voodoo's positive minimum damage deterministically ends a one-HP fight.
            # A living skeleton has no expireafterfight flag and must survive victory.
            terminal={'enemies':[dict(enemy,creaturehealth=1)],'options':{'type':'forest'}}
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encode(terminal),player])
            status,body=request('forest.php?op=specialty'); self.assertEqual(200,status,body[:2000])
            victoryform=self._security_fields(body)|{'level':'2'}
            status,body=request('forest.php?op=specialty',victoryform); self.assertEqual(200,status,body[:2000])
            self.assertEqual('',self.query('SELECT badguy FROM accounts WHERE acctid=?',[player])[0]['badguy'])
            retained=companions()['skeleton_warrior']; self.assertEqual(skeleton['hitpoints'],retained['hitpoints'])
            self.assertEqual(skeleton['attack'],retained['attack']); before=snapshot()
            self.assertEqual(409,request('forest.php?op=specialty',victoryform)[0]); self.assertEqual(before,snapshot())
            self.assertEqual(200,request('newday.php?continue=1')[0]); self.assertEqual(retained,companions()['skeleton_warrior'])
            # A fixed RNG seed drives the real companion damage/removal path.
            lethal={'enemies':[dict(enemy,creaturehealth=100000000,creatureattack=100000,creaturedefense=100000)],'options':{'type':'forest'}}
            wounded=dict(skeleton,hitpoints=1)
            self.query('UPDATE accounts SET badguy=?,companions=?,hitpoints=1000000000,maxhitpoints=1000000000,attack=1,defense=1 WHERE acctid=?',[encode(lethal),encode({'skeleton_warrior':wounded}),player])
            status,body=request('forest.php?op=specialty'); self.assertEqual(200,status,body[:2000])
            deathform=self._security_fields(body)|{'level':'3'}
            status,body=request('forest.php?op=specialty',deathform,fixture='skeleton-death')
            self.assertEqual(200,status,body[:2000]); self.assertIn('Your skeleton warrior crumbles to dust.',body)
            self.assertEqual([],companions()); before=snapshot()
            self.assertEqual(409,request('forest.php?op=specialty',deathform,fixture='skeleton-death')[0]); self.assertEqual(before,snapshot())
            status,body=request('forest.php?op=specialty'); self.assertEqual(200,status)
            self.assertEqual(200,request('forest.php?op=specialty',self._security_fields(body)|{'level':'2'},fixture='skeleton-death')[0]); self.assertEqual([],companions())
            # Return to a live encounter before injecting malformed persisted companions.
            self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encode({'enemies':[enemy],'options':{'type':'forest'}}),player])
            # Valid persistent runtime flags and injury survive read-only hydration.
            for flags in [{'used':False,'suspended':True},{'used':True,'suspended':False}]:
                state=dict(skeleton,hitpoints=1,**flags)
                self.query('UPDATE accounts SET companions=? WHERE acctid=?',[encode({'skeleton_warrior':state}),player])
                self.assertEqual(200,request('village.php')[0]); self.assertEqual(state,companions()['skeleton_warrior'])
            malformed=[None,{},dict(skeleton,hitpoints=0),dict(skeleton,hitpoints=-1),dict(skeleton,hitpoints=44),
                dict(skeleton,hitpoints='43'),dict(skeleton,maxhitpoints=2147483648),dict(skeleton,attack=27.5),
                dict(skeleton,defense=[]),dict(skeleton,used=1),dict(skeleton,suspended='1'),
                dict(skeleton,rounds=1),dict(skeleton,cannotdie=True),dict(skeleton,expireafterfight=True),
                dict(skeleton,abilities={'fight':True,'magic':100}),dict(skeleton,extra={'nested':1})]
            for key in ['name','hitpoints','maxhitpoints','attack','defense','abilities','ignorelimit','dyingtext']:
                state=dict(skeleton); del state[key]; malformed.append(state)
            payloads=[encode({'skeleton_warrior':state}) for state in malformed]
            payloads += ['broken','a:0:{}junk','O:8:"stdClass":0:{}','a:1:{s:16:"skeleton_warrior";O:8:"stdClass":0:{}}',encode('wrong root')]
            for payload in payloads:
                with self.subTest(payload=payload[:100]):
                    self.query('UPDATE accounts SET companions=? WHERE acctid=?',[payload,player]); before=snapshot()
                    for fields in [None,{'skill':'DA','l':'1','companions':'forged'}]:
                        status,body=request('forest.php?op=specialty',fields)
                        self.assertEqual(409,status,body[:1000]); self.assertEqual(before,snapshot())
            # Recovery is explicit fixture/admin repair, never silent state deletion.
            self.query('UPDATE accounts SET companions=? WHERE acctid=?',[encode({'skeleton_warrior':skeleton}),player])
            self.assertEqual(200,request('village.php')[0]); self.assertEqual(skeleton,companions()['skeleton_warrior'])
            # Retained Dragon lifecycle, through a real win and its generated continuation.
            # This is NOT certification of the still-legacy Dragon HTTP authority.
            dragon={'creaturename':'The Green Dragon','creaturelevel':18,'creatureweapon':'Great Flaming Maw',
                'creatureattack':1,'creaturedefense':1,'creaturehealth':1,'diddamage':0,'type':'dragon'}
            self.query("UPDATE accounts SET badguy=?,level=15,hitpoints=150,maxhitpoints=150,attack=1,defense=1,bufflist='a:0:{}' WHERE acctid=?",[encode(dragon),player])
            self.query("UPDATE module_userprefs SET value='5' WHERE userid=? AND modulename='specialtydarkarts' AND setting='uses'",[player])
            status,body=request('dragon.php?op=specialty'); self.assertEqual(200,status,body[:2000])
            forms=[part for action,part in re.findall(r'<form[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S) if action=='dragon.php?op=specialty']
            status,body=request('dragon.php?op=specialty',self._security_fields(forms[0])|{'level':'2'}); self.assertEqual(200,status,body[:2000])
            self.assertIn('skeleton_warrior',companions())
            link=re.search(r'dragon\.php\?op=prologue1',body)
            self.assertIsNotNone(link,body[:2000]); self.assertEqual(200,request('dragon.php?op=prologue1',self._security_fields(body,'dragon.php?op=prologue1'))[0])
            self.assertEqual([],companions()); self.assertEqual('1',self.query('SELECT dragonkills FROM accounts WHERE acctid=?',[player])[0]['dragonkills'])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            self.query("DELETE FROM settings WHERE setting='enablecompanions'")
            for row in settings: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])
            for row in registry: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])


    def test_fairy_settings_and_dragon_carry_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        self.query('UPDATE modules SET active=1'); call=self._security_client()
        settings=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['fairy'])
        def request(url,form=None): self._security_allow(player,url); return call(url,form)
        try:
            for carry in [1,0]:
                self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
                self.query("UPDATE accounts SET alive=1,level=15,dragonkills=0,dragonpoints='a:0:{}',maxhitpoints=165,hitpoints=165,gems=100,bufflist='a:0:{}',attack=100000,defense=100000,race='Human',specialty='DA',specialinc='' WHERE acctid=?",[player])
                for module in ['cedrikspotions','fairy']:
                    self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[module,'extrahps',player,'15' if module=='fairy' else '0'])
                # Save Fairy's actual declared settings through the certified editor.
                self.query('UPDATE accounts SET superuser=128 WHERE acctid=?',[player]); call=self._security_client()
                base='configuration.php?op=modulesettings&module=fairy'; save=base+'&save=1'
                for invalid in [{'carrydk':'2'},{'hptoaward':'0'},{'hptoaward':'6'},{'fftoaward':'6'}]:
                    _,body=request(base); form=self._security_fields(body,save)
                    self.assertEqual(400,request(save,{**form,**invalid})[0])
                _,body=request(base); form={**self._security_fields(body,save),'carrydk':str(carry),'hptoaward':'5','fftoaward':'5'}
                self.assertEqual(200,request(save,form)[0]); self.assertEqual(409,request(save,form)[0])
                self.query('UPDATE accounts SET superuser=0 WHERE acctid=?',[player]); call=self._security_client()
                enemy={'creaturename':'Synthetic Green Dragon','creatureweapon':'Padded stick','creaturelevel':18,'creatureattack':1,'creaturedefense':1,'creaturehealth':1,'diddamage':0,'type':'dragon'}
                encoded=subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(enemy),text=True,capture_output=True,cwd=ROOT,check=True).stdout
                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encoded,player])
                # Exercise the real battle with a deterministic roll, not a probabilistic victory.
                with self._seeded_module_actions('header-dragon') as seed:
                    seed(0)
                    status,body=request('dragon.php?op=fight'); self.assertEqual(200,status,body[:1500])
                    status,body=request('dragon.php?op=fight',self._security_fields(body,'dragon.php?op=fight')); self.assertEqual(200,status,body[:1500])
                link=re.search(r'href=[\'"](dragon.php\?op=prologue1[^\'"]*)',body); self.assertIsNotNone(link,body[:1500])
                status,body=request(html.unescape(link.group(1)),self._security_fields(body,'dragon.php?op=prologue1')); self.assertEqual(200,status,body[:1500])
                state=self.query('SELECT dragonkills,maxhitpoints,bufflist FROM accounts WHERE acctid=?',[player])[0]
                self.assertEqual('1',state['dragonkills']); self.assertEqual(str(25 if carry else 10),state['maxhitpoints']); self.assertNotIn('transmute',state['bufflist'])
                self.assertEqual(str(15 if carry else 0),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,'fairy','extrahps'])[0]['value'])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            self.query('DELETE FROM module_settings WHERE modulename=?',['fairy'])
            for row in settings: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',['fairy',row['setting'],row['value']])
            self.query('UPDATE modules SET active=0')

    def test_potions_dragon_reset_persistence_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT * FROM module_userprefs WHERE userid=?',[player])
        self.query('UPDATE modules SET active=1'); call=self._security_client()
        settings=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['cedrikspotions'])
        def request(url,form=None): self._security_allow(player,url); return call(url,form)
        def setting(key,value): self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['cedrikspotions',key,str(value)])
        def buy(wish):
            base='runmodule.php?module=cedrikspotions&op=gems'; _,body=request(base)
            url=html.unescape(re.search(r'<form action=[\'"]([^\'"]+)',body).group(1))
            status,body=request(url,dict(self._security_fields(body),wish=str(wish),gemcount='10')); self.assertEqual(200,status,body[:1500])
        try:
            for carry in [1,0]:
                self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
                self.query("UPDATE accounts SET alive=1,level=15,dragonkills=0,dragonpoints='a:0:{}',maxhitpoints=150,hitpoints=150,gems=100,bufflist='a:0:{}',attack=100000,defense=100000,race='Human',specialty='DA',specialinc='' WHERE acctid=?",[player])
                for module in ['cedrikspotions','fairy']:
                    self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[module,'extrahps',player,'0'])
                for key,value in {'carrydk':carry,'random':0,'maxcost':2,'vitalgain':3,'transcost':2,'transmuteturns':20,'survive':1,'atkmod':'.5','defmod':'.75'}.items(): setting(key,value)
                call=self._security_client(); buy(2); buy(5)
                self.assertEqual('165',self.query('SELECT maxhitpoints FROM accounts WHERE acctid=?',[player])[0]['maxhitpoints'])
                self.assertEqual('15',self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,'cedrikspotions','extrahps'])[0]['value'])
                enemy={'creaturename':'Synthetic Green Dragon','creatureweapon':'Padded stick','creaturelevel':18,'creatureattack':1,'creaturedefense':1,'creaturehealth':1,'diddamage':0,'type':'dragon'}
                encoded=subprocess.run([shutil.which('php'),'-r','echo serialize(json_decode(stream_get_contents(STDIN),true));'],input=json.dumps(enemy),text=True,capture_output=True,cwd=ROOT,check=True).stdout
                self.query('UPDATE accounts SET badguy=? WHERE acctid=?',[encoded,player])
                # Exercise the real battle with a deterministic roll, not a probabilistic victory.
                with self._seeded_module_actions('header-dragon') as seed:
                    seed(0)
                    status,body=request('dragon.php?op=fight'); self.assertEqual(200,status,body[:1500])
                    status,body=request('dragon.php?op=fight',self._security_fields(body,'dragon.php?op=fight')); self.assertEqual(200,status,body[:1500])
                link=re.search(r'href=[\'"](dragon.php\?op=prologue1[^\'"]*)',body); self.assertIsNotNone(link,body[:1500])
                status,body=request(html.unescape(link.group(1)),self._security_fields(body,'dragon.php?op=prologue1')); self.assertEqual(200,status,body[:1500])
                state=self.query('SELECT dragonkills,maxhitpoints,bufflist FROM accounts WHERE acctid=?',[player])[0]
                self.assertEqual('1',state['dragonkills']); self.assertEqual(str(25 if carry else 10),state['maxhitpoints']); self.assertNotIn('transmute',state['bufflist'])
                self.assertEqual(str(15 if carry else 0),self.query('SELECT value FROM module_userprefs WHERE userid=? AND modulename=? AND setting=?',[player,'cedrikspotions','extrahps'])[0]['value'])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original if k!='acctid')+' WHERE acctid=?',[*[v for k,v in original.items() if k!='acctid'],player])
            self.query('DELETE FROM module_userprefs WHERE userid=?',[player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',[row['modulename'],row['setting'],player,row['value']])
            self.query('DELETE FROM module_settings WHERE modulename=?',['cedrikspotions'])
            for row in settings: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',['cedrikspotions',row['setting'],row['value']])
            self.query('UPDATE modules SET active=0')

    def test_darkhorse_mounted_entry_and_exit_http(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT hashorse,specialinc,specialmisc,gold,superuser,turns FROM accounts WHERE acctid=?',[player])[0]
        forest_saved=self.query('SELECT value FROM settings WHERE setting=?',['forestchance'])
        saved=self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['darkhorse','tavernname'])
        self.query('UPDATE modules SET active=1')
        self.query('INSERT INTO mounts(mountname,mountcategory) VALUES (?,?)',['Tavern fixture','Fixture'])
        ident=self.query('SELECT mountid FROM mounts WHERE mountname=?',['Tavern fixture'])[0]['mountid']
        call=self._security_client(); url='runmodule.php?module=darkhorse&op=enter'
        def request(path,data=None): self._security_allow(player,path); return call(path,data)
        def state(): return self.query('SELECT hashorse,specialinc,specialmisc,gold FROM accounts WHERE acctid=?',[player])
        def preference(value): self.query('INSERT INTO module_objprefs(modulename,objtype,setting,objid,value) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['darkhorse','mounts','findtavern',ident,str(value)])
        try:
            self.query("UPDATE accounts SET hashorse=0,specialinc='',specialmisc='',gold=1000 WHERE acctid=?",[player])
            self.assertIn(self._security_client(login=None)(url)[0],[302,303,403])
            self.assertEqual(403,request(url+'&mountid='+str(ident)+'&findtavern=1')[0])
            self.query('UPDATE accounts SET hashorse=? WHERE acctid=?',[ident,player])
            for value in ['0','2','true','1e0']:
                preference(value); before=state(); self.assertEqual(403,request(url)[0]); self.assertEqual(before,state())
            preference(1); before=state(); status,body=request(url); self.assertEqual(200,status); self.assertEqual(before,state())
            form=self._security_fields(body,url)
            for bad in [{},dict(form,csrf_token='bad')]: self.assertEqual(403,request(url,bad)[0]); self.assertEqual(before,state())
            # Authoritative object changes invalidate an already rendered entrance.
            self.query('UPDATE mounts SET mountname=? WHERE mountid=?',['Changed Tavern fixture',ident]); self.assertEqual(409,request(url,form)[0])
            _,body=request(url); form=self._security_fields(body,url)
            preference(0); self.assertEqual(403,request(url,form)[0]); preference(1)
            # The same typed settings editor supplies the real displayed tavern name.
            self.query('UPDATE accounts SET superuser=128 WHERE acctid=?',[player]); call=self._security_client()
            edit='configuration.php?op=modulesettings&module=darkhorse'; save=edit+'&save=1'
            _,body=request(edit); title='Configured Tavern <script>safe</script>'
            self.assertEqual(200,request(save,dict(self._security_fields(body,save),tavernname=title))[0])
            self.query('UPDATE accounts SET superuser=0 WHERE acctid=?',[player]); call=self._security_client()
            _,body=request(url); form=dict(self._security_fields(body,url),mountid='999',findtavern='0',gold='0')
            status,body=request(url,form); self.assertEqual(200,status,body[:2000]); self.assertIn('Configured Tavern',body); self.assertNotIn('<script>safe</script>',body)
            self.assertEqual('module:darkhorse',state()[0]['specialinc']); self.assertEqual('1000',state()[0]['gold']); self.assertEqual(409,request(url,form)[0])
            leave='forest.php?op=leave'; before=state(); _,body=request(leave); self.assertEqual(before,state()); form=self._security_fields(body,leave)
            self.assertEqual(403,request(leave,{})[0]); self.assertEqual(before,state())
            self.assertEqual(200,request(leave,form)[0]); self.assertEqual('',state()[0]['specialinc']); after=state()
            # After the event has ended, the same POST cannot re-enter/charge/reward.
            request(leave,form); self.assertEqual(after,state())
            # The real Forest selector discovers the encounter without a tavern mount.
            self.query('UPDATE modules SET active=0'); self.query('UPDATE modules SET active=1 WHERE modulename=?',['darkhorse'])
            self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['forestchance','100'])
            self.query("UPDATE accounts SET hashorse=0,specialinc='',turns=10 WHERE acctid=?",[player])
            _,body=request('forest.php?eventhandler=module:darkhorse&op=tavern'); self.assertEqual('',state()[0]['specialinc'])
            status,body=request('forest.php?op=search'); self.assertEqual(200,status,body[:1000])
            status,body=request('forest.php?op=search',self._security_fields(body,'forest.php?op=search')); self.assertEqual(200,status,body[:1000])
            status,body=request('forest.php'); self.assertEqual(200,status,body[:1000]); self.assertIn('cluster of trees',body)
            self.assertEqual('module:darkhorse',state()[0]['specialinc']); self.assertEqual('1000',state()[0]['gold'])
            _,body=request('forest.php?op=tavern'); self.assertIn('Configured Tavern',body)
            leave='forest.php?op=leaveleave'; _,body=request(leave); form=self._security_fields(body,leave)
            self.assertEqual(200,request(leave,form)[0]); self.assertEqual('',state()[0]['specialinc'])
            self.query('UPDATE accounts SET hashorse=? WHERE acctid=?',[ident,player]); _,body=request(url); form=self._security_fields(body,url)
            self.query('DELETE FROM mounts WHERE mountid=?',[ident]); self.assertEqual(403,request(url,form)[0])
        finally:
            self.query('DELETE FROM settings WHERE setting=?',['forestchance'])
            if forest_saved: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',['forestchance',forest_saved[0]['value']])
            self.query('DELETE FROM module_objprefs WHERE objtype=? AND objid=?',['mounts',ident]); self.query('DELETE FROM mounts WHERE mountid=?',[ident])
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            if saved: self.query('UPDATE module_settings SET value=? WHERE modulename=? AND setting=?',[saved[0]['value'],'darkhorse','tavernname'])
            self.query('UPDATE modules SET active=0')


    def _security_client(self, login='WebPlayer', password="Synthetic web O'Reilly \\ password"):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect)
        def request(url, data=None, fixture=None):
            req=urllib.request.Request(f'http://127.0.0.1:{self.port}/'+url,
                data=None if data is None else urllib.parse.urlencode(data).encode(),
                headers={} if fixture is None else {'X-Resurrection-Fixture':fixture})
            try: response=client.open(req,timeout=20)
            except urllib.error.HTTPError as error: response=error
            body=response.read().decode('utf-8',errors='replace')
            self.assertNotRegex(body,r'(?i)(fatal error|warning:|deprecated:|notice:)',body[:2000])
            if response.status == 500:
                self.server_log.seek(0)
                body += '\n'.join(self.server_log.read().splitlines()[-8:])
            return response.status,body
        if login:
            _,body=request('home.php')
            csrf=re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
            self.assertEqual(303,request('login.php',{'csrf_token':csrf,'name':login,'password':password})[0])
        return request

    def _security_allow(self, player, url):
        value='a:1:{s:'+str(len(url))+':"'+url+'";b:1;}'
        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[value,player])

    def _security_fields(self, body, action=None):
        if action:
            forms=re.findall(r'<form\b[^>]*action=[\'"]([^\'"]+)[\'"][^>]*>(.*?)</form>',body,re.S|re.I)
            matches=[part for url,part in forms if html.unescape(url)==action]
            self.assertEqual(1,len(matches),action+' '+body[:2000]); body=matches[0]
        result={}
        for key in ['csrf_token','action_token']:
            match=re.search(r'name=[\'"]'+key+r'[\'"] value=[\'"]([a-f0-9]{64})',body)
            self.assertIsNotNone(match,body[:2000]); result[key]=match.group(1)
        return result

    def _security_target(self, login):
        source=self.query('SELECT * FROM accounts WHERE login=?',['FixtureAdmin'])[0]
        source.pop('acctid'); source.update(login=login,name="`2O'Reilly \\ 雪 %_! <img src=x>",superuser='0',loggedin='0')
        columns=list(source)
        self.query('INSERT INTO accounts ('+','.join('`'+key+'`' for key in columns)+') VALUES ('+','.join('?' for _ in columns)+')',list(source.values()))
        ident=self.query('SELECT acctid FROM accounts WHERE login=?',[login])[0]['acctid']
        self.query("INSERT INTO accounts_output(acctid,output) VALUES (?,'')",[ident])
        return ident

    @contextmanager
    def _pvp_fixture(self):
        with self._specialty_accounting_fixture('pvp') as f:
            player=f['player']; target=self._security_target('BroaderPvpVictim'); entry=f'pvp.php?act=attack&name={target}'
            settings={'pvp':'1','pvpattgain':'10','pvpdeflose':'5','pvpdefgain':'10','pvpattlose':'15','maxattacks':'4','autofight':'1','autofightfull':'1'}
            saved_settings=self.query('SELECT * FROM settings WHERE setting IN ('+','.join('?' for _ in settings)+')',list(settings))
            for key,value in settings.items(): self.query('INSERT INTO settings(setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[key,value])
            def patch(who,**values):
                self.query('UPDATE accounts SET '+','.join('`'+k+'`=?' for k in values)+' WHERE acctid=?',[*values.values(),who])
            def prepare(inn=0,**values):
                f['prepare']('TS',badguy='',level=5,gold=1000,gems=10,experience=5000,attack=40,defense=20,hitpoints=500,maxhitpoints=500,playerfights=10,location='Degolburg',age=20,pk=0)
                if values: patch(player,**values)
                patch(target,name='PvP Victim',level=5,gold=100,experience=1000,maxhitpoints=10000,hitpoints=250,attack=40,defense=20,alive=1,loggedin=0,locked=0,slaydragon=0,age=20,dragonkills=0,pk=0,pvpflag='2000-01-01 00:00:00',location='The Boar\'s Head Inn' if inn else 'Degolburg',boughtroomtoday=inn,race='Human',badguy='')
                if inn:
                    name=self.query("SELECT value FROM settings WHERE setting='innname'")
                    patch(target,location=name[0]['value'] if name else "The Boar's Head Inn")
            def snap():
                rows=self.query('SELECT * FROM accounts WHERE acctid IN (?,?) ORDER BY acctid',[player,target])
                for row in rows:
                    for key in ['laston','gentime','gentimecount','gensize','allowednavs','restorepage','lastip','uniqueid','lastmotd','lastnews']: row.pop(key,None)
                return rows+[self.query('SELECT * FROM bounty WHERE target=? ORDER BY bountyid',[target]),self.query('SELECT * FROM mail WHERE msgto=? ORDER BY messageid',[target]),self.query('SELECT COUNT(*) n FROM news'),self.query('SELECT COUNT(*) n FROM debuglog')]
            def form(path='pvp.php?op=fight'):
                status,body=f['request'](path); self.assertEqual(200,status,body[:3000]); return self._security_fields(body)
            def reject(data,path='pvp.php?op=fight',status=409):
                before=snap(); code,body=f['request'](path,data); self.assertEqual(status,code,body[:3500]); self.assertEqual(before,snap())
            def enter(inn=0):
                path=entry+('&inn=1' if inn else ''); before=snap(); data=form(path); self.assertEqual(before,snap())
                status,body=f['request'](path,data); self.assertEqual(200,status,body[:3500]); self.assertNotEqual('',snap()[0]['badguy']); return body,data
            try: yield f|dict(target=target,entry=entry,pvp_prepare=prepare,patch=patch,pvp_snapshot=snap,pvp_form=form,pvp_reject=reject,enter=enter)
            finally:
                self.query('DELETE FROM settings WHERE setting IN ('+','.join('?' for _ in settings)+')',list(settings))
                for row in saved_settings: self.query('INSERT INTO settings(setting,value) VALUES (?,?)',[row['setting'],row['value']])
                self.query('DELETE FROM bounty WHERE target=?',[target]); self.query('DELETE FROM mail WHERE msgto=?',[target])
                self.query('DELETE FROM accounts_output WHERE acctid=?',[target]); self.query('DELETE FROM accounts WHERE acctid=?',[target])

    def test_pvp_round_stale_reservation_and_victim_authority(self):
        with self._pvp_fixture() as f:
            f['pvp_prepare'](attack=100,defense=50,hitpoints=5000,maxhitpoints=5000); f['patch'](f['target'],attack=1000,defense=80); f['enter'](); before=f['pvp_snapshot'](); stale=f['pvp_form'](); data=f['pvp_form']()
            code,body=f['request']('pvp.php?op=fight',data|{'victory':1,'payout':99999,'name':99999,'hitpoints':99999}); self.assertEqual(200,code,body[:3000])
            after=f['pvp_snapshot'](); enemy=f['decode'](after[0]['badguy'])['enemies'][0]
            self.assertEqual(('5000','10000','4823',9960),(before[0]['hitpoints'],f['decode'](before[0]['badguy'])['enemies'][0]['creaturehealth'],after[0]['hitpoints'],enemy['creaturehealth']))
            self.assertEqual('9',after[0]['playerfights']); self.assertEqual(before[1],after[1]); self.assertEqual(before[2:],after[2:])
            self.assertEqual(200,f['request']('pvp.php')[0]); self.assertEqual(after,f['pvp_snapshot']())
            f['pvp_reject'](stale); f['pvp_reject'](data)
            for key,value in [('location','Elsewhere'),('pk','1'),('hitpoints','249'),('alive','0'),('locked','1'),('pvpflag','2099-01-01 00:00:00'),('gold','90'),('experience','999'),('boughtroomtoday','1')]:
                data=f['pvp_form'](); prior=f['pvp_snapshot']()[1][key]; f['patch'](f['target'],**{key:value}); f['pvp_reject'](data)
                # Even a newly issued form cannot reinterpret the original encounter.
                fresh=f['pvp_form'](); f['pvp_reject'](fresh)
                f['patch'](f['target'],**{key:prior})
            # Explicit repair restores continuation. Request-selected enemy/result cannot bypass it.
            data=f['pvp_form'](); self.assertEqual(200,f['request']('pvp.php?op=fight',data)[0])
            for path in ['pvp.php?op=fight&victory=1','pvp.php?op=fight&newtarget=2','pvp.php?op=fight&skill=godmode','pvp.php?op=result']:
                before=f['pvp_snapshot'](); self.assertEqual(400,f['request'](path)[0]); self.assertEqual(before,f['pvp_snapshot']())

    def test_pvp_eligibility_and_entry_rollback(self):
        with self._pvp_fixture() as f:
            for who,changes in [('player',dict(alive=0,hitpoints=0)),('player',dict(playerfights=0)),('player',dict(specialinc='fixture.php')),('player',dict(badguy='a:0:{}')),
                ('target',dict(alive=0)),('target',dict(locked=1)),('target',dict(slaydragon=1)),('target',dict(age=0)),('target',dict(location='Elsewhere')),('target',dict(level=2)),('target',dict(level=8)),('target',dict(loggedin=1,laston='2099-01-01 00:00:00')),('target',dict(pvpflag='2099-01-01 00:00:00'))]:
                f['pvp_prepare'](); f['patch'](f[who],**changes); data=f['pvp_form'](f['entry']); f['pvp_reject'](data,f['entry'])
            # Listing range [-1,+2] and setup range +/-2 differ historically; do not rebalance.
            for changes in [dict(level=3),dict(level=7),dict(age=0,dragonkills=100),dict(age=0,pk=1),dict(age=0,experience=1501),dict(loggedin=1,laston='2000-01-01 00:00:00')]:
                f['pvp_prepare'](); f['patch'](f['target'],**changes); f['enter']()
            f['pvp_prepare'](age=0,experience=0); data=f['pvp_form'](f['entry']); before=f['pvp_snapshot']()
            self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_pvp_entry CHECK (login <> 'WebPlayer' OR playerfights=10)")
            try: f['pvp_reject'](data,f['entry'],500)
            finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_pvp_entry')
            f['pvp_reject'](data,f['entry']); f['enter'](); self.assertEqual('1',f['pvp_snapshot']()[0]['pk']); self.assertEqual('9',f['pvp_snapshot']()[0]['playerfights'])
            # Another attack cannot replace the active encounter or steal the reserved victim.
            f['pvp_reject'](f['pvp_form'](f['entry']),f['entry'])

    def test_pvp_victory_zero_negative_rewards_replay_and_rollback(self):
        with self._pvp_fixture() as f, self._specialty_terminal_capture() as terminal:
            for hp in [10,9]:
                f['pvp_prepare'](attack=0,defense=1000); f['enter']()
                state=f['decode'](f['pvp_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=hp
                buff={'proof':dict(name='Proof',schema='pvp',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=3,allowinpvp=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)}
                f['patch'](f['player'],badguy=f['encode'](state),bufflist=f['encode'](buff)); self.query('DELETE FROM fixture_specialty_terminal')
                data=f['pvp_form'](); stale=f['pvp_form'](); before=f['pvp_snapshot']()
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_pvp_end CHECK (login <> 'WebPlayer' OR badguy <> '')")
                try: f['pvp_reject'](data,status=500); self.assertEqual([],terminal())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_pvp_end')
                f['pvp_reject'](data); data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight',data|{'gold':999999,'experience':999999,'winner':999}); self.assertEqual(200,code,body[:3000])
                after=f['pvp_snapshot'](); self.assertEqual(hp-10,terminal()[0]['enemy']['creaturehealth']); self.assertEqual('battle-victory',terminal()[0]['hook'])
                self.assertEqual(['1','500','1230','5100','9','', 'Degolburg'],[after[0][k] for k in ['alive','hitpoints','gold','experience','playerfights','badguy','location']])
                self.assertEqual(['0','250','0','950',before[1]['pvpflag'],'Degolburg'],[after[1][k] for k in ['alive','hitpoints','gold','experience','pvpflag','location']])
                self.assertEqual(str(f['target']),after[3][-1]['msgto']); self.assertEqual('0',after[3][-1]['msgfrom']); self.assertEqual(str(f['player']),after[3][-1]['originator']); self.assertIn(before[0]['name'],after[3][-1]['body'])
                news=self.query('SELECT newstext,arguments,accountid FROM news ORDER BY newsid DESC LIMIT 1')[0]; self.assertEqual([before[0]['name'],before[1]['name']],f['decode'](news['arguments'])[:2]); self.assertEqual(str(f['player']),news['accountid'])
                self.assertEqual(len(before[3])+1,len(after[3])); self.assertEqual(int(before[4][0]['n'])+1,int(after[4][0]['n'])); self.assertEqual(int(before[5][0]['n'])+2,int(after[5][0]['n']))
                f['pvp_reject'](data); f['pvp_reject'](stale); f['pvp_reject'](data,f['entry'])
                self.assertEqual(409,f['request']('pvp.php?op=fight')[0]); self.assertEqual(after,f['pvp_snapshot']())

    def test_pvp_defeat_inn_bodyguard_settlement_and_rollback(self):
        with self._pvp_fixture() as f, self._specialty_terminal_capture() as terminal:
            for inn in [0,1,2,3,4,5]:
                f['pvp_prepare'](inn=inn,attack=0,defense=1000); f['enter'](inn); row=f['pvp_snapshot']()[0]
                buffs=f['decode'](row['bufflist']) or {}
                if inn:
                    self.assertEqual([1.05,1.1,1.2,1.3,1.4][inn-1],buffs['bodyguard']['badguyatkmod']); self.assertEqual([.95,.9,.8,.7,.6][inn-1],buffs['bodyguard']['defmod'])
                    self.assertEqual(-1,buffs['bodyguard']['rounds']); self.assertEqual(1,len(f['decode'](row['badguy'])['enemies']))
                # Attacker-side fixed adverse effect reaches actual engine defeat.
                buffs['adverse']=dict(name='Adverse',schema='pvp',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=3,allowinpvp=1,minioncount=1,mingoodguydamage=10,maxgoodguydamage=10)
                f['patch'](f['player'],hitpoints=10,bufflist=f['encode'](buffs)); self.query('DELETE FROM fixture_specialty_terminal')
                before=f['pvp_snapshot'](); data=f['pvp_form'](); stale=f['pvp_form']()
                self.query("ALTER TABLE accounts ADD CONSTRAINT fixture_pvp_loss CHECK (login <> 'WebPlayer' OR alive=1)")
                try: f['pvp_reject'](data,status=500); self.assertEqual([],terminal())
                finally: self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_pvp_loss')
                f['pvp_reject'](data); data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight',data); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()
                self.assertEqual(['0','0','0','4250','9','','Degolburg'],[after[0][k] for k in ['alive','hitpoints','gold','experience','playerfights','badguy','location']])
                self.assertEqual(['1','250','445','1500',before[1]['pvpflag']],[after[1][k] for k in ['alive','hitpoints','gold','experience','pvpflag']])
                self.assertNotIn('bodyguard',f['decode'](after[0]['bufflist'])); self.assertEqual('battle-defeat',terminal()[0]['hook']); self.assertIn('You have been slain',body)
                self.assertEqual(str(f['target']),after[3][-1]['msgto']); self.assertEqual('0',after[3][-1]['msgfrom']); self.assertEqual(str(f['player']),after[3][-1]['originator']); self.assertIn(before[0]['name'],after[3][-1]['body'])
                news=self.query('SELECT newstext,arguments,accountid FROM news ORDER BY newsid DESC LIMIT 1')[0]; self.assertEqual([before[0]['name'],before[1]['name']],f['decode'](news['arguments'])[:2]); self.assertEqual(str(f['player']),news['accountid'])
                self.assertEqual(len(before[3])+1,len(after[3])); self.assertEqual(int(before[4][0]['n'])+1,int(after[4][0]['n']))
                f['pvp_reject'](data); f['pvp_reject'](stale); f['pvp_reject'](data,f['entry'])

            # Actual victim retaliation, with Dag enabled, is independently terminal.
            self.query("UPDATE modules SET active=1 WHERE modulename='dag'")
            self.query("INSERT INTO bounty(amount,target,setter,setdate,status) VALUES (250,?,0,'2020-01-01 00:00:00',0)",[f['target']])
            f['pvp_prepare'](attack=100,defense=50,hitpoints=5000,maxhitpoints=5000); f['patch'](f['target'],attack=1000,defense=80); f['enter'](); f['patch'](f['player'],hitpoints=1)
            before=f['pvp_snapshot'](); data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight',data); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()
            self.assertEqual(['0','0','0','4250','9',''],[after[0][k] for k in ['alive','hitpoints','gold','experience','playerfights','badguy']]); self.assertEqual(before[2],after[2]); self.assertEqual('445',after[1]['gold']); self.assertIn('177',body); f['pvp_reject'](data)

    def test_pvp_malformed_state_preserved_and_repaired(self):
        with self._pvp_fixture() as f:
            f['pvp_prepare'](); f['enter'](); good=f['pvp_snapshot']()[0]['badguy']; base=f['decode'](good)
            invalid=['broken','O:8:"stdClass":0:{}','s:4:"oops";','a:0:{}']
            for key,value in [('owner',999),('target',999),('type','forest'),('reservation','bad'),('encounter','bad'),('victimhash','bad'),('maxattacks',0),('didsurprise',[]),('experience',[999])]:
                state=f['decode'](good); state['options'][key]=value; invalid.append(f['encode'](state))
            for key,value in [('acctid',999),('creaturehealth',0),('creaturehealth',-1),('creaturehealth','1e3'),('creaturehealth',[]),('creatureattack',-1),('creaturedefense','bad'),('creaturegold',99999),('dead',True),('istarget',False),('diddamage',[]),('bodyguardlevel',5),('creatureaiscript','return true;')]:
                state=f['decode'](good); state['enemies'][0][key]=value; invalid.append(f['encode'](state))
            state=f['decode'](good); state['enemies'].append(state['enemies'][0]); invalid.append(f['encode'](state))
            for encoded in invalid:
                data=f['pvp_form'](); f['patch'](f['player'],badguy=encoded); f['pvp_reject'](data); f['patch'](f['player'],badguy=good)
            for column,encoded,status in [('bufflist','s:4:"oops";',409),('bufflist','O:8:"stdClass":0:{}',409),('bufflist',f['encode']({'bad':{'rounds':2,'atkmod':[]}}),409),('companions','s:4:"oops";',409),('companions',f['encode']({'bad':{'hitpoints':1}}),409)]:
                data=f['pvp_form'](); f['patch'](f['player'],**{column:encoded}); before=f['pvp_snapshot']()
                code,body=f['request']('pvp.php?op=fight',data); self.assertEqual(status,code,body[:2000]); self.assertEqual(before,f['pvp_snapshot']()); self.assertEqual(409,f['request']('pvp.php?op=fight')[0]); self.assertEqual(before,f['pvp_snapshot']()); f['patch'](f['player'],**{column:'a:0:{}'})
            self.assertEqual(200,f['request']('pvp.php?op=fight',f['pvp_form']())[0])

    def test_pvp_buffs_companions_run_and_bodyguard_authority(self):
        with self._pvp_fixture() as f:
            f['pvp_prepare'](attack=0,defense=1000); f['enter']()
            skeleton=dict(name='`4Skeleton Warrior',hitpoints=20,maxhitpoints=43,attack=26.5,defense=14.5,dyingtext='`$Your skeleton warrior crumbles to dust.`n',abilities=dict(fight=True),ignorelimit=True)
            buffs={'suspended':dict(name='Suspended',schema='fixture',rounds=4,atkmod=99), 'allowed':dict(name='Allowed',schema='fixture',rounds=4,allowinpvp=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10,effectmsg='',effectnodmgmsg='',effectfailmsg='')}
            f['patch'](f['player'],companions=f['encode']({'skeleton_warrior':skeleton}),bufflist=f['encode'](buffs))
            before=f['pvp_snapshot'](); data=f['pvp_form']('pvp.php?op=run'); code,body=f['request']('pvp.php?op=run',data); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()[0]
            self.assertIn('Your pride prevents you from running',body); self.assertEqual('9',after['playerfights']); self.assertEqual(before[1],f['pvp_snapshot']()[1])
            self.assertEqual(9959,f['decode'](after['badguy'])['enemies'][0]['creaturehealth'])
            self.assertEqual(skeleton|dict(suspended=True,used=False),f['decode'](after['companions'])['skeleton_warrior']); b=f['decode'](after['bufflist']); self.assertEqual(4,b['suspended']['rounds']); self.assertEqual(3,b['allowed']['rounds']); f['pvp_reject'](data,'pvp.php?op=run')
            for level in [1,5]:
                f['pvp_prepare'](inn=level); f['enter'](level); before=f['pvp_snapshot'](); data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight&inn=0',data); self.assertEqual(400,code); self.assertEqual(before,f['pvp_snapshot']())
                data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight',data|dict(bodyguardlevel=0,bodyguardhealth=0,victory=1)); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()[0]
                self.assertEqual(('500',9981) if level==1 else ('497',9983),(after['hitpoints'],f['decode'](after['badguy'])['enemies'][0]['creaturehealth']))
                self.assertEqual(level,int(f['decode'](after['badguy'])['enemies'][0]['bodyguardlevel'])); self.assertIn('bodyguard',f['decode'](after['bufflist']))
                data=f['pvp_form'](); b=f['decode'](after['bufflist']); b['bodyguard']['badguyatkmod']=999; f['patch'](f['player'],bufflist=f['encode'](b)); f['pvp_reject'](data); f['pvp_reject'](f['pvp_form']())

    def test_pvp_cross_player_reservation_and_tokens(self):
        with self._pvp_fixture() as f:
            other=self._security_target('OtherPvpAttacker')
            try:
                f['pvp_prepare'](); f['patch'](other,level=5,alive=1,hitpoints=500,attack=40,defense=20,playerfights=10,badguy='',specialinc='',location='Degolburg',bufflist='a:0:{}',companions='a:0:{}')
                othercall=self._security_client('OtherPvpAttacker','Synthetic administrator password')
                def request(path,data=None): self._security_allow(other,path); return othercall(path,data,fixture='specialty-accounting')
                first=f['pvp_form'](f['entry']); _,body=request(f['entry']); second=self._security_fields(body)
                before=f['pvp_snapshot'](); self.assertEqual(403,request(f['entry'],first)[0]); self.assertEqual(before,f['pvp_snapshot']())
                f['enter'](); before=f['pvp_snapshot'](); self.assertEqual(409,request(f['entry'],second)[0]); self.assertEqual(before,f['pvp_snapshot']())
                _,body=request(f['entry']); self.assertEqual(409,request(f['entry'],self._security_fields(body))[0]); self.assertEqual(before,f['pvp_snapshot']())
                # Copying an encounter to another account fails the persisted owner binding.
                data=f['pvp_form'](); f['patch'](other,badguy=before[0]['badguy']); self.assertEqual(409,request('pvp.php?op=fight',data)[0]); self.assertEqual(before,f['pvp_snapshot']())
            finally:
                self.query('DELETE FROM accounts_output WHERE acctid=?',[other]); self.query('DELETE FROM accounts WHERE acctid=?',[other])

    def test_pvp_reward_rounding_level15_and_inn_victory(self):
        with self._pvp_fixture() as f, self._specialty_terminal_capture() as terminal:
            for level,targetlevel,inn,exp,gold,expectedgold,expectedexp,lost in [(5,3,0,1005,1,0,81,50),(5,7,0,1005,100,322,121,50),(15,15,0,1005,100,0,0,50),(5,5,1,1005,100,230,101,50)]:
                f['pvp_prepare'](inn=inn,level=level,attack=0,defense=1000); f['patch'](f['target'],level=targetlevel,experience=exp,gold=gold); f['enter'](inn)
                row=f['pvp_snapshot']()[0]; state=f['decode'](row['badguy']); state['enemies'][0]['creaturehealth']=10
                buffs=f['decode'](row['bufflist']) or {}; buffs['proof']=dict(name='Proof',schema='pvp',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=3,allowinpvp=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)
                f['patch'](f['player'],badguy=f['encode'](state),bufflist=f['encode'](buffs)); before=f['pvp_snapshot'](); data=f['pvp_form'](); code,body=f['request']('pvp.php?op=fight',data); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()
                self.assertEqual(str(1000+expectedgold),after[0]['gold']); self.assertEqual(str(5000+expectedexp),after[0]['experience']); self.assertEqual(str(exp-lost),after[1]['experience']); self.assertEqual('0',after[1]['gold']); self.assertNotIn('bodyguard',f['decode'](after[0]['bufflist'])); f['pvp_reject'](data)
            # Historical level-15 defender receives zero experience but still gold:
            # $wonamount typo never zeroed $winamount. Preserve actual shipped behavior.
            f['pvp_prepare'](level=15,attack=0,defense=1000); f['patch'](f['target'],level=15); f['enter']()
            buffs={'adverse':dict(name='Adverse',schema='pvp',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=3,allowinpvp=1,minioncount=1,mingoodguydamage=10,maxgoodguydamage=10)}
            f['patch'](f['player'],hitpoints=10,bufflist=f['encode'](buffs)); code,body=f['request']('pvp.php?op=fight',f['pvp_form']()); self.assertEqual(200,code,body[:3000]); self.assertEqual('1136',f['pvp_snapshot']()[1]['gold']); self.assertEqual('1000',f['pvp_snapshot']()[1]['experience'])

    def test_pvp_autorounds_overflow_and_permitted_companion(self):
        with self._pvp_fixture() as f:
            proof=dict(name='Proof',schema='pvp',effectmsg='',effectnodmgmsg='',effectfailmsg='',rounds=30,allowinpvp=1,minioncount=1,minbadguydamage=10,maxbadguydamage=10)
            f['pvp_prepare'](attack=0,defense=0); f['patch'](f['target'],attack=0,defense=0); f['enter'](); f['pvp_reject'](f['pvp_form']())
            f['pvp_prepare'](attack=1,defense=0,hitpoints=1000000,maxhitpoints=1000000); f['patch'](f['target'],attack=1,defense=0,maxhitpoints=1000000); f['enter'](); f['pvp_reject'](f['pvp_form']('pvp.php?op=fight&auto=full'),'pvp.php?op=fight&auto=full')
            for rounds,count in [('five',5),('ten',10),('full',1)]:
                f['pvp_prepare'](attack=0,defense=0,hitpoints=50000,maxhitpoints=50000); f['patch'](f['target'],attack=1000,defense=80); f['enter']()
                if rounds=='full':
                    state=f['decode'](f['pvp_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=10; f['patch'](f['player'],badguy=f['encode'](state))
                f['patch'](f['player'],bufflist=f['encode']({'proof':proof})); url='pvp.php?op=fight&auto='+rounds; data=f['pvp_form'](url)
                code,body=f['request'](url,data); self.assertEqual(200,code,body[:3000]); row=f['pvp_snapshot']()[0]
                self.assertEqual('9',row['playerfights'])
                if rounds=='full': self.assertEqual('',row['badguy']); self.assertEqual('0',f['pvp_snapshot']()[1]['alive'])
                else: self.assertEqual(10000-count*10,f['decode'](row['badguy'])['enemies'][0]['creaturehealth']); self.assertEqual(30-count,f['decode'](row['bufflist'])['proof']['rounds'])
                f['pvp_reject'](data,url)
            for key in ['gold','experience']:
                f['pvp_prepare'](attack=0,defense=0,hitpoints=50000,maxhitpoints=50000); f['patch'](f['target'],attack=1000,defense=80); f['enter']()
                state=f['decode'](f['pvp_snapshot']()[0]['badguy']); state['enemies'][0]['creaturehealth']=10
                f['patch'](f['player'],badguy=f['encode'](state),bufflist=f['encode']({'proof':proof}),**{key:2147483647})
                f['pvp_reject'](f['pvp_form']())
            f['pvp_prepare'](attack=100,defense=50,hitpoints=5000,maxhitpoints=5000); f['patch'](f['target'],attack=120,defense=80); f['enter']()
            companion=dict(name='PvP Helper',hitpoints=1000,maxhitpoints=1000,attack=100,defense=50,allowinpvp=True,abilities=dict(fight=True),dyingtext='Helper falls',schema='fixture')
            f['patch'](f['player'],companions=f['encode']({'helper':companion})); before=f['pvp_snapshot'](); code,body=f['request']('pvp.php?op=fight',f['pvp_form']()); self.assertEqual(200,code,body[:3000]); after=f['pvp_snapshot']()[0]
            self.assertEqual(('5000',9946,923),(after['hitpoints'],f['decode'](after['badguy'])['enemies'][0]['creaturehealth'],f['decode'](after['companions'])['helper']['hitpoints']))
            text=re.sub('<[^>]+>','',body); self.assertLess(text.index('You hit PvP Victim'),text.index('PvP Helper hits PvP Victim')); self.assertLess(text.index('tries to hit you'),text.index('PvP Helper hits PvP Victim'))

    def test_dag_funded_pvp_and_failure_rollback(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        target=self._security_target('PvpVictimFixture')
        modules=self.query('SELECT modulename,active FROM modules')
        self.query('UPDATE modules SET active=1')
        request=self._security_client()
        def call(url,fields=None):
            self._security_allow(player,url); return request(url,fields)
        def prepare():
            self.query("UPDATE accounts SET gold=1000,level=5,age=20,experience=5000,attack=10000,defense=10000,hitpoints=10000,maxhitpoints=10000,playerfights=10,alive=1,badguy='',bufflist='a:0:{}',companions='a:0:{}',specialinc='',location='Degolburg',superuser=0 WHERE acctid=?",[player])
            self.query("UPDATE accounts SET gold=0,level=5,age=20,experience=0,dragonkills=0,pk=0,attack=1,defense=1,maxhitpoints=1,hitpoints=1,alive=1,loggedin=0,locked=0,slaydragon=0,pvpflag='2000-01-01 00:00:00',location='Degolburg' WHERE acctid=?",[target])
        def snapshot():
            return [self.query('SELECT acctid,gold,experience,alive,hitpoints,playerfights,badguy,pvpflag FROM accounts WHERE acctid IN (?,?) ORDER BY acctid',[player,target]),
                self.query('SELECT * FROM bounty WHERE target=? ORDER BY bountyid',[target]),
                self.query('SELECT COUNT(*) n FROM news'),self.query('SELECT COUNT(*) n FROM debuglog'),self.query('SELECT COUNT(*) n FROM mail')]
        entry=f'pvp.php?act=attack&name={target}'
        try:
            prepare()
            # Funded eligible, own, future and already-closed rows are independent.
            for amount,setter,date,status in [(250,0,'2020-01-01 00:00:00',0),(125,player,'2020-01-01 00:00:00',0),(75,0,'2099-01-01 00:00:00',0),(50,0,'2020-01-01 00:00:00',1)]:
                self.query('INSERT INTO bounty(amount,target,setter,setdate,status) VALUES (?,?,?,?,?)',[amount,target,setter,date,status])
            before=snapshot(); status,body=call(entry); self.assertEqual(200,status); form=self._security_fields(body)
            self.assertEqual(before,snapshot())  # confirmation GET never starts a fight
            self.assertEqual(403,call(entry,{})[0]); self.assertEqual(before,snapshot())
            # Every failure is fixture DDL outside the application transaction.
            for table,condition in [('bounty','status=0 OR amount<>250'),('news',"newstext NOT LIKE '%collected%gold bounty%'"),('mail','msgto<>'+str(target)),('accounts','gold<>1250')]:
                self.query(f'ALTER TABLE {table} ADD CONSTRAINT fixture_dag_failure CHECK ({condition})')
                try:
                    status,body=call(entry); form=self._security_fields(body)
                    status,body=call(entry,form)
                    # Surprise and misses can leave combat nonterminal. Reach the real
                    # settlement failure without weakening its rollback assertions.
                    for _ in range(8):
                        if status!=200 or self.query('SELECT alive FROM accounts WHERE acctid=?',[target])[0]['alive']=='0': break
                        before=snapshot(); fight='pvp.php?op=fight'; form=self._security_fields(body,fight)
                        status,body=call(fight,form)
                    self.assertEqual(500,status,body[:2000]); self.assertEqual(before,snapshot())
                    self.assertEqual(409,call(entry,form)[0])
                finally:
                    self.query(f'ALTER TABLE {table} DROP CONSTRAINT fixture_dag_failure')
                if table != 'accounts': prepare(); before=snapshot()
            # Retry the failed final write using actual preserved state, without resetting accounts.
            retry=entry if self.query('SELECT badguy FROM accounts WHERE acctid=?',[player])[0]['badguy']=='' else 'pvp.php?op=fight'
            status,body=call(retry); form=self._security_fields(body)
            status,body=call(retry,{**form,'amount':'999999','target':'999999','winner':'999999'})
            self.assertEqual(200,status,body[:2000])
            for _ in range(8):
                if self.query('SELECT alive FROM accounts WHERE acctid=?',[target])[0]['alive']=='0': break
                fight='pvp.php?op=fight'; form=self._security_fields(body,fight)
                status,body=call(fight,form); self.assertEqual(200,status,body[:2000])
            self.assertNotIn('<img src=x>',body)
            self.assertEqual('0',self.query('SELECT alive FROM accounts WHERE acctid=?',[target])[0]['alive'])
            self.assertEqual('1250',self.query('SELECT gold FROM accounts WHERE acctid=?',[player])[0]['gold'])
            self.assertEqual('9',self.query('SELECT playerfights FROM accounts WHERE acctid=?',[player])[0]['playerfights'])
            bounties=self.query('SELECT amount,status,winner FROM bounty WHERE target=? ORDER BY amount',[target])
            self.assertEqual([('50','1'),('75','0'),('125','0'),('250','1')],[(r['amount'],r['status']) for r in bounties])
            self.assertEqual(str(player),bounties[-1]['winner'])
            after=snapshot(); self.assertEqual(409,call('pvp.php?op=fight',form)[0]); self.assertEqual(after,snapshot())
            status,body=call(entry); self.assertEqual(200,status)
            self.assertEqual(409,call(entry,self._security_fields(body))[0]); self.assertEqual(after,snapshot())
            # Actor/target authority survives bypass of issued navigation.
            # common.php derives actor alive from HP; use a consistent dead account.
            for changes in ["alive=0,hitpoints=0", "playerfights=0"]:
                prepare(); self.query('UPDATE accounts SET '+changes+' WHERE acctid=?',[player])
                _,body=call(entry); before=snapshot(); self.assertEqual(409,call(entry,self._security_fields(body))[0],changes); self.assertEqual(before,snapshot())
            for changes in ["alive=0","locked=1","age=0","location='Elsewhere'","level=15","loggedin=1,laston=NOW()","pvpflag='2099-01-01 00:00:00'"]:
                prepare(); self.query('UPDATE accounts SET '+changes+' WHERE acctid=?',[target])
                _,body=call(entry); before=snapshot(); self.assertEqual(409,call(entry,self._security_fields(body))[0],changes); self.assertEqual(before,snapshot())
            prepare()
            for ident in ['-1','0','1e2','999999999999999999999','1%20OR%201=1']:
                self.assertEqual(400,call('pvp.php?act=attack&name='+ident)[0])
            for ident in [player,999999]:
                url=f'pvp.php?act=attack&name={ident}'; _,body=call(url); before=snapshot()
                self.assertEqual(409,call(url,self._security_fields(body))[0]); self.assertEqual(before,snapshot())
        finally:
            self.query('DELETE FROM bounty WHERE target=?',[target])
            self.query('DELETE FROM accounts_output WHERE acctid=?',[target])
            self.query('DELETE FROM accounts WHERE acctid=?',[target])
            for row in [original]:
                keys=[k for k in row if k not in ['acctid','allowednavs','restorepage']]
                self.query('UPDATE accounts SET '+','.join('`'+k+'`=?' for k in keys)+' WHERE acctid=?',[*[row[k] for k in keys],row['acctid']])
            for row in modules: self.query('UPDATE modules SET active=? WHERE modulename=?',[row['active'],row['modulename']])

    def test_dag_administrator_http_matrix(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT superuser,gold FROM accounts WHERE acctid=?',[player])[0]
        self.query('UPDATE modules SET active=1')
        request=self._security_client()
        base='runmodule.php?module=dag&manage=true'
        place=base+'&op=addbounty&admin=true'
        listing=base+'&op=viewbounties&type=1&sort=1&dir=1&admin=true'
        cleanup=base+'&op=cleanup'
        def call(url,fields=None): self._security_allow(player,url); return request(url,fields)
        ident=self._security_target('DagAdminTargetFixture')
        target=self.query('SELECT acctid,name FROM accounts WHERE acctid=?',[ident])[0]
        try:
            anon=self._security_client(None)
            for url in [base,place,cleanup,base+'&op=closebounty&id=1']:
                self.assertIn(anon(url,{})[0],[302,303,403])
            for role in [0,16]:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[role,player])
                for url in [base,listing,place,cleanup,base+'&op=closebounty&id=1']:
                    self.assertEqual(403,call(url)[0]); self.assertEqual(403,call(url,{})[0])
            self.query('UPDATE accounts SET superuser=64 WHERE acctid=?',[player])
            status,body=call(base); self.assertEqual(200,status,body[:2000])
            form=self._security_fields(body,place)
            self.assertEqual(403,call(place)[0]); self.assertEqual(403,call(place,{})[0])
            for amount in ['0','-1','1e3','2147483648']:
                _,body=call(base); form=self._security_fields(body,place)
                self.assertEqual(400,call(place,{**form,'amount':amount,'contractname':target['name']})[0])
            for name in ['MissingFixture',"' OR 1=1 --",'x'*101]:
                _,body=call(base); form=self._security_fields(body,place)
                before=self.query('SELECT COUNT(*) n FROM bounty')
                status,_=call(place,{**form,'amount':'250','contractname':name})
                self.assertIn(status,[200,400]); self.assertEqual(before,self.query('SELECT COUNT(*) n FROM bounty'))
            _,body=call(base); form={**self._security_fields(body,place),'amount':'250','contractname':target['name']}
            self.assertEqual(200,call(place,form)[0]); self.assertEqual(409,call(place,form)[0])
            self.assertEqual(original['gold'],self.query('SELECT gold FROM accounts WHERE acctid=?',[player])[0]['gold'])
            bounty=self.query('SELECT bountyid,status,setter FROM bounty WHERE target=? ORDER BY bountyid DESC LIMIT 1',[target['acctid']])[0]
            self.assertEqual('0',bounty['setter'])
            _,body=call(base)  # actual funded overview, including location and duplicate-name grouping
            status,body=call(listing); self.assertEqual(200,status)
            self.assertNotIn('<img src=x>',body)
            search=base+'&op=viewbounties&type=search&admin=true'
            status,body=call(search,{'target':target['name'],'s':'1','d':'1'})
            self.assertEqual(200,status); self.assertNotIn('<img src=x>',body)
            for invalid in [{'target[]':'x'},{'s':'9'},{'target':'x'*101}]: self.assertEqual(400,call(search,invalid)[0])
            close=base+'&op=closebounty&id='+bounty['bountyid']+'&admin=true'
            form=self._security_fields(body,close)
            self.assertEqual(403,call(close)[0]); self.assertEqual(403,call(close,{})[0])
            self.assertEqual(200,call(close,form)[0]); after=self.query('SELECT * FROM bounty WHERE bountyid=?',[bounty['bountyid']])
            self.assertEqual('1',after[0]['status']); self.assertEqual(409,call(close,form)[0]); self.assertEqual(after,self.query('SELECT * FROM bounty WHERE bountyid=?',[bounty['bountyid']]))
            for ident in ['0','-1','1e2','2147483648','1%20OR%201=1']:
                self.assertEqual(400,call(base+'&op=closebounty&id='+ident,form)[0])
            self.assertEqual(409,call(base+'&op=closebounty&id=999999',form)[0])
            # Issue a legitimate close form, then remove its record before submission.
            self.query('UPDATE bounty SET status=0 WHERE bountyid=?',[bounty['bountyid']]); _,body=call(listing); form=self._security_fields(body,close)
            self.query('DELETE FROM bounty WHERE bountyid=?',[bounty['bountyid']]); self.assertEqual(404,call(close,form)[0])
            self.query("INSERT INTO bounty(amount,target,setter,setdate,status,windate) VALUES (250,?,0,'2020-01-01 00:00:00',1,'2020-01-01 00:00:00')",[target['acctid']])
            _,body=call(base); form=self._security_fields(body,cleanup)
            self.assertEqual(403,call(cleanup)[0]); self.assertEqual(403,call(cleanup,{})[0])
            self.assertEqual(200,call(cleanup,form)[0]); self.assertEqual(409,call(cleanup,form)[0])
            self.assertEqual([],self.query('SELECT * FROM bounty WHERE target=?',[target['acctid']]))
        finally:
            self.query('DELETE FROM bounty WHERE target=?',[target['acctid']])
            self.query('DELETE FROM accounts_output WHERE acctid=?',[target['acctid']])
            self.query('DELETE FROM accounts WHERE acctid=?',[target['acctid']])
            self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[original['superuser'],player])
            self.query('UPDATE modules SET active=0')

    def test_drinks_editor_delegation_create_activation_delete(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT superuser FROM accounts WHERE acctid=?',[player])[0]
        prefs=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=?',['drinks',player])
        self.query('UPDATE modules SET active=1')
        request=self._security_client()
        base='runmodule.php?module=drinks&act=editor&admin=true'
        add='runmodule.php?module=drinks&act=editor&op=add&admin=true'
        save='runmodule.php?module=drinks&act=editor&op=save&admin=true'
        created=[]
        def call(url,fields=None): self._security_allow(player,url); return request(url,fields)
        def grant(value):
            self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['drinks','canedit',player,str(value)])
        try:
            anon=self._security_client(None)
            for url in [base,add,save]: self.assertIn(anon(url,{})[0],[302,303,403])
            grant(0)
            for role in [0,16]:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[role,player])
                for url in [base,add,save]:
                    self.assertEqual(403,call(url)[0]); self.assertEqual(403,call(url,{'canedit':'1'})[0])
            # Both legitimate delegation and SU_EDIT_USERS perform the actual create and CRUD routes.
            for role,delegated in [(0,1),(64,0)]:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[role,player]); grant(delegated)
                status,body=call(add); self.assertEqual(200,status,body[:1800])
                form=self._security_fields(body,save)
                values={**form,'drinkid':'0','name':"`2O'Reilly 🐉",'remarks':"UTF-8 雪 \\ <img src=x> apostrophe's",'costperlevel':'2147483647','drunkeness':'100','buffrounds':'127','hpmin':'-20','hpmax':'20','turnmin':'-5','turnmax':'5','hppercent':'25','buffatkmod':'999999.999999'}
                self.assertEqual(403,call(save)[0]); self.assertEqual(403,call(save,{**values,'csrf_token':''})[0])
                count=self.query('SELECT COUNT(*) n FROM drinks')
                self.assertEqual(200,call(save,values)[0]); self.assertEqual(409,call(save,values)[0])
                self.assertEqual(int(count[0]['n'])+1,int(self.query('SELECT COUNT(*) n FROM drinks')[0]['n']))
                drink=self.query('SELECT * FROM drinks ORDER BY drinkid DESC LIMIT 1')[0]; ident=drink['drinkid']; created.append(ident)
                for key in ['name','remarks','costperlevel','drunkeness','buffrounds','hpmin','hpmax','turnmin','turnmax','hppercent','buffatkmod']:
                    self.assertEqual(values[key],drink[key])
                edit=f'runmodule.php?module=drinks&act=editor&op=edit&drinkid={ident}&admin=true'
                for invalid in [{'costperlevel':'2147483648'},{'drunkeness':'101'},{'buffrounds':'128'},{'hpmin':'-21'},{'turnmax':'6'},{'active':'1'},{'name[]':'array'},{'buffatkmod':'INF'}]:
                    _,body=call(edit); bad={k:v for k,v in drink.items() if k!='active'}; bad.update(self._security_fields(body,save)); bad.update(invalid)
                    self.assertEqual(400,call(save,bad)[0]); self.assertEqual(drink,self.query('SELECT * FROM drinks WHERE drinkid=?',[ident])[0])
                for op,active in [('activate','1'),('deactivate','0')]:
                    url=f'runmodule.php?module=drinks&act=editor&op={op}&drinkid={ident}&admin=true'
                    _,body=call(base); form=self._security_fields(body,url)
                    self.assertEqual(403,call(url)[0]); self.assertEqual(403,call(url,{})[0])
                    self.assertEqual(200,call(url,form)[0]); self.assertEqual(409,call(url,form)[0])
                    self.assertEqual(active,self.query('SELECT active FROM drinks WHERE drinkid=?',[ident])[0]['active'])
                delete=f'runmodule.php?module=drinks&act=editor&op=del&drinkid={ident}&admin=true'
                _,body=call(base); form=self._security_fields(body,delete)
                self.assertEqual(403,call(delete)[0]); self.assertEqual(403,call(delete,{})[0])
                if delegated:
                    grant(0); self.assertEqual(403,call(delete,form)[0]); self.assertEqual(drink,self.query('SELECT * FROM drinks WHERE drinkid=?',[ident])[0]); grant(1)
                self.assertEqual(200,call(delete,form)[0]); self.assertEqual(409,call(delete,form)[0]); self.assertEqual([],self.query('SELECT * FROM drinks WHERE drinkid=?',[ident]))
            for ident in ['0','-1','1e2','32768','1%20OR%201=1']:
                self.assertEqual(400,call(f'runmodule.php?module=drinks&act=editor&op=edit&drinkid={ident}&admin=true')[0])
            self.assertEqual(404,call('runmodule.php?module=drinks&act=editor&op=edit&drinkid=32767&admin=true')[0])
        finally:
            for ident in created: self.query('DELETE FROM drinks WHERE drinkid=?',[ident])
            self.query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',['drinks',player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',['drinks',row['setting'],player,row['value']])
            self.query('UPDATE accounts SET superuser=? WHERE acctid=?',[original['superuser'],player])
            self.query('UPDATE modules SET active=0')

    def test_potions_configured_prices_and_random_bounds(self):
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        original=self.query('SELECT gold,gems,charm,maxhitpoints,hitpoints,race,specialty,bufflist FROM accounts WHERE acctid=?',[player])[0]
        settings=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['cedrikspotions'])
        prefs=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=?',['cedrikspotions',player])
        self.query('UPDATE modules SET active=1')
        request=self._security_client()
        url='runmodule.php?module=cedrikspotions&op=gems'
        action='runmodule.php?module=cedrikspotions&op=gems'
        def setting(key,value):
            self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['cedrikspotions',key,str(value)])
        def buy(wish,quantity):
            self._security_allow(player,url); status,body=request(url); self.assertEqual(200,status,body[:1000])
            form=self._security_fields(body)
            match=re.search(r'<form action=[\'"]([^\'"]+)[\'"] method=[\'"]POST',body)
            actual=html.unescape(match.group(1))
            self._security_allow(player,actual)
            post={**form,'wish':str(wish),'gemcount':str(quantity),'cost':'0','randcost':'0'}
            status,body=request(actual,post)
            saved=self.query('SELECT gems,charm,maxhitpoints,hitpoints,race,specialty,bufflist FROM accounts WHERE acctid=?',[player])
            self._security_allow(player,actual); self.assertEqual(409,request(actual,post)[0]); self.assertEqual(saved,self.query('SELECT gems,charm,maxhitpoints,hitpoints,race,specialty,bufflist FROM accounts WHERE acctid=?',[player]))
            return status,body
        try:
            setting('random',0)
            for wish,key in enumerate(['charmcost','maxcost','tempcost','forgcost','transcost'],1):
                setting(key,wish+1)
                self.query("UPDATE accounts SET gems=100,charm=10,maxhitpoints=10,hitpoints=10,race='Human',specialty='DA',bufflist='a:0:{}' WHERE acctid=?",[player])
                status,body=buy(wish,3*(wish+1)+1); self.assertEqual(200,status,body[:1000])
                self.assertEqual(str(100-((wish+1) if wish>=4 else 3*(wish+1))),self.query('SELECT gems FROM accounts WHERE acctid=?',[player])[0]['gems'])
            setting('random',1); setting('minrand',1); setting('maxrand',10)
            for cost in [1,10]:
                setting('randcost',cost); self.query('UPDATE accounts SET gems=100 WHERE acctid=?',[player])
                self.assertEqual(200,buy(1,3*cost)[0]); self.assertEqual(str(100-3*cost),self.query('SELECT gems FROM accounts WHERE acctid=?',[player])[0]['gems'])
            for low,high,cost in [(9,2,5),(0,10,5),(1,11,5),(1,10,0),(1,10,'1e1')]:
                setting('minrand',low); setting('maxrand',high); setting('randcost',cost)
                before=self.query('SELECT gems,charm FROM accounts WHERE acctid=?',[player])
                self.assertEqual(400,buy(1,10)[0]); self.assertEqual(before,self.query('SELECT gems,charm FROM accounts WHERE acctid=?',[player]))
            setting('random',0); setting('charmcost',2)
            self.query('UPDATE accounts SET gems=1 WHERE acctid=?',[player]); before=self.query('SELECT gems,charm FROM accounts WHERE acctid=?',[player])
            self.assertEqual(200,buy(1,2)[0]); self.assertEqual(before,self.query('SELECT gems,charm FROM accounts WHERE acctid=?',[player]))
            for quantity in ['0','-1','1e2','2147483648','bad']:
                self.assertEqual(400,buy(1,quantity)[0]); self.assertEqual(before,self.query('SELECT gems,charm FROM accounts WHERE acctid=?',[player]))
            # Max valid offered amount, single-dose reset retains historical charge.
            setting('forgcost',10); self.query('UPDATE accounts SET gems=2147483647 WHERE acctid=?',[player])
            self.assertEqual(200,buy(4,2147483647)[0]); self.assertEqual('2147483637',self.query('SELECT gems FROM accounts WHERE acctid=?',[player])[0]['gems'])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_settings WHERE modulename=?',['cedrikspotions'])
            for row in settings: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',['cedrikspotions',row['setting'],row['value']])
            self.query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',['cedrikspotions',player])
            for row in prefs: self.query('INSERT INTO module_userprefs(modulename,setting,userid,value) VALUES (?,?,?,?)',['cedrikspotions',row['setting'],player,row['value']])
            self.query('UPDATE modules SET active=0')

    def test_darkhorse_shared_jackpot_concurrency(self):
        from concurrent.futures import ThreadPoolExecutor
        import threading
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0)); second_port=sock.getsockname()[1]
        server=subprocess.Popen([shutil.which('php'),'-d','display_errors=1','-S',f'127.0.0.1:{second_port}','-t',str(ROOT)],
            cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        def client(port):
            opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect)
            def request(url,fields=None):
                req=urllib.request.Request(f'http://127.0.0.1:{port}/'+url,data=None if fields is None else urllib.parse.urlencode(fields).encode())
                try: response=opener.open(req,timeout=20)
                except urllib.error.HTTPError as error: response=error
                body=response.read().decode('utf-8',errors='replace')
                self.assertNotRegex(body,r'(?i)(fatal error|warning:|deprecated:|notice:)',body[:1000])
                return response.status,body
            return request
        def token(body,key):
            match=re.search(r'name=[\'"]'+key+r'[\'"] value=[\'"]([a-f0-9]{64})',body)
            self.assertIsNotNone(match,body[:1000]); return match.group(1)
        players=self.query('SELECT acctid,gold,specialinc,specialmisc,lasthit FROM accounts WHERE login IN (?,?) ORDER BY login',['FixtureAdmin','WebPlayer'])
        settings=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['game_fivesix'])
        prefs=self.query('SELECT userid,value FROM module_userprefs WHERE modulename=? AND setting=?',['game_fivesix','playstoday'])
        url='runmodule.php?module=game_fivesix'
        requests=[client(second_port),client(self.port)]
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1',second_port),timeout=.1): break
                except OSError: time.sleep(.05)
            else: self.fail('Second loopback server did not start')
            self.query('UPDATE modules SET active=1')
            forms=[]
            for row,request,login,password in zip(players,requests,['FixtureAdmin','WebPlayer'],['Synthetic administrator password',"Synthetic web O'Reilly \\ password"]):
                status,body=request('home.php'); self.assertEqual(200,status)
                status,_=request('login.php',{'csrf_token':token(body,'csrf_token'),'name':login,'password':password}); self.assertEqual(303,status)
                event='forest.php?op=oldman'; allowed='a:1:{s:'+str(len(event))+':"'+event+'";b:1;}'
                self.query('UPDATE accounts SET gold=2000,lasthit=UTC_TIMESTAMP(),specialinc=?,specialmisc=?,allowednavs=? WHERE acctid=?',['module:darkhorse','',allowed,row['acctid']])
                status,body=request(event); self.assertEqual(200,status)
                links=[html.unescape(x) for x in re.findall(r'href=[\'"]([^\'"]+)',body)]
                link=next(x for x in links if x.startswith(url))
                status,body=request(link); self.assertEqual(200,status)
                forms.append({'csrf_token':token(body,'csrf_token'),'action_token':token(body,'action_token'),'action':'roll'})
                self.query('INSERT INTO module_userprefs (modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['game_fivesix','playstoday',row['acctid'],'0'])
            for key,value in [('cost','5'),('dailyuses','0'),('jackpot','1000'),('maxjackpot','5000')]:
                self.query('INSERT INTO module_settings (modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['game_fivesix',key,value])
            gate=threading.Barrier(2)
            def roll(i): gate.wait(timeout=5); return requests[i](url,forms[i])
            with ThreadPoolExecutor(max_workers=2) as pool:
                futures=[pool.submit(roll,i) for i in range(2)]
                for future in futures: self.assertEqual(200,future.result()[0])
            results=[self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?',[row['acctid']])[0] for row in players]
            actual=[int(row['gold'])-1995 for row in results]
            states=[json.loads(row['specialmisc']) for row in results]
            sixes=[json.loads(state['data']).count(6) for state in states]
            jackpot=int(self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['game_fivesix','jackpot'])[0]['value'])
            possible=[]
            for order in [(0,1),(1,0)]:
                pot=1000; paid=[0,0]
                for i in order:
                    pot=min(5000,pot+5)
                    paid[i]=pot if sixes[i]==5 else ((pot+5)//10 if sixes[i]==4 else ((pot+10)//20 if sixes[i]==3 else 0))
                    pot=100 if sixes[i]==5 else pot-paid[i]
                possible.append((paid,pot))
            self.assertIn((actual,jackpot),possible,'Concurrent results must equal one serial execution, with no lost update')
            for i,row in enumerate(players):
                self.assertEqual(int(row['acctid']),states[i]['owner']); self.assertTrue(states[i]['settled'])
                self.assertEqual('1',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['game_fivesix','playstoday',row['acctid']])[0]['value'])
                status,_=requests[i](url,forms[i]); self.assertEqual(409,status)
                self.assertEqual(results[i],self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?',[row['acctid']])[0])
            self.assertEqual(str(jackpot),self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['game_fivesix','jackpot'])[0]['value'])
        finally:
            server.terminate(); server.wait(timeout=5)
            for row in players:
                self.query('UPDATE accounts SET gold=?,specialinc=?,specialmisc=?,lasthit=? WHERE acctid=?',[row['gold'],row['specialinc'],row['specialmisc'],row['lasthit'],row['acctid']])
            self.query('DELETE FROM module_settings WHERE modulename=?',['game_fivesix'])
            for row in settings: self.query('INSERT INTO module_settings (modulename,setting,value) VALUES (?,?,?)',['game_fivesix',row['setting'],row['value']])
            self.query('DELETE FROM module_userprefs WHERE modulename=? AND setting=?',['game_fivesix','playstoday'])
            for row in prefs: self.query('INSERT INTO module_userprefs (modulename,setting,userid,value) VALUES (?,?,?,?)',['game_fivesix','playstoday',row['userid'],row['value']])
            self.query('UPDATE modules SET active=0')

    def test_darkhorse_wagers_abandonment_and_information(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect)
        def request(url, data=None):
            req=urllib.request.Request(f'http://127.0.0.1:{self.port}/'+url,
                data=None if data is None else urllib.parse.urlencode(data).encode())
            try: response=client.open(req,timeout=15)
            except urllib.error.HTTPError as error: response=error
            body=response.read().decode('utf-8',errors='replace')
            self.assertNotRegex(body,r'(?i)(fatal error|warning:|deprecated:|notice:)',body[:1800])
            return response.status,body
        def fields(body, **extra):
            result={}
            for key in ['csrf_token','action_token']:
                match=re.search(r'name=[\'"]'+key+r'[\'"] value=[\'"]([a-f0-9]{64})',body)
                self.assertIsNotNone(match,body[:1800]); result[key]=match.group(1)
            return {**result,**extra}
        def allow(url):
            value='a:1:{s:'+str(len(url))+':"'+url+'";b:1;}'
            self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[value,player])
        def snapshot():
            return self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?',[player])[0]
        def state(): return json.loads(snapshot()['specialmisc'])
        def page(url):
            self.query('UPDATE accounts SET lasthit=UTC_TIMESTAMP() WHERE acctid=?',[player])
            allow(url); status,body=request(url); self.assertEqual(200,status,body[:1000]); return body
        status,body=request('home.php')
        csrf=re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
        status,_=request('login.php',{'csrf_token':csrf,'name':'WebPlayer','password':"Synthetic web O'Reilly \\ password"})
        self.assertEqual(303,status)
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        prior=self.query('SELECT gold,specialmisc,specialinc FROM accounts WHERE acctid=?',[player])[0]
        settings=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['game_fivesix'])
        self.query('UPDATE modules SET active=1')
        oldman='forest.php?op=oldman'
        try:
            self.query('UPDATE accounts SET specialinc=?,specialmisc=?,gold=1000 WHERE acctid=?',['module:darkhorse','',player])
            body=page(oldman)  # issues server-owned return for all three games
            self.assertEqual('',snapshot()['specialmisc'])
            # No active state cannot authorize an abandonment, even with correct CSRF.
            empty_form=fields(page('runmodule.php?module=game_dice'))
            allow(oldman); status,_=request(oldman,{**empty_form,'game':'game_dice'})
            self.assertEqual(409,status)
            for module in ['game_stones','game_dice']:
                url='runmodule.php?module='+module
                body=page(url)
                if module=='game_stones':
                    status,body=request(url,fields(body,action='choose',side='likepair')); self.assertEqual(200,status)
                before=int(snapshot()['gold'])
                post=fields(body,action='bet',bet='10')
                status,_=request(url,{'action':'bet','bet':'10'}); self.assertEqual(403,status)
                status,body=request(url,post); self.assertEqual(200,status)
                self.assertEqual(before-10,int(snapshot()['gold']))
                saved=snapshot()
                body=page(oldman)
                self.assertEqual(saved,snapshot())  # GET oldman cannot erase risk
                self.assertIn('Resume game',re.sub('<[^>]+>','',body))
                other='runmodule.php?module='+('game_dice' if module=='game_stones' else 'game_stones')
                allow(other); status,_=request(other); self.assertEqual(409,status); self.assertEqual(saved,snapshot())
                body=page(oldman)
                wrong=fields(body,game='game_fivesix')
                status,_=request(oldman,wrong); self.assertEqual(409,status); self.assertEqual(saved,snapshot())
                body=page(oldman); post=fields(body,game=module)
                status,_=request(oldman,{'game':module}); self.assertEqual(403,status)
                status,body=request(oldman,post); self.assertEqual(200,status)
                self.assertEqual(before-10,int(snapshot()['gold'])); self.assertEqual('abandoned',state()['stage'])
                saved=snapshot(); allow(oldman)
                status,_=request(oldman,post); self.assertEqual(409,status); self.assertEqual(saved,snapshot())
                body=page(oldman); self.assertEqual(saved,snapshot())
            # Dice finite progression, request-carried state rejected, payout once.
            url='runmodule.php?module=game_dice'; body=page(url); before=int(snapshot()['gold'])
            for bet in ['-1','0','100000000000000000000','100000']:
                status,_=request(url,fields(body,action='bet',bet=bet)); self.assertEqual(400,status)
                self.assertEqual(before,int(snapshot()['gold'])); body=page(url)
            status,body=request(url,fields(body,action='bet',bet='10')); self.assertEqual(200,status)
            for attempt in [2,3]:
                status,body=request(url,fields(body,action='pass')); self.assertEqual(200,status)
                self.assertEqual(attempt,json.loads(state()['data'])['tries']); self.assertEqual(before-10,int(snapshot()['gold']))
            saved=snapshot()
            status,_=request(url,fields(body,action='pass')); self.assertEqual(400,status); self.assertEqual(saved,snapshot())
            body=page(url)
            for extra in [{'bet':'20'},{'try':'1'},{'what':'keep'},{'result':'win'}]:
                status,_=request(url,fields(body,action='keep',**extra)); self.assertEqual(400,status); self.assertEqual(saved,snapshot()); body=page(url)
            # Actual DML failure at final account write rolls settlement back.
            self.wager_failure('game_dice',player)
            post=fields(body,action='keep'); status,_=request(url,post); self.assertGreaterEqual(status,500)
            self.assertEqual(saved,snapshot()); self.remove_wager_failure()
            body=page(url); post=fields(body,action='keep'); status,body=request(url,post); self.assertEqual(200,status)
            dice=json.loads(state()['data']); comparison=(dice['roll']>dice['opponent'])-(dice['roll']<dice['opponent'])
            self.assertEqual(before+comparison*10,int(snapshot()['gold'])); self.assertTrue(state()['settled'])
            saved=snapshot(); status,_=request(url,post); self.assertEqual(409,status); self.assertEqual(saved,snapshot())
            body=page(oldman); self.assertEqual(saved,snapshot())
            # Five/Six shared jackpot and daily counter commit with the actor.
            for key,value in [('cost','5'),('dailyuses','1'),('jackpot','100'),('maxjackpot','5000')]:
                self.query('INSERT INTO module_settings (modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['game_fivesix',key,value])
            self.query('INSERT INTO module_userprefs (modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['game_fivesix','playstoday',player,'0'])
            url='runmodule.php?module=game_fivesix'; body=page(url); before=snapshot()
            self.wager_failure('game_fivesix',player)
            status,_=request(url,fields(body,action='roll')); self.assertGreaterEqual(status,500)
            self.assertEqual(before,snapshot())
            self.assertEqual('100',self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['game_fivesix','jackpot'])[0]['value'])
            self.assertEqual('0',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['game_fivesix','playstoday',player])[0]['value'])
            self.remove_wager_failure()
            body=page(url); post=fields(body,action='roll'); status,body=request(url,post); self.assertEqual(200,status)
            sixes=json.loads(state()['data']).count(6)
            payout={5:105,4:11,3:5}.get(sixes,0)
            self.assertEqual(int(before['gold'])-5+payout,int(snapshot()['gold']))
            self.assertEqual(str(100 if sixes==5 else 105-payout),self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['game_fivesix','jackpot'])[0]['value'])
            saved=snapshot(); status,_=request(url,post); self.assertEqual(409,status); self.assertEqual(saved,snapshot())
            body=page(url); status,_=request(url,fields(body,action='roll')); self.assertEqual(400,status); self.assertEqual(saved,snapshot())
            # Every game rejects GET value operations even with issued navigation.
            for suffix in ['game_dice&bet=10','game_dice&what=keep','game_fivesix&what=roll','game_stones&action=draw']:
                attack='runmodule.php?module='+suffix; allow(attack)
                status,_=request(attack); self.assertEqual(403,status); self.assertEqual(saved,snapshot())
            # Bartender search binds quote/backslash/UTF-8; paid GET only confirms.
            url='forest.php?op=bartender&what=enemies'; page(url); url+='&subop=search'
            status,body=request(url,{'name':"O'Reilly \\ 雪"}); self.assertEqual(200,status); self.assertEqual(saved,snapshot())
            url='forest.php?op=bartender&what=enemies&who=WebPlayer'; body=page(url)
            self.assertEqual(saved,snapshot()); post=fields(body)
            status,_=request(url,{}); self.assertEqual(403,status); self.assertEqual(saved,snapshot())
            status,body=request(url,post); self.assertEqual(200,status)
            self.assertEqual(int(saved['gold'])-100,int(snapshot()['gold']))
            after=snapshot(); allow(url); status,_=request(url,post); self.assertEqual(409,status); self.assertEqual(after,snapshot())
            for who in ['Nonexistent',"O'Reilly\\"]:
                url='forest.php?op=bartender&what=enemies&who='+urllib.parse.quote(who)
                body=page(url); status,_=request(url,fields(body)); self.assertEqual(200,status); self.assertEqual(after,snapshot())
            url='forest.php?op=bartender&what=enemies&who=WebPlayer'
            body=page(url); status,_=request(url,{**fields(body),'cost':'0'}); self.assertEqual(400,status); self.assertEqual(after,snapshot())
            self.query('UPDATE accounts SET gold=99 WHERE acctid=?',[player]); body=page(url)
            status,_=request(url,fields(body)); self.assertEqual(200,status); self.assertEqual('99',snapshot()['gold'])
            # Persisted malformed/foreign-owner state never silently becomes a new wager.
            for value in ['O:8:"stdClass":0:{}','{}',json.dumps({**state(),'owner':int(player)+999})]:
                self.query('UPDATE accounts SET specialmisc=? WHERE acctid=?',[value,player]); allow(oldman)
                status,_=request(oldman); self.assertEqual(409,status); self.assertEqual(value,snapshot()['specialmisc'])
        finally:
            self.remove_wager_failure()
            self.query('UPDATE accounts SET gold=?,specialmisc=?,specialinc=? WHERE acctid=?',[prior['gold'],prior['specialmisc'],prior['specialinc'],player])
            self.query('DELETE FROM module_settings WHERE modulename=?',['game_fivesix'])
            for row in settings:
                self.query('INSERT INTO module_settings (modulename,setting,value) VALUES (?,?,?)',['game_fivesix',row['setting'],row['value']])
            self.query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',['game_fivesix',player])
            self.query('UPDATE modules SET active=0')

    def test_module_audrey_village_authority(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        jar=http.cookiejar.CookieJar()
        client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect)
        def request(path, fields=None):
            req=urllib.request.Request(f'http://127.0.0.1:{self.port}/'+path,
                data=None if fields is None else urllib.parse.urlencode(fields).encode())
            try: response=client.open(req,timeout=15)
            except urllib.error.HTTPError as error: response=error
            body=response.read().decode('utf-8',errors='replace')
            self.assertNotRegex(body,r'(?i)(fatal error|warning:|deprecated:|notice:)',body[:1500])
            return response.status,body
        def fields(body):
            return {key:re.search(r'name=[\'"]'+key+r'[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
                    for key in ['csrf_token','action_token']}
        def allow(url):
            value='a:1:{s:'+str(len(url))+':"'+url+'";b:1;}'
            self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[value,player])
        def snapshot():
            return self.query('SELECT gold,gems,charm,hitpoints,maxhitpoints,turns,bufflist FROM accounts WHERE acctid=?',[player])[0]
        status,body=request('home.php')
        csrf=re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
        status,_=request('login.php',{'csrf_token':csrf,'name':'WebPlayer','password':"Synthetic web O'Reilly \\ password"})
        self.assertEqual(303,status)
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        self.query('UPDATE modules SET active=1')
        prior=self.query('SELECT * FROM accounts WHERE acctid=?',[player])[0]
        saved=self.query('SELECT setting,value FROM module_settings WHERE modulename=?',['crazyaudrey'])
        savedprefs=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=?',['crazyaudrey',player])
        def pref(key,value):
            self.query('INSERT INTO module_userprefs (modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=?',['crazyaudrey',key,player,str(value),str(value)])
        def setting(key,value):
            self.query('INSERT INTO module_settings (modulename,setting,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=?',['crazyaudrey',key,str(value),str(value)])
        def state():
            return (snapshot(),self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['crazyaudrey',player]),self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['crazyaudrey','profit']))
        def get(op):
            url='runmodule.php?module=crazyaudrey&op='+op
            allow(url); before=state(); status,body=request(url)
            self.assertEqual(200,status,body[-1000:]); self.assertEqual(before,state())
            return url,fields(body)
        try:
            self.query('UPDATE accounts SET gold=100,turns=30,alive=1,specialinc=? WHERE acctid=?',['',player])
            pref('played',0); pref('paidvisit',0)
            setting('cost',7); setting('profit',11)
            for key in ['animal','animals','lanimal','lanimals','sound','buffname']:
                setting(key,"O'Reilly \\ chat é <img src=x onerror=alert(1)>")
            # Bypassing allowed navigation cannot authorize a free Village basket reward.
            url,post=get('play'); before=state()
            self.assertEqual(409,request(url,post)[0]); self.assertEqual(before,state())
            url,post=get('pet'); before=state()
            self.assertEqual(403,request(url,{'action_token':post['action_token']})[0]); self.assertEqual(before,state())
            status,body=request(url,{**post,'cost':'0','profit':'999999','played':'0'})
            self.assertEqual(200,status); self.assertNotIn('<img src=x',body)
            after=state(); self.assertEqual(93,int(after[0]['gold'])); self.assertEqual('18',after[2][0]['value'])
            self.assertIn('crazyaudrey',after[0]['bufflist'])
            allow(url); self.assertEqual(409,request(url,post)[0]); self.assertEqual(after,state())
            url,post=get('play'); self.assertEqual(200,request(url,post)[0]); after=state()
            self.assertEqual('1',dict((r['setting'],r['value']) for r in after[1])['played'])
            self.assertEqual('0',dict((r['setting'],r['value']) for r in after[1])['paidvisit'])
            allow(url); self.assertEqual(409,request(url,post)[0]); self.assertEqual(after,state())
            url,post=get('play'); self.assertEqual(409,request(url,post)[0]); self.assertEqual(after,state())
            # Historical repeated petting is allowed, but never a second daily basket game.
            url,post=get('pet'); self.assertEqual(200,request(url,post)[0]); after=state()
            url,post=get('play'); self.assertEqual(409,request(url,post)[0]); self.assertEqual(after,state())
            self.query('UPDATE accounts SET gold=6 WHERE acctid=?',[player])
            url,post=get('pet'); before=state(); self.assertEqual(409,request(url,post)[0]); self.assertEqual(before,state())
            self.query('UPDATE accounts SET gold=100 WHERE acctid=?',[player])
            for bad in ['-1','no','2147483648']:
                setting('cost',bad); url,post=get('pet'); before=state()
                self.assertEqual(409,request(url,post)[0]); self.assertEqual(before,state())
            setting('cost',7)
            # A failed final player write rolls back the earlier preference/profit/debug writes.
            self.query('ALTER TABLE accounts ADD CONSTRAINT fixture_audrey_failure CHECK (gold <> 93)')
            url,post=get('pet'); before=state()
            audit=self.query('SELECT COUNT(*) AS n FROM debuglog WHERE actor=?',[player])
            try:
                self.assertEqual(500,request(url,post)[0]); self.assertEqual(before,state())
                self.assertEqual(audit,self.query('SELECT COUNT(*) AS n FROM debuglog WHERE actor=?',[player]))
            finally:
                self.query('ALTER TABLE accounts DROP CONSTRAINT fixture_audrey_failure')
            url,post=get('pet'); self.assertEqual(200,request(url,post)[0])
            # Active module and authentication remain mandatory even with an issued intent.
            url,post=get('pet'); before=state()
            self.query('UPDATE modules SET active=0 WHERE modulename=?',['crazyaudrey'])
            allow(url); request(url,post); self.assertEqual(before,state())
            self.query('UPDATE modules SET active=1 WHERE modulename=?',['crazyaudrey'])
            anonymous=urllib.request.build_opener(NoRedirect)
            try: response=anonymous.open(urllib.request.Request(f'http://127.0.0.1:{self.port}/'+url,data=urllib.parse.urlencode(post).encode()))
            except urllib.error.HTTPError as error: response=error
            self.assertIn(response.status,[302,303,403]); response.close(); self.assertEqual(before,state())
            url,old_day_post=get('pet')
            # The actual New Day route resets both daily played and the pending paid visit.
            pref('played',1); pref('paidvisit',1)
            self.query('UPDATE accounts SET lasthit=?,race=?,specialty=? WHERE acctid=?',['2000-01-01 00:00:00','Human','DA',player])
            allow('newday.php?continue=1')
            self.assertEqual(200,request('newday.php?continue=1')[0])
            current=dict((r['setting'],r['value']) for r in state()[1])
            self.assertEqual('0',current['played']); self.assertEqual('0',current['paidvisit'])
            before=state(); allow(url); self.assertEqual(409,request(url,old_day_post)[0]); self.assertEqual(before,state())
            url,post=get('play'); before=state(); self.assertEqual(409,request(url,post)[0]); self.assertEqual(before,state())
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in prior)+' WHERE acctid=?',list(prior.values())+[player])
            self.query('DELETE FROM module_settings WHERE modulename=?',['crazyaudrey'])
            for row in saved: setting(row['setting'],row['value'])
            self.query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',['crazyaudrey',player])
            for row in savedprefs: pref(row['setting'],row['value'])
            self.query('UPDATE modules SET active=0')

    def test_module_purchases_post_csrf_replay_and_effects(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        jar=http.cookiejar.CookieJar()
        client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect)
        def request(path, fields=None):
            req=urllib.request.Request(f'http://127.0.0.1:{self.port}/'+path,
                data=None if fields is None else urllib.parse.urlencode(fields).encode())
            try: response=client.open(req,timeout=15)
            except urllib.error.HTTPError as error: response=error
            body=response.read().decode('utf-8',errors='replace')
            self.assertNotRegex(body,r'(?i)(fatal error|warning:|deprecated:|notice:)',body[:1500])
            return response.status,body
        def fields(body):
            return {key:re.search(r'name=[\'"]'+key+r'[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
                    for key in ['csrf_token','action_token']}
        def allow(url):
            value='a:1:{s:'+str(len(url))+':"'+url+'";b:1;}'
            self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[value,player])
        def snapshot():
            return self.query('SELECT gold,gems,charm,hitpoints,maxhitpoints,specialty,race,bufflist FROM accounts WHERE acctid=?',[player])[0]
        status,body=request('home.php')
        csrf=re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})',body).group(1)
        status,_=request('login.php',{'csrf_token':csrf,'name':'WebPlayer','password':"Synthetic web O'Reilly \\ password"})
        self.assertEqual(303,status)
        player=self.query('SELECT acctid FROM accounts WHERE login=?',['WebPlayer'])[0]['acctid']
        self.query('UPDATE modules SET active=1')
        original=snapshot()
        self.query('UPDATE accounts SET gold=10000,gems=100,charm=10,hitpoints=10,maxhitpoints=10,race=?,specialty=? WHERE acctid=?',['Human','DA',player])
        try:
            url='runmodule.php?module=cedrikspotions&op=gems'
            for wish in range(1,6):
                allow(url); before=snapshot()
                status,body=request(url)
                self.assertEqual(200,status)
                post=fields(body)
                action=html.unescape(re.search(r'<form action=[\'"]([^\'"]+)[\'"] method=[\'"]POST',body).group(1))
                self.assertEqual(before,snapshot())
                status,_=request(action,{'wish':str(wish),'gemcount':'2'})
                self.assertEqual(403,status)
                status,body=request(action,{**post,'wish':str(wish),'gemcount':'2'})
                self.assertEqual(200,status)
                after=snapshot()
                self.assertEqual(int(before['gems'])-2,int(after['gems']))
                if wish==1: self.assertEqual(int(before['charm'])+1,int(after['charm']))
                if wish==2: self.assertEqual(int(before['maxhitpoints'])+1,int(after['maxhitpoints']))
                if wish==3: self.assertEqual(max(int(before['hitpoints']),int(before['maxhitpoints']))+20,int(after['hitpoints']))
                if wish==4: self.assertEqual('',after['specialty'])
                if wish==5:
                    self.assertEqual('Horrible Gelatinous Blob',after['race'])
                    self.assertIn('transmute',after['bufflist'])
                    self.assertIn('survivenewday',after['bufflist'])
                allow(action)
                status,_=request(action,{**post,'wish':str(wish),'gemcount':'2'})
                self.assertEqual(409,status)
                self.assertEqual(after,snapshot())
            for invalid in ['-2','0','2e1','99999999999999999999999']:
                allow(url); status,body=request(url); post=fields(body)
                action=html.unescape(re.search(r'<form action=[\'"]([^\'"]+)[\'"] method=[\'"]POST',body).group(1))
                before=snapshot(); status,_=request(action,{**post,'wish':'1','gemcount':invalid})
                self.assertEqual(400,status); self.assertEqual(before,snapshot())
            # Shared real Forest dispatcher: pending event, POST, consumed reward, replay.
            for module,choice in [('findgem',''),('findgold',''),('foilwench','give'),('fairy','give'),
                                  ('glowingstream','drink'),('goldmine','mine'),('crazyaudrey','play')]:
                self.query('UPDATE accounts SET specialinc=?,specialmisc=?,gems=20,gold=1000,hitpoints=100,maxhitpoints=100,turns=30,alive=1,race=?,specialty=? WHERE acctid=?',
                           ['module:'+module,'','Human','DA',player])
                skill_before=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['specialtydarkarts',player])
                event_url='forest.php?op='
                allow(event_url); before=snapshot(); status,body=request(event_url)
                self.assertEqual(200,status); self.assertEqual(before,snapshot())
                event_post=fields(body)
                status,_=request(event_url,{})
                self.assertEqual(403,status); self.assertEqual(before,snapshot())
                status,body=request(event_url,event_post)
                self.assertEqual(200,status)
                if choice:
                    event_url='forest.php?op='+choice
                    allow(event_url); status,body=request(event_url)
                    self.assertEqual(200,status)
                    event_post=fields(body)
                    before=snapshot()
                    status,body=request(event_url,event_post)
                    self.assertEqual(200,status)
                after=snapshot()
                if module=='findgem': self.assertEqual(int(before['gems'])+1,int(after['gems']))
                if module=='findgold':
                    level=int(self.query('SELECT level FROM accounts WHERE acctid=?',[player])[0]['level'])
                    self.assertGreaterEqual(int(after['gold'])-int(before['gold']),10*level)
                    self.assertLessEqual(int(after['gold'])-int(before['gold']),50*level)
                if module=='foilwench':
                    self.assertEqual(int(before['gems'])-1,int(after['gems']))
                    prior_skill=int(next((r['value'] for r in skill_before if r['setting']=='skill'),'0'))
                    skill_after=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['specialtydarkarts',player])
                    self.assertEqual(prior_skill+1,int(next(r['value'] for r in skill_after if r['setting']=='skill')))
                if module=='fairy': self.assertIn(int(after['gems'])-int(before['gems']),[-1,1])
                self.assertEqual('',self.query('SELECT specialinc FROM accounts WHERE acctid=?',[player])[0]['specialinc'])
                # A replay after completion cannot re-enter the consumed event.
                allow(event_url); status,_=request(event_url,event_post)
                self.assertIn(status,(200,302,303,403,409))
                self.assertEqual(after,snapshot())
                if module=='foilwench':
                    self.assertEqual(skill_after,self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['specialtydarkarts',player]))
            # Current Foil Wench encounter still requires a real gem, not merely a valid form.
            self.query('UPDATE accounts SET specialinc=?,gems=0,specialty=? WHERE acctid=?',['module:foilwench','DA',player])
            event_url='forest.php?op=give'
            allow(event_url); status,body=request(event_url)
            self.assertEqual(200,status); post=fields(body); before=snapshot()
            skill_before=self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['specialtydarkarts',player])
            invalid_url='forest.php?op[]=give'
            allow(invalid_url); status,_=request(invalid_url)
            self.assertEqual(400,status); self.assertEqual(before,snapshot())
            allow(event_url); status,_=request(event_url,post)
            self.assertEqual(200,status); self.assertEqual(before,snapshot())
            self.assertEqual(skill_before,self.query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=? ORDER BY setting',['specialtydarkarts',player]))
            self.query('UPDATE accounts SET alive=1,hitpoints=100,turns=30,specialinc=? WHERE acctid=?',['',player])
            # Server availability applies even when a forged form selects a hidden potion.
            self.query('UPDATE module_settings SET value=? WHERE modulename=? AND setting=?',['0','cedrikspotions','ischarm'])
            allow(url); status,body=request(url); post=fields(body)
            action=html.unescape(re.search(r'<form action=[\'"]([^\'"]+)[\'"] method=[\'"]POST',body).group(1))
            before=snapshot(); status,_=request(action,{**post,'wish':'1','gemcount':'2'})
            self.assertEqual(400,status); self.assertEqual(before,snapshot())
            self.query('UPDATE module_settings SET value=? WHERE modulename=? AND setting=?',['1','cedrikspotions','ischarm'])
            drink=self.query('SELECT drinkid,costperlevel FROM drinks WHERE harddrink=1 ORDER BY drinkid LIMIT 1')[0]
            url='runmodule.php?module=drinks&act=buy&id='+drink['drinkid']
            allow(url); before=snapshot(); status,body=request(url)
            self.assertEqual(200,status); self.assertEqual(before,snapshot()); post=fields(body)
            status,_=request(url,{})
            self.assertEqual(403,status)
            status,body=request(url,post)
            self.assertEqual(200,status)
            after=snapshot(); level=int(self.query('SELECT level FROM accounts WHERE acctid=?',[player])[0]['level'])
            self.assertEqual(int(before['gold'])-level*int(drink['costperlevel']),int(after['gold']))
            allow(url); status,_=request(url,post)
            self.assertEqual(409,status); self.assertEqual(after,snapshot())
            self.query('UPDATE module_userprefs SET value=? WHERE modulename=? AND setting=? AND userid=?',['3','drinks','harddrinks',player])
            allow(url); status,body=request(url); post=fields(body)
            status,_=request(url,post)
            self.assertEqual(400,status); self.assertEqual(after,snapshot())
            url='runmodule.php?module=sethsong'
            allow(url); before=snapshot(); status,body=request(url)
            self.assertEqual(200,status); self.assertEqual(before,snapshot()); post=fields(body)
            status,_=request(url,{})
            self.assertEqual(403,status)
            status,_=request(url,post)
            self.assertEqual(200,status)
            after=snapshot()
            self.assertEqual('1',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['sethsong','been',player])[0]['value'])
            allow(url); status,_=request(url,post)
            self.assertEqual(409,status); self.assertEqual(after,snapshot())
            for visit in ['pay','free']:
                for setting in ['usedouthouse','stage']:
                    self.query('INSERT INTO module_userprefs (modulename,setting,userid,value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value=?',['outhouse',setting,player,'0','0'])
                url='runmodule.php?module=outhouse&op='+visit
                allow(url); before=snapshot(); status,body=request(url)
                self.assertEqual(200,status); self.assertEqual(before,snapshot()); post=fields(body)
                status,_=request(url,{})
                self.assertEqual(403,status)
                status,_=request(url,post)
                self.assertEqual(200,status)
                used=snapshot()
                cost=int(self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?',['outhouse','cost'])[0]['value']) if visit=='pay' else 0
                self.assertEqual(int(before['gold'])-cost,int(used['gold']))
                allow(url); status,_=request(url,post)
                self.assertEqual(409,status); self.assertEqual(used,snapshot())
                url='runmodule.php?module=outhouse&op=wash'+visit
                allow(url); status,body=request(url); post=fields(body)
                status,_=request(url,post)
                self.assertEqual(200,status)
                washed=snapshot()
                self.assertEqual('0',self.query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',['outhouse','stage',player])[0]['value'])
                allow(url); status,_=request(url,post)
                self.assertEqual(409,status); self.assertEqual(washed,snapshot())
                # Even a freshly obtained CSRF/intent cannot repeat a completed visit.
                allow(url); status,body=request(url); post=fields(body)
                status,_=request(url,post)
                self.assertEqual(409,status); self.assertEqual(washed,snapshot())
            # A player cannot smuggle an internal module preference through a suffix.
            pref_before=self.query('SELECT modulename,setting,value FROM module_userprefs WHERE userid=? ORDER BY modulename,setting',[player])
            csrf=fields(body)['csrf_token']
            for forged in ['drinks___canedit___user_','specialtydarkarts___skill___check_',
                           'drinks___user_forged','absentmodule___user_forged']:
                pref_url='prefs.php?op=save'; allow(pref_url)
                status,_=request(pref_url,{'csrf_token':csrf,forged:'1'})
                self.assertIn(status,(400,403))
                self.assertEqual(pref_before,self.query('SELECT modulename,setting,value FROM module_userprefs WHERE userid=? ORDER BY modulename,setting',[player]))
            # Editor authorization is checked even with an issued route fixture.
            editor='runmodule.php?module=drinks&act=editor&op=edit&drinkid='+drink['drinkid']+'&admin=true'
            self.query('UPDATE accounts SET superuser=0 WHERE acctid=?',[player])
            allow(editor); status,_=request(editor)
            self.assertEqual(403,status)
            self.query('UPDATE accounts SET superuser=64 WHERE acctid=?',[player])
            allow(editor); status,body=request(editor)
            self.assertEqual(200,status); post=fields(body)
            original_drink=self.query('SELECT * FROM drinks WHERE drinkid=?',[drink['drinkid']])[0]
            values={key:value for key,value in original_drink.items() if key!='active'}
            values.update(post); values['name']="`2O'Reilly <img>"; values['remarks']="UTF-8 🐉 \\ apostrophe's"
            save='runmodule.php?module=drinks&act=editor&op=save&admin=true'
            status,_=request(save)
            self.assertEqual(403,status)
            status,_=request(save,{**values,'csrf_token':''})
            self.assertEqual(403,status)
            status,body=request(save,values)
            self.assertEqual(200,status)
            self.assertNotIn("O'Reilly <img>",body)
            saved=self.query('SELECT name,remarks FROM drinks WHERE drinkid=?',[drink['drinkid']])[0]
            self.assertEqual(values['name'],saved['name']); self.assertEqual(values['remarks'],saved['remarks'])
            allow(save); status,_=request(save,values)
            self.assertEqual(409,status)
            self.query('UPDATE drinks SET name=?,remarks=? WHERE drinkid=?',[original_drink['name'],original_drink['remarks'],drink['drinkid']])
        finally:
            self.query('UPDATE accounts SET superuser=0 WHERE acctid=?',[player])
            self.query('UPDATE modules SET active=0')
            self.query('UPDATE module_settings SET value=? WHERE modulename=? AND setting=?',['1','cedrikspotions','ischarm'])
            self.query('UPDATE accounts SET '+','.join(key+'=?' for key in original)+' WHERE acctid=?', [*original.values(),player])

    def test_create_login_rotate_render_logout(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args):
                return None
        jar = http.cookiejar.CookieJar()
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
        def request(path, fields=None, extra_headers=None):
            data = None if fields is None else urllib.parse.urlencode(fields).encode()
            req = urllib.request.Request(f'http://127.0.0.1:{self.port}/' + path, data=data, headers=extra_headers or {})
            try:
                response = client.open(req, timeout=15)
            except urllib.error.HTTPError as response_error:
                response = response_error
            body = response.read().decode('utf-8', errors='replace')
            self.assertNotRegex(body, r'(?i)(fatal error|warning:|deprecated:|notice:|runtime error in)', body[:2500])
            return response.status, response.headers, body
        def token(body):
            match = re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})', body)
            self.assertIsNotNone(match, body[:500])
            return match.group(1)
        def comment_form(body):
            class Forms(HTMLParser):
                def __init__(self):
                    super().__init__()
                    self.forms = []
                    self.current = None
                def handle_starttag(self, tag, attrs):
                    attributes = dict(attrs)
                    if tag == 'form':
                        self.current = [attributes.get('action', ''), {}]
                    elif tag == 'input' and self.current is not None and 'name' in attributes:
                        self.current[1][attributes['name']] = attributes.get('value', '')
                def handle_endtag(self, tag):
                    if tag == 'form' and self.current is not None:
                        self.forms.append(self.current)
                        self.current = None
            forms = Forms()
            forms.feed(body)
            return next(form for form in forms.forms if 'insertcommentary' in form[1])
        def issued_link(body, prefix):
            links = re.findall(r'href=[\'"]([^\'"]+)', body)
            found = next((html.unescape(link) for link in links if html.unescape(link).startswith(prefix)), None)
            self.assertIsNotNone(found, 'Missing issued navigation: ' + prefix)
            return found
        def session_id():
            return next(c.value for c in jar if c.name == 'PHPSESSID')

        status, headers, body = request('home.php', extra_headers={'Cookie': 'PHPSESSID=attackerchosenid1234567890123456'})
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        initial_id = session_id()
        self.assertNotEqual('attackerchosenid1234567890123456', initial_id)
        self.assertIn('HttpOnly', headers.get('Set-Cookie', ''))
        self.assertIn('SameSite=Lax', headers.get('Set-Cookie', ''))
        status, _, body = request('create.php')
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        csrf = token(body)
        password = "Synthetic web O'Reilly \\ password"
        status, _, body = request('create.php?op=create', {'csrf_token': csrf, 'name': 'WebPlayer',
                                    'pass1': password, 'pass2': password, 'email': 'web@example.invalid', 'sex': '0'})
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        self.assertIn('Your account was created', body)
        self.assertNotIn(password, body)
        stored_hash = self.query('SELECT password FROM accounts WHERE login=?', ['WebPlayer'])[0]['password']
        for rejected in ['wrong password', stored_hash]:
            status, headers, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password': rejected})
            self.assertEqual(303, status)
            self.assertEqual('index.php', headers['Location'])
            self.assertEqual(initial_id, session_id())
        status, _, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password[]': password})
        self.assertEqual(400, status)
        logs = self.query('SELECT post,id FROM faillog')
        self.assertTrue(logs)
        for record in logs:
            self.assertEqual('invalid_credentials', record['post'])
            self.assertEqual('', record['id'])
        for secret in [password, stored_hash, initial_id, csrf]:
            self.assertNotIn(secret, json.dumps(logs))
        status, headers, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password': password})
        self.assertEqual(303, status)
        self.assertEqual('village.php', headers['Location'])
        self.assertNotEqual(initial_id, session_id())
        authenticated_id = session_id()
        self.query('UPDATE modules SET active=1 WHERE modulename IN (?,?)',['racehuman','specialtymysticpower'])
        status, headers, body = request('village.php')
        # First login follows the game's existing character onboarding, including
        # active Human and Mystical Powers POST selection.
        if status in (302, 303) and headers['Location'] == 'newday.php':
            status, _, body = request('newday.php')
            self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
            race_form=self._security_fields(body) | {'onboarding':'race','setrace':'Human'}
            status, _, body=request('newday.php?continue=1',race_form)
            self.assertEqual(200,status)
            status, _, body=request(issued_link(body,'newday.php?continue=1'))
            self.assertEqual(200,status)
            specialty_form=self._security_fields(body) | {'onboarding':'specialty','setspecialty':'MP'}
            status, _, body=request('newday.php?continue=1',specialty_form)
            self.assertEqual(200,status)
            status, _, body=request(issued_link(body,'newday.php?continue=1'))
            self.assertEqual(200,status)
            status, headers, body = request(issued_link(body, 'village.php'))
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        self.assertIn('WebPlayer', body)
        action, fields = comment_form(body)
        comment = "O'Reilly \\ <script>alert(8675309)</script>"
        fields['insertcommentary'] = comment
        status, _, body = request(action, fields)
        self.assertEqual(200, status)
        self.assertNotIn('<script>alert(8675309)</script>', body)
        rows = self.query('SELECT commentid,comment FROM commentary WHERE author=(SELECT acctid FROM accounts WHERE login=?) ORDER BY commentid DESC LIMIT 1', ['WebPlayer'])
        self.assertEqual(comment, rows[0]['comment'])
        action, fields = comment_form(body)
        status, _, _ = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(403, status)
        self.assertEqual(1, len(self.query('SELECT commentid FROM commentary WHERE commentid=?', [rows[0]['commentid']])))
        # Grant only the documented moderator bit in this disposable fixture.
        self.query('UPDATE accounts SET superuser=16 WHERE login=?', ['WebPlayer'])
        before_privilege_change = session_id()
        status, _, _ = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(403, status)  # privilege refresh rotated the old CSRF token
        self.assertNotEqual(before_privilege_change, session_id())
        status, _, body = request(action)
        self.assertEqual(200, status)
        action, fields = comment_form(body)
        status, _, body = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(200, status)
        self.assertEqual([], self.query('SELECT commentid FROM commentary WHERE commentid=?', [rows[0]['commentid']]))
        authenticated_id = session_id()

        # All bundled modules active in one real authenticated HTTP session.
        # Lifecycle/cache transitions are tested through the real API in PHPUnit.
        self.query('UPDATE modules SET active=1')
        try:
            village_action, _ = comment_form(body)
            status, headers, body = request(village_action)
            self.assertEqual(200, status, headers.get('Location', 'Village render failed'))
            status, _, body = request(issued_link(body, 'inn.php'))
            self.assertEqual(200, status)
            for module in ['dag', 'lovers', 'sethsong']:
                status, _, body = request(issued_link(body, 'runmodule.php?module=' + module))
                self.assertEqual(200, status)
                self.assertIn('WebPlayer', body)
                if module == 'dag':
                    player_id = self.query('SELECT acctid FROM accounts WHERE login=?', ['WebPlayer'])[0]['acctid']
                    target = self.query('SELECT acctid,name,level,age FROM accounts WHERE login=?', ['FixtureAdmin'])[0]
                    gold = self.query('SELECT gold FROM accounts WHERE acctid=?', [player_id])[0]['gold']
                    self.query('UPDATE accounts SET gold=1000 WHERE acctid=?', [player_id])
                    self.query('UPDATE accounts SET level=5,age=10 WHERE acctid=?', [target['acctid']])
                    try:
                        status, _, body = request(issued_link(body, 'runmodule.php?module=dag&op=addbounty'))
                        self.assertEqual(200, status)
                        nonce = re.search(r'name="action_token" value="([a-f0-9]{64})"', body).group(1)
                        fields = {'csrf_token':token(body),'action_token':nonce,'contractname':'FixtureAdmin','amount':'250'}
                        url = 'runmodule.php?module=dag&op=finalize'
                        status, _, _ = request(url)
                        self.assertEqual(403,status)
                        status, _, _ = request(url, {'contractname':'FixtureAdmin','amount':'250'})
                        self.assertEqual(403,status)
                        self.assertEqual('1000',self.query('SELECT gold FROM accounts WHERE acctid=?',[player_id])[0]['gold'])
                        status, _, body = request(url,fields)
                        self.assertEqual(200,status)
                        self.assertEqual('725',self.query('SELECT gold FROM accounts WHERE acctid=?',[player_id])[0]['gold'])
                        bounties = self.query('SELECT bountyid,amount FROM bounty WHERE setter=? AND target=?',[player_id,target['acctid']])
                        self.assertEqual(1,len(bounties))
                        status, _, _ = request(url,fields)
                        self.assertIn(status,(302,303,409))
                        self.assertEqual(bounties,self.query('SELECT bountyid,amount FROM bounty WHERE setter=? AND target=?',[player_id,target['acctid']]))
                        self.assertEqual('725',self.query('SELECT gold FROM accounts WHERE acctid=?',[player_id])[0]['gold'])
                        status, _, body = request('runmodule.php?module=dag')
                        # Force a valid navigation fixture to prove the capability boundary itself.
                        admin_url = 'runmodule.php?module=dag&manage=true'
                        allowed = 'a:1:{s:'+str(len(admin_url))+':"'+admin_url+'";b:1;}'
                        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[allowed,player_id])
                        status, _, _ = request(admin_url)
                        self.assertEqual(403,status)
                        allowed = 'a:1:{s:7:"inn.php";b:1;}'
                        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?',[allowed,player_id])
                        status, _, body = request('inn.php')
                        status, _, body = request(issued_link(body,'runmodule.php?module=dag'))
                    finally:
                        self.query('DELETE FROM bounty WHERE setter=? AND target=?',[player_id,target['acctid']])
                        self.query('UPDATE accounts SET gold=? WHERE acctid=?',[gold,player_id])
                        self.query('UPDATE accounts SET level=?,age=? WHERE acctid=?',[target['level'],target['age'],target['acctid']])
                status, _, body = request(issued_link(body, 'inn.php'))
                self.assertEqual(200, status)
            status, _, body = request(issued_link(body, 'village.php'))
            self.assertEqual(200, status)
            status, _, body = request(issued_link(body, 'forest.php'))
            self.assertEqual(200, status)
            self.assertIsNotNone(issued_link(body, 'runmodule.php?module=outhouse'))
            # Actual Dark Horse event -> Stones forms. Only fixture setup writes SQL.
            player_id = self.query('SELECT acctid FROM accounts WHERE login=?', ['WebPlayer'])[0]['acctid']
            prior_gold = self.query('SELECT gold FROM accounts WHERE acctid=?', [player_id])[0]['gold']
            event_url = 'forest.php?op=oldman'
            allowed = 'a:1:{s:' + str(len(event_url)) + ':"' + event_url + '";b:1;}'
            self.query('UPDATE accounts SET specialinc=?,specialmisc=?,gold=100,allowednavs=? WHERE acctid=?',
                       ['module:darkhorse', '', allowed, player_id])
            status, _, body = request(event_url)
            self.assertEqual(200, status)
            status, _, body = request(issued_link(body, 'runmodule.php?module=game_stones'))
            self.assertEqual(200, status)
            game_url = 'runmodule.php?module=game_stones'
            def game_fields(body, **extra):
                nonce = re.search(r'name="action_token" value="([a-f0-9]{64})"', body)
                self.assertIsNotNone(nonce, body[:1000])
                return {'csrf_token': token(body), 'action_token': nonce.group(1), **extra}
            fields = game_fields(body, action='choose', side='likepair')
            status, _, _ = request(game_url, {'action':'choose', 'side':'likepair'})
            self.assertEqual(403, status)
            self.assertEqual('', self.query('SELECT specialmisc FROM accounts WHERE acctid=?', [player_id])[0]['specialmisc'])
            status, _, body = request(game_url, fields)
            self.assertEqual(200, status)
            status, _, _ = request(game_url, fields)
            self.assertEqual(409, status)
            status, _, body = request(game_url)
            fields = game_fields(body, action='bet', bet='-10')
            status, _, _ = request(game_url, fields)
            self.assertEqual(400, status)
            self.assertEqual('100', self.query('SELECT gold FROM accounts WHERE acctid=?', [player_id])[0]['gold'])
            status, _, body = request(game_url)
            fields = game_fields(body, action='bet', bet='10')
            status, _, body = request(game_url, fields)
            self.assertEqual(200, status)
            self.assertEqual('90', self.query('SELECT gold FROM accounts WHERE acctid=?', [player_id])[0]['gold'])
            for _ in range(9):
                wager = json.loads(self.query('SELECT specialmisc FROM accounts WHERE acctid=?', [player_id])[0]['specialmisc'])
                state = json.loads(wager['data'])
                action = 'settle' if state['red']+state['blue']==0 or state['player']>8 or state['oldman']>8 else 'draw'
                fields = game_fields(body, action=action)
                if action == 'settle':
                    before_failure=self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?',[player_id])[0]
                    self.wager_failure('game_stones',player_id)
                    try:
                        status,_,_=request(game_url,fields); self.assertEqual(503,status)
                        self.assertEqual(before_failure,self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?',[player_id])[0])
                    finally: self.remove_wager_failure()
                    status,_,body=request(game_url); self.assertEqual(200,status)
                    fields=game_fields(body,action=action)
                status, _, body = request(game_url, fields)
                self.assertEqual(200, status)
                saved = self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?', [player_id])[0]
                status, _, _ = request(game_url, fields)
                self.assertEqual(409, status)
                self.assertEqual(saved, self.query('SELECT gold,specialmisc FROM accounts WHERE acctid=?', [player_id])[0])
                status, _, body = request(game_url)
                self.assertEqual(200, status)
                if action == 'settle':
                    self.assertTrue(json.loads(saved['specialmisc'])['settled'])
                    self.assertFalse(json.loads(saved['specialmisc'])['active'])
                    self.assertIn(saved['gold'], ['90','100','110'])
                    break
            else:
                self.fail('Stones did not complete within its finite draw limit')
            self.query('UPDATE accounts SET specialmisc=? WHERE acctid=?', ['O:8:"stdClass":0:{}', player_id])
            status, _, _ = request(game_url)
            self.assertEqual(409, status)
            self.query('UPDATE accounts SET specialmisc=?,gold=? WHERE acctid=?', ['[]', prior_gold, player_id])
            status, _, body = request(game_url)
            status, _, body = request(issued_link(body, 'forest.php?op=tavern'))
            self.assertEqual(200, status)
            status, _, body = request(issued_link(body, 'forest.php?op=leave'))
            self.assertEqual(200, status)
            status, _, body = request('forest.php?op=leave', self._security_fields(body,'forest.php?op=leave'))
            self.assertEqual(200, status)
            status, _, body = request(issued_link(body, 'village.php'))
            self.assertEqual(200, status)
        finally:
            self.query('UPDATE modules SET active=0')


        # Actual mailbox UI: stored subject HTML, HTTP methods, CSRF and ownership.
        player_id = self.query('SELECT acctid FROM accounts WHERE login=?', ['WebPlayer'])[0]['acctid']
        subject = '<img src=x onerror=alert(8675309)>'
        self.query('INSERT INTO mail (msgfrom,msgto,subject,body,sent) VALUES (1,?,?,?,NOW())', [player_id, subject, 'Synthetic message'])
        message_id = self.query('SELECT messageid FROM mail WHERE msgto=? ORDER BY messageid DESC LIMIT 1', [player_id])[0]['messageid']
        status, _, body = request('mail.php')
        self.assertEqual(200, status)
        self.assertNotIn(subject, body)
        csrf = token(body)
        status, _, _ = request('mail.php?op=del&id=' + message_id)
        self.assertEqual(403, status)
        status, _, _ = request('mail.php?op=del', {'id':message_id})
        self.assertEqual(403, status)
        status, _, _ = request('mail.php?op=process', {'csrf_token':csrf, 'msg[]':"1') OR 1=1 --"})
        self.assertEqual(400, status)
        self.assertEqual(1, len(self.query('SELECT messageid FROM mail WHERE messageid=?', [message_id])))
        status, _, _ = request('mail.php?op=del', {'csrf_token':csrf, 'id':message_id})
        self.assertEqual(303, status)
        self.assertEqual([], self.query('SELECT messageid FROM mail WHERE messageid=?', [message_id]))

        status, _, body = request('petition.php')
        self.assertEqual(200, status)
        petition_token = token(body)
        status, _, _ = request('petition.php?op=submit')
        self.assertEqual(403, status)
        status, _, _ = request('petition.php?op=submit', {'description':'missing token'})
        self.assertEqual(403, status)
        description = "Petition O'Reilly \\ <script>synthetic</script>"
        status, _, body = request('petition.php?op=submit', {'csrf_token':petition_token, 'description':description,
            'password':'SYNTHETIC-UNEXPECTED-FIELD', 'charname':'Forged identity'})
        self.assertEqual(200, status)
        self.assertIn('Your petition has been sent', body)
        saved = self.query('SELECT body,pageinfo,id FROM petitions WHERE author=? ORDER BY petitionid DESC LIMIT 1', [player_id])[0]
        self.assertIn(description, saved['body'])
        self.assertNotIn('Forged identity', saved['body'])
        self.assertEqual('Session diagnostics intentionally omitted.', saved['pageinfo'])
        self.assertEqual('', saved['id'])
        for secret in [password, stored_hash, session_id(), petition_token, 'SYNTHETIC-UNEXPECTED-FIELD']:
            self.assertNotIn(secret, json.dumps(saved))

        status, _, body = request('login.php?op=logout')
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        status, headers, _ = request('login.php?op=logout', {'csrf_token': token(body)})
        self.assertEqual(303, status)
        self.assertFalse(any(c.name == 'PHPSESSID' for c in jar))
        status, headers, _ = request('village.php')
        self.assertIn(status, (302, 303))
        self.assertIn('index.php', headers['Location'])
        status, headers, _ = request('village.php', extra_headers={'Cookie': 'PHPSESSID=' + authenticated_id})
        self.assertIn(status, (302, 303))
        self.assertIn('index.php', headers['Location'])
        status, _, _ = request('create.php?op=forgot')
        self.assertEqual(410, status)
        status, _, _ = request('runmodule.php?module[]=drinks&admin=true')
        self.assertEqual(400, status)
        status, _, _ = request('installer.php?stage=9')
        self.assertEqual(403, status)
