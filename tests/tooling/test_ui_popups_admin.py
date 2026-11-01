"""Popups, admin and superuser screens in the Resurrection UI (Phase 5 task 11,
docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md).

Static: runs wherever the suite runs (Baseline integrity too). It checks that

- every input on the admin and editor pages has a label: the 105 controls task 9 left unlabelled
  (docs/PHASE5-TASK9-FORMS-20261031.md) are now 0;
- the form script ticks the inbox's checkboxes (Check All) and draws the settings editors' tabs without an extra field;
- the stylesheet draws the mail tabs and inbox, the New badge, the MoTD items and poll, the staff's petition badges and
  the tables' scroll boxes as docs/UI-DESIGN-SPEC.md says;
- the spec and the changes ledger describe them.

The rendered pages (the mail popup with no italics, petition, MoTD, the translator tool, the Grotto, the settings tabs,
the creature editor's table, the petition badges' counts, and every page in a 1.1.2 skin byte for byte) are checked in
tests/unit/PopupAdminRenderTest.php.
"""
from pathlib import Path
import re
import unittest

from test_ui_forms import controls

ROOT = Path(__file__).resolve().parents[2]
CSS = ROOT / 'assets' / 'ui' / 'resurrection.css'
SCRIPT = ROOT / 'assets' / 'ui' / 'showform.js'
SPEC = ROOT / 'docs' / 'UI-DESIGN-SPEC.md'
CHANGES = ROOT / 'docs' / 'CHANGES-FROM-1.1.2.md'

# The admin and editor pages whose 105 controls task 9 listed as unlabelled.
ADMIN = ['badword.php', 'companions.php', 'creatures.php', 'donators.php', 'lib/clan/detail.php', 'lib/commentary.php',
         'lib/motd.php', 'lib/typed_editor.php', 'lib/user/user_setupban.php', 'masters.php', 'moderate.php', 'modules.php',
         'mounts.php', 'taunt.php', 'translatortool.php', 'untranslated.php', 'user.php']
# The id a control is given and the label that names it, written out or through the task 11 helpers (lib/output.php).
CONTROL_ID = re.compile(r'''resurrection_control_id\(('[^']*'|"[^"]*")''')
LITERAL_ID = re.compile(r'''\bid=["']([A-Za-z0-9_$\-{}]+)["']''')


def unlabelled(text):
    """The controls in a PHP file's strings with no label: no <label for> naming their id, through a helper or written
    out, and no <label> still open where they are printed."""
    missing = []
    for match in re.finditer(r'<(input|select|textarea)\b([^>]*)>', text):
        tag = match.group(0)
        if tag not in controls(tag):
            continue
        helper = CONTROL_ID.search(tag)
        literal = LITERAL_ID.search(tag)
        if helper:
            names = [helper.group(1)]
        elif literal:
            names = [repr(literal.group(1)), '"' + literal.group(1) + '"']
            if re.search(r'''for=(\\?["'])''' + re.escape(literal.group(1)) + r'''\1''', text):
                continue
        else:
            names = []
        if any(f'resurrection_label_open({name})' in text or f'resurrection_label_for({name},' in text for name in names):
            continue
        # Inside a label opened before it and not yet closed: "<label>Option <input ...", or a row's label around its box.
        before = text[:match.start()]
        opened = before.rfind('<label')
        if opened >= 0 and '</label>' not in before[opened:]:
            continue
        missing.append(tag)
    return missing


class AdminLabelTests(unittest.TestCase):
    def test_every_admin_and_editor_input_has_a_label(self):
        found = {rel: unlabelled((ROOT / rel).read_text()) for rel in ADMIN}
        self.assertEqual({rel: [] for rel in ADMIN}, found)
        self.assertEqual(0, sum(map(len, found.values())))

    def test_the_scan_finds_an_unlabelled_control(self):
        self.assertEqual(["<input name='word'>"], unlabelled("output('Word:');\nrawoutput(\"<input name='word'>\");"))
        self.assertEqual([], unlabelled("resurrection_label_open('w');\noutput('Word:');\nresurrection_label_close();\n"
                                        "rawoutput(\"<input name='word'\".resurrection_control_id('w').\">\");"))
        self.assertEqual(["<input name='word'\".resurrection_control_id('w').\">"],
                         unlabelled("rawoutput(\"<input name='word'\".resurrection_control_id('w').\">\");"))
        self.assertEqual([], unlabelled("rawoutput('<p><label>Word ');\nrawoutput('<input name=\"word\">');"))
        self.assertEqual(["<input name=\"word\">"], unlabelled("rawoutput('<label>Word</label>');\nrawoutput('<input name=\"word\">');"))

    def test_the_label_helpers_print_nothing_in_an_old_skin(self):
        output = (ROOT / 'lib' / 'output.php').read_text()
        for name in ('resurrection_control_id', 'resurrection_label_open', 'resurrection_label_close', 'resurrection_label_for'):
            start = output.index('function ' + name + '(')
            body = output[start:output.index('\n}\n', start)]
            self.assertIn('resurrection_ui()', body, name)
        self.assertIn('''if (!resurrection_ui()) return $legacy === "" ? "" : " id='$legacy'";''', output)


class ScriptTests(unittest.TestCase):
    def test_check_all_and_tabs_without_an_index_field(self):
        script = SCRIPT.read_text()
        for needle in ("querySelectorAll('[data-rz-checkall]')", "button.form || document", "box.checked = true;",
                       "list.hasAttribute('data-rz-tabs-noindex')", "index.name = 'showFormTabIndex';"):
            self.assertIn(needle, script, needle)
        self.assertIsNone(re.search(r'innerHTML|eval\(|document\.write', script))
        inbox = (ROOT / 'lib' / 'mail_view.php').read_text()
        self.assertIn("data-rz-checkall='msg[]'", inbox)
        self.assertNotIn('onClick', inbox)
        editor = (ROOT / 'lib' / 'typed_editor.php').read_text()
        self.assertIn('data-rz-tabs-noindex', editor)


class PopupAdminStyleTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.css = CSS.read_text()

    def rule(self, selector):
        found = re.search(r'(?m)^\s*' + re.escape(selector) + r'\s*\{([^}]*)\}', self.css)
        self.assertIsNotNone(found, selector)
        return found.group(1)

    def test_mail(self):
        self.assertIn('flex-wrap: wrap;', self.rule('.rz-mailtabs ul'))
        new = self.rule('.rz-badge-new')
        for value in ('background: transparent;', 'border: 1px solid var(--accent);', 'color: var(--accent);'):
            self.assertIn(value, new)
        self.assertIn('color: var(--muted);', self.rule('.rz-mail-meta'))
        self.assertIn('list-style: none;', self.rule('.rz-inbox'))
        self.assertIn('min-height: var(--touch-target);', self.rule('.rz-mail-pick'))
        self.assertIn('color: var(--muted);', self.rule('.rz-mail-note, .rz-mail-empty'))
        self.assertIn('position: absolute;', self.rule('.rz-sr'))

    def test_motd(self):
        self.assertIn('font-family: var(--font-display);', self.rule('.rz-content .rz-motd-title'))
        self.assertIn('font-family: var(--font-story);', self.rule('.rz-motd-body'))
        self.assertIn('color: var(--muted);', self.rule('.rz-motd-meta'))
        self.assertIn('background: var(--accent);', self.rule('.rz-poll-bar::-webkit-progress-value'))
        self.assertIn('min-height: var(--touch-target);', self.rule('.rz-poll-option > label'))

    def test_petition_badges(self):
        outlined = self.rule('.rz-petition-badge')
        self.assertIn('background: transparent;', outlined)
        self.assertIn('border: 1px solid var(--input-border);', outlined)
        unread = self.rule('.rz-petition-badge.rz-petition-unhandled')
        self.assertIn('background: var(--accent);', unread)
        self.assertIn('color: var(--ground);', unread)

    def test_tables_scroll_in_their_own_box(self):
        self.assertIn('overflow-x: auto;', self.rule('.rz-content table'))
        self.assertRegex(self.css, r'(?m)^\.rz-content table \{ position: relative; \}')

    def test_settings_sections(self):
        self.assertIn('flex-direction: column;', self.rule('.rz-form-section > p > label'))


class DocumentationTests(unittest.TestCase):
    def test_spec_and_changes(self):
        spec = SPEC.read_text()
        for needle in ('### Tabs in popups', '### The inbox', '### MoTD items', '### Admin tables', '### Petition badges'):
            self.assertIn(needle, spec)
        changes = CHANGES.read_text()
        for needle in ('newscroll.GIF', 'italic', 'Phase 5 task 11'):
            self.assertIn(needle, changes)


if __name__ == '__main__':
    unittest.main()
