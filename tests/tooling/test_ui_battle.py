"""The battle screen, commentary and shop lists in the Resurrection UI (Phase 5 task 10,
docs/PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md).

Static: runs wherever the suite runs (Baseline integrity too). It checks that

- the stylesheet draws the fighter cards, the action bar, the battle log, the result block, the commentary list, its
  pager and the shops' item links as docs/UI-DESIGN-SPEC.md says;
- every battle form helper puts its button in the battle screen's groups, with Fight the one primary;
- 1.1.2's draft-saving script (the addEvent call) is gone from previewfield() and recorded in CHANGES-FROM-1.1.2.md;
- the spec describes each new component.

The rendered pages (cards with their numbers and bar widths, the log as a list, the result block, the commentary list
and pager, the old skins byte for byte) are checked in tests/unit/BattleCommentaryRenderTest.php, and the shops' links
in tests/unit/FormsRenderTest.php.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
CSS = ROOT / 'assets' / 'ui' / 'resurrection.css'


class BattleScreenStyleTests(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.css = CSS.read_text()

    def rule(self, selector):
        found = re.search(r'(?m)^' + re.escape(selector) + r'\s*\{([^}]*)\}', self.css)
        self.assertIsNotNone(found, selector)
        return found.group(1)

    def test_fighter_cards(self):
        self.assertIn('display: grid;', self.rule('.rz-fighters'))
        self.assertIn('border: 1px solid var(--border);', self.rule('.rz-fighter'))
        self.assertIn('border-color: var(--foe);', self.rule('.rz-fighter-foe'))
        self.assertIn('color: var(--damage);', self.rule('.rz-fighter-foe .rz-fighter-name'))
        self.assertIn('color: var(--text);', self.rule('.rz-fighter-sub i'))

    def test_fight_bars(self):
        track = self.rule('.rz-fighterbar-track')
        self.assertIn('height: var(--bar-height-fight);', track)
        self.assertIn('background: var(--track);', track)
        self.assertIn('background: var(--health);', self.rule('.rz-fighterbar-fill'))
        self.assertIn('background: var(--foe);', self.rule('.rz-fighter-foe .rz-fighterbar-fill'))
        self.assertIn('justify-content: space-between;', self.rule('.rz-fighterbar-text'))
        tokens = (ROOT / 'assets' / 'ui' / 'tokens.css').read_text()
        self.assertIn('--bar-height-fight: 10px;', tokens)

    def test_action_bar(self):
        row = self.rule('.rz-battle-buttons')
        self.assertIn('display: flex;', row)
        self.assertIn('flex-wrap: wrap;', row)
        self.assertIn('text-transform: uppercase;', self.rule('.rz-content .rz-battle-grouphead'))

    def test_battle_log_and_result(self):
        self.assertIn('list-style: none;', self.rule('.rz-battle-rounds'))
        self.assertIn('border-top: 1px solid var(--border);', self.rule('.rz-battle-round'))
        # Jim, task 10 fix: no "Round N" label; each round is its own item, a quiet divider between rounds.
        self.assertNotIn('rz-battle-roundlabel', self.css)
        template = (ROOT / 'templates' / 'resurrection.htm').read_text()
        self.assertIn('<!--!battleround--><li class="rz-battle-round">{lines}</li>\n', template)
        self.assertNotIn("'Round %s'", (ROOT / 'lib' / 'battle_view.php').read_text())
        self.assertIn('box-shadow: inset 3px 0 0 var(--accent);', self.rule('.rz-battle-result-victory'))
        self.assertIn('box-shadow: inset 3px 0 0 var(--damage);', self.rule('.rz-battle-result-defeat'))
        self.assertIn('color: var(--accent);', self.rule('.rz-battle-result-victory .rz-battle-resulthead'))
        self.assertIn('color: var(--damage);', self.rule('.rz-battle-result-defeat .rz-battle-resulthead'))

    def test_commentary_and_pager(self):
        self.assertIn('list-style: none;', self.rule('.rz-comments'))
        self.assertIn('font-family: var(--font-display);', self.rule('.rz-comment .rz-plain-link'))
        pager = self.rule('.rz-pager')
        self.assertIn('display: flex;', pager)
        self.assertIn('flex-wrap: wrap;', pager)
        self.assertIn('display: block;', self.rule('.rz-content label[for="inputinsertcommentary"]'))
        self.assertIn('display: flex;', self.rule('.rz-talkform'))
        # Task 8's phone rule for the old paging links is replaced by the pager's buttons (44px at every width).
        self.assertNotIn('a[href*="refresh=1"]', self.css)

    def test_shop_links(self):
        link = self.rule('.rz-content .rz-shop-link, .rz-content .rz-shop-link:hover')
        for value in ('color: var(--accent);', 'font-weight: var(--weight-ui-bold);', 'background: none;', 'border: 0;',
                      'min-height: 0;'):
            self.assertIn(value, link, value)
        self.assertIn('height: var(--touch-target);', self.rule('.rz-content tr:has(.rz-shop-link) > td'))
        self.assertIn('position: relative;', self.rule('.rz-content tr:has(.rz-shop-link)'))
        self.assertIn('inset: 0;', self.rule('.rz-shop-link::after'))
        output = (ROOT / 'lib' / 'output.php').read_text()
        self.assertIn('"shop-button" => array(" class=\'rz-shop-link\'"', output)
        self.assertNotIn("rz-button-primary'\", \" style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer", output)


class BattleActionTests(unittest.TestCase):

    def test_fight_is_the_one_primary_in_each_route(self):
        routes = {
            'lib/forest_combat.php': "resurrection_forest_form('fight','Fight',$context,'','main',true);",
            'lib/graveyard_combat.php': "resurrection_graveyard_form('fight','Fight',$context,'','main',true);",
            'lib/training_combat.php': "resurrection_training_form('fight','Fight',$context,'','main',true);",
        }
        for name, call in routes.items():
            text = (ROOT / name).read_text()
            self.assertIn(call, text, name)
            self.assertEqual(1, text.count("'main',true"), name)
        self.assertIn("resurrection_forest_form('run','Run',$context,'','main');", (ROOT / 'lib/forest_combat.php').read_text())
        dragon = (ROOT / 'lib/dragon_combat.php').read_text()
        self.assertIn("'main',true", dragon)
        pvp = (ROOT / 'pvp.php').read_text()
        self.assertIn("'main',true", pvp)
        self.assertIn("resurrection_battle_action('special'", (ROOT / 'lib/specialty_combat.php').read_text())

    def test_battle_php_drops_the_divider_only_in_the_new_screen(self):
        battle = (ROOT / 'battle.php').read_text()
        self.assertIn('~ ~ ~ Fight ~ ~ ~', battle, 'old skins keep the divider')
        divider = battle.index('~ ~ ~ Fight ~ ~ ~')
        self.assertIn('if (!$rzbattle)', battle[divider - 200:divider])


class DraftScriptTests(unittest.TestCase):

    def test_the_draft_script_is_removed_and_recorded(self):
        forms = (ROOT / 'lib' / 'forms.php').read_text()
        self.assertNotIn("addEvent('keyup'", forms)
        self.assertNotIn('localStorage', forms)
        changes = (ROOT / 'docs' / 'CHANGES-FROM-1.1.2.md').read_text()
        self.assertIn('addEvent', changes)


class SpecTests(unittest.TestCase):

    def test_the_spec_describes_each_component(self):
        spec = (ROOT / 'docs' / 'UI-DESIGN-SPEC.md').read_text()
        for name in ('**Fighter cards** (Phase 5 task 10', '**Battle action bar** (task 10)', '**Battle log** (task 10)',
                     '**Battle result** (task 10)', '**Commentary** (task 10', '**Commentary pager** (task 10)',
                     '**Buy lists** (task 10'):
            self.assertIn(name, spec, name)
        self.assertNotIn('one entry per combat line', spec)
        self.assertNotIn('"Round %s"', spec)
        self.assertIn('with no label (Jim, task 10\n  fix: no "Round N")', spec)


if __name__ == '__main__':
    unittest.main()
