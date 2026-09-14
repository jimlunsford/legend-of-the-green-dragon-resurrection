<?php
require_once __DIR__.'/player_mutation.php';
require_once __DIR__.'/../src/Http/SettingDescriptor.php';

/**
 * @return array<string,mixed> */
function resurrection_editor_module(string $module): array {
    if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $module) || !injectmodule($module, true)) throw new DomainException('Unknown module.');
    return get_module_info($module);
}

/**
 * @return array<string,string> */
function resurrection_settings_values(string $namespace, bool $lock=false): array {
    $rows = $namespace === 'core'
        ? db_query('SELECT setting,value FROM '.db_prefix('settings').' ORDER BY setting'.($lock?' FOR UPDATE':''),true)
        : db_query('SELECT setting,value FROM '.db_prefix('module_settings').' WHERE modulename=? ORDER BY setting'.($lock?' FOR UPDATE':''),true,[$namespace]);
    $values=[];
    foreach ($rows as $row) $values[$row['setting']]=$row['value'];
    return $values;
}

/**
 * @param array<string,array<string,mixed>> $schema
 * @param array<string,mixed> $values */
function resurrection_editor_context(array $schema, array $values): string {
    $contract=[]; $state=[];
    foreach ($schema as $key=>$descriptor) {
        unset($descriptor['label']);
        if (isset($descriptor['options'])) $descriptor['options']=array_keys($descriptor['options']);
        $contract[$key]=$descriptor;
        $state[$key]=$values[$key] ?? $descriptor['default'];
    }
    ksort($state); ksort($contract);
    return hash('sha256',json_encode([$contract,$state],JSON_THROW_ON_ERROR));
}

/**
 * @param array<string,array<string,mixed>> $schema
 * @param array<string,string> $values */
function resurrection_settings_rules(string $namespace,array $schema,array $values): void {
    // Validate the resulting declared state, including unchanged fields and defaults.
    foreach ($schema as $key=>$descriptor) \Resurrection\Http\SettingDescriptor::value($descriptor,$values[$key] ?? $descriptor['default']);
    $pairs = match($namespace) {
        'cedrikspotions'=>[['minrand','maxrand']], 'findgold'=>[['mingold','maxgold']],
        'sethsong'=>[['mingold','maxgold'],['mingems','maxgems']], 'dag'=>[['bountymin','bountymax']],
        'core'=>[['mininterest','maxinterest'],['multibasemin','multibasemax'],['multislummin','multislummax'],['multithrillmin','multithrillmax'],['multisuimin','multisuimax']], default=>[]
    };
    foreach ($pairs as [$low,$high]) {
        if ((float)($values[$low] ?? $schema[$low]['default'])>(float)($values[$high] ?? $schema[$high]['default'])) throw new DomainException('Reversed range.');
    }
    if ($namespace==='cedrikspotions') {
        foreach (['charmgain','vitalgain','tempgain'] as $key) if ((int)($values[$key] ?? $schema[$key]['default'])<0) throw new DomainException('Negative effect.');
        if (($values['random'] ?? $schema['random']['default'])==='1') {
            $cost=(int)($values['randcost'] ?? $schema['randcost']['default']);
            if ($cost<(int)($values['minrand'] ?? $schema['minrand']['default']) || $cost>(int)($values['maxrand'] ?? $schema['maxrand']['default'])) throw new DomainException('Invalid current random price.');
        }
    }
}

/**
 * @param array<string,array<string,mixed>> $schema */
function resurrection_settings_save(string $namespace,array $schema): void {
    check_su_access(SU_EDIT_CONFIG);
    $before=resurrection_settings_values($namespace);
    $context=resurrection_editor_context($schema,$before);
    resurrection_consume_action('settings-'.$namespace,$context);
    $patch=\Resurrection\Http\SettingDescriptor::patch($schema,$_POST);
    try {
        resurrection_player_mutation(function () use ($namespace,$schema,$context,$patch) {
            global $session;
            check_su_access(SU_EDIT_CONFIG);
            if ($namespace!=='core') {
                if (!db_query('SELECT modulename FROM '.db_prefix('modules').' WHERE modulename=? FOR UPDATE',true,[$namespace])) throw new DomainException('Module removed.');
            }
            $old=resurrection_settings_values($namespace,true);
            if (resurrection_editor_context($schema,$old)!==$context) throw new DomainException('Settings changed.');
            $values=array_replace($old,$patch);
            resurrection_settings_rules($namespace,$schema,$values);
            require_once 'lib/gamelog.php';
            foreach ($patch as $key=>$value) {
                if ($namespace==='core') {
                    if (in_array($key,['villagename','innname'],true) && isset($old[$key]) && $old[$key]!==$value) {
                        db_query('UPDATE '.db_prefix('accounts').' SET location=? WHERE location=?',true,[$value,$old[$key]]);
                        if ($session['user']['location']===$old[$key]) $session['user']['location']=$value;
                        if ($key==='villagename') db_query('UPDATE '.db_prefix('companions').' SET companionlocation=? WHERE companionlocation=?',true,[$value,$old[$key]]);
                    }
                    savesetting($key,$value);
                } else set_module_setting($key,$value,$namespace);
                gamelog('Changed '.$namespace.' setting '.$key,'settings');
                // Shipped callbacks perform DML only. No network or DDL callback is certified here.
                modulehook('changesetting',['module'=>$namespace,'setting'=>$key,'old'=>$old[$key] ?? '', 'new'=>$value],true);
            }
        });
    } catch (Throwable $error) {
        $GLOBALS['settings']=null; $GLOBALS['module_settings']=[];
        invalidatedatacache('game-settings'); invalidatedatacache('modulesettings-'.$namespace);
        throw $error;
    }
    output('Settings saved.');
}

/**
 * @param array<string,array<string,mixed>> $schema
 * @param array<string,mixed> $values */
function resurrection_editor_form(string $url,string $scope,string $context,array $schema,array $values): void {
    $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    rawoutput('<form method="POST" action="'.$escape($url).'">'.resurrection_action_fields($scope,$context));
    foreach ($schema as $key=>$descriptor) {
        $value=$values[$key] ?? $descriptor['default'];
        rawoutput('<p><label>'.$escape($descriptor['label']).' ');
        if (in_array($descriptor['type'],['bool','enum'],true)) {
            $options=$descriptor['type']==='bool' ? ['0'=>'No','1'=>'Yes'] : $descriptor['options'];
            rawoutput('<select name="'.$escape($key).'">');
            foreach ($options as $v=>$label) rawoutput('<option value="'.$escape($v).'"'.((string)$v===(string)$value?' selected':'').'>'.$escape($label).'</option>');
            rawoutput('</select>');
        } else rawoutput('<input name="'.$escape($key).'" value="'.$escape($value).'">');
        rawoutput('</label></p>');
    }
    rawoutput('<button class="button">Save</button></form>');
}

/**
 * @return array{schema:array<string,array<string,mixed>>,object:array<string,mixed>,values:array<string,string>} */
function resurrection_object_editor_state(string $type,string $module,int $id,bool $lock=false): array {
    global $session;
    if ($type!=='mounts' || $id<1 || $id>2147483647) throw new DomainException('Unsupported object.');
    // Drinks object editing remains disabled until its separate caller matrix is closed.
    check_su_access(SU_EDIT_MOUNTS);
    $info=resurrection_editor_module($module);
    $schema=\Resurrection\Http\SettingDescriptor::declare($info['prefs-'.$type] ?? []);
    if (!$schema) throw new DomainException('Undeclared object preferences.');
    $rows=db_query('SELECT * FROM '.db_prefix('mounts').' WHERE mountid=?'.($lock?' FOR UPDATE':''),true,[$id]);
    if (count($rows)!==1) throw new DomainException('Object no longer exists.');
    $values=[];
    foreach (db_query('SELECT setting,value FROM '.db_prefix('module_objprefs').' WHERE objtype=? AND objid=? AND modulename=? ORDER BY setting'.($lock?' FOR UPDATE':''),true,[$type,$id,$module]) as $row) $values[$row['setting']]=$row['value'];
    return ['schema'=>$schema,'object'=>$rows[0],'values'=>$values];
}

/**
 * @param array{schema:array<string,array<string,mixed>>,object:array<string,mixed>,values:array<string,string>} $state */
function resurrection_object_context(array $state): string {
    return hash('sha256',json_encode($state['object'],JSON_THROW_ON_ERROR).resurrection_editor_context($state['schema'],$state['values']));
}

function resurrection_object_editor(string $type,string $module,int $id,bool $save): void {
    $state=resurrection_object_editor_state($type,$module,$id);
    $scope='object-'.$type.'-'.$module.'-'.$id;
    $context=resurrection_object_context($state);
    if ($save) {
        resurrection_consume_action($scope,$context);
        $patch=\Resurrection\Http\SettingDescriptor::patch($state['schema'],$_POST);
        resurrection_player_mutation(function () use ($type,$module,$id,$context,$patch) {
            $locked=resurrection_object_editor_state($type,$module,$id,true);
            if (resurrection_object_context($locked)!==$context) throw new DomainException('Object changed.');
            foreach ($patch as $key=>$value) set_module_objpref($type,$id,$key,$value,$module);
            require_once 'lib/gamelog.php';
            gamelog('Changed '.$module.' preferences for '.$type.' '.$id,'settings');
        });
        output('Preferences saved.');
        $state=resurrection_object_editor_state($type,$module,$id);
    }
    $url='mounts.php?op=save&subop=module&id='.$id.'&module='.rawurlencode($module);
    addnav('',$url);
    resurrection_editor_form($url,$scope,resurrection_object_context($state),$state['schema'],$state['values']);
}
