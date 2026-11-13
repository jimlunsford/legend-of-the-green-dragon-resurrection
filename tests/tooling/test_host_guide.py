"""The host guide (Phase 6 task 6, B2) and its sample server rules agree with the tree and the code, read without PHP.

`docs/HOST-GUIDE.md` tells a host what to upload, what the web server serves and what it must refuse, and names files,
commands, settings, environment variables and headers. `docs/hosting/nginx.conf`, `apache.conf` and `htaccess` hold
the refusals as server rules. These checks keep them true:

- every top-level path of the repository (and every file the game or its tools make there) is served on purpose or on
  the guide's deny list, and every deny list entry is refused by all three rule files, while nothing served is: a new
  top-level folder or file without a decision fails here;
- every file, command, option, setting, environment variable and header the guide names exists in the code;
- the upload list and the deny list do not contradict each other.
"""
import fnmatch
import json
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
GUIDE = ROOT / 'docs' / 'HOST-GUIDE.md'
NGINX = ROOT / 'docs' / 'hosting' / 'nginx.conf'
APACHE = ROOT / 'docs' / 'hosting' / 'apache.conf'
HTACCESS = ROOT / 'docs' / 'hosting' / 'htaccess'
APACHE_ROOT = '/srv/resurrection/game'

# Files the game, the setup page, the tools or the server make in the game's folder, which Git does not track. Each one
# is named in .gitignore, except the two a server or panel makes (.htaccess, .well-known/).
GENERATED = ['dbconnect.php', 'prefixes.php', 'resurrection-setup.php', 'resurrection-setup-failures.php', 'vendor/',
             '.local-play/', '.env', 'var/', '.phpunit.cache/', '.phpstan-cache/', '.git/']
SERVER_MADE = ['.htaccess', '.well-known/']

# PHP's own directives the guide names (they are PHP's, not the game's).
PHP_INI = {'display_errors', 'log_errors', 'memory_limit', 'expose_php', 'zend.exception_ignore_args', 'session.save_path',
           'output_buffering', 'max_execution_time', 'sendmail_path', 'error_log', 'php.ini'}


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


def entries(text, heading):
    """The paths a list names: the code spans of each list item before its first colon (patterns such as `*.md`
    included). The words after the colon say why, and may name other things."""
    items = []
    for line in section(text, heading).splitlines():
        if re.match(r'- ', line):
            items.append(line[2:])
        elif items and line.startswith('  ') and line.strip():
            items[-1] += ' ' + line.strip()
    found = []
    for item in items:
        outside = re.sub(r'`[^`]*`', lambda m: 'x' * len(m.group(0)), item)
        head = item[:outside.index(': ')] if ': ' in outside else item
        found += re.findall(r'`([^`]+)`', head)
    return found


def repository_files():
    """Every file Git tracks, and new files not yet committed (not the ignored ones)."""
    return subprocess.run(['git', 'ls-files', '-co', '--exclude-standard'], cwd=ROOT, check=True, capture_output=True,
                          text=True).stdout.split()


def tracked_top_level():
    files = repository_files()
    names = set()
    for name in files:
        head, _, rest = name.partition('/')
        names.add(head + '/' if rest else head)
    return names


def matches(item, path):
    """Whether a list entry names a path: a folder entry covers what is under it, a pattern matches the file's name."""
    if item.endswith('/'):
        return path == item or path.startswith(item)
    if '*' in item:
        return not path.endswith('/') and fnmatch.fnmatchcase(path.split('/')[-1], item)
    return path == item


def upload_files():
    """The files the guide's "Upload these" list takes, sorted: a folder entry its files, a file entry that file, and a
    pattern entry (`*.php`, "every PHP file in the top folder") the top folder's files it matches. The release package
    (scripts/build-release-package.py) must hold exactly these (tests/tooling/test_release_package.py)."""
    upload = entries(guide(), '### Upload these')
    taken = set()
    for path in repository_files():
        if not (ROOT / path).is_file():
            continue
        for item in upload:
            if ('*' in item and '/' not in path and fnmatch.fnmatchcase(path, item)) or ('*' not in item and matches(item, path)):
                taken.add(path)
    return sorted(taken)


def package_names():
    """The names the release package holds beside upload/, and upload/ itself, as the build script makes them
    (scripts/build-release-package.py, Phase 6 task 16): the guide may name them as the package has them."""
    import importlib.util
    spec = importlib.util.spec_from_file_location('build_release_package', ROOT / 'scripts' / 'build-release-package.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    top = module.NAME + '-' + module.version(ROOT) + '/'
    return {name[len(top):] for name, _ in module.package_entries(ROOT) if not name.startswith(top + 'upload/')} | {'upload'}


def shipped_code():
    """The text of everything the game ships and runs (no tests, docs or development tools)."""
    parts = []
    for pattern in ['*.php', 'lib/**/*.php', 'src/**/*.php', 'modules/**/*.php', 'templates/*.htm', 'assets/ui/*.js',
                    'scripts/install.php', 'scripts/upgrade.php', 'composer.json']:
        for path in sorted(ROOT.glob(pattern)):
            parts.append(path.read_text(encoding='utf-8', errors='replace'))
    return '\n'.join(parts)


class Rules:
    """The refusals of the three sample rule files, as patterns tested against a path."""

    def __init__(self):
        nginx = NGINX.read_text(encoding='utf-8')
        self.nginx = []
        for operator, pattern in re.findall(r'^\s*location (~\*?) (\S+) \{ return 404; \}', nginx, re.M):
            self.nginx.append(re.compile(pattern, re.I if operator == '~*' else 0))
        apache = APACHE.read_text(encoding='utf-8')
        self.apache_dirs = [re.compile(p.replace('${RESURRECTION_ROOT}', APACHE_ROOT))
                            for p in re.findall(r'<DirectoryMatch "([^"]+)">\s*Require all denied', apache)]
        self.apache_files = [re.compile(p) for p in re.findall(r'<FilesMatch "([^"]+)">\s*Require all denied', apache)]
        htaccess = HTACCESS.read_text(encoding='utf-8')
        self.rewrite = [re.compile(pattern, re.I if 'NC' in flags.split(',') else 0)
                        for pattern, flags in re.findall(r'^\s*RewriteRule "([^"]+)" - \[([^\]]*R=404[^\]]*)\]', htaccess, re.M)]
        self.htaccess_files = [re.compile(p) for p in re.findall(r'<FilesMatch "([^"]+)">\s*Require all denied', htaccess)]

    def refused(self, path):
        """Which of the three files refuse a request for path (relative to the game's folder, no leading slash)."""
        folders = [APACHE_ROOT + '/' + '/'.join(path.split('/')[:depth]) for depth in range(1, path.count('/') + 1)]
        name = path.rstrip('/').split('/')[-1] if not path.endswith('/') else None
        return {
            'nginx': any(p.search('/' + path) for p in self.nginx),
            'apache.conf': any(p.search(folder) for p in self.apache_dirs for folder in folders)
                           or (name is not None and any(p.search(name) for p in self.apache_files)),
            'htaccess': any(p.search(path) for p in self.rewrite),
            # The .htaccess's second layer, for a server without mod_rewrite: the files only.
            'htaccess files': name is not None and any(p.search(name) for p in self.htaccess_files),
        }


def sample(path):
    """A real request path under a list entry: a file that exists under a folder, else a made-up one."""
    if path.endswith('/'):
        under = sorted(p for p in (ROOT / path).rglob('*') if p.is_file()) if (ROOT / path).is_dir() else []
        return [path, (under[0].relative_to(ROOT).as_posix() if under else path + 'x.php')]
    return [path]


class HostGuideTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = guide()
        cls.upload = entries(cls.text, '### Upload these')
        cls.not_upload = entries(cls.text, '### Do not upload')
        cls.served = entries(cls.text, '### Served on purpose')
        cls.refused = entries(cls.text, '### Refused')
        cls.rules = Rules()

    def classify(self, path):
        """'refused' or 'served' for a path in the game's folder; refusal wins over a served pattern."""
        if any(matches(item, path) for item in self.refused) or (path.startswith('.') and not path.startswith('.well-known/')):
            return 'refused'
        if any(matches(item, path) for item in self.served):
            return 'served'
        return None

    def test_the_lists_are_there(self):
        for name, items in [('Upload these', self.upload), ('Do not upload', self.not_upload),
                            ('Served on purpose', self.served), ('Refused', self.refused)]:
            self.assertGreater(len(items), 3, name)
        for name, patterns in [('nginx', self.rules.nginx), ('apache.conf folders', self.rules.apache_dirs),
                               ('apache.conf files', self.rules.apache_files), ('htaccess rewrite', self.rules.rewrite),
                               ('htaccess files', self.rules.htaccess_files)]:
            self.assertTrue(patterns, f'no refusal found in {name}')

    def test_every_top_level_path_has_a_decision(self):
        """A new top-level folder or file must be served on purpose or refused (and then in every rule file)."""
        undecided = []
        for path in sorted(tracked_top_level() | set(GENERATED) | set(SERVER_MADE)):
            decision = self.classify(path)
            if decision is None:
                undecided.append(path)
            elif decision == 'served' and path.endswith('.php'):
                # A served .php file is a page: it marks itself as an entry point first (the S2 guard).
                head = (ROOT / path).read_text(encoding='utf-8', errors='replace')[:200]
                self.assertIn("define('RESURRECTION_ENTRY', true);", head, f'{path} is served as a page but is not an entry point')
        self.assertEqual([], undecided, 'top-level paths neither served on purpose nor on the guide\'s deny list')

    def test_every_refused_path_is_refused_by_every_rule_file(self):
        missing = []
        paths = sorted(tracked_top_level() | set(GENERATED) | set(SERVER_MADE))
        refused = [p for p in paths if self.classify(p) == 'refused']
        # The deny list's own entries and patterns, as paths a request could name.
        for item in self.refused:
            refused.append(item.replace('*', 'README') if '*' in item else item)
        refused += ['assets/README.md', 'LIB/output.php', 'Composer.json', '.git/HEAD', '.github/workflows/modern-core.yml']
        for path in sorted(set(refused)):
            for request in sample(path):
                verdict = self.rules.refused(request)
                for rule in ['nginx', 'apache.conf', 'htaccess']:
                    if not verdict[rule]:
                        missing.append(f'{rule}: /{request}')
                # Without mod_rewrite the .htaccess still refuses the files (not the folders).
                if not request.endswith('/') and '/' not in request and not verdict['htaccess files']:
                    missing.append(f'htaccess (files block): /{request}')
        self.assertEqual([], missing, 'paths on the deny list a sample rule file does not refuse')

    def test_nothing_served_is_refused(self):
        wrongly = []
        paths = [p for p in sorted(tracked_top_level()) if self.classify(p) == 'served']
        paths += ['assets/ui/resurrection.css', 'assets/ui/behaviors.js', 'images/trans.gif', 'templates/resurrection.htm',
                  '.well-known/acme-challenge/token', 'setup.php', 'webcron.php', 'index.php']
        for path in paths:
            for request in sample(path):
                for rule, refused in self.rules.refused(request).items():
                    if refused:
                        wrongly.append(f'{rule}: /{request}')
        self.assertEqual([], wrongly)

    def test_the_upload_list_and_the_deny_list_agree(self):
        upload = [item.replace(' ', '') for item in self.upload]
        for item in self.not_upload:
            self.assertNotIn(item, upload, f'{item} is on both upload lists')
            # Uploaded by mistake, it is still refused.
            for request in sample(item.replace('*', 'README')):
                self.assertEqual('refused', self.classify(request), f'{item} is not to be uploaded, but the guide does not refuse it')
        for item in self.served:
            self.assertNotIn(item, self.refused, f'{item} is both served and refused')
        uploaded = set()
        for item in upload:
            if item == '*.php':
                uploaded |= {p for p in tracked_top_level() if p.endswith('.php')}
            elif item in ('scripts/',):
                continue
            else:
                uploaded.add(item)
        for path in sorted(uploaded):
            self.assertIsNotNone(self.classify(path), f'{path} is uploaded, but neither served nor refused')
        for path in sorted(p for p in tracked_top_level() if self.classify(p) == 'served' and p != '.well-known/'):
            self.assertTrue(any(matches(item, path) for item in upload), f'{path} is served but not on the upload list')
        # The shell path's two commands are uploaded, and nothing else from scripts/: each file there is on one list.
        self.assertIn('scripts/install.php', upload)
        self.assertIn('scripts/upgrade.php', upload)
        for path in sorted(p for p in repository_files() if p.startswith('scripts/')):
            listed = [item for item in upload + self.not_upload if '*' not in item and matches(item, path)]
            self.assertEqual(1, len(listed), f'{path} must be on exactly one of the two upload lists: {listed}')
        # Every path the game reads at run time is uploaded.
        for needed in ['lib/', 'src/', 'modules/', 'upgrades/', 'templates/', 'assets/', 'images/', 'common.php', 'battle.php']:
            self.assertTrue(any(matches(item, needed) for item in upload), f'{needed} is needed but not uploaded')

    def test_generated_names_are_ignored_by_git(self):
        ignored = (ROOT / '.gitignore').read_text(encoding='utf-8')
        for name in GENERATED:
            if name == '.git/':
                continue
            self.assertTrue(re.search(r'^/?' + re.escape(name.rstrip('/')) + r'/?$', ignored, re.M) or name == '.env',
                            f'{name} is listed as generated but .gitignore does not name it')

    def test_every_name_the_guide_uses_exists(self):
        code = shipped_code()
        text = self.text
        problems = []
        tracked = set(repository_files())
        # Phase 6 task 16 (R1): the release package's own names, beside upload/ (scripts/build-release-package.py).
        package = package_names()
        known_paths = tracked | {name.rstrip('/') for name in GENERATED + SERVER_MADE} | package

        def path_exists(path):
            path = path.rstrip('/')
            return path in known_paths or any(t.startswith(path + '/') for t in tracked | package)

        spans = re.findall(r'`([^`\n]+)`', re.sub(r'```.*?```', '', text, flags=re.S))
        blocks = re.findall(r'```[a-z]*\n(.*?)```', text, flags=re.S)
        for span in spans:
            if span in ('http://', 'https://'):
                continue
            if span.startswith(('http://', 'https://')):
                tail = re.sub(r'^https?://[^/]+/', '', span).split('?')[0]
                if tail and not path_exists(tail):
                    problems.append(f'URL path {span}')
            elif span.startswith('php '):
                self.check_command(span, problems)
            elif re.fullmatch(r'[\w.*-]+(/[\w.*-]*)*/?', span) and ('/' in span or re.search(r'\.(php|md|json|lock|txt|htm|neon|xml|ico|png)$', span) or span == 'NOTICE'):
                if span.startswith('/'):
                    continue
                if '*' in span:
                    if not re.search('[a-z]', span):
                        continue
                    if not any(fnmatch.fnmatchcase(t, span) or fnmatch.fnmatchcase(t.split('/')[-1], span) for t in tracked):
                        problems.append(f'pattern {span}')
                elif not path_exists(span):
                    problems.append(f'file {span}')
            elif re.fullmatch(r'[A-Z][A-Z0-9]*(_[A-Z0-9]+)+|HTTPS', span):
                if not re.search(r'\b' + re.escape(span) + r'\b', code):
                    problems.append(f'variable or constant {span}')
            elif re.fullmatch(r'[A-Z][a-z]*(-[A-Z][A-Za-z]*)+', span):
                if not re.search(r'(?<![\w-])' + re.escape(span) + r'(?![\w-])', code):
                    problems.append(f'header {span}')
            elif re.fullmatch(r'[a-z][a-z0-9_.-]*', span):
                if span not in PHP_INI and not re.search(r'(?<![\w.-])' + re.escape(span) + r'(?![\w-])', code):
                    problems.append(f'name {span}')
            elif span.startswith('Resurrection: ') or span.endswith('.') and ' ' in span:
                fixed = re.split(r'\(\.\.\.\)|\.\.\.|<', span)[0]
                if fixed.rstrip() not in code:
                    problems.append(f'log line {span}')
        for block in blocks:
            for line in block.splitlines():
                for command in re.findall(r'(?:^|\s)(php [^>|]+?)(?:\s*>|\s*$)', line):
                    self.check_command(command, problems)
                for script in re.findall(r'/(\w+\.php)\b', line):
                    if script != 'dbconnect.php' and script not in tracked and not any(t.endswith('/' + script) for t in tracked):
                        problems.append(f'script {script} in {line.strip()}')
        # A setting named after its label in Game Settings: the label is the setting's own.
        descriptors = (ROOT / 'lib' / 'configuration_descriptors.php').read_text(encoding='utf-8')
        for label, name in re.findall(r'\*\*([^*]+)\*\* \(`([a-z_]+)`', text):
            m = re.search(r'"' + re.escape(name) + r'"=>"([^"]*)"', descriptors)
            if m is None:
                problems.append(f'setting {name} is not in Game Settings')
            elif not m.group(1).startswith(label):
                problems.append(f'setting {name} is labelled "{m.group(1)[:40]}", not "{label}"')
        # The page titles the troubleshooting names are the pages' own.
        for title in re.findall(r'^\*\*"([^"]+)"', section(text, '## 13. Troubleshooting'), re.M):
            fixed = title.split(':')[0].split(' (')[0]
            if fixed not in code:
                problems.append(f'page title {title}')
        self.assertEqual([], problems)

    def check_command(self, command, problems):
        words = command.split()
        script = next((w for w in words[1:] if w.endswith('.php')), None)
        if script is None:
            problems.append(f'command {command}')
            return
        relative = script.split('/public_html/')[-1].split('/httpdocs/')[-1].lstrip('/')
        source = ROOT / relative
        if not source.is_file():
            problems.append(f'command {command}: no {relative}')
            return
        text = source.read_text(encoding='utf-8')
        for option in [w.split('=')[0] for w in words if w.startswith('--')]:
            if option not in text:
                problems.append(f'command {command}: {relative} has no {option}')

    def test_the_extensions_are_composer_json_s(self):
        required = json.loads((ROOT / 'composer.json').read_text(encoding='utf-8'))['require']
        declared = sorted(name[4:] for name in required if name.startswith('ext-'))
        line = next(line for line in self.text.splitlines() if 'PHP extensions:' in line)
        listed = sorted(re.findall(r'`([a-z_]+)`', line + section(self.text, '## 1. What you need').split('PHP extensions:')[1].split('\n- ')[0]))
        self.assertEqual(declared, sorted(set(listed)))
        self.assertIn('~8.4.0 || ~8.5.0', required['php'])
        self.assertIn('PHP 8.4 or 8.5', self.text)

    def test_the_samples_are_not_active(self):
        for path in [NGINX, APACHE, HTACCESS]:
            self.assertIn('A SAMPLE, NOT ACTIVE', path.read_text(encoding='utf-8'), path.name)
        # The repository ships no active server rules: no .htaccess in the game's folder.
        self.assertFalse((ROOT / '.htaccess').exists())
        self.assertNotIn('.htaccess', tracked_top_level())
        # Apache allows no comment after a directive on the same line.
        for path in [APACHE, HTACCESS]:
            for number, line in enumerate(path.read_text(encoding='utf-8').splitlines(), 1):
                stripped = line.strip()
                if stripped and not stripped.startswith('#'):
                    self.assertNotRegex(stripped, r'\s#\s', f'{path.name}:{number} has a comment after a directive')


if __name__ == '__main__':
    unittest.main()
