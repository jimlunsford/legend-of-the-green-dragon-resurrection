"""The module author guide (Phase 6 task 10, S10) agrees with the code.

`docs/MODULE-AUTHOR-GUIDE.md` tells a module author which hooks fire, which helpers, classes, constants and settings to
use, and shows code. Each name it gives in `code font` is checked here against the code at this commit:

- every function (`name()`) is defined in the game's PHP, and every `Class::method()` in that class;
- every class (`\\Resurrection\\...`), class constant (`Class::NAME`) and constant (`NAME`) exists;
- every CSS class (`.name`) is in the stylesheets, and every `data-rz-` attribute in assets/ui/behaviors.js;
- every `resurrection_presentation('key')` key is in the map in lib/output.php;
- every setting ("the `x` setting", "setting `x`") is a game setting, every hook ("the `x` hook") fires, and the
  guide's list of every hook that fires is exactly the set the code calls: a new hook, or a hook that goes, fails here;
- every file and folder exists, and every test it names (`file::test`) is defined;
- what the guide says is gone is gone: those hooks never fire and those settings are not game settings;
- every PHP example passes `php -l`, and every function it calls is the game's or PHP's own.

`mymodule` and `<...>` stand for the author's own names and are not checked.
"""
from pathlib import Path
import re
import subprocess
import tempfile
import unittest

import php_runtime

ROOT = Path(__file__).resolve().parents[2]
GUIDE = ROOT / 'docs' / 'MODULE-AUTHOR-GUIDE.md'
GONE = '## What 1.1.2 had that is gone'
EVERY_HOOK = '### Every hook that fires'
# Folders that are not the game's own PHP (third-party code, tests, the lab, caches and local state).
NOT_GAME = {'vendor', 'tests', 'lab', '.git', '.phpstan-cache', '.phpunit.cache', '.local-play', 'var'}
# PHP's own constants and functions the guide names.
PHP_CONSTANTS = {'PHP_SAPI'}
PHP_FUNCTIONS = {'htmlspecialchars'}
# Language constructs a call pattern also matches in the examples.
CONSTRUCTS = {'array', 'isset', 'empty', 'exit', 'die', 'list', 'echo', 'print', 'if', 'elseif', 'while', 'for',
              'foreach', 'switch', 'match', 'function', 'fn', 'use', 'return', 'unset', 'catch', 'require_once', 'include'}


def guide():
    return GUIDE.read_text(encoding='utf-8')


def section(text, heading):
    """The lines under a heading, up to the next heading of the same or a higher level."""
    level = len(heading) - len(heading.lstrip('#'))
    lines = text.splitlines()
    start = lines.index(heading) + 1
    for index in range(start, len(lines)):
        if lines[index].startswith('#') and len(lines[index]) - len(lines[index].lstrip('#')) <= level:
            return '\n'.join(lines[start:index])
    return '\n'.join(lines[start:])


def without_blocks(text):
    return re.sub(r'^```.*?^```', '', text, flags=re.S | re.M)


def spans(text):
    """The inline code spans of the prose (fenced blocks left out); a double-backtick span keeps its inner text."""
    prose = without_blocks(text)
    found = [m.group(1).strip() for m in re.finditer(r'``(.+?)``', prose)]
    prose = re.sub(r'``.+?``', '', prose)
    return found + re.findall(r'`([^`\n]+)`', prose)


def php_blocks(text):
    return re.findall(r'^```php\n(.*?)^```', text, flags=re.S | re.M)


def placeholder(name):
    return 'mymodule' in name or '<' in name


def game_php():
    for path in sorted(ROOT.rglob('*.php')):
        if path.relative_to(ROOT).parts[0] not in NOT_GAME:
            yield path


_SOURCES = None


def sources():
    global _SOURCES
    if _SOURCES is None:
        _SOURCES = {path: path.read_text(encoding='utf-8', errors='replace') for path in game_php()}
    return _SOURCES


def defined_functions():
    names = set()
    for text in sources().values():
        names.update(re.findall(r'\bfunction\s+&?([A-Za-z_]\w*)\s*\(', text))
    return names


def class_source(name):
    """The source of the class with this short name, from src/."""
    for path in sorted((ROOT / 'src').rglob('*.php')):
        text = path.read_text(encoding='utf-8')
        if re.search(r'^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum)\s+' + re.escape(name) + r'\b',
                     text, re.M):
            return text
    return None


def hooks_that_fire():
    """Every hook name the game's PHP calls: modulehook("name"), resurrection_object_locations("name"). A name built
    from a variable is written with the variable in braces ("header-$script" is header-{script})."""
    names = set()
    for text in sources().values():
        for match in re.finditer(r'''modulehook\(\s*(["'])((?:(?!\1).)*)\1''', text):
            name = re.sub(r"\{?\$session\['user'\]\['(\w+)'\]\}?", r'{\1}', match.group(2))
            names.add(re.sub(r'\{?\$(\w+)\}?', r'{\1}', name))
        names.update(re.findall(r'''resurrection_object_locations\(\s*["']([^"']+)["']''', text))
    return names


def game_settings():
    names = set()
    for name in ('lib/configuration_descriptors.php', 'lib/installer/installer_default_settings.php'):
        names.update(re.findall(r'''["']([A-Za-z_][A-Za-z0-9_]*)["']\s*=>''', (ROOT / name).read_text(encoding='utf-8')))
    return names


def stylesheet_classes():
    text = ''.join(path.read_text(encoding='utf-8') for path in sorted((ROOT / 'assets' / 'ui').glob('*.css')))
    return set(re.findall(r'\.([A-Za-z_][\w-]*)', text))


def presentation_keys():
    text = (ROOT / 'lib' / 'output.php').read_text(encoding='utf-8')
    body = re.search(r'function resurrection_presentation\(\$key\)\{(.*?)\n\}', text, re.S).group(1)
    return set(re.findall(r'^\s*"([a-z-]+)"\s*=>', body, re.M))


def named_settings(text):
    return set(re.findall(r'\bsetting `([A-Za-z_]\w*)`', text)) | set(re.findall(r'`([A-Za-z_]\w*)` setting\b', text))


def named_hooks(text):
    """The hooks the prose names: "the `x` hook", "the hooks `x` and `y`", "the `x` and `y` hooks"."""
    names = set()
    run = r'`[^`]+`(?:(?:,? and |, )`[^`]+`)*'
    for match in re.finditer(r'\bhooks? (' + run + ')', text):
        names.update(re.findall(r'`([^`]+)`', match.group(1)))
    for match in re.finditer('(' + run + r') hooks?\b', text):
        names.update(re.findall(r'`([^`]+)`', match.group(1)))
    return names


class ModuleAuthorGuideTests(unittest.TestCase):
    def test_the_guide_exists_and_is_linked(self):
        self.assertTrue(GUIDE.is_file(), 'docs/MODULE-AUTHOR-GUIDE.md is missing')
        policy = (ROOT / 'docs' / 'MODULE-POLICY.md').read_text(encoding='utf-8')
        self.assertIn('MODULE-AUTHOR-GUIDE.md', policy, 'docs/MODULE-POLICY.md does not point to the guide')
        self.assertIn('docs/MODULE-AUTHOR-GUIDE.md', (ROOT / 'README.md').read_text(encoding='utf-8'))

    def test_the_entry_guard_is_the_one_every_module_file_carries(self):
        # tests/unit/EntryGuardTest.php holds the line every internal file must start with (its GUARD).
        source = (ROOT / 'tests' / 'unit' / 'EntryGuardTest.php').read_text(encoding='utf-8')
        guard = re.search(r'const string GUARD = "(.*)";', source).group(1).replace('\\\\', '\\')
        self.assertIn('<?php\n' + guard + '\n', section(guide(), '## 1. The entry guard'))

    def test_the_list_of_hooks_is_every_hook_that_fires(self):
        listed = re.findall(r'`([^`]+)`', section(guide(), EVERY_HOOK).split('\n\n', 2)[-1])
        self.assertEqual(len(listed), len(set(listed)), 'a hook is listed twice')
        code = hooks_that_fire()
        self.assertEqual(sorted(set(listed) - code), [], 'listed, but no code calls it')
        self.assertEqual(sorted(code - set(listed)), [], 'called by the code, but not in the guide')

    def test_every_hook_and_setting_the_guide_names_exists(self):
        text = guide()
        kept = text.replace(section(text, GONE), '')
        fire = hooks_that_fire()
        hooks = {name for name in named_hooks(without_blocks(kept)) if not placeholder(name)}
        self.assertTrue({'scene', 'creatureportrait', 'header-graveyard', 'camplocs', 'stablelocs'} <= hooks, hooks)
        self.assertEqual(sorted(hooks - fire), [], 'hooks the guide names that never fire')
        settings = game_settings()
        named = named_settings(without_blocks(kept))
        self.assertIn('csp_mode', named)
        self.assertEqual(sorted(named - settings), [], 'settings the guide names that do not exist')

    def test_what_the_guide_says_is_gone_is_gone(self):
        gone = section(guide(), GONE)
        hooks = set(re.findall(r'`([^`]+)`', gone.split('**With the skins')[0]))
        self.assertTrue({'validatesettings', 'mod-dyn-settings', 'rawsql-execphp'} <= hooks, hooks)
        self.assertEqual(sorted(hooks & hooks_that_fire()), [], 'hooks the guide says are gone still fire')
        settings = set(re.findall(r'`([a-z]+)`', gone.split('**With the skins')[1].split('settings')[0]))
        self.assertTrue({'defaultskin', 'homeskinselect'} <= settings, settings)
        self.assertEqual(sorted(settings & game_settings()), [], 'settings the guide says are gone still exist')

    def test_every_function_class_and_constant_the_guide_names_exists(self):
        functions = defined_functions()
        missing = []
        for span in spans(guide()):
            if placeholder(span):
                continue
            for name in re.findall(r'(?<![\w:>$\\.-])([a-z][a-z0-9_]*)\(', span):
                if name not in functions and name not in PHP_FUNCTIONS:
                    missing.append(f'function {name}()')
            for owner, member in re.findall(r'([A-Z]\w*)::(\w+)', span):
                source = class_source(owner)
                if source is None:
                    missing.append(f'class {owner}')
                elif member.isupper():
                    if not re.search(r'\bconst\s+(?:[\w|?]+\s+)?' + member + r'\b', source):
                        missing.append(f'constant {owner}::{member}')
                elif not re.search(r'\bfunction\s+' + member + r'\s*\(', source):
                    missing.append(f'method {owner}::{member}()')
            for name in re.findall(r'\\Resurrection\\(?:\w+\\)*(\w+)', span):
                if class_source(name) is None:
                    missing.append(f'class {name}')
            if re.fullmatch(r'[A-Z][A-Z0-9_]{2,}', span) and span not in PHP_CONSTANTS:
                if not any(re.search(r'''define\(\s*["']''' + span + r'''["']|\bconst\s+''' + span + r'\b', text)
                           for text in sources().values()):
                    missing.append(f'constant {span}')
        self.assertEqual(missing, [])

    def test_every_css_class_attribute_and_presentation_key_exists(self):
        text = guide()
        classes = stylesheet_classes()
        behaviors = (ROOT / 'assets' / 'ui' / 'behaviors.js').read_text(encoding='utf-8')
        keys = presentation_keys()
        missing = []
        for span in spans(text):
            for name in re.findall(r'(?:^|\s)\.([A-Za-z][\w-]*)\b', span):
                if name not in classes:
                    missing.append(f'class .{name}')
            for name in re.findall(r'data-rz-[a-z-]+', span):
                if name not in behaviors:
                    missing.append(f'attribute {name}')
            for key in re.findall(r"resurrection_presentation\('([^']+)'\)", span):
                if key not in keys:
                    missing.append(f"presentation key {key}")
        self.assertEqual(missing, [])
        self.assertGreaterEqual(len(re.findall(r"resurrection_presentation\('", text)), 10)

    def test_every_file_and_test_the_guide_names_exists(self):
        missing = []
        for span in spans(guide()):
            if placeholder(span) or ' ' in span or '(' in span:
                continue
            path, _, test = span.partition('::')
            if not re.fullmatch(r'[\w./-]+', path) or not ('/' in path or re.search(r'\.(php|js|css|md|htm|py)$', path)):
                continue
            if not (ROOT / path).exists():
                missing.append(path)
            elif test and not re.search(r'\bdef ' + test + r'\(|\bfunction ' + test + r'\(',
                                        (ROOT / path).read_text(encoding='utf-8')):
                missing.append(span)
        self.assertEqual(missing, [])

    def test_every_php_example_passes_lint_and_calls_only_known_functions(self):
        php = php_runtime.supported_php()
        blocks = php_blocks(guide())
        self.assertGreaterEqual(len(blocks), 5)
        functions = defined_functions()
        calls = set()
        with tempfile.TemporaryDirectory() as directory:
            for number, block in enumerate(blocks, 1):
                file = Path(directory) / f'example{number}.php'
                file.write_text(block if block.startswith('<?php') else '<?php\n' + block, encoding='utf-8')
                result = subprocess.run([php, '-l', str(file)], capture_output=True, text=True, timeout=60)
                self.assertEqual(result.returncode, 0, f'example {number}:\n{block}\n{result.stdout}{result.stderr}')
                own = set(re.findall(r'\bfunction\s+(\w+)\s*\(', block))
                calls |= {name for name in re.findall(r'(?<![\w:>$\\])([A-Za-z_]\w*)\s*\(', block)
                          if name not in own and name not in CONSTRUCTS and name.lower() not in CONSTRUCTS}
        unknown = sorted(name for name in calls - functions if not name[0].isupper())
        result = subprocess.run([php, '-n', '-r', 'foreach (array_slice($argv, 1) as $f) if (!function_exists($f)) echo $f, "\\n";',
                                 '--', *unknown], capture_output=True, text=True, timeout=60)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout.split(), [], 'functions the examples call that neither the game nor PHP has')


if __name__ == '__main__':
    unittest.main()
