<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The install, as one service (Phase 6 task 5a; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.5). It was the
 * body of scripts/install.php. The command keeps its arguments, its environment and its output; the web setup page
 * (task 5b) calls the same function, so there is one install path.
 *
 * Callers load lib/dbwrapper_pdo.php, lib/constants.php, lib/datacache.php, lib/settings.php, lib/modules.php,
 * lib/output.php, lib/sanitize.php and lib/translator.php first, as the command does.
 */
require_once __DIR__ . '/fresh_install.php';
require_once __DIR__ . '/../database_config.php';
require_once __DIR__ . '/../../src/Configuration/ConfigFile.php';
require_once __DIR__ . '/../../src/Security/Passwords.php';

/**
 * Install a new game into the empty database the configuration names, writing the configuration file first when asked.
 *
 * With $writeConfig, everything that can refuse runs before the file is written: the target, the values, the
 * administrator, and the database itself (reachable and empty; the install checks it again under its lock). Then the
 * install reads its database settings from the file, whether it existed or was just written.
 *
 * @param string $modules the module choice: recommended or none (resurrection_install_module_plan())
 * @param string $config the configuration file: the one to read, or with $writeConfig the one to write
 * @param bool $allowServed with $writeConfig: the file may be inside $servedRoot
 * @param string|null $login the first administrator's login; null when the caller has none
 * @param string|null $password the first administrator's password; null when the caller has none
 * @param array<string, string> $values with $writeConfig: DB_HOST, DB_USER, DB_PASS, DB_NAME and DB_PREFIX
 * @param string $servedRoot the game's directory
 * @return array{tables: int, modules_installed: int, modules_active: int, administrator: int, state: string}
 */
function resurrection_install_run(string $modules, string $config, bool $writeConfig, bool $allowServed, ?string $login,
        #[\SensitiveParameter] ?string $password, string $email, #[\SensitiveParameter] array $values, string $servedRoot): array {
    $activate = resurrection_install_module_plan($modules);
    if ($login === null || $password === null) { throw new RuntimeException('Administrator credentials must be provided through the environment.'); }
    if ($writeConfig) {
        $target = \Resurrection\Configuration\ConfigFile::target($config, $servedRoot, $allowServed);
        $values = \Resurrection\Configuration\ConfigFile::fromEnvironment($values);
        resurrection_validate_account($login, $email);
        \Resurrection\Security\Passwords::hash($password);
        $GLOBALS['DB_PREFIX'] = $values['DB_PREFIX'];
        resurrection_database_open($values);
        resurrection_refuse_install_state(resurrection_install_state());
        \Resurrection\Configuration\ConfigFile::write($target, $values);
        $config = $target;
    }
    unset($values);
    resurrection_database_open(resurrection_database_config($config));
    return resurrection_fresh_install($login, $password, $email, $activate);
}
