#!/usr/bin/env python3
"""The phone tap-target check (Phase 6 task 19, N3 and N8 site-wide; docs/UI-DESIGN-SPEC.md, "Tap targets").

On a phone (390 x 844) every visible link, button, select, checkbox and radio on every page the crawl reaches is at
least 44 x 44 px, a checkbox or radio by its largest label. The one exemption is running text: a link inside a sentence
of game prose, where a 44 px box would break the sentence. Each one is listed in EXEMPT with its reason.

CI has no browser, so this is a local check, not a test (CLAUDE.md, "No test may skip in CI"); the result goes in the
task's evidence doc. It needs Node with Playwright and its Chromium (NODE_PATH), `php` on PATH, and an empty disposable
database named by RESURRECTION_TEST_DB_HOST, _USER, _PASSWORD and _NAME, as scripts/release-upgrade-check.py does.

    python3 tests/browser/tap_targets.py [--tree DIR] [--width 390|1440] [--out FILE] [--old-css FILE] [--results FILE]

It copies the tree (the working tree by default) to a temporary folder, installs it with the recommended modules,
adds three crawl accounts and a little content, serves it with `php -S`, and crawls it as four roles
(tests/browser/tap-targets.js). The server lets each crawl request through the game's navigation check
(allowednavs) for the crawl account named in an X-Crawl-Acct header, from a prepend file written to the temporary
folder: never part of the game, and never in its tree.

At 390 it exits 1 on any target under 44 x 44 that EXEMPT does not name. With --old-css (a stylesheet from before a
change) each page is measured again with that file in its place, the same response, so only the stylesheet differs; at
1440 it exits 1 when any element moved or changed size, which a phone-only change must not do. Exit 2: it cannot run.
"""

import argparse
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CRAWLER = Path(__file__).resolve().parent / 'tap-targets.js'
TOUCH = 44

# Running text only: a link inside a sentence of game prose. A link alone in a table cell or a nav row is never one.
# (page, selector pattern, href pattern, reason); the patterns are regular expressions searched in the crawl's values.
EXEMPT = [
    ('about.php', r'span\.colDkGreen > a$', r'^https?://',
     "about.php's credits: the links inside its 1.1.2 paragraphs (\"by Seth Able Robinson\", \"under a Creative Commons "
     "License\", \"at DragonPrime\"), whose markup stays 1.1.2's byte for byte"),
    ('petition.php', r'form > a$', r'^petition\.php\?op=faq$',
     "the petition form's sentence \"please check out the FAQ.\""),
    ('viewpetition.php', r'span\.rz-typed > a$', r'^mailto:',
     "an address inside the petition's own text, which the page turns into a mailto link where it stands"),
]

PREPEND = r"""<?php
// The tap-target crawl only (tests/browser/tap_targets.py), never the game: with X-Crawl-Acct, allow exactly this
// request for that crawl account, so each page renders as if reached by its link.
if (isset($_SERVER['HTTP_X_CRAWL_ACCT']) && ctype_digit($_SERVER['HTTP_X_CRAWL_ACCT']) && getenv('RZ_CRAWL_DSN')) {
    $uri = ltrim((string)($_SERVER['REQUEST_URI'] ?? ''), '/');
    $pdo = new PDO(getenv('RZ_CRAWL_DSN'), getenv('RZ_CRAWL_USER'), getenv('RZ_CRAWL_PASSWORD'));
    $pdo->prepare('UPDATE accounts SET allowednavs=? WHERE acctid=? AND login LIKE ?')->execute([serialize([$uri => true]), (int)$_SERVER['HTTP_X_CRAWL_ACCT'], 'Crawl%']);
    unset($pdo, $uri);
}
"""

SEED = r"""<?php
// The tap-target crawl's accounts (on today's game day) and content, so mail, petitions, commentary and news have rows.
define("RESURRECTION_ENTRY", true); require "dbconnect.php"; ob_start();
foreach (["dbwrapper_pdo","constants","datacache","settings","datetime","installer/fresh_install","accounts"] as $l) require_once "lib/$l.php";
ob_end_clean(); db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME);
$day = gmdate("Y-m-d", gametime()); $now = date("Y-m-d H:i:s");
$ids = ['CrawlAdmin' => (int)db_query("SELECT acctid FROM accounts WHERE login='CrawlAdmin'")[0]['acctid']];
foreach (["CrawlPlayer","CrawlDead"] as $login) $ids[$login] = resurrection_create_account($login, "Synthetic crawl player password", strtolower($login)."@example.invalid");
foreach (["CrawlAdmin"=>1,"CrawlPlayer"=>1,"CrawlDead"=>0] as $login=>$alive)
  db_query("UPDATE accounts SET name=?,race=?,specialty=?,lastnewday=?,lasthit=?,laston=?,loggedin=0,alive=?,hitpoints=?,maxhitpoints=50,level=5,gold=500,gems=5,turns=10,deathpower=100,gravefights=10,soulpoints=50,restorepage=?,allowednavs=? WHERE login=?", true,
    [$login,"Human","MP",$day,$now,$now,$alive,$alive?50:0,"","a:0:{}",$login]);
$a=$ids['CrawlAdmin']; $p=$ids['CrawlPlayer']; $d=$ids['CrawlDead'];
foreach ([[$a,$p,'Welcome','A word from the staff.'],[$p,$a,'Question','How do I train?'],[0,$p,'Notice','A system message.'],[$d,$a,'From the shades','Send help.']] as [$f,$t,$s,$b])
  db_query("INSERT INTO mail (msgfrom,msgto,subject,body,sent,seen) VALUES (?,?,?,?,?,0)", true, [$f,$t,$s,$b,$now]);
db_query("INSERT INTO petitions (author,date,status,body,pageinfo,ip,id) VALUES (?,?,0,?,?,?,?)", true, [$p,$now,"[charname] CrawlPlayer\n[email] crawlplayer@example.invalid\n[description] I am stuck in the forest.",'','127.0.0.1','crawl']);
foreach ([['village',$p,'Hello, village!'],['village',$a,'Welcome to the game.'],['village',$p,':waves'],['shade',$d,'It is cold here.'],['superuser',$a,'Staff note.']] as [$sec,$au,$c])
  db_query("INSERT INTO commentary (section,author,comment,postdate) VALUES (?,?,?,?)", true, [$sec,$au,$c,$now]);
foreach (["`%CrawlPlayer`5 has slain a monster.","`%CrawlDead`5 was killed in the forest."] as $n)
  db_query("INSERT INTO news (newstext,newsdate,accountid,arguments,tlschema) VALUES (?,?,?,?,?)", true, [$n,$day,$p,'','news']);
if (array_values($ids) !== [1, 2, 3]) { fwrite(STDERR, "unexpected account ids " . json_encode($ids) . "\n"); exit(1); }
echo "seeded, game day $day\n";
"""


def fail(message, code=2):
    print(f'tap-target check: {message}', file=sys.stderr)
    sys.exit(code)


def exempt(page, item):
    script = page['path'].split('?')[0]
    for where, selector, href, _ in EXEMPT:
        if script == where and re.search(selector, item['sel']) and re.search(href, item['href']):
            return True
    return False


def small(item):
    return item['w'] < TOUCH or item['h'] < TOUCH


def crawl(tree, width, out, old_css=None):
    for name in ('RESURRECTION_TEST_DB_HOST', 'RESURRECTION_TEST_DB_USER', 'RESURRECTION_TEST_DB_PASSWORD', 'RESURRECTION_TEST_DB_NAME'):
        if not os.environ.get(name):
            fail(f'{name} is not set (an empty disposable database)')
    php, node = shutil.which('php'), shutil.which('node')
    if not php or not node:
        fail('needs php and node on PATH')
    probe = subprocess.run([node, '-e', "require('playwright')"], capture_output=True, text=True)
    if probe.returncode:
        fail('needs Node with Playwright (set NODE_PATH)')
    host = os.environ['RESURRECTION_TEST_DB_HOST']
    hostname, _, port = host.partition(':')
    work = Path(tempfile.mkdtemp(prefix='rz-tap-'))
    site = work / 'site'
    ignore = shutil.ignore_patterns('vendor', '.git', '.phpunit.cache', '.phpstan-cache', 'dbconnect.php', '__pycache__', 'node_modules')
    shutil.copytree(tree, site, ignore=ignore, symlinks=True)
    env = dict(os.environ, DB_HOST=host, DB_USER=os.environ['RESURRECTION_TEST_DB_USER'], DB_PASS=os.environ['RESURRECTION_TEST_DB_PASSWORD'],
               DB_NAME=os.environ['RESURRECTION_TEST_DB_NAME'], DB_PREFIX='', RESURRECTION_ADMIN_LOGIN='CrawlAdmin',
               RESURRECTION_ADMIN_PASSWORD='Synthetic crawl admin password')
    installed = subprocess.run([php, 'scripts/install.php', '--write-config', '--allow-served-path', str(site / 'dbconnect.php')],
                               cwd=site, env=env, capture_output=True, text=True)
    if installed.returncode:
        fail(f'the install failed (is the database empty?): {installed.stdout[-400:]} {installed.stderr[-400:]}')
    (work / 'seed.php').write_text(SEED)
    seeded = subprocess.run([php, str(work / 'seed.php')], cwd=site, capture_output=True, text=True)
    if seeded.returncode:
        fail(f'seeding failed: {seeded.stdout[-400:]} {seeded.stderr[-400:]}')
    (work / 'prepend.php').write_text(PREPEND)
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        http_port = s.getsockname()[1]
    server_env = dict(os.environ, RZ_CRAWL_DSN=f'mysql:host={hostname};port={port or 3306};dbname={env["DB_NAME"]}',
                      RZ_CRAWL_USER=env['DB_USER'], RZ_CRAWL_PASSWORD=env['DB_PASS'], PHP_CLI_SERVER_WORKERS='4')
    log = open(work / 'server.log', 'w')
    server = subprocess.Popen([php, '-d', f'auto_prepend_file={work / "prepend.php"}', '-d', 'display_errors=0', '-d', 'log_errors=1',
                               '-d', f'error_log={work / "php-errors.log"}', '-d', 'sendmail_path=/bin/false',
                               '-S', f'127.0.0.1:{http_port}', '-t', str(site)], env=server_env, stdout=log, stderr=log)
    base = f'http://127.0.0.1:{http_port}/'
    try:
        for _ in range(50):
            try:
                urllib.request.urlopen(base + 'home.php', timeout=2)
                break
            except OSError:
                time.sleep(0.2)
        result = subprocess.run([node, str(CRAWLER), base, str(out), str(width)] + ([str(Path(old_css).resolve())] if old_css else []), text=True)
        if result.returncode:
            fail('the crawl failed')
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()
        shutil.rmtree(work, ignore_errors=True)
    return json.loads(Path(out).read_text())


def check_errors(pages):
    errors = [p for p in pages if 'error' in p]
    for p in errors:
        print(f"  not measured: {p['role']}\t{p['path']}\t{p['error']}")
    return 1 if errors else 0


def check_phone(pages):
    findings = [(p, i) for p in pages for i in p.get('items', []) if small(i) and not exempt(p, i)]
    exempted = sum(1 for p in pages for i in p.get('items', []) if small(i) and exempt(p, i))
    targets = sum(len(p.get('items', [])) for p in pages)
    roles = {}
    for p in pages:
        roles[p['role']] = roles.get(p['role'], 0) + 1
    print(f'pages {len(pages)} ({", ".join(f"{k} {v}" for k, v in roles.items())}), targets {targets}, '
          f'under {TOUCH} x {TOUCH}: {len(findings)} (exempt as running text: {exempted})')
    for p, i in findings:
        print(f"  {p['role']}\t{p['path']}\t{i['kind']}\t{i['w']} x {i['h']}\t{i['sel']}\t{i['text']!r}")
    return 1 if findings else 0


def check_css(pages, width):
    """Each page measured with the old stylesheet in place of the new: at 1440 nothing may move."""
    unswapped = [p for p in pages if 'cssDiff' in p and not p['cssDiff']['swapped']]
    moved = [p for p in pages if 'cssDiff' in p and p['cssDiff']['swapped'] and (p['cssDiff']['boxes'] or p['cssDiff']['height'][0] != p['cssDiff']['height'][1])]
    compared = sum(1 for p in pages if 'cssDiff' in p and p['cssDiff']['swapped'])
    print(f'at {width}: pages compared against the old stylesheet {compared}, pages where an element moved or changed size: {len(moved)}'
          f'{f", pages without the stylesheet: {len(unswapped)}" if unswapped else ""}')
    for p in moved:
        d = p['cssDiff']
        print(f"  {p['role']}\t{p['path']}\t{d['boxes']} element box(es), page height {d['height'][0]} -> {d['height'][1]}, "
              f"{len(d['targets'])} target(s) changed")
    return 1 if moved and width == 1440 else 0


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--tree', default=str(ROOT))
    parser.add_argument('--width', type=int, default=390, choices=[390, 1440])
    parser.add_argument('--out', default=None)
    parser.add_argument('--old-css', default=None, help="measure each page again with this file in place of assets/ui/resurrection.css")
    parser.add_argument('--results', default=None, help='check a saved crawl instead of crawling')
    args = parser.parse_args()
    if args.results:
        pages = json.loads(Path(args.results).read_text())
    else:
        out = args.out or os.path.join(tempfile.gettempdir(), f'rz-tap-targets-{args.width}.json')
        pages = crawl(Path(args.tree).resolve(), args.width, out, args.old_css)
        print(f'crawl saved to {out}')
    code = check_phone(pages) if args.width == 390 else 0
    code = max(code, check_errors(pages))
    if any('cssDiff' in p for p in pages):
        code = max(code, check_css(pages, args.width))
    sys.exit(code)


if __name__ == '__main__':
    main()
