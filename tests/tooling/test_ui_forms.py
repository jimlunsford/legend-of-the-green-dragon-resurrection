"""Forms, buttons and tables in the Resurrection UI (Phase 5 task 9, docs/PHASE5-TASK9-FORMS-20261031.md).

Static: runs wherever the suite runs (Baseline integrity too). It checks that

- no page or bundled module prints presentation inline (<font>, bgcolor=, style=) except the allow-listed places, each
  with its reason: resurrection_presentation() in lib/output.php keeps 1.1.2's markup for the old skins until task 15
  removes them, and so on;
- every input the hand-fixed pages print, and every one showform() prints, has a bound label;
- showform()'s tabs in the Resurrection template carry no inline event handler, and assets/ui/showform.js does the work;
- the stylesheet draws buttons, inputs, tabs and tables as docs/UI-DESIGN-SPEC.md says.

The rendered pages (one primary per form, old skins' button markup byte for byte, the tabs' aria-selected) are checked
in tests/unit/FormsRenderTest.php.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
CSS = ROOT / 'assets' / 'ui' / 'resurrection.css'
SCRIPT = ROOT / 'assets' / 'ui' / 'showform.js'
INLINE = re.compile(r'<font|bgcolor=|style=', re.I)
# Not game pages: Composer's packages, the tests, and the 1.1.2 skins (removed whole by task 15).
SKIPPED = ('vendor', 'tests', 'templates', '.git')

# Each place inline presentation may stay, with why. (file, text on the line, reason)
ALLOWED = [
    ('lib/output.php', None,
     "resurrection_presentation(): the one map that keeps 1.1.2's markup for a 1.1.2 skin, byte for byte, until task 15"),
    ('lib/showform.php', "class='trhead' style='\\\"+weight+\\\"float: left;",
     "showform()'s 1.1.2 tab script, printed only in a 1.1.2 skin; the Resurrection template gets server-drawn tabs"),
    ('lib/showform.php', "<div style='display: block;'>&nbsp;</div>",
     "the same 1.1.2 tab script's spacer, only in a 1.1.2 skin"),
    ('lib/about/about_listmodules.php', "bgcolor='#999999'",
     "the about page's 1.1.2 strings stay verbatim (\"original text from the about page must be preserved\", "
     "test_license_text.py); the stylesheet draws its grid as rz-grid's (table[bgcolor='#999999'])"),
    ('lib/installer/installer_sqlstatements.php', "'Gypsy Bandit'",
     "data: the Gypsy Bandit's stored creature script, which CreatureAi::supported() recognizes by its SHA-256; the "
     "pickpocket's output is printed by lib/extended-battle.php, which uses classes"),
]

# The pages task 9 fixes by hand: every input they print has a bound label (<label for> or a wrapping <label>). The
# commentary's player form only (talkform(); its Add box is labelled in viewcommentary()): the moderation controls in
# viewcommentary() are an operator's, listed for task 11.
HAND_FIXED = [('create.php', None), ('bank.php', None), ('prefs.php', None), ('lib/commentary.php', 'talkform'),
              ('lib/mail/case_write.php', None), ('lib/mail/case_address.php', None), ('lib/petition/petition_default.php', None),
              ('lib/inn/inn_room.php', None), ('healer.php', None), ('lib/equipment_authority.php', None)]
# Operator controls inside a hand-fixed page, left for task 11 (admin and editor forms) with the others it lists.
OPERATOR = {
    ('lib/commentary.php', "<select name='section'>"): "talkform()'s section chooser, only in moderate.php's all-sections view",
}
# Controls that need no label of their own: buttons say what they do, hidden inputs show nothing.
UNLABELLED_TYPES = {'hidden', 'submit', 'button', 'reset', 'image'}


def sources():
    for path in sorted(ROOT.rglob('*.php')):
        rel = path.relative_to(ROOT)
        if rel.parts[0] in SKIPPED or rel.parts[0].startswith('.') or rel.name == 'dbconnect.php':
            continue
        yield str(rel), path.read_text(errors='replace')


def function_body(text, name):
    start = text.index('function ' + name + '(')
    return start, text.index('\n}\n', start)


def controls(text):
    """The <input>, <select> and <textarea> tags in a PHP file's strings that need a label, as written."""
    found = []
    for tag in re.finditer(r'<(input|select|textarea)\b([^>]*)>', text):
        kind = re.search(r'''type=["']?([a-z]+)''', tag.group(2))
        if tag.group(1) == 'input' and kind and kind.group(1) in UNLABELLED_TYPES:
            continue
        # Generated tag text (`"<input type='"`) and the old forms' runtime type ('$type') are checked where they render.
        if tag.group(1) == 'input' and re.search(r'''type=["']?\$''', tag.group(2)):
            continue
        found.append(tag.group(0))
    return found


class InlinePresentationTest(unittest.TestCase):
    def test_no_inline_presentation_outside_the_allow_list(self):
        found = []
        used = set()
        for rel, text in sources():
            for number, line in enumerate(text.split('\n'), 1):
                if not INLINE.search(line):
                    continue
                offset = sum(len(l) + 1 for l in text.split('\n')[:number - 1])
                allowed = None
                for n, (path, needle, _) in enumerate(ALLOWED):
                    if path != rel:
                        continue
                    if needle is None:
                        start, end = function_body(text, 'resurrection_presentation') if 'function resurrection_presentation(' in text else (-1, -1)
                        if start <= offset <= end:
                            allowed = n
                    elif needle in line:
                        allowed = n
                if allowed is None:
                    found.append(f'{rel}:{number}: {line.strip()[:120]}')
                else:
                    used.add(allowed)
        self.assertEqual([], found)
        # No stale entry: each allowed place is still there.
        self.assertEqual(set(range(len(ALLOWED))), used)

    def test_the_old_skin_markup_lives_only_in_the_map(self):
        # appoencode()'s `c, `> and `< go through the map; the legacy tab script is only in the 1.1.2 branch of showform().
        output = (ROOT / 'lib' / 'output.php').read_text()
        start, end = function_body(output, 'resurrection_presentation')
        self.assertIn('return $markup[$key][resurrection_ui() ? 0 : 1];', output[start:end])
        appo = output[output.index('function appoencode('):]
        for key in ('center', 'float-right', 'float-left'):
            self.assertIn(f'resurrection_presentation("{key}")', appo, key)
        self.assertNotIn("<div align='center'>", appo)
        showform = (ROOT / 'lib' / 'showform.php').read_text()
        legacy = showform.index('} elseif ($showform_id==1){')
        for needle in ("class='trhead' style=", "<div style='display: block;'>"):
            self.assertGreater(showform.index(needle), legacy, needle)


class LabelTest(unittest.TestCase):
    def assertLabelled(self, rel, text):
        missing = []
        for tag in controls(text):
            if (rel, tag) in OPERATOR:
                continue
            control = re.search(r'''\bid=["']?([A-Za-z0-9_$\-{}]+)''', tag)
            if control is None:
                # A control wrapped in its label: "<label><input ...".
                if not re.search(r'<label>\s*' + re.escape(tag[:20]), text):
                    missing.append(tag)
                continue
            name = control.group(1)
            # A <label for>, written out or through the mail form's $label() helper.
            if not re.search(r'''for=(\\?["'])''' + re.escape(name) + r'''\1''', text) and f"$label('{name}'," not in text:
                missing.append(tag)
        self.assertEqual([], missing, rel)

    def test_the_hand_fixed_pages_label_every_input(self):
        for rel, function in HAND_FIXED:
            text = (ROOT / rel).read_text()
            if function is not None:
                start, end = function_body(text, function)
                self.assertLabelled(rel, text[start:end])
            else:
                self.assertLabelled(rel, text)

    def test_previewfield_inputs_are_labelled_where_they_are_used(self):
        # lib/forms.php's previewfield() prints input$name; each caller labels it.
        forms = (ROOT / 'lib' / 'forms.php').read_text()
        self.assertIn("id='input$name'", forms)
        self.assertIn("<label for='inputinsertcommentary'>", (ROOT / 'lib' / 'commentary.php').read_text())
        self.assertIn("$label('inputbody',", (ROOT / 'lib' / 'mail' / 'case_write.php').read_text())

    def test_showform_labels_every_control(self):
        showform = (ROOT / 'lib' / 'showform.php').read_text()
        # One id per control, its row's <label for>; a group of choices labels each choice.
        self.assertIn('function showform_control_id($form, $key){', showform)
        self.assertIn("<label for='$id'>", showform)
        for tag in controls(showform):
            if 'id=' in tag:
                continue
            # The checklist, radio and bitfield choices: each inside its own <label>.
            self.assertTrue(re.search(r'<label>\s*' + re.escape(tag[:24]), showform), tag)


class TabsTest(unittest.TestCase):
    def test_showform_tabs_without_inline_handlers(self):
        showform = (ROOT / 'lib' / 'showform.php').read_text()
        start, end = function_body(showform, 'showform_tabs')
        tabs = showform[start:end]
        for needle in ("role='tablist'", "role='tab'", "aria-selected='$selected'", "aria-controls='showFormTable$x'",
                       "data-rz-tabs='$form' hidden"):
            self.assertIn(needle, tabs, needle)
        self.assertIsNone(re.search(r'\bon[a-z]+=', tabs, re.I))
        self.assertIn("role='tabpanel' aria-labelledby='showFormButton$title_id'", showform)
        output = (ROOT / 'lib' / 'output.php').read_text()
        script = output[slice(*function_body(output, 'resurrection_form_script'))]
        self.assertIn('if ($done || !resurrection_ui()) return "";', script)
        self.assertIn('return "<script src=\\"assets/ui/showform.js\\" defer></script>";', script)

    def test_the_form_script(self):
        script = SCRIPT.read_text()
        self.assertTrue(script.isascii())
        for needle in ("querySelectorAll('[data-rz-tabs]')", "setAttribute('aria-selected'", "tab.tabIndex = on ? 0 : -1;",
                       "'ArrowRight'", "'ArrowLeft'", "'Home'", "'End'", "list.hidden = false;", "data-rz-resize",
                       "index.name = 'showFormTabIndex';",
                       "box.rows + step > 3 && box.rows + step < 50", "window.confirm(button.getAttribute('data-rz-confirm'))"):
            self.assertIn(needle, script, needle)
        for unsafe in ('eval(', 'innerHTML', 'document.write', 'new Function', 'http:', 'https:', 'import '):
            self.assertNotIn(unsafe, script, unsafe)


class StylesheetTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.css = CSS.read_text()

    def rule(self, selector):
        found = re.search(r'(?m)^' + re.escape(selector) + r'\s*\{([^}]*)\}', self.css)
        self.assertIsNotNone(found, selector)
        return found.group(1)

    def test_buttons(self):
        button = self.rule('.button, button, input[type="submit"], input[type="button"], input[type="reset"]')
        for value in ('min-height: var(--touch-target);', 'padding: 0 16px;', 'background: transparent;', 'color: var(--text);',
                      'border: 1px solid var(--input-border);', 'border-radius: var(--radius-control);', 'margin: 4px 8px 4px 0;'):
            self.assertIn(value, button, value)
        primary = self.rule('.rz-button-primary, input.rz-button-primary')
        for value in ('background: var(--accent);', 'color: var(--ground);', 'font-weight: var(--weight-ui-bold);'):
            self.assertIn(value, primary, value)

    def test_inputs(self):
        inputs = self.rule('input:not([type]), input[type="text"], input[type="password"], input[type="number"], input[type="email"],\n'
                           'input[type="search"], input[type="url"], input[type="tel"], select, textarea')
        for value in ('min-height: var(--touch-target);', 'background: var(--panel);', 'border: 1px solid var(--input-border);',
                      'border-radius: var(--radius-control);'):
            self.assertIn(value, inputs, value)
        self.assertNotIn('--raised', inputs)
        self.assertIn('color: var(--muted);', self.rule('::placeholder'))
        self.assertIn('min-height: 88px;', self.rule('textarea'))

    def test_tabs(self):
        self.assertIn('flex-wrap: wrap;', self.rule('.rz-tabs'))
        self.assertIn('display: none;', self.rule('.rz-tabs[hidden]'))
        active = self.rule('.rz-tab-button[aria-selected="true"]')
        self.assertIn('background: var(--accent);', active)
        self.assertIn('color: var(--ground);', active)

    def test_tables(self):
        head = self.rule('.trhead, .trhead td, .trhead th')
        for value in ('background: var(--raised);', 'color: var(--muted);', 'font-size: var(--size-label);',
                      'letter-spacing: var(--tracking-label);', 'text-transform: uppercase;'):
            self.assertIn(value, head, value)
        self.assertIn('background: var(--panel);', self.rule('.trlight'))
        self.assertIn('background: var(--header);', self.rule('.trdark'))
        self.assertIn('box-shadow: inset 3px 0 0 var(--accent);', self.rule('.trhilight > td:first-child, .trhilight > th:first-child'))
        scroll = self.rule('.rz-content table')
        self.assertIn('overflow-x: auto;', scroll)
        self.assertIn('max-width: 100%;', scroll)
        self.assertIn('text-align: right;', self.rule('.rz-content td[align="right"], .rz-content th[align="right"]'))

    def test_the_presentation_classes(self):
        output = (ROOT / 'lib' / 'output.php').read_text()
        start, end = function_body(output, 'resurrection_presentation')
        classes = set()
        for markup in re.findall(r'array\("([^"]*)", ', output[start:end]):
            for names in re.findall(r"""class=['"]([^'"]+)['"]""", markup):
                # The game's own classes (rz-) and the color codes' (col); "button" and "input" are 1.1.2's.
                classes.update(name for name in names.split() if name.startswith(('rz-', 'col')))
        self.assertIn('rz-grid', classes)
        tokens = (ROOT / 'assets' / 'ui' / 'tokens.css').read_text()
        for name in sorted(classes):
            self.assertTrue(re.search(r'\.' + re.escape(name) + r'\b', self.css + tokens), name)


if __name__ == '__main__':
    unittest.main()
