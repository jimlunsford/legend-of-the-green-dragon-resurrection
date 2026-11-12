"""The credit and the host's duties are stated within the licenses (Phase 6 task 11, S9 closed by Jim's decision).

Section 4(d) of CC BY-NC-SA 2.0 is the credit clause: for a Derivative Work, "at a minimum such credit will appear where
any other comparable authorship credit appears and in a manner at least as prominent as such other comparable
authorship credit", with the URI the Licensor specifies "to the extent reasonably practicable". Sections 4(a) and 4(b)
say that you "may not offer or impose any terms" that "alter or restrict the terms of this License". So the shipped
documents state the Resurrection credit in 4(d)'s terms and add no term, condition or penalty of their own. These
checks keep them so:

- no shipped document (NOTICE, the top-level guides, assets/LICENSE and assets/README.md, and every undated page in
  docs/) uses wording that adds a term, such as "cannot be removed" or "the license terms require", where it speaks of
  the credit, the license or the copyright;
- every paragraph of a shipped document that states the credit names section 4(d) or uses its words, and NOTICE,
  README.md, the host guide and the release notes name section 4(d);
- NOTICE quotes section 4(d), the start of section 4(c) (the noncommercial clause) and LICENSE.txt's public hosting
  sentence word for word, and describes the template check as the software's behavior, not a license term.

Dated pages (`*-YYYYMMDD*.md`) are evidence records and are not checked: they quote earlier wording as it was, and the
legal review packet (`docs/LEGAL-REVIEW-PACKET-20261107.md`, not sent) is one of them. `CHANGES-FROM-1.1.2.md` and
`ROADMAP.md` are records too; they are checked for wording that adds a term but may name the credit without 4(d).
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
CREDIT = 'A Phoenix 2:33 LLC Project by Jim Lunsford'

# Wording that states a term, condition or penalty beyond the licenses. Matched without regard to case or line breaks.
FORBIDDEN = [
    r'cannot\s+be\s+removed', r'must\s+never\s+be\s+removed', r'never\s+be\s+removed', r'non-?\s*removable',
    r'must\s+not\s+be\s+removed', r'may\s+not\s+be\s+removed', r'(?:links|credit|line)\s+cannot\s+be',
    r'terms\s+that\s+these\s+links', r'do\s+not\s+remove,?\s+hide\s+or\s+shrink', r'license\s+terms\s+require',
    r'already\s+requires,?\s+not\s+an\s+added\s+term', r'must\s+travel\s+with',
]
# A paragraph is about the credit or the license when it names one of these.
TOPIC = re.compile(r'credit|attribution|licen[cs]e|copyright|Phoenix 2:33|NOTICE', re.I)
# Records: may name the credit without restating 4(d).
RECORDS = {'docs/CHANGES-FROM-1.1.2.md', 'ROADMAP.md'}
# Must state the credit in 4(d)'s terms.
NAMES_4D = ['NOTICE', 'README.md', 'docs/HOST-GUIDE.md', 'docs/RELEASE-NOTES-1.0.0-rc.1.md']

SECTION_4C_START = ('You may not exercise any of the rights granted to You in Section 3 above in any manner that is '
                    'primarily intended for or directed toward commercial advantage or private monetary compensation.')
SECTION_4D = (
    'If you distribute, publicly display, publicly perform, or publicly digitally perform the Work or any Derivative '
    'Works or Collective Works, You must keep intact all copyright notices for the Work and give the Original Author '
    'credit reasonable to the medium or means You are utilizing by conveying the name (or pseudonym if applicable) of '
    'the Original Author if supplied; the title of the Work if supplied; to the extent reasonably practicable, the '
    'Uniform Resource Identifier, if any, that Licensor specifies to be associated with the Work, unless such URI does '
    'not refer to the copyright notice or licensing information for the Work; and in the case of a Derivative Work, a '
    'credit identifying the use of the Work in the Derivative Work (e.g., "French translation of the Work by Original '
    'Author," or "Screenplay based on original Work by Original Author"). Such credit may be implemented in any '
    'reasonable manner; provided, however, that in the case of a Derivative Work or Collective Work, at a minimum such '
    'credit will appear where any other comparable authorship credit appears and in a manner at least as prominent as '
    'such other comparable authorship credit.')


def flat(text):
    return re.sub(r'\s+', ' ', text).strip()


def shipped_documents():
    names = ['NOTICE', 'assets/LICENSE', 'assets/README.md'] + sorted(p.name for p in ROOT.glob('*.md'))
    names += sorted('docs/' + p.name for p in (ROOT / 'docs').glob('*.md') if not re.search(r'-\d{8}', p.name))
    return [n for n in names if (ROOT / n).is_file()]


def paragraphs(text):
    """Blank-line separated blocks; a list item or table row counts as a block of its own."""
    blocks = []
    for block in re.split(r'\n\s*\n', text):
        current = []
        for line in block.split('\n'):
            if re.match(r'\s*(?:[-*]|\d+\.|\|)\s', line) and current:
                blocks.append('\n'.join(current))
                current = []
            current.append(line)
        if current:
            blocks.append('\n'.join(current))
    return blocks


class CreditWordingTest(unittest.TestCase):
    maxDiff = None

    def test_the_scope_holds_the_documents_that_state_the_credit(self):
        documents = shipped_documents()
        for name in NAMES_4D + ['CLAUDE.md', 'CONTRIBUTING.md', 'docs/LICENSING-AND-PROVENANCE.md',
                                'docs/SECURITY-STATUS.md', 'docs/MODULE-AUTHOR-GUIDE.md', 'assets/LICENSE']:
            self.assertIn(name, documents)
        self.assertNotIn('docs/LEGAL-REVIEW-PACKET-20261107.md', documents)

    def test_no_shipped_document_adds_a_term(self):
        found = []
        for name in shipped_documents():
            for block in paragraphs((ROOT / name).read_text(encoding='utf-8')):
                if not TOPIC.search(block):
                    continue
                for pattern in FORBIDDEN:
                    match = re.search(pattern, flat(block), re.I)
                    if match:
                        found.append(f'{name}: "{match.group(0)}" in: {flat(block)[:160]}')
        self.assertEqual([], found)

    def test_each_statement_of_the_credit_names_section_4d(self):
        missing = []
        for name in shipped_documents():
            text = (ROOT / name).read_text(encoding='utf-8')
            if name in NAMES_4D and not re.search(r'section 4\(d\)', flat(text), re.I):
                missing.append(f'{name}: never names section 4(d)')
            if name in RECORDS:
                continue
            blocks = paragraphs(text)
            for i, block in enumerate(blocks):
                if CREDIT not in flat(block):
                    continue
                near = flat(' '.join(blocks[max(0, i - 1):i + 2]))
                if not re.search(r'4\(d\)|comparable authorship credit', near):
                    missing.append(f'{name}: {flat(block)[:160]}')
        self.assertEqual([], missing)

    def test_no_document_calls_section_4c_the_credit_clause(self):
        found = []
        for name in shipped_documents():
            text = flat((ROOT / name).read_text(encoding='utf-8'))
            for match in re.finditer(r'4\(c\)[^.;]{0,60}?\b(?:credit|attribution)', text, re.I):
                found.append(f'{name}: {match.group(0)}')
        self.assertEqual([], found)

    def test_notice_quotes_the_license_and_adds_no_term(self):
        notice = flat((ROOT / 'NOTICE').read_text(encoding='utf-8'))
        self.assertIn(flat(SECTION_4D), notice)
        self.assertIn(SECTION_4C_START, notice)
        license_text = flat((ROOT / 'LICENSE.txt').read_text(encoding='latin-1'))
        sentence = re.search(r"if you put this game on a web server.*?remove the code entirely from the machine\.",
                             license_text)
        self.assertIsNotNone(sentence)
        self.assertIn(sentence.group(0), notice)
        self.assertIn(CREDIT, notice)
        for url in ('https://phoenix233.com', 'https://jimlunsford.com'):
            self.assertIn(url, notice)
        self.assertIn('to the extent reasonably practicable', notice)
        self.assertIn('adds no term of its own', notice)
        self.assertIn('How the software behaves (not a license term)', notice)
        self.assertIn('A host may change their copy of the code under the license.', notice)


if __name__ == '__main__':
    unittest.main()
