"""The one template (Phase 5 task 15, docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md).

The 1.1.2 skins (18 templates in templates/, each with its stylesheet and image folder) and skin selection are removed:
templates/resurrection.htm is the only template; no code reads or sets the template cookie, so an old cookie is
ignored; the defaultskin and homeskinselect settings and the theme field type are gone, so stored rows on an older
install are ignored; home.php and prefs.php have no skin control. The skins remain byte for byte at the
historical-source-1.1.2 tag.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too). The rendered pages (an
old cookie and stored settings ignored, a template without {copyright} refused) are in tests/unit/TemplateRenderTest.php.
"""
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]


def tracked(*patterns):
    """Tracked files matching the patterns, outside the tests and the docs."""
    out = subprocess.run(['git', 'ls-files', '-z', '--', *patterns], cwd=ROOT, capture_output=True, check=True).stdout
    return [name for name in out.decode().split('\0') if name and not name.startswith(('tests/', 'docs/', 'vendor/'))]


def code(name):
    """A PHP or JavaScript file's text without its comments (the comments that tell a removal's history may name it)."""
    return code_of((ROOT / name).read_text(errors='replace'))


def code_of(text):
    text = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
    return re.sub(r'(?m)(^|[ \t;{}])(//|#).*$', r'\1', text)


# The template cookie, read or set, in any spelling the code could use.
COOKIE = re.compile(r'''_COOKIE\s*\[\s*['"]template['"]|setcookie\s*\(\s*['"]template['"]|cookie[^\n;]*['"]template=''', re.I)
# A skin setting, or a field declared with the theme type (1.1.2's skin chooser: "Name,theme").
SETTING = re.compile(r'''\b(defaultskin|homeskinselect)\b|,\s*theme\s*['"]|case\s+['"]theme['"]|['"]theme['"]\s*=>''')


class OneTemplateTests(unittest.TestCase):
    def test_only_the_resurrection_template_is_under_templates(self):
        self.assertEqual(['templates/resurrection.htm'], tracked('templates'))
        on_disk = sorted(str(p.relative_to(ROOT)) for p in (ROOT / 'templates').rglob('*') if p.is_file())
        self.assertEqual(['templates/resurrection.htm'], on_disk)

    def test_no_code_reads_or_sets_the_template_cookie(self):
        found = [f'{name}: {m.group(0)}' for name in tracked('*.php', '*.js', '*.htm')
                 for m in COOKIE.finditer(code(name))]
        self.assertEqual([], found)

    def test_no_skin_setting_or_theme_type(self):
        found = [f'{name}: {m.group(0)}' for name in tracked('*.php') for m in SETTING.finditer(code(name))]
        self.assertEqual([], found)

    def test_template_loading_names_the_one_template(self):
        # prepare_template() and loadtemplate() load templates/resurrection.htm only; no other template name in the code.
        template, pageparts = code('lib/template.php'), code('lib/pageparts.php')
        self.assertIn('$templatename="resurrection.htm";', template)
        self.assertIn('$fulltemplate = file_get_contents("templates/resurrection.htm");', pageparts)
        names = {m.group(0) for name in tracked('*.php') for m in re.finditer(r'\b[A-Za-z0-9_]+\.htm\b', code(name))}
        self.assertEqual({'resurrection.htm'}, names)

    def test_home_and_prefs_have_no_skin_control(self):
        for name in ('home.php', 'prefs.php'):
            text = code(name)
            for gone in ("name='template'", 'name="template"', "httppost('template')", 'httppost("template")',
                         "$post['template']=", '"template"=>', 'display skin', '"Skin,', 'Choose a different'):
                self.assertNotIn(gone, text, f'{name}: {gone}')

    def test_the_scans_find_what_they_look_for(self):
        self.assertIsNotNone(COOKIE.search("setcookie('template',$_POST['template'],strtotime('+45 days'));"))
        self.assertIsNotNone(COOKIE.search('$skin = $_COOKIE["template"];'))
        self.assertIsNotNone(COOKIE.search("document.cookie = 'template=jade.htm';"))
        self.assertIsNotNone(SETTING.search('"defaultskin"=>"resurrection.htm",'))
        self.assertIsNotNone(SETTING.search('"template"=>"Skin,theme",'))
        self.assertIsNotNone(SETTING.search('case "theme":'))
        self.assertIsNone(SETTING.search('"themed"=>"Themed,bool",'))
        self.assertEqual('$a = 1; \n', code_of('$a = 1; // defaultskin\n'))


if __name__ == '__main__':
    unittest.main()
