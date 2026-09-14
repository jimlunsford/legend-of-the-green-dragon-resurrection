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
        cls.server_log = tempfile.TemporaryFile(mode='w+t')
        cls.server = subprocess.Popen([shutil.which('php'), '-d', 'display_errors=1', '-d', 'error_reporting=-1',
                                       '-d', 'zend.exception_ignore_args=1', '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT)],
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
            self.assertEqual(200,request('newday.php?setrace=Human')[0])
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
                status,body=request('forest.php?op=fight'); self.assertEqual(200,status,body[:1500])
                if remaining: self.assertEqual(remaining,buffs()['transmute']['rounds'])
                else: self.assertNotIn('transmute',buffs())
            self.query("UPDATE accounts SET badguy='' WHERE acctid=?",[player])
            # A new potion without carry expires at the next real New Day.
            stored({}); self.assertEqual(200,buy()[2][0]); self.assertEqual(0,buffs()['transmute']['survivenewday'])
            self.assertEqual(200,request('newday.php?setrace=Human')[0]); self.assertEqual(200,request('newday.php?continue=1')[0]); self.assertNotIn('transmute',buffs())
            for invalid in [None,{},dict(first,rounds=0),dict(first,rounds=-1),dict(first,rounds=2147483648),dict(first,atkmod='<attack>'),dict(first,defmod=[]),dict(first,forged=1)]:
                stored({'transmute':invalid}); before=self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player])
                self.assertEqual(400,request(base)[0]); self.assertEqual(before,self.query('SELECT gems,race,bufflist FROM accounts WHERE acctid=?',[player]))
            stored({}); self.assertEqual(200,request(base)[0])
        finally:
            self.query('UPDATE accounts SET '+','.join(k+'=?' for k in original)+' WHERE acctid=?',[*original.values(),player])
            self.query('DELETE FROM module_settings WHERE modulename=?',['cedrikspotions'])
            for row in saved: self.query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',['cedrikspotions',row['setting'],row['value']])
            self.query('UPDATE modules SET active=0')


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
                link=re.search(r'href=[\'"](dragon.php\?op=prologue1[^\'"]*)',body); self.assertIsNotNone(link,body[:1500])
                status,body=request(html.unescape(link.group(1))); self.assertEqual(200,status,body[:1500])
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
                link=re.search(r'href=[\'"](dragon.php\?op=prologue1[^\'"]*)',body); self.assertIsNotNone(link,body[:1500])
                status,body=request(html.unescape(link.group(1))); self.assertEqual(200,status,body[:1500])
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
            status,body=request('forest.php?op=search'); self.assertEqual(200,status,body[:1000]); self.assertIn('cluster of trees',body)
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
        def request(url, data=None):
            req=urllib.request.Request(f'http://127.0.0.1:{self.port}/'+url,
                data=None if data is None else urllib.parse.urlencode(data).encode())
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
        status, headers, body = request('village.php')
        # First login follows the game's existing character onboarding, including
        # fallback choices when no races or specialties have been activated.
        if status in (302, 303) and headers['Location'] == 'newday.php':
            status, _, body = request('newday.php')
            self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
            for _ in range(3):
                if 'No Races Installed' not in body and 'No Specialties Installed' not in body:
                    break
                status, headers, body = request(issued_link(body, 'newday.php?continue=1'))
                self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
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
