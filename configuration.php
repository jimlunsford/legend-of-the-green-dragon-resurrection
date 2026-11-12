<?php
define('RESURRECTION_ENTRY', true);
require_once 'common.php';
require_once 'lib/typed_editor.php';
require_once 'lib/configuration_descriptors.php';
check_su_access(SU_EDIT_CONFIG);
try {
    $op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'save', 'modulesettings', 'webcron', 'webcron_on', 'webcron_off', 'webcron_new'], '');
    if (str_starts_with($op, 'webcron')) {
        // Web maintenance (Phase 6 task 5a): every change is a POST with the CSRF token. Turning it on and making a new
        // address both make a new secret, which this page shows once.
        require_once 'lib/webcron.php';
        require_once 'lib/gamelog.php';
        $secret = null;
        if ($op !== 'webcron') {
            resurrection_require_post();
            if ($op === 'webcron_off') { resurrection_webcron_disable(); gamelog('Turned off web maintenance', 'settings'); }
            else { $secret = resurrection_webcron_enable(); gamelog($op === 'webcron_on' ? 'Turned on web maintenance' : 'Made a new web maintenance address', 'settings'); }
        }
        header('Cache-Control: no-store');
        page_header('Game Settings');
        require_once 'lib/superusernav.php';
        superusernav();
        addnav('Standard settings', 'configuration.php');
        addnav('Web maintenance', 'configuration.php?op=webcron');
        module_editor_navs('settings', 'configuration.php?op=modulesettings&module=');
        resurrection_webcron_render($secret);
        unset($secret);
        page_footer();
    }
    $namespace = $op === 'modulesettings' ? \Resurrection\Http\Input::string($_GET, 'module') : 'core';
    if ($op==='modulesettings' && $namespace==='core') throw new InvalidArgumentException();
    $layout = $namespace === 'core' ? resurrection_core_layout() : resurrection_editor_module($namespace)['settings'] ?? [];
    $schema = \Resurrection\Http\SettingDescriptor::declare($layout);
    $save = $op === 'save' || ($op === 'modulesettings' && isset($_GET['save']));
    // A bounded setting outside its range (Phase 6 task 8) is refused on the page itself: nothing is saved, the status
    // is 400, and one line above the form says why. Every other refusal stays the plain 400 below.
    $refused = null;
    if ($save) {
        try { resurrection_settings_save($namespace, $schema); }
        catch (InvalidArgumentException $error) {
            for ($cause = $error; $cause !== null && $refused === null; $cause = $cause->getPrevious()) {
                if ($cause->getCode() === \Resurrection\Http\SettingDescriptor::OUT_OF_BOUNDS) $refused = $cause->getMessage();
            }
            if ($refused === null) throw $error;
        }
    }
    if ($save && $namespace==='core') $schema=\Resurrection\Http\SettingDescriptor::declare(resurrection_core_layout());
    $values = resurrection_settings_values($namespace);
    if ($refused !== null) http_response_code(400);
    page_header('Game Settings');
    require_once 'lib/superusernav.php';
    superusernav();
    addnav('Standard settings', 'configuration.php');
    addnav('Web maintenance', 'configuration.php?op=webcron');
    module_editor_navs('settings', 'configuration.php?op=modulesettings&module=');
    $url = $namespace === 'core' ? 'configuration.php?op=save' : 'configuration.php?op=modulesettings&module='.rawurlencode($namespace).'&save=1';
    addnav('', $url);
    if ($refused !== null) rawoutput('<p class="rz-setting-refused" role="alert">'.htmlspecialchars($refused, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</p>');
    resurrection_editor_form($url, 'settings-'.$namespace, resurrection_editor_context($schema, $values), $schema, $values, $layout);
    page_footer();
} catch (InvalidArgumentException|DomainException $error) {
    http_response_code(400); exit('Invalid or stale settings.');
} catch (Throwable $error) { http_response_code(500); exit('Settings were not saved.'); }
