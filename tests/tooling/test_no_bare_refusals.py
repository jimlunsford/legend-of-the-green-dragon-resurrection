"""No bare refusal on the game's own player pages (Phase 6 task 27, no dead ends, part 1).

Jim's rc.2 to rc.4 server tests found three dead ends in a row (R10, R12, R13), each a player path that ended on a bare
status and text: an http_response_code() with a 4xx or 5xx status, then an exit or die with text, and no frame or link.
Task 27 framed every such refusal on the game's own player pages through resurrection_refuse_page() and
resurrection_refuse_action() (lib/web_security.php), each with one way out. This test keeps it so.

It scans every PHP file in the tree (vendor/ and .git/ aside) and finds each bare refusal: an http_response_code() whose
status is a 4xx or 5xx literal or any expression (a helper's $status), followed, in the same block and before a framed
helper, page_footer() or a return, by an exit or die with text (an argument that is not an integer) or by output (echo,
print, inline HTML) and then exit. The same holds for header('HTTP/1.x 4xx'). Every file with one must be listed in
EXCEPTIONS, by name, with the exact count and the reason; a file not listed must have none. So a refusal moved into a
new file, or added to a listed one, fails here until it is framed or listed by name with a reason.

The exceptions are:
- group two: pages outside forced navigation, or before a game page is drawn, plain by design;
- group three: the superuser editors and tools;
- the plain refusals task 27 kept on player pages: the include-only 404s ("Not an application endpoint"), the refusals
  that run before common.php and forced navigation load, and the framed helpers' own plain fallbacks;
- modules/: task 28's, listed with their counts, to be emptied there.
"""
from pathlib import Path
import os
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SKIP_DIRS = {'.git', 'vendor', 'node_modules'}

GROUP_TWO = 'group two: outside forced navigation or before a game page is drawn; plain by design (task 22, task 24 section 6)'
GROUP_THREE = 'group three: a superuser editor or tool (staff, not a player page)'
TASK_28 = 'task 28: a bundled module (modules/), framed in no dead ends, part 2'

# file: (bare refusals, reason). Every count is exact: a new refusal in a listed file fails as a new file does.
EXCEPTIONS = {
    # Group two.
    'common.php': (3, GROUP_TWO + ': stored-state checks while common.php loads, before any game page'),
    'cron.php': (1, GROUP_TWO + ': the command line, not a web page'),
    'installer.php': (1, GROUP_TWO + ': the removed web installer, 403 for every request'),
    'lib/forcednavigation.php': (6, GROUP_TWO + ': stored-state checks inside forced navigation itself'),
    'lib/motd.php': (7, GROUP_TWO + ': the MoTD popup (OVERRIDE_FORCED_NAV)'),
    'lib/petition/petition_default.php': (2, GROUP_TWO + ': the petition popup (OVERRIDE_FORCED_NAV)'),
    'login.php': (5, GROUP_TWO + ': the sign-in, before a game page is drawn'),
    'mail.php': (6, GROUP_TWO + ': player mail, a popup (OVERRIDE_FORCED_NAV)'),
    'motd.php': (1, GROUP_TWO + ': the MoTD popup (OVERRIDE_FORCED_NAV)'),
    'runmodule.php': (1, GROUP_TWO + ': an unknown module, refused before the module page loads'),
    'scripts/install.php': (1, GROUP_TWO + ': a command, 404 to the web'),
    'scripts/upgrade.php': (1, GROUP_TWO + ': a command, 404 to the web'),
    # Group three.
    'badword.php': (1, GROUP_THREE),
    'companions.php': (2, GROUP_THREE),
    'configuration.php': (2, GROUP_THREE),
    'creatures.php': (2, GROUP_THREE),
    'donators.php': (3, GROUP_THREE),
    'gamelog.php': (1, GROUP_THREE),
    'lib/admin_authority.php': (5, GROUP_THREE),
    'lib/admin_tools.php': (5, GROUP_THREE),
    'lib/content_editor.php': (5, GROUP_THREE),
    'lib/module_manager.php': (3, GROUP_THREE),
    'lib/stored_state_repair.php': (1, GROUP_THREE + ': the user editor stored-state repair'),
    'lib/su_access.php': (2, GROUP_THREE + ': check_su_access(), the staff gate'),
    'lib/user/user_debuglog.php': (1, GROUP_THREE),
    'lib/user/user_delban.php': (1, GROUP_THREE),
    'lib/user/user_edit.php': (1, GROUP_THREE),
    'lib/user/user_removeban.php': (1, GROUP_THREE),
    'moderate.php': (3, GROUP_THREE),
    'modules.php': (2, GROUP_THREE),
    'mounts.php': (2, GROUP_THREE),
    'referers.php': (1, GROUP_THREE),
    'superuser.php': (2, GROUP_THREE),
    'translatortool.php': (2, GROUP_THREE),
    'untranslated.php': (2, GROUP_THREE),
    'user.php': (4, GROUP_THREE),
    'viewpetition.php': (7, GROUP_THREE),
    # The plain refusals kept on player pages (task 27, DO 2).
    'battle.php': (1, 'include-only 404 ("Not an application endpoint"): never a page a player is sent to'),
    'lib/training_outcomes.php': (1, 'include-only 404 ("Not an application endpoint"): never a page a player is sent to'),
    'graveyard.php': (1, 'before common.php: a legacy specialty parameter, refused before there is a session, frame or player'),
    'train.php': (2, 'before common.php: unknown parameters (no session, frame or player yet); and the load-time '
                     'fallback, plain only when the page never reached forced navigation (else framed by training)'),
    'pvp.php': (1, "the load-time fallback: plain only when the page never reached forced navigation (else framed by PvP's way out)"),
    'lib/web_security.php': (2, "the framed helpers' own plain fallback, for a request outside a game page or a frame that fails"),
}
# Task 28's: the bundled modules, framed in no dead ends, part 2.
MODULES = {
    'modules/cedrikspotions.php': 5,
    'modules/crazyaudrey.php': 2,
    'modules/dag/misc_functions.php': 6,
    'modules/dag/run.php': 2,
    'modules/darkhorse.php': 10,
    'modules/drinks/misc_functions.php': 6,
    'modules/drinks/run.php': 3,
    'modules/game_dice.php': 3,
    'modules/game_fivesix.php': 3,
    'modules/game_stones.php': 6,
    'modules/lovers.php': 3,
    'modules/outhouse.php': 3,
    'modules/sethsong.php': 3,
}
EXCEPTIONS.update({name: (count, TASK_28) for name, count in MODULES.items()})

STATUS_CALL = re.compile(r'\\?\bhttp_response_code\s*\(')
HEADER_STATUS = re.compile(r"""\\?\bheader\s*\(\s*['"]\s*HTTP/[\d.]+\s+([45]\d\d)""", re.I)
# Calls that frame the answer, or end the page in the frame, so that what follows is not a bare refusal.
FRAMED = re.compile(r'\\?\b(?:resurrection_refuse_page|resurrection_refuse_action|resurrection_require_post|page_footer|popup_footer|'
                    r'resurrection_\w+_refuse)\s*\(')
OUTPUT = '\x01'  # marks output in the stripped code: echo, print, inline HTML


def strip_php(text):
    """(code, full): the source with comments and inline HTML blanked and newlines kept; in code, string contents are
    blanked too (so a call named inside a string is not a call), and output is marked with OUTPUT."""
    code, full = [], []
    i, n, in_php = 0, len(text), False

    def put(ch, keep_code=True, keep_full=True):
        if ch == '\n':
            code.append('\n'); full.append('\n')
        else:
            code.append(ch if keep_code else ' '); full.append(ch if keep_full else ' ')

    def html(segment):
        marked = False
        for ch in segment:
            if not marked and not ch.isspace():
                code.append(OUTPUT); full.append(' '); marked = True
            else:
                put(ch if ch == '\n' else ' ', False, False)

    while i < n:
        if not in_php:
            starts = [x for x in (text.find('<?php', i), text.find('<?=', i)) if x != -1]
            if not starts:
                html(text[i:]); break
            start = min(starts)
            html(text[i:start])
            tag = '<?php' if text.startswith('<?php', start) else '<?='
            for _ in tag: put(' ')
            if tag == '<?=':
                code[-1] = OUTPUT
            i, in_php = start + len(tag), True
            continue
        c = text[i]
        if text.startswith('?>', i):
            put(' '); put(' '); i += 2; in_php = False
            continue
        if text.startswith('//', i) or (c == '#' and not text.startswith('#[', i)):
            while i < n and text[i] != '\n' and not text.startswith('?>', i):
                put(' ', False, False); i += 1
            continue
        if text.startswith('/*', i):
            end = text.find('*/', i + 2)
            end = n if end == -1 else end + 2
            for ch in text[i:end]: put(ch if ch == '\n' else ' ', False, False)
            i = end
            continue
        if c in ('"', "'"):
            put(c); j = i + 1
            while j < n and text[j] != c:
                if text[j] == '\\':
                    put(text[j], False); j += 1
                if j < n: put(text[j], False)
                j += 1
            if j < n: put(c)
            i = j + 1
            continue
        if text.startswith('<<<', i):
            m = re.match(r"<<<[ \t]*(['\"]?)([A-Za-z_]\w*)\1\r?\n", text[i:])
            if m:
                for ch in m.group(0): put(ch)
                j = i + len(m.group(0))
                end = re.compile(r'(?m)^[ \t]*' + re.escape(m.group(2)) + r'\b').search(text, j)
                stop = end.start() if end else n
                for ch in text[j:stop]: put(ch, False)
                i = stop
                continue
        put(c); i += 1
    return ''.join(code), ''.join(full)


def _parens(code, start):
    """The text inside the parentheses that open just before start, and the index after the closing one."""
    depth, i = 1, start
    while i < len(code) and depth:
        depth += {'(': 1, ')': -1}.get(code[i], 0)
        i += 1
    return code[start:i - 1], i


def _bare_after(code, start):
    """Whether the refusal whose status call ends at start is bare: an exit or die with text, or output then exit,
    before the block ends, a return, or a framed call."""
    depth, i, output = 0, start, False
    while i < len(code):
        c = code[i]
        if c == '{':
            depth += 1
        elif c == '}':
            depth -= 1
            if depth < 0: return False
        elif c == OUTPUT:
            output = True
        elif c.isalpha() or c == '_' or c == '\\':
            m = re.match(r'\\?[A-Za-z_]\w*', code[i:])
            word = m.group(0).lstrip('\\')
            if FRAMED.match(code, i):
                return False
            if word == 'return' and depth <= 0:
                return False
            if word in ('echo', 'print', 'printf', 'print_r', 'var_dump', 'readfile', 'fpassthru'):
                output = True
            if word in ('exit', 'die'):
                rest = code[i + len(m.group(0)):]
                arg = re.match(r'\s*\(', rest)
                if not arg:
                    return output
                inner, _ = _parens(rest, arg.end())
                inner = inner.strip()
                return output or (inner != '' and not re.fullmatch(r'-?\d+', inner))
            i += len(m.group(0))
            continue
        i += 1
    return False


def refusals(text):
    """[(line, status)] of the bare refusals in one PHP file's source."""
    code, full = strip_php(text)
    found = []
    for m in STATUS_CALL.finditer(code):
        arg, end = _parens(code, m.end())
        status = arg.strip().lstrip('\\')
        literal = re.fullmatch(r'\d+', status)
        if literal and not re.fullmatch(r'[45]\d\d', status):
            continue
        if _bare_after(code, end):
            found.append((code.count('\n', 0, m.start()) + 1, status))
    for m in HEADER_STATUS.finditer(full):
        if code[m.start()] in ' \n':
            continue  # inside a comment or a string
        close = code.find(')', m.start())
        if _bare_after(code, close + 1):
            found.append((code.count('\n', 0, m.start()) + 1, m.group(1)))
    return sorted(found)


_TREE = {}


_SCANNED = {}


def _refusals_cached(text):
    if text not in _SCANNED:
        _SCANNED[text] = refusals(text)
    return _SCANNED[text]


def tree_files(root=ROOT):
    """{relative path: source} of every PHP file in the tree (a fresh copy each call; the tree is read once)."""
    if root not in _TREE:
        _TREE[root] = _read_tree(root)
    return dict(_TREE[root])


def _read_tree(root):
    files = {}
    for folder, dirs, names in os.walk(root):
        dirs[:] = sorted(d for d in dirs if d not in SKIP_DIRS)
        for name in names:
            if name.endswith('.php'):
                path = Path(folder) / name
                files[path.relative_to(root).as_posix()] = path.read_text(encoding='utf-8', errors='replace')
    return files


def problems(files):
    """Every way the files differ from EXCEPTIONS: a file with bare refusals that is not listed, a listed count that
    differs, and a listed file that no longer has any (or is gone)."""
    found = {name: _refusals_cached(text) for name, text in files.items()}
    out = []
    for name, rows in sorted(found.items()):
        if not rows:
            continue
        where = ', '.join(f'{line} ({status})' for line, status in rows)
        if name not in EXCEPTIONS:
            out.append(f'{name}: bare refusal at line {where}; frame it (resurrection_refuse_page(), '
                       'resurrection_refuse_action()) or list the file in EXCEPTIONS with a reason')
        elif EXCEPTIONS[name][0] != len(rows):
            out.append(f'{name}: {len(rows)} bare refusals ({where}), EXCEPTIONS lists {EXCEPTIONS[name][0]}')
    for name, (count, _) in sorted(EXCEPTIONS.items()):
        if not found.get(name):
            out.append(f'{name}: listed in EXCEPTIONS with {count}, but has no bare refusal (remove it)')
    return out


class NoBareRefusalTests(unittest.TestCase):
    def test_no_bare_refusal_outside_the_listed_exceptions(self):
        self.assertEqual([], problems(tree_files()))

    def test_every_exception_has_a_reason_and_modules_are_task_28s(self):
        for name, (count, reason) in EXCEPTIONS.items():
            self.assertGreater(count, 0, name)
            self.assertGreater(len(reason), 20, name)
            self.assertEqual(name.startswith('modules/'), reason == TASK_28, name)

    def test_a_bare_refusal_planted_in_a_game_page_fails(self):
        files = tree_files()
        files['bank.php'] = files['bank.php'].replace("page_header(\"Ye Olde Bank\");",
            "page_header(\"Ye Olde Bank\");\nif (httpget('op') === 'planted') { http_response_code(409); exit('Planted refusal.'); }", 1)
        self.assertIn('Planted refusal', files['bank.php'])
        found = problems(files)
        self.assertEqual(1, len(found), found)
        self.assertTrue(found[0].startswith('bank.php: bare refusal at line'), found)

    def test_a_bare_refusal_moved_into_a_new_file_fails(self):
        files = tree_files()
        files['lib/planted_refusal.php'] = ("<?php\nfunction resurrection_planted(int $status, string $plain): never {\n"
                                            "    http_response_code($status);\n    exit($plain);\n}\n")
        found = problems(files)
        self.assertEqual(['lib/planted_refusal.php: bare refusal at line 3 ($status); frame it (resurrection_refuse_page(), '
                          'resurrection_refuse_action()) or list the file in EXCEPTIONS with a reason'], found)
        # A listed file with one more is caught the same way.
        files = tree_files()
        files['mail.php'] += "\n<?php http_response_code(400); exit('Another.');\n"
        self.assertEqual(1, len(problems(files)), problems(files))

    def test_the_scanner_reads_the_shapes_it_must(self):
        bare = [
            "<?php http_response_code(403); exit('No.');",
            "<?php if ($x) { http_response_code(400);\n exit(\"No.\"); }",
            "<?php function f($s,$m) { http_response_code($s); header('Cache-Control: no-store'); exit($m); }",
            "<?php http_response_code(500); echo 'No.'; exit;",
            "<?php http_response_code(404); ?>Not found<?php exit;",
            "<?php header('HTTP/1.1 403 Forbidden'); die('No.');",
            "<?php try { x(); } catch (Throwable $e) { http_response_code(500); exit('Failed.'); }",
        ]
        plain = [
            "<?php if (!defined('RESURRECTION_ENTRY')) { http_response_code(404); exit; }",
            "<?php http_response_code(200); exit('Fine.');",
            "<?php http_response_code(302); exit('Moved.');",
            "<?php http_response_code(409); resurrection_refuse_page(409, 'No.', 'T', 'No.');",
            "<?php if ($post) http_response_code(403); output('No.'); } addnav('Back', 'clan.php');",
            "<?php http_response_code(403); output('No.'); page_footer(); exit('unreachable');",
            "<?php // http_response_code(400); exit('a comment');",
            "<?php $s = \"http_response_code(400); exit('a string');\";",
            "<?php http_response_code(500); exit(1);",
            "<?php http_response_code(400); resurrection_bank_refuse(400, 'Invalid.');",
        ]
        for source in bare:
            self.assertEqual(1, len(refusals(source)), source)
        for source in plain:
            self.assertEqual([], refusals(source), source)


if __name__ == '__main__':
    unittest.main()
