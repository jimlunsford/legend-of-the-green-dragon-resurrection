<?php
require_once 'common.php';
require_once 'lib/typed_editor.php';
require_once 'lib/configuration_descriptors.php';
check_su_access(SU_EDIT_CONFIG);
try {
    $op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'save', 'modulesettings'], '');
    $namespace = $op === 'modulesettings' ? \Resurrection\Http\Input::string($_GET, 'module') : 'core';
    if ($op==='modulesettings' && $namespace==='core') throw new InvalidArgumentException();
    $layout = $namespace === 'core' ? resurrection_core_layout() : resurrection_editor_module($namespace)['settings'] ?? [];
    $schema = \Resurrection\Http\SettingDescriptor::declare($layout);
    $save = $op === 'save' || ($op === 'modulesettings' && isset($_GET['save']));
    if ($save) resurrection_settings_save($namespace, $schema);
    if ($save && $namespace==='core') $schema=\Resurrection\Http\SettingDescriptor::declare(resurrection_core_layout());
    $values = resurrection_settings_values($namespace);
    page_header('Game Settings');
    require_once 'lib/superusernav.php';
    superusernav();
    addnav('Standard settings', 'configuration.php');
    module_editor_navs('settings', 'configuration.php?op=modulesettings&module=');
    $url = $namespace === 'core' ? 'configuration.php?op=save' : 'configuration.php?op=modulesettings&module='.rawurlencode($namespace).'&save=1';
    addnav('', $url);
    resurrection_editor_form($url, 'settings-'.$namespace, resurrection_editor_context($schema, $values), $schema, $values);
    page_footer();
} catch (InvalidArgumentException|DomainException $error) {
    http_response_code(400); exit('Invalid or stale settings.');
} catch (Throwable $error) { http_response_code(500); exit('Settings were not saved.'); }
