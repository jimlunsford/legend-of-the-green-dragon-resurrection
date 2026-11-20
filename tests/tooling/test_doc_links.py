"""The docs index lists every document, and every link a reader can follow in the living documents works (Phase 6
task 31). Read without PHP, a database or the network.

`docs/README.md` is the index of the documents. Its "Start here" and "Reference" sections list the living documents,
the guides and references kept current; "Records" lists the evidence of each task, kept as written. These checks keep
it true:

- every file in docs/, its folders included, is the target of exactly one link in the index;
- every relative link and image in the README, the index, the top-level living documents, the notes in the code's
  folders and the living documents the index lists points to a file or folder in the repository (tracked, or new and
  not ignored), never outside it, and every heading anchor it names exists, by GitHub's rule for heading anchors;
- every image in those documents has alt text.

Links in fenced code blocks and code spans are text, not links, and are not checked. Links to other sites are checked
once by hand, with a network fetch, and recorded in the task's evidence; these checks never use the network. Records
are not checked: they link what existed when they were written, and are never rewritten.
"""
import functools
import html
from pathlib import Path
import re
import subprocess
import unittest
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'docs'
INDEX = DOCS / 'README.md'

# Living documents outside docs/: the front door, the project's rules and plan, and the notes in the code's folders.
TOP_LEVEL = ['README.md', 'CONTRIBUTING.md', 'SECURITY.md', 'PROVENANCE.md', 'ROADMAP.md', 'CLAUDE.md',
             'assets/README.md', 'upgrades/README.md', 'lab/historical-1.1.2/README.md',
             'tests/fixtures/upgrade-steps/README.md']
# The index's sections that list living documents.
LIVING_SECTIONS = ('## Start here', '## Reference')

FENCE = re.compile(r'^[ \t]*(`{3,}|~{3,})')
CODE_SPAN = re.compile(r'(`+)(?!`)(.+?)(?<!`)\1(?!`)', re.S)
# [text](target "title") and ![alt](target): the text may hold one level of brackets and wrap; the target may sit in
# angle brackets or hold balanced parentheses.
INLINE = re.compile(r'(!?)\[((?:[^\[\]]|\[[^\[\]]*\])*)\]\(\s*(<[^<>\n]*>|(?:[^\s()<>]|\([^\s()]*\))*)'
                    r'(?:\s+(?:"[^"]*"|\'[^\']*\'|\([^)]*\)))?\s*\)')
DEFINITION = re.compile(r'^ {0,3}\[([^\]]+)\]:[ \t]*(<[^<>\n]*>|\S+)', re.M)
HTML_LINK = re.compile(r'<(a|img|source)\b([^>]*)>', re.I)
ATTRIBUTE = re.compile(r'\b(href|src|alt|id|name)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')', re.I)
SCHEME = re.compile(r'[A-Za-z][A-Za-z0-9+.-]*:')
ATX = re.compile(r'^ {0,3}(#{1,6})(?:[ \t]+(.*?))??(?:[ \t]+#+)?[ \t]*$')
SETEXT = re.compile(r'^ {0,3}(=+|-+)[ \t]*$')
LINE_ANCHOR = re.compile(r'L\d+(?:-L\d+)?')


def closes(fence, line):
    """Whether a line closes a fenced code block that opened with fence."""
    mark = FENCE.match(line)
    return bool(mark) and mark.group(1)[0] == fence[0] and len(mark.group(1)) >= len(fence) \
        and not line.strip()[len(mark.group(1)):].strip()


def blocks(text):
    """The text's paragraphs outside fenced code blocks, as (first line number, text), code spans blanked."""
    found, lines, start, fence = [], [], 0, None
    for number, line in enumerate(text.splitlines(), 1):
        mark = FENCE.match(line)
        if fence is not None:
            if closes(fence, line):
                fence = None
            continue
        if mark:
            fence = mark.group(1)
        if mark or not line.strip():
            if lines:
                found.append((start, '\n'.join(lines)))
            lines = []
            continue
        if not lines:
            start = number
        lines.append(line)
    if lines:
        found.append((start, '\n'.join(lines)))
    return [(number, CODE_SPAN.sub(lambda m: ' ' * len(m.group(0)), block)) for number, block in found]


def links(text):
    """Every link and image in Markdown text: (line, kind, target, alt), kind 'link' or 'image'; alt is the image's
    text (None for a link)."""
    found = []
    for number, block in blocks(text):
        def line_of(offset):
            return number + block.count('\n', 0, offset)
        for match in INLINE.finditer(block):
            image = match.group(1) == '!'
            found.append((line_of(match.start()), 'image' if image else 'link', match.group(3), match.group(2) if image else None))
        for match in DEFINITION.finditer(block):
            found.append((line_of(match.start()), 'link', match.group(2), None))
        for match in HTML_LINK.finditer(block):
            attributes = {name.lower(): (double if double is not None else single) for name, double, single in ATTRIBUTE.findall(match.group(2))}
            tag = match.group(1).lower()
            if tag == 'a' and 'href' in attributes:
                found.append((line_of(match.start()), 'link', attributes['href'], None))
            elif tag in ('img', 'source') and 'src' in attributes:
                found.append((line_of(match.start()), 'image', attributes['src'], attributes.get('alt', '') if tag == 'img' else 'source'))
    return found


def rendered(heading):
    """A heading's text as GitHub shows it: code spans and escapes keep their characters, a link keeps its text, an
    image and HTML tags show nothing, emphasis marks go."""
    kept = []

    def keep(value):
        kept.append(value)
        return f'\0{len(kept) - 1}\0'
    text = CODE_SPAN.sub(lambda m: keep(m.group(2)), heading)
    text = re.sub(r'\\([!-/:-@\[-`{-~])', lambda m: keep(m.group(1)), text)
    text = re.sub(r'!\[[^\]]*\]\([^)]*\)', '', text)
    text = re.sub(r'\[([^\]]*)\]\([^)]*\)|\[([^\]]*)\]\[[^\]]*\]', lambda m: m.group(1) if m.group(1) is not None else m.group(2), text)
    text = re.sub(r'<[^>]+>', '', text)
    text = re.sub(r'(?<!\w)[*_]+|[*_]+(?!\w)', '', text)
    text = html.unescape(text)
    return re.sub('\0(\\d+)\0', lambda m: kept[int(m.group(1))], text).strip()


def slug(text):
    """GitHub's anchor for a heading: lower case, every character but letters, digits, '_', '-' and spaces removed,
    each space a '-'."""
    return re.sub(r'[^\w\- ]', '', text.lower()).replace(' ', '-')


def headings(text):
    """The text of every Markdown heading outside fenced code blocks, in order (ATX and setext)."""
    found, fence, previous = [], None, ''
    for line in text.splitlines():
        mark = FENCE.match(line)
        if fence is not None:
            if closes(fence, line):
                fence = None
            previous = ''
            continue
        if mark:
            fence, previous = mark.group(1), ''
            continue
        atx = ATX.match(line)
        if atx:
            found.append(rendered(atx.group(2) or ''))
            previous = ''
            continue
        if SETEXT.match(line) and previous.strip() and not re.match(r'^ {0,3}([-*+>|]|\d+[.)])', previous):
            found.append(rendered(previous.strip()))
            previous = ''
            continue
        previous = line
    return found


@functools.lru_cache(maxsize=None)
def anchors(path):
    """The anchors a Markdown file has (see anchors_of)."""
    return anchors_of(path.read_text(encoding='utf-8'))


def anchors_of(text):
    """The anchors Markdown text has: its headings' slugs, numbered on repeats the way GitHub numbers them, and the
    ids and names its HTML gives."""
    seen, result = {}, set()
    for heading in headings(text):
        base = candidate = slug(heading)
        while candidate in seen:
            seen[base] += 1
            candidate = f'{base}-{seen[base]}'
        seen[candidate] = 0
        result.add(candidate)
    for _, block in blocks(text):
        for match in HTML_LINK.finditer(block):
            for name, double, single in ATTRIBUTE.findall(match.group(2)):
                if name.lower() in ('id', 'name'):
                    result.add(double if double is not None else single)
    return result


def committed_files():
    """The files a commit of this tree would hold (tracked, or new and not ignored), or None outside a Git checkout."""
    try:
        listed = subprocess.run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], cwd=ROOT,
                                capture_output=True, check=True).stdout
    except (OSError, subprocess.CalledProcessError):
        return None
    return {name for name in listed.decode('utf-8').split('\0') if name and (ROOT / name).is_file()}


def index_targets():
    """The index's links, as (section, path relative to the repository, fragment); section is the '## ' heading the
    link sits under."""
    text = INDEX.read_text(encoding='utf-8')
    found, section = [], ''
    sections = []
    for number, line in enumerate(text.splitlines(), 1):
        if line.startswith('## '):
            sections.append((number, line.strip()))
    for number, kind, target, _ in links(text):
        section = next((name for start, name in reversed(sections) if start <= number), '')
        target = target[1:-1] if target.startswith('<') else target
        if SCHEME.match(target) or target.startswith('#'):
            continue
        path, _, fragment = target.partition('#')
        resolved = (INDEX.parent / unquote(path)).resolve()
        try:
            found.append((section, resolved.relative_to(ROOT).as_posix(), fragment))
        except ValueError:
            found.append((section, str(resolved), fragment))
    return found


def living_documents():
    """The living documents whose links are checked, relative to the repository: the top-level ones, the index and the
    Markdown files the index lists under "Start here" and "Reference"."""
    listed = [path for section, path, _ in index_targets() if section in LIVING_SECTIONS and path.endswith('.md')
              and path.startswith('docs/')]
    return list(dict.fromkeys(TOP_LEVEL + ['docs/README.md'] + listed))


class DocLinksTest(unittest.TestCase):
    def test_the_index_lists_every_file_in_docs_exactly_once(self):
        self.assertTrue(INDEX.is_file(), 'docs/README.md, the index of the documents, is missing')
        counts = {}
        for _, path, _ in index_targets():
            if path.startswith('docs/'):
                counts[path] = counts.get(path, 0) + 1
        files = sorted(path.relative_to(ROOT).as_posix() for path in DOCS.rglob('*') if path.is_file() and path != INDEX)
        self.assertGreater(len(files), 100)
        self.assertEqual([], [path for path in files if path not in counts], 'files in docs/ the index does not list')
        self.assertEqual([], sorted(path for path, count in counts.items() if count > 1), 'files the index links more than once')
        self.assertEqual([], sorted(path for path in counts if path not in files), 'index links to files not in docs/')

    def test_the_index_lists_the_living_documents_in_start_here_and_reference(self):
        living = living_documents()
        for path in ('docs/HOST-GUIDE.md', 'docs/CHANGES-FROM-1.1.2.md', 'docs/SECURITY-STATUS.md', 'docs/MODULE-AUTHOR-GUIDE.md'):
            self.assertIn(path, living)
        self.assertNotIn('docs/COMPATIBILITY-STATUS.md', living)
        self.assertNotIn('docs/PHASE6-RC-READINESS-20261106.md', living)

    def test_every_relative_link_in_the_living_documents_resolves(self):
        committed = committed_files()
        broken, checked = [], 0
        for name in living_documents():
            source = ROOT / name
            self.assertTrue(source.is_file(), name)
            for number, _, target, _ in links(source.read_text(encoding='utf-8')):
                target = target[1:-1] if target.startswith('<') else target
                if SCHEME.match(target):
                    continue
                checked += 1
                where = f'{name}:{number}: {target}'
                path, _, fragment = target.partition('#')
                path = unquote(path.split('?', 1)[0])
                if path.startswith('/'):
                    broken.append(f'{where} (an absolute path; links are relative)')
                    continue
                destination = (source.parent / path).resolve() if path else source
                try:
                    relative = destination.relative_to(ROOT).as_posix()
                except ValueError:
                    broken.append(f'{where} (outside the repository)')
                    continue
                if destination.is_dir():
                    if committed is not None and not any(item.startswith(relative.rstrip('/') + '/') for item in committed):
                        broken.append(f'{where} (a folder with no committed file)')
                    continue
                if not destination.is_file() or (committed is not None and relative not in committed):
                    broken.append(f'{where} (no such file in the repository)')
                    continue
                if not fragment:
                    continue
                if destination.suffix.lower() == '.md':
                    if unquote(fragment) not in anchors(destination):
                        broken.append(f'{where} (no heading with that anchor in {relative})')
                elif not LINE_ANCHOR.fullmatch(fragment):
                    broken.append(f'{where} (an anchor in a file that is not Markdown)')
        self.assertGreater(checked, 200)
        self.assertEqual([], broken)

    def test_every_image_in_the_living_documents_has_alt_text(self):
        missing, images = [], 0
        for name in living_documents():
            for number, kind, target, alt in links((ROOT / name).read_text(encoding='utf-8')):
                if kind != 'image':
                    continue
                images += 1
                if not (alt or '').strip():
                    missing.append(f'{name}:{number}: {target}')
        self.assertGreaterEqual(images, 5)
        self.assertEqual([], missing)


class HelpersTest(unittest.TestCase):
    """The parsing the checks rely on, on small samples."""

    def test_slugs_follow_githubs_rule(self):
        cases = {
            '5.3 If an install fails': '53-if-an-install-fails',
            '9. Upgrade': '9-upgrade',
            'Upload `upload/`, not the folder': 'upload-upload-not-the-folder',
            'Licenses and credits': 'licenses-and-credits',
            'What it is (and isn\'t)': 'what-it-is-and-isnt',
            'The [host guide](docs/HOST-GUIDE.md)': 'the-host-guide',
            '**Bold** and _emphasis_ and `snake_case`': 'bold-and-emphasis-and-snake_case',
            'A &amp; B': 'a--b',
            'PHP 8.4 / MariaDB 11.4': 'php-84--mariadb-114',
        }
        for heading, expected in cases.items():
            self.assertEqual(expected, slug(rendered(heading)), heading)

    def test_repeated_headings_are_numbered_and_fences_skipped(self):
        text = '# A\n\n## Notes\n\ntext\n\n## Notes\n\n## Notes-1\n\nSetext\n------\n\n```\n## Not a heading\n```\n' \
               '\n<a id="kept"></a>\n'
        self.assertEqual({'a', 'notes', 'notes-1', 'notes-1-1', 'setext', 'kept'}, anchors_of(text))

    def test_links_skip_code_and_find_every_form(self):
        text = '\n'.join([
            'See [the guide](docs/HOST-GUIDE.md#5-install) and ![alt text](docs/images/village.png "Village").',
            'A link that [wraps',
            'over a line](CONTRIBUTING.md), `[not](a-link.md)` and <a href="NOTICE">NOTICE</a>.',
            '',
            '```',
            '[also not](inside-a-fence.md)',
            '```',
            '',
            '[label]: <SECURITY.md>',
            '<img src="docs/images/phone.png" alt="">',
            '- [x] 30: done, not a link',
        ])
        found = [(kind, target, alt) for _, kind, target, alt in links(text)]
        self.assertEqual([('link', 'docs/HOST-GUIDE.md#5-install', None), ('image', 'docs/images/village.png', 'alt text'),
                          ('link', 'CONTRIBUTING.md', None), ('link', 'NOTICE', None), ('link', '<SECURITY.md>', None),
                          ('image', 'docs/images/phone.png', '')], found)
        self.assertEqual(2, [number for number, _, target, _ in links(text) if target == 'CONTRIBUTING.md'][0])


if __name__ == '__main__':
    unittest.main()
