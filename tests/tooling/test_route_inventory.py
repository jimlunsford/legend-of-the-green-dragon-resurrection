"""The route inventory (docs/ROUTE-INVENTORY-20261022.md) lists every request entry point the shipped code serves.

A static source scan finds every top-level page, every `op` and `subop` a page compares, chooses or links to, and every
`op` and `subop` of each bundled module. The inventory must list exactly those, no more and no fewer, so a new page,
op or module cannot appear without an inventory entry, and a removed one cannot stay listed.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
INVENTORY = ROOT / 'docs' / 'ROUTE-INVENTORY-20261022.md'
STATUSES = ('CLOSED', 'DISABLED', 'READ-ONLY', 'OPEN')
LITERAL = r"""['"]([A-Za-z0-9_\-]*)['"]"""
# A request key other than op that selects a separate entry point while op is empty; each of its values needs a row.
ENTRY_KEYS = {'pvp.php': 'act'}


def _block(text, start):
    """The braced block that follows position start."""
    opening = text.index('{', start)
    depth = 0
    for index in range(opening, len(text)):
        if text[index] == '{':
            depth += 1
        elif text[index] == '}':
            depth -= 1
            if depth == 0:
                return text[opening:index]
    return text[opening:]


def compared(text, name):
    """Literal values a request parameter is compared with, chosen from, or switched on."""
    var = re.escape(name)
    read = r"""(?:\$""" + var + r"""\b|httpget\(\s*['"]""" + var + r"""['"]\s*\))"""
    found = set()
    for pattern in [read + r'\s*[!=]==?\s*' + LITERAL, LITERAL + r'\s*[!=]==?\s*' + read]:
        found |= set(re.findall(pattern, text))
    for match in re.finditer(r"""Input::choice\(\s*\$_GET\s*,\s*['"]""" + var + r"""['"]\s*,\s*\[([^\]]*)\]""", text):
        found |= set(re.findall(LITERAL, match.group(1)))
    for match in re.finditer(r'in_array\(\s*' + read + r'\s*,\s*(?:array\(|\[)([^\])]*)[\])]', text):
        found |= set(re.findall(LITERAL, match.group(1)))
    for match in re.finditer(r'switch\s*\(\s*' + read + r'\s*\)', text):
        found |= set(re.findall(r'case\s+' + LITERAL + r'\s*:', _block(text, match.end())))
    return found


def shipped():
    return sorted(ROOT.glob('*.php')) + sorted((ROOT / 'lib').rglob('*.php')) + sorted((ROOT / 'modules').rglob('*.php'))


def scan():
    """{page: {'op': set, 'subop': set}} and {module: {'op': set, 'subop': set}} from the shipped source."""
    pages = {path.name: {'op': set(), 'subop': set()} for path in ROOT.glob('*.php')}
    for name, found in pages.items():
        text = (ROOT / name).read_text(errors='replace')
        found['op'] |= compared(text, 'op')
        found['subop'] |= compared(text, 'subop')
    # Links any shipped file emits to a top-level page.
    for path in shipped():
        for page, query in re.findall(r"""\b([a-z_]+\.php)\?([^'"\s<>]*)""", path.read_text(errors='replace')):
            if page in pages and page != 'runmodule.php':
                for key, value in re.findall(r'(?:^|&(?:amp;)?)(op|subop)=([A-Za-z0-9_]*)', query):
                    pages[page][key].add(value)
    # Pages that include lib/<area>/<prefix>$op.php serve one op per file.
    for page, area, prefix in [('about.php', 'about', 'about_'), ('petition.php', 'petition', 'petition_')]:
        for path in (ROOT / 'lib' / area).glob(prefix + '*.php'):
            op = path.stem[len(prefix):]
            if op != 'default':
                pages[page]['op'].add(op)
    modules = {}
    for path in sorted((ROOT / 'modules').glob('*.php')):
        folder = ROOT / 'modules' / path.stem
        text = '\n'.join(p.read_text(errors='replace') for p in [path, *(sorted(folder.rglob('*.php')) if folder.is_dir() else [])])
        modules[path.stem] = {'op': compared(text, 'op'), 'subop': compared(text, 'subop')}
    # runmodule.php links any shipped file emits to a module's op.
    for path in shipped():
        for query in re.findall(r"""\brunmodule\.php\?([^'"\s<>]*)""", path.read_text(errors='replace')):
            values = dict(re.findall(r'(?:^|&(?:amp;)?)(module|op|subop)=([A-Za-z0-9_]*)', query))
            if values.get('module') in modules:
                for key in ['op', 'subop']:
                    if key in values:
                        modules[values['module']][key].add(values[key])
    for page, key in ENTRY_KEYS.items():
        pages[page][key] = compared((ROOT / page).read_text(errors='replace'), key) - {''}
    for found in [*pages.values(), *modules.values()]:
        found['op'].add('')
        found['subop'].discard('')
    del pages['runmodule.php']['op'], pages['runmodule.php']['subop']
    return pages, modules


ENTRY = re.compile(r'^(?P<page>[a-z_]+\.php)(?:\?(?P<query>.*))?$')
_QUOTED = r"""(?:"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')"""
# A link the game sends a browser to: a nav entry, an href or form action, a redirect or a Location header.
LINK = re.compile(r"""(?:\baddnav\(\s*(?:""" + _QUOTED + r"""|[^,()'"]+)\s*,\s*|\bhref\s*=\s*|\baction\s*=\s*|"""
                  r"""\bredirect\(\s*|\bLocation:\s*)\\?["']?(?P<page>[a-z_]+\.php)(?:\?(?P<query>[^'"\\\s<>]*))?""")
# 1.1.2 links to optional pages it never shipped, listed for Jim (UNNECESSARY-FILES-20261024.md, round 2), not removed:
# the Loot Editor and the Pavilion navs appear only if their file exists, and retitle.php is a hidden nav (no link).
UNSHIPPED = {('superuser.php', 'looteditor.php'), ('village.php', 'pavilion.php'), ('village.php', 'retitle.php')}
DEAD_STATUS = re.compile(r'^(?:403|404|410)\b|\bgets? (?:403|404|410)\b|\bSame (?:403|404|410)\b')


def links(text):
    """(page, op, module) for every link to a top-level page in the text. A value built at run time reads as empty."""
    found = []
    for match in LINK.finditer(text):
        query = dict(re.findall(r'(?:^|&(?:amp;)?)(op|module)=([A-Za-z0-9_]*)', match.group('query') or ''))
        found.append((match.group('page'), query.get('op', ''), query.get('module')))
    return found


def dead_links():
    """Links in shipped files to a page that does not exist (404) or to an entry the inventory lists as DISABLED."""
    pages = {path.name for path in ROOT.glob('*.php')}
    disabled, enabled = {}, {}
    for cells in inventory():
        match = ENTRY.match(cells[0].strip('`'))
        query = dict(part.split('=', 1) for part in (match.group('query') or '').split('&') if part)
        if 'module' in query:
            continue
        ops = set(query.get('op', '').split('|'))
        (disabled if cells[3] == 'DISABLED' else enabled).setdefault(match.group('page'), set()).update(ops)
    dead = []
    for path in [*shipped(), *sorted((ROOT / 'templates').glob('*.htm'))]:
        for page, op, module in links(path.read_text(errors='replace')):
            where = str(path.relative_to(ROOT))
            if page not in pages and (where, page) not in UNSHIPPED:
                dead.append((where, page, 'no such page (404)'))
            elif page in disabled and (page not in enabled or op in disabled[page]):
                dead.append((where, page + ('?op=' + op if op else ''), 'disabled (403, 404 or 410)'))
            elif page == 'runmodule.php' and module and not (ROOT / 'modules' / (module + '.php')).is_file():
                dead.append((where, page + '?module=' + module, 'no such module (404)'))
    return dead


def inventory():
    """Rows of the inventory tables: (entry, who, changes, status, evidence)."""
    rows = []
    for line in INVENTORY.read_text().splitlines():
        if line.startswith('| `'):
            # Cells split on unescaped pipes; an entry lists several ops as op=a\|b.
            cells = [cell.strip().replace('\\|', '|') for cell in re.split(r'(?<!\\)\|', line.strip()[1:-1])]
            rows.append(cells)
    return rows


def listed(rows):
    pages, modules = {}, {}
    for cells in rows:
        match = ENTRY.match(cells[0].strip('`'))
        if not match:
            raise AssertionError('Unreadable inventory entry: ' + cells[0])
        query = dict(part.split('=', 1) for part in (match.group('query') or '').split('&') if part)
        entry_key = ENTRY_KEYS.get(match.group('page'))
        if set(query) - {'op', 'subop', 'module', entry_key}:
            raise AssertionError('Unexpected inventory parameter: ' + cells[0])
        # A module's default view is implied by its routes; a page's needs its own row.
        target = modules.setdefault(query['module'], {'op': {''}, 'subop': set()}) if 'module' in query else \
            pages.setdefault(match.group('page'), {'op': set(), 'subop': set()})
        target['op'] |= set(query.get('op', '').split('|'))
        if 'subop' in query:
            target['subop'] |= set(query['subop'].split('|'))
        if entry_key in query:
            target.setdefault(entry_key, set()).update(query[entry_key].split('|'))
        if 'module' not in query:
            target.setdefault('pages', set()).add(match.group('page'))
    return pages, modules


class RouteInventoryTests(unittest.TestCase):
    def test_every_page_op_and_module_route_is_inventoried(self):
        rows = inventory()
        self.assertGreater(len(rows), 100)
        found_pages, found_modules = scan()
        pages, modules = listed(rows)
        self.assertEqual(sorted(found_pages), sorted(pages), 'top-level pages')
        self.assertEqual(sorted(found_modules), sorted(modules), 'bundled modules')
        for page, found in sorted(found_pages.items()):
            with self.subTest(page=page):
                if page == 'runmodule.php':
                    continue
                self.assertEqual(sorted(found['op']), sorted(pages[page]['op']), page + ' ops')
                self.assertEqual(sorted(found['subop']), sorted(pages[page]['subop']), page + ' subops')
                if page in ENTRY_KEYS:
                    key = ENTRY_KEYS[page]
                    self.assertEqual(sorted(found[key]), sorted(pages[page].get(key, set())), page + ' ' + key)
        for module, found in sorted(found_modules.items()):
            with self.subTest(module=module):
                self.assertEqual(sorted(found['op']), sorted(modules[module]['op']), module + ' ops')
                self.assertEqual(sorted(found['subop']), sorted(modules[module]['subop']), module + ' subops')

    def test_rows_have_a_status_and_the_counts_add_up(self):
        rows = inventory()
        for cells in rows:
            with self.subTest(entry=cells[0]):
                self.assertEqual(5, len(cells), cells)
                self.assertIn(cells[3], STATUSES)
                self.assertTrue(cells[4], 'every entry cites its evidence or says what is missing')
        text = INVENTORY.read_text()
        for status in STATUSES:
            match = re.search(r'^\| ' + re.escape(status) + r' \| (\d+) \|$', text, re.M)
            self.assertIsNotNone(match, status + ' count missing')
            self.assertEqual(sum(cells[3] == status for cells in rows), int(match.group(1)), status)
        total = re.search(r'^\| Total \| (\d+) \|$', text, re.M)
        self.assertIsNotNone(total)
        self.assertEqual(len(rows), int(total.group(1)))

    def test_rescan_task2_entries_have_their_evidence(self):
        # The admin tools and the bio return link closed by final rescan task 2 cite its evidence document and an HTTP
        # test. A route that changes state must be CLOSED; one that only reads (or, like the keepalive and the referer
        # expiry, writes server values only) READ-ONLY.
        rows = {cells[0].strip('`'): cells for cells in inventory()}
        expected = {
            'badword.php?op=add|remove|addgood|removegood': 'CLOSED', 'referers.php?op=rebuild': 'CLOSED',
            'superuser.php?op=newsdelete': 'CLOSED', 'translatortool.php?op=save': 'CLOSED', 'untranslated.php?op=list|step2': 'CLOSED',
            'bio.php': 'READ-ONLY', 'gamelog.php': 'READ-ONLY', 'referers.php': 'READ-ONLY', 'superuser.php?op=keepalive': 'READ-ONLY',
            'translatortool.php': 'READ-ONLY', 'translatortool.php?op=list': 'READ-ONLY', 'untranslated.php': 'READ-ONLY',
        }
        for entry, status in expected.items():
            with self.subTest(entry=entry):
                cells = rows[entry]
                self.assertEqual(status, cells[3])
                self.assertIn('`RESCAN-TASK2-20261022.md`', cells[4])
                self.assertRegex(cells[4], r'`test_[a-z0-9_]+_http`')
                self.assertTrue((ROOT / 'docs' / 'RESCAN-TASK2-20261022.md').is_file())
        tests = (ROOT / 'tests' / 'tooling' / 'test_web_application.py').read_text()
        for name in sorted({name for cells in rows.values() if 'RESCAN-TASK2' in cells[4] for name in re.findall(r'`(test_[a-z0-9_]+_http)`', cells[4])}):
            self.assertIn('def ' + name + '(self', tests, name)

    def test_no_page_links_a_missing_or_disabled_page(self):
        # A nav entry, link, form or redirect must not send a player or an admin to a page that answers 404, 403 or
        # 410. Missing pages are 404; the inventory's DISABLED entries are the ones that answer 403, 404 or 410.
        for cells in inventory():
            if cells[3] == 'DISABLED':
                with self.subTest(entry=cells[0]):
                    self.assertRegex(cells[4], DEAD_STATUS)
        for where, page in sorted(UNSHIPPED):
            with self.subTest(unshipped=page):
                self.assertFalse((ROOT / page).exists(), page + ' now ships: drop it from UNSHIPPED')
                self.assertIn(page, [link[0] for link in links((ROOT / where).read_text())], where)
        self.assertEqual([], dead_links())

    def test_link_scan_finds_known_links(self):
        # The scan itself must keep working: home.php's own navs and every link form it reads.
        home = links((ROOT / 'home.php').read_text())
        self.assertTrue({('create.php', '', None), ('list.php', '', None), ('about.php', 'setup', None)} <= set(home))
        sample = '''addnav("Back","village.php"); addnav($label, 'forest.php?op=search'); addnav("Yes, really","inn.php?op=bartender");
            output("<a href='bio.php?char=1'>"); rawoutput("<form action=\\"login.php?op=logout\\" method='POST'>");
            redirect("news.php"); header('Location: index.php?op=timeout', true, 303); require_once("lib/nav.php");'''
        self.assertEqual([('village.php', '', None), ('forest.php', 'search', None), ('inn.php', 'bartender', None),
                          ('bio.php', '', None), ('login.php', 'logout', None), ('news.php', '', None),
                          ('index.php', 'timeout', None)], links(sample))
        self.assertEqual([('runmodule.php', 'enter', 'lovers')], links('addnav("L","runmodule.php?module=lovers&op=enter");'))
        self.assertRegex('403: installation is a CLI action', DEAD_STATUS)
        self.assertRegex('A web request gets 404 (`X.md`)', DEAD_STATUS)
        self.assertNotRegex('Reads only; 404 is not involved here', DEAD_STATUS)

    def test_scan_finds_known_ops(self):
        # The scan itself must keep working: these are dispatched in four different ways.
        pages, modules = scan()
        self.assertTrue({'', 'buy', 'heal'} <= pages['mercenarycamp.php']['op'])
        self.assertTrue({'fight', 'run', 'newtarget', 'search', 'dragon', 'specialty'} <= pages['forest.php']['op'])
        self.assertTrue({'faq', 'faq1', 'primer'} <= pages['petition.php']['op'])
        self.assertTrue({'least', 'most'} <= pages['hof.php']['subop'])
        self.assertTrue({'give', 'dont'} <= modules['foilwench']['op'])
        self.assertEqual({'attack'}, pages['pvp.php']['act'])


if __name__ == '__main__':
    unittest.main()
