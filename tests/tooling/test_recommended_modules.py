"""The modules a fresh install activates are 1.1.2's recommended modules, limited to the bundled ones.

1.1.2's installer.php lists `$recommended_modules`, and its module stage (lib/installer/installer_stage_8.php) selects
that list for activation on a fresh install. Resurrection ships 24 bundled modules (resurrection_bundled_modules()).
resurrection_recommended_modules() must be exactly the bundled modules on 1.1.2's list, in the bundled order, read from
the historical tag, not copied by hand.
"""
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
TAG = 'historical-source-1.1.2'
BUNDLED = ROOT / 'lib' / 'installer' / 'bundled_modules.php'


def historical(path):
    return subprocess.run(['git', '-C', str(ROOT), 'show', f'{TAG}:{path}'], capture_output=True, check=True, text=True).stdout


def php_list(source, function):
    """The single-quoted names a PHP function returns as a list literal."""
    body = re.search(r'function ' + function + r'\(\): array \{\s*return \[(.*?)\];', source, re.S)
    if body is None:
        raise AssertionError(function + ' not found')
    return re.findall(r"'([a-z_]+)'", body.group(1))


class RecommendedModuleTests(unittest.TestCase):
    def test_recommended_modules_are_the_bundled_modules_on_the_112_list(self):
        installer = historical('installer.php')
        block = re.search(r'\$recommended_modules = array\((.*?)\);', installer, re.S).group(1)
        # A commented line is not on the list (1.1.2 left "cities" out that way).
        listed = [name for line in block.splitlines() if not line.strip().startswith('//')
                  for name in re.findall(r'"([a-z_]+)"', line)]
        self.assertEqual(59, len(listed))
        self.assertNotIn('cities', listed)
        source = BUNDLED.read_text()
        bundled = php_list(source, 'resurrection_bundled_modules')
        recommended = php_list(source, 'resurrection_recommended_modules')
        self.assertEqual(24, len(bundled))
        self.assertEqual([name for name in bundled if name in listed], recommended)
        self.assertEqual(['game_fivesix'], [name for name in bundled if name not in listed])

    def test_112_selected_the_list_on_a_fresh_install(self):
        stage = historical('lib/installer/installer_stage_8.php')
        self.assertIn("thisItem = document.getElementById('activate-$val');", stage)
        # Called on page load unless the install is an upgrade.
        self.assertRegex(stage, r"if \(!\$session\['dbinfo'\]\['upgrade'\]\)\{\s*rawoutput\(\"\s*chooseRecommendedModules\(\);\"\);")


if __name__ == '__main__':
    unittest.main()
