"""The foe portraits and their parts kit (Phase 5 task 14, docs/PHASE5-TASK14-PORTRAITS-20261105.md).

Batch 4 (Jim, approved 2026-11-05): 313 portraits drawn from the parts kit assets/portraits/creature/kit.json by
scripts/art/build_creature_portraits.py, one per seeded creature (by creatureid) and seeded training master (by
masterid), the Green Dragon's, and one fallback per creature type plus unknown. This checks that

- every portrait is the file assets/portraits/SHA256SUMS lists, and the kit and SHA256SUMS are the ones supplied
  (tests/tooling/test_art.py pins both and checks the files are plain drawings in the art palette, under 16 KB);
- the kit holds exactly the creatures and masters a fresh install seeds, read from
  lib/installer/installer_sqlstatements.php, each with its file, and no two recipes at the same level are the same;
- the fallbacks are exactly the creature types src/Ui/Art.php knows;
- the generator, run on the kit into an empty folder, draws the committed files byte for byte.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too).
"""
import collections
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / 'assets'
KIT = ASSETS / 'portraits' / 'creature' / 'kit.json'
SUMS = ASSETS / 'portraits' / 'SHA256SUMS'
GENERATOR = ROOT / 'scripts' / 'art' / 'build_creature_portraits.py'
INSTALLER = ROOT / 'lib' / 'installer' / 'installer_sqlstatements.php'
ART = ROOT / 'src' / 'Ui' / 'Art.php'

# The seeds as the installer's statements write them: "INSERT INTO creatures (fields) VALUES (id, 'name', level,
# 'weapon', ..." (SQL quoting, '' for '), and "INSERT|REPLACE INTO masters VALUES (id,'name',level,'weapon', ..." (PHP
# quoting, \\' for ').
CREATURE_ROW = re.compile(r'''"INSERT INTO " \. db_prefix\("creatures"\) \. " \$creaturefields(?:111)? VALUES \((\d+), '((?:[^']|'')*)', (\d+), '((?:[^']|'')*)',''')
MASTER_ROW = re.compile(r'''"(?:INSERT|REPLACE) INTO " \. db_prefix\("masters"\) \. " VALUES \((\d+),'((?:[^'\\]|\\.)*)',(\d+),'((?:[^'\\]|\\.)*)',''')


def sums():
    """SHA256SUMS as {path under assets/: checksum}."""
    found = {}
    for line in SUMS.read_text().splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})  \./(portraits/[a-z0-9/-]+\.svg)', line)
        if match is None:
            raise AssertionError('Not a SHA256SUMS line: ' + line)
        found[match.group(2)] = match.group(1)
    return found


def seeds():
    """The seeded creatures and masters: {id: (name, level, weapon)} each. Any statement that renames or removes a
    creature or master after it is seeded fails the test instead, so the list is never read wrong."""
    text = INSTALLER.read_text(encoding='utf-8')
    creatures, masters = {}, {}
    for creatureid, name, level, weapon in CREATURE_ROW.findall(text):
        creatures[creatureid] = (name.replace("''", "'"), int(level), weapon.replace("''", "'"))
    for masterid, name, level, weapon in MASTER_ROW.findall(text):
        row = (re.sub(r"\\(.)", r'\1', name), int(level), re.sub(r"\\(.)", r'\1', weapon))
        if masters.get(masterid, row) != row:
            raise AssertionError('Master %s is seeded twice, differently' % masterid)
        masters[masterid] = row
    other = [line for line in text.splitlines() if re.search(r'db_prefix\("(?:creatures|masters)"\)', line)
             and not CREATURE_ROW.search(line) and not MASTER_ROW.search(line)]
    # What else touches the two tables changes no id, name, level or weapon.
    allowed = ['"1|UPDATE " . db_prefix("creatures") . " SET forest=1",',
               '"1|UPDATE " . db_prefix("creatures") . " SET graveyard=1 where location=1",',
               '"ALTER TABLE " . db_prefix("creatures"). " DROP oldcreatureexp;",',
               '''"UPDATE " . db_prefix("masters") . " SET creaturewin='Learn to adapt your style, and you shall prevail.' WHERE creaturename='Sensei Noetha'",''']
    if sorted(line.strip() for line in other) != sorted(allowed):
        raise AssertionError('Another statement touches the creatures or masters: %r' % other)
    return creatures, masters


def same_name(seeded, drawn):
    """The kit's name for a seed: the same, but where the installer's text holds U+FFFD (1.1.2's file has a broken
    byte in "Un�lith", master 5, as the historical tag does) any one character there."""
    pattern = ''.join('.' if c == '�' else re.escape(c) for c in seeded)
    return re.fullmatch(pattern, drawn) is not None


class PortraitKitTests(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.kit = json.loads(KIT.read_text(encoding='utf-8'))
        cls.sums = sums()

    def path(self, group, key):
        return self.kit['paths'][group].format(key=key)

    def test_every_portrait_matches_sha256sums(self):
        on_disk = sorted(str(p.relative_to(ASSETS)) for folder in ('creature', 'master')
                         for p in (ASSETS / 'portraits' / folder).rglob('*.svg'))
        self.assertEqual(313, len(on_disk))
        self.assertEqual(sorted(self.sums), on_disk)
        for name, expected in self.sums.items():
            self.assertEqual(expected, hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), name)
        # Besides the SVGs, the portrait folders hold only the kit; SHA256SUMS sits beside them.
        other = sorted(str(p.relative_to(ASSETS)) for p in (ASSETS / 'portraits').rglob('*')
                       if p.is_file() and p.suffix != '.svg')
        self.assertEqual(['portraits/SHA256SUMS', 'portraits/creature/kit.json'], other)

    def test_the_kit_holds_exactly_the_seeded_creatures_and_masters(self):
        creatures, masters = seeds()
        self.assertEqual(288, len(creatures))
        self.assertEqual(14, len(masters))
        self.assertEqual(['about', 'paths', 'creature', 'master', 'fallback', 'special'], list(self.kit))
        self.assertEqual(sorted(creatures, key=int), sorted(self.kit['creature'], key=int))
        for creatureid, (name, level, weapon) in creatures.items():
            entry = self.kit['creature'][creatureid]
            self.assertEqual((name, level, weapon), (entry['name'], entry['level'], entry['weapon']), creatureid)
        self.assertEqual(sorted(masters, key=int), sorted(self.kit['master'], key=int))
        for masterid, (name, level, weapon) in masters.items():
            entry = self.kit['master'][masterid]
            self.assertTrue(same_name(name, entry['name']), (masterid, name, entry['name']))
            self.assertEqual((level, weapon), (entry['level'], entry['weapon']), masterid)
        self.assertEqual(['green-dragon'], list(self.kit['special']))
        # Each entry has its file and each file its entry.
        drawn = sorted(self.path(group, key) for group in ('creature', 'master', 'fallback', 'special') for key in self.kit[group])
        self.assertEqual(sorted(self.sums), drawn)

    def test_the_seed_reader_finds_what_it_must(self):
        self.assertTrue(same_name('Un�lith', 'Unélith'))
        self.assertFalse(same_name('Un�lith', 'Unlith'))
        self.assertFalse(same_name('Fie', 'Fie2'))
        row = '''"INSERT INTO " . db_prefix("creatures") . " $creaturefields VALUES (19, 'Someone''s Grandfather', 3, 'Stories that go nowhere', 'x', '', 1)",'''
        self.assertEqual([('19', "Someone''s Grandfather", '3', 'Stories that go nowhere')], CREATURE_ROW.findall(row))
        row = '''"INSERT INTO " . db_prefix("masters") . " VALUES (6,'Adwares',6,'Dwarven Battle Axe','Ach!  Y\\\\' do',NULL,NULL,66,12,12)",'''
        self.assertEqual([('6', 'Adwares', '6', 'Dwarven Battle Axe')], MASTER_ROW.findall(row))

    def test_no_two_recipes_at_the_same_level_are_the_same(self):
        seen = collections.defaultdict(list)
        for group in ('creature', 'master'):
            for key, entry in self.kit[group].items():
                seen[(entry['level'], json.dumps(entry['recipe'], sort_keys=True))].append(group + ' ' + key)
        self.assertEqual([], [names for names in seen.values() if len(names) > 1])
        # Two files are the same drawing only for one creature seeded at two levels: 1.1.2 seeds "Loneliness"
        # (weapon "Silence") as creatures 134 (level 17) and 135 (level 18), and both get its one portrait.
        by_sum = collections.defaultdict(list)
        for name, checksum in self.sums.items():
            by_sum[checksum].append(name)
        same = [sorted(names) for names in by_sum.values() if len(names) > 1]
        self.assertEqual([['portraits/creature/134.svg', 'portraits/creature/135.svg']], same)
        first, second = self.kit['creature']['134'], self.kit['creature']['135']
        self.assertEqual((first['name'], first['weapon'], first['recipe']), (second['name'], second['weapon'], second['recipe']))
        self.assertNotEqual(first['level'], second['level'])

    def test_the_fallbacks_are_the_creature_types_the_code_knows(self):
        art = ART.read_text()
        block = re.search(r'public const array CREATURE_TYPES = \[(.*?)\];', art, re.S)
        self.assertIsNotNone(block)
        types = re.findall(r"'([a-z]+)'", block.group(1))
        self.assertIn('self::UNKNOWN', block.group(1))
        types.append('unknown')
        self.assertEqual(10, len(types))
        self.assertEqual(sorted(types), sorted(self.kit['fallback']))
        self.assertEqual(sorted(types), sorted(p.stem for p in (ASSETS / 'portraits' / 'creature' / 'fallback').glob('*.svg')))
        self.assertIn("public const string GREEN_DRAGON = 'green-dragon';", art)
        # Every recipe's type has its fallback.
        for group in ('creature', 'master', 'special'):
            for key, entry in self.kit[group].items():
                self.assertIn(entry['recipe']['type'], types, group + ' ' + key)

    def test_the_generator_rebuilds_the_committed_files(self):
        with tempfile.TemporaryDirectory() as out:
            done = subprocess.run([sys.executable, '-I', str(GENERATOR), str(KIT), out], capture_output=True, text=True, timeout=120)
            self.assertEqual(0, done.returncode, done.stderr)
            self.assertEqual('', done.stdout + done.stderr)
            built = {str(p.relative_to(out)): p.read_bytes() for p in Path(out).rglob('*') if p.is_file()}
        self.assertEqual(sorted(self.sums), sorted(built))
        for name, data in built.items():
            self.assertEqual((ASSETS / name).read_bytes(), data, name)
            self.assertEqual(self.sums[name], hashlib.sha256(data).hexdigest(), name)

    def test_the_generator_is_core_code_without_an_agpl_label(self):
        text = GENERATOR.read_text()
        self.assertNotIn('AGPL', text)
        self.assertNotIn('Affero', text)
        self.assertTrue(text.startswith('#!/usr/bin/env python3\n'))


if __name__ == '__main__':
    unittest.main()
