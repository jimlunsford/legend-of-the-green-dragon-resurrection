<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
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
    foreach ($schema as $key=>$descriptor) {
        try {
            $value=$values[$key] ?? $descriptor['default'];
            // Historical core false defaults were stored as empty strings. POST remains strict 0/1.
            if ($namespace==='core' && $descriptor['type']==='bool' && $value==='') $value='0';
            \Resurrection\Http\SettingDescriptor::value($descriptor,$value);
        }
        catch (InvalidArgumentException $error) { throw new InvalidArgumentException('Invalid declared field: '.$key, $error->getCode(), $error); }
    }
    $pairs = match($namespace) {
        'cedrikspotions'=>[['minrand','maxrand']], 'findgold'=>[['mingold','maxgold']],
        'sethsong'=>[['mingold','maxgold'],['mingems','maxgems']], 'dag'=>[['bountymin','bountymax']],
        'core'=>[['mininterest','maxinterest'],['multibasemin','multibasemax'],['multislummin','multislummax'],['multithrillmin','multithrillmax'],['multisuimin','multisuimax']], default=>[]
    };
    foreach ($pairs as [$low,$high]) {
        if ((float)($values[$low] ?? $schema[$low]['default'])>(float)($values[$high] ?? $schema[$high]['default'])) throw new DomainException('Reversed range.');
    }
    if ($namespace==='core') {
        $days=(int)($values['daysperday'] ?? $schema['daysperday']['default']);
        if ((int)($values['gameoffsetseconds'] ?? $schema['gameoffsetseconds']['default'])>86400/$days) throw new DomainException('Invalid day offset.');
        // The View PHP Source link accepts only an http or https URL (lib/source_link.php).
        require_once 'lib/source_link.php';
        if (resurrection_valid_source_url((string)($values['sourceurl'] ?? $schema['sourceurl']['default']))===null) throw new InvalidArgumentException('Invalid source URL.');
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
                // As 1.1.2's configuration.php: a setting is logged, with its old and new value, and the changesetting
                // hook told, only when the submitted value differs from the stored one (or none is stored yet).
                $changed=!array_key_exists($key,$old) || (string)$old[$key]!=(string)$value;
                if ($namespace==='core') {
                    if (in_array($key,['villagename','innname'],true) && isset($old[$key]) && $old[$key]!==$value) {
                        db_query('UPDATE '.db_prefix('accounts').' SET location=? WHERE location=?',true,[$value,$old[$key]]);
                        if ($session['user']['location']===$old[$key]) $session['user']['location']=$value;
                        if ($key==='villagename') db_query('UPDATE '.db_prefix('companions').' SET companionlocation=? WHERE companionlocation=?',true,[$value,$old[$key]]);
                    }
                    savesetting($key,$value);
                } else set_module_setting($key,$value,$namespace);
                if (!$changed) continue;
                $label=$namespace==='core' ? 'core' : 'module(`5'.$namespace.'`@)';
                gamelog('`@Changed '.$label.' setting `^'.$key.'`@ from `#'.(string)($old[$key] ?? '').'`@ to `&'.(string)$value.'`0','settings');
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
 * The settings in the sections their layout declares ("Game Setup,title" and so on), in order: section title => keys.
 * Settings before the first title go in a section of their own.
 *
 * @param array<int|string,mixed> $layout
 * @param array<string,array<string,mixed>> $schema
 * @return list<array{title:string,keys:list<string>}>
 */
function resurrection_editor_sections(array $layout, array $schema): array {
    $sections=[]; $current=null; $locked=resurrection_editor_locked($layout);
    foreach ($layout as $key=>$declaration) {
        if (is_array($declaration)) $declaration=$declaration[0] ?? '';
        if (!is_string($key)) {
            $parts=array_map('trim', explode(',', (string)$declaration));
            if (($parts[1] ?? '')==='title') { $sections[]=['title'=>$parts[0],'keys'=>[]]; $current=count($sections)-1; }
            continue;
        }
        if (!isset($schema[$key]) && !isset($locked[$key])) continue;
        if ($current===null) { $sections[]=['title'=>'Settings','keys'=>[]]; $current=count($sections)-1; }
        $sections[$current]['keys'][]=$key;
    }
    return array_values(array_filter($sections, static fn($section)=>$section['keys']!==[]));
}

/**
 * @param array<string,array<string,mixed>> $schema
 * @param array<string,mixed> $values
 * @param array<int|string,mixed> $layout the declarations the schema came from: their sections become tabs (Phase 5
 *   task 11), as showform() drew them in 1.1.2. (The one list was kept for the 1.1.2 skins until Phase 5 task 15.) */
function resurrection_editor_form(string $url,string $scope,string $context,array $schema,array $values,array $layout=[]): void {
    $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    rawoutput('<form method="POST" action="'.$escape($url).'">'.resurrection_action_fields($scope,$context));
    $sections=resurrection_editor_sections($layout, $schema);
    if (count($sections)>1) {
        // showform()'s tabs (Phase 5 task 9) without the index field: the editor takes only the settings it declares.
        require_once __DIR__.'/showform.php';
        $titles=[];
        foreach ($sections as $n=>$section) $titles[$n+1]=translate_inline($section['title']);
        rawoutput(str_replace(" data-rz-tabs=", " data-rz-tabs-noindex data-rz-tabs=", showform_tabs('settings', $titles, 1)));
        foreach ($sections as $n=>$section) {
            $x=$n+1;
            rawoutput("<div id='showFormTable$x' class='rz-form-section' role='tabpanel' aria-labelledby='showFormButton$x'>"
                ."<h2 class='rz-form-section-title'>".resurrection_plain_text($titles[$x])."</h2>");
            resurrection_editor_fields($schema, $values, $section['keys'], resurrection_editor_locked($layout));
            rawoutput('</div>');
        }
        rawoutput('<button class="button">Save</button></form>'.resurrection_form_script());
        return;
    }
    resurrection_editor_fields($schema, $values, $sections[0]['keys'] ?? array_keys($schema), resurrection_editor_locked($layout));
    rawoutput('<button class="button">Save</button></form>');
}

/**
 * The layout's locked settings (Phase 6 task 7; lib/settings.php, resurrection_locked_settings()): key => label. Only a
 * setting the game holds at one value may be declared locked.
 *
 * @param array<int|string,mixed> $layout
 * @return array<string,string> */
function resurrection_editor_locked(array $layout): array {
    $locked=[]; $held=resurrection_locked_settings();
    foreach ($layout as $key=>$declaration) {
        if (is_array($declaration)) $declaration=$declaration[0] ?? '';
        $parts=array_map('trim', explode(',', (string)$declaration));
        if (!is_string($key) || ($parts[1] ?? '')!=='locked') continue;
        if (!array_key_exists($key, $held)) throw new DomainException('A locked setting the game does not hold.');
        $locked[$key]=$parts[0];
    }
    return $locked;
}

/** Why a locked setting is locked, in one line for Game Settings. */
function resurrection_editor_locked_reason(string $key): string {
    return match ($key) {
        'requirevalidemail' => 'Locked off. Turn on account mail first (Game Settings, Account mail): a sign-up can confirm its address only while the game can send mail.',
        default => 'Locked.',
    };
}

/**
 * The host guide section a locked setting's note links, online at this version's tag (Phase 6 task 16, R2;
 * lib/source_link.php), or null: [path, label, section].
 * @return array{0:string,1:string,2:string}|null */
function resurrection_editor_locked_doc(string $key): ?array {
    return match ($key) {
        'requirevalidemail' => ['docs/HOST-GUIDE.md', '"Mail" in the host guide', '11-mail'],
        default => null,
    };
}

/**
 * A bounded setting's range and why, in one line for Game Settings (Phase 6 task 8; lib/settings.php).
 * @param array<string,mixed> $descriptor */
function resurrection_editor_bounds_note(string $key, array $descriptor): string {
    $range='From '.(int)$descriptor['min'].' to '.(int)$descriptor['max'];
    return match ($key) {
        'LOGINTIMEOUT' => $range.' seconds (5 minutes to 1 day). Below that, players are signed out almost at once; above it, a closed browser stays signed in for days. A stored value outside the range is used as the nearest end of it.',
        'account_mail_hourly' => $range.' mails an hour. Many hosts cap mail at 100 to 500 an hour; past this limit account mail waits for the next hour. A stored value outside the range is used as the nearest end of it.',
        'signin_fail_pair' => $range.' failures (the default is 5). Below that, a few typos lock a player out; above it, one guesser gets many tries. A stored value outside the range is used as the nearest end of it.',
        'signin_fail_address' => $range.' failures (the default is 20). Below that, players who share an address lock each other out; above it, one address can guess at many names. A stored value outside the range is used as the nearest end of it.',
        'signin_fail_account' => $range.' failures (the default is 50). Kept high on purpose: below 20, anyone could keep a player out by failing on purpose. A stored value outside the range is used as the nearest end of it.',
        default => $range.'.',
    };
}

/**
 * Each setting's row, in the order given: its label around its control. A locked setting shows the value the game holds
 * it at in a disabled control with no name, so a browser never sends it, and the line saying why.
 *
 * @param array<string,array<string,mixed>> $schema
 * @param array<string,mixed> $values
 * @param list<string>|null $keys the settings to show, in order (null: every one in the schema)
 * @param array<string,string> $locked resurrection_editor_locked() */
function resurrection_editor_fields(array $schema,array $values,?array $keys=null,array $locked=[]): void {
    $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    foreach ($keys ?? array_keys($schema) as $key) {
        if (isset($locked[$key])) {
            $held=resurrection_locked_settings()[$key];
            $id='rz-locked-'.$key;
            $doc='';
            if (($link=resurrection_editor_locked_doc($key))!==null) {
                require_once 'lib/source_link.php';
                $doc=' See '.resurrection_doc_link(...$link).'.';
            }
            rawoutput('<p class="rz-setting-locked"><label>'.$escape($locked[$key]).' <select disabled aria-describedby="'.$escape($id).'">'
                .'<option selected>'.($held==='1' ? 'Yes' : 'No').'</option></select></label>'
                .'<span class="rz-setting-locked-note" id="'.$escape($id).'">'.$escape(resurrection_editor_locked_reason($key)).$doc.'</span></p>');
            continue;
        }
        if (!isset($schema[$key])) continue;
        $descriptor=$schema[$key];
        $value=$values[$key] ?? $descriptor['default'];
        if (!empty($descriptor['bounded'])) {
            // Phase 6 task 8: a bounded whole number shows its range and why, and the browser keeps to it too.
            $id='rz-bounds-'.$key;
            rawoutput('<p class="rz-setting-bounded"><label>'.$escape($descriptor['label']).' <input name="'.$escape($key).'" value="'.$escape($value).'"'
                .' type="number" inputmode="numeric" step="1" min="'.(int)$descriptor['min'].'" max="'.(int)$descriptor['max'].'" aria-describedby="'.$escape($id).'"></label>'
                .'<span class="rz-setting-bounds" id="'.$escape($id).'">'.$escape(resurrection_editor_bounds_note($key,$descriptor)).'</span></p>');
            continue;
        }
        rawoutput('<p><label>'.$escape($descriptor['label']).' ');
        if (in_array($descriptor['type'],['bool','enum'],true)) {
            $options=$descriptor['type']==='bool' ? ['0'=>'No','1'=>'Yes'] : $descriptor['options'];
            rawoutput('<select name="'.$escape($key).'">');
            foreach ($options as $v=>$label) rawoutput('<option value="'.$escape($v).'"'.((string)$v===(string)$value?' selected':'').'>'.$escape($label).'</option>');
            rawoutput('</select>');
        } else rawoutput('<input name="'.$escape($key).'" value="'.$escape($value).'">');
        rawoutput('</label></p>');
    }
}

/**
 * The object types whose module preferences have an editor: the table's ID column, the editor's access flag and page.
 * @return array<string,array{id:string,access:int,url:string}> */
function resurrection_object_types(): array {
    return [
        'mounts'=>['id'=>'mountid','access'=>SU_EDIT_MOUNTS,'url'=>'mounts.php?op=save&subop=module&id='],
        'creatures'=>['id'=>'creatureid','access'=>SU_EDIT_CREATURES,'url'=>'creatures.php?op=save&subop=module&creatureid='],
        'companions'=>['id'=>'companionid','access'=>SU_EDIT_MOUNTS,'url'=>'companions.php?op=save&subop=module&id='],
    ];
}

/**
 * @return array{schema:array<string,array<string,mixed>>,object:array<string,mixed>,values:array<string,string>} */
function resurrection_object_editor_state(string $type,string $module,int $id,bool $lock=false): array {
    global $session;
    // Drinks object editing remains disabled until its separate caller matrix is closed.
    $object=resurrection_object_types()[$type] ?? null;
    if ($object===null || $id<1 || $id>2147483647) throw new DomainException('Unsupported object.');
    check_su_access($object['access']);
    if ($lock && !db_query('SELECT modulename FROM '.db_prefix('modules').' WHERE modulename=? FOR UPDATE',true,[$module])) throw new DomainException('Module removed.');
    $info=resurrection_editor_module($module);
    $schema=\Resurrection\Http\SettingDescriptor::declare($info['prefs-'.$type] ?? []);
    if (!$schema) throw new DomainException('Undeclared object preferences.');
    $rows=db_query('SELECT * FROM '.db_prefix($type).' WHERE '.$object['id'].'=?'.($lock?' FOR UPDATE':''),true,[$id]);
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
    $url=resurrection_object_types()[$type]['url'].$id.'&module='.rawurlencode($module);
    addnav('',$url);
    resurrection_editor_form($url,$scope,resurrection_object_context($state),$state['schema'],$state['values']);
}
