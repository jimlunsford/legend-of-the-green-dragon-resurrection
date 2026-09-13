<?php
/** Fixed shipped registry. Archive-only modules are never discovered or imported. */
function resurrection_bundled_modules(): array {
    return ['cedrikspotions','crazyaudrey','dag','darkhorse','drinks','fairy','findgem','findgold',
        'foilwench','game_dice','game_fivesix','game_stones','glowingstream','goldmine','lovers','outhouse',
        'racedwarf','raceelf','racehuman','racetroll','sethsong','specialtydarkarts','specialtymysticpower','specialtythiefskills'];
}

/** Called only inside the locked, empty-database fresh-install path. */
function resurrection_install_bundled_modules(): void {
    global $session, $mostrecentmodule;
    $previous = $session ?? null;
    $session = ['loggedin' => false, 'user' => ['acctid' => 0, 'name' => 'Installer', 'superuser' => 0, 'loggedin' => false, 'prefs' => []]];
    try {
        foreach (resurrection_bundled_modules() as $module) {
            $GLOBALS['fresh_install_phase'] = 'bundled-module:' . $module;
            require_once 'modules/' . $module . '.php';
            $info = ($module . '_getmoduleinfo')();
            if (!empty($info['requires'])) { throw new RuntimeException('Bundled dependency plan requires review.'); }
            $mostrecentmodule = $module;
            db_query('INSERT INTO ' . db_prefix('modules') . ' (modulename,formalname,moduleauthor,active,filename,installdate,installedby,category,infokeys,version,download,description,filemoddate) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', true,
                [$module, $info['name'], $info['author'], 0, $module . '.php', date('Y-m-d H:i:s'), 'Installer',
                 $info['category'], '|' . implode('|', array_keys($info)) . '|', $info['version'], $info['download'] ?? '', $info['description'] ?? '', date('Y-m-d H:i:s', filemtime('modules/' . $module . '.php'))]);
            foreach ($info['settings'] ?? [] as $key => $descriptor) {
                if (!is_string($key)) { continue; }
                $parts = explode('|', is_array($descriptor) ? $descriptor[0] : $descriptor, 2);
                if (isset($parts[1])) { set_module_setting($key, $parts[1], $module); }
            }
            if (($module . '_install')() === false) { throw new RuntimeException('Bundled module installation failed.'); }
        }
    } finally { $session = $previous; }
}
