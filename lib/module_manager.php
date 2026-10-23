<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/installer/bundled_modules.php';

/** The module manager's single-module operations; "mass" applies one of them to the boxes ticked in a list. */
const RESURRECTION_MODULE_OPS = ['install', 'uninstall', 'activate', 'deactivate', 'reinstall'];
/** The buttons each list offers: an installed category, or the uninstalled list. */
const RESURRECTION_MODULE_CATEGORY_BUTTONS = ['activate', 'deactivate', 'reinstall', 'uninstall'];
const RESURRECTION_MODULE_UNINSTALLED_BUTTONS = ['install'];

/** A module name as the lifecycle API accepts it. */
function resurrection_module_name(mixed $module): string {
    if (!is_string($module) || !preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $module)) throw new InvalidArgumentException('Invalid module name.');
    return $module;
}

/** Only the shipped modules can be installed or run from here; archive-only files are never loaded. */
function resurrection_module_bundled(string $module): bool {
    return in_array($module, resurrection_bundled_modules(), true) && is_file('modules/' . $module . '.php');
}

/**
 * A module's install state: null when it is not installed.
 * @return array<string, mixed>|null
 */
function resurrection_module_state(string $module): ?array {
    $rows = db_query('SELECT modulename,active,version,installdate,filemoddate FROM ' . db_prefix('modules') . ' WHERE modulename=?', true, [$module]);
    return $rows[0] ?? null;
}

/**
 * Whether $op may run on the module in this state: an unknown or non-bundled name is a bad request (only an installed
 * module that is not bundled may still be deactivated), and the wrong install state for the operation is a conflict.
 * @param array<string, mixed>|null $state
 */
function resurrection_module_allowed(string $op, string $module, ?array $state): void {
    if (!resurrection_module_bundled($module) && ($op !== 'deactivate' || $state === null)) throw new InvalidArgumentException('Not a bundled module.');
    if (($op === 'install') !== ($state === null)) throw new DomainException('Module state changed.');
}

/**
 * The states a form was shown with.
 * @param array<string, mixed> $states
 */
function resurrection_module_context(string $op, string $target, array $states): string {
    ksort($states);
    return hash('sha256', json_encode([$op, $target, $states], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The modules a mass form lists: an installed category's rows, or the bundled modules not installed.
 * @return array<string, array<string, mixed>|null>
 */
function resurrection_module_list(string $cat): array {
    $states = [];
    if ($cat !== '') {
        foreach (db_query('SELECT modulename,active,version,installdate,filemoddate FROM ' . db_prefix('modules') . ' WHERE category=?', true, [$cat]) as $row) {
            $states[$row['modulename']] = $row;
        }
    } else {
        $installed = array_column(db_query('SELECT modulename FROM ' . db_prefix('modules')), 'modulename');
        foreach (resurrection_bundled_modules() as $module) {
            if (!in_array($module, $installed, true) && is_file('modules/' . $module . '.php')) $states[$module] = null;
        }
    }
    ksort($states);
    return $states;
}

/** The single operation's confirmation page and its form; GET never changes anything. */
function resurrection_module_confirm_url(string $op, string $module, string $cat): string {
    return 'modules.php?op=' . $op . '&module=' . rawurlencode($module) . '&cat=' . rawurlencode($cat);
}

/** One manager request at a time, so a form's state is checked and acted on without another request between. */
function resurrection_module_lock(): void {
    $rows = db_query('SELECT GET_LOCK(?, 10) AS held', true, ['resurrection-module-manager']);
    if ((string)($rows[0]['held'] ?? '') !== '1') throw new RuntimeException('Module manager busy.');
}

function resurrection_module_unlock(): void {
    db_query('SELECT RELEASE_LOCK(?) AS released', true, ['resurrection-module-manager']);
}

function resurrection_module_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid module request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('This module changed. Reload the module manager before acting.'); }
    http_response_code(500); exit('Nothing was changed. Reload the module manager and try again.');
}

/**
 * Only the named query parameters, each a plain string.
 * @param list<string> $allowed
 */
function resurrection_module_query(array $allowed): void {
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowed, true) || !is_string($value)) throw new InvalidArgumentException('Unexpected parameter.');
    }
}

/**
 * A mass request's shape: one button of the list's own, and a list of distinct module names.
 * @return array{0: string, 1: list<string>}
 */
function resurrection_module_mass_request(string $cat): array {
    $buttons = $cat === '' ? RESURRECTION_MODULE_UNINSTALLED_BUTTONS : RESURRECTION_MODULE_CATEGORY_BUTTONS;
    $pressed = array_values(array_intersect(array_keys($_POST), $buttons));
    if (count($pressed) !== 1 || !is_string($_POST[$pressed[0]])) throw new InvalidArgumentException('Choose one operation.');
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', 'module', $pressed[0]]) !== []) throw new InvalidArgumentException('Unexpected field.');
    $posted = $_POST['module'] ?? [];
    if (!is_array($posted) || !array_is_list($posted)) throw new InvalidArgumentException('Invalid module list.');
    $modules = array_map('resurrection_module_name', $posted);
    if (count(array_unique($modules)) !== count($modules)) throw new InvalidArgumentException('Repeated module.');
    return [$pressed[0], $modules];
}

/**
 * Every ticked box must be a module the (unchanged) list showed that the operation may run on; one that is not refuses
 * the whole request before anything changes.
 * @param list<string> $modules
 * @param array<string, array<string, mixed>|null> $shown
 */
function resurrection_module_mass_allowed(string $op, array $modules, array $shown): void {
    foreach ($modules as $module) {
        if (!array_key_exists($module, $shown)) throw new InvalidArgumentException('Module not in this list.');
        resurrection_module_allowed($op, $module, $shown[$module]);
    }
}

/**
 * Run one operation, with 1.1.2's page lines. Returns false only when an uninstall did not complete (the page says
 * what it removed); a mass request stops there. Other failures print 1.1.2's lines and the list continues, as in 1.1.2.
 */
function resurrection_module_run(string $op, string $module): bool {
    output("`2Performing `^%s`2 on `%%s`0`n", translate_inline($op), $module);
    if ($op === 'install') {
        if (install_module($module)) {
            output("`^Module installed.  It is not yet active.`n");
            return true;
        }
        $info = get_module_info($module);
        if (!module_check_requirements($info['requires'] ?? [])) output("`\$Module could not installed -- it did not meet its prerequisites.`n");
        httpset('cat', '');
        return true;
    }
    if ($op === 'uninstall') {
        if (!injectmodule($module, true)) {
            output("Unable to inject module.  Module not uninstalled.`n");
            return true;
        }
        return uninstall_module($module);
    }
    if ($op === 'activate') {
        if (activate_module($module)) return true;
        $info = get_module_info($module);
        if (!module_check_requirements($info['requires'] ?? [])) output("`n`3Module `#%s`3 does not meet its prerequisites.`n", $module);
        return true;
    }
    if ($op === 'deactivate') {
        deactivate_module($module);
        return true;
    }
    install_module($module, true);
    return true;
}
