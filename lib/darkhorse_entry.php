<?php
require_once __DIR__.'/player_mutation.php';

/** Mount and preference identity come exclusively from the authenticated account. */
function resurrection_darkhorse_mount_context(bool $lock=false): string {
    global $session;
    $id=filter_var($session['user']['hashorse'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    if (empty($session['loggedin']) || $id===false || !is_module_active('darkhorse')) throw new DomainException('Tavern unavailable.');
    if ($lock && !db_query('SELECT modulename FROM '.db_prefix('modules').' WHERE modulename=? AND active=1 FOR UPDATE',true,['darkhorse'])) throw new DomainException('Module unavailable.');
    $mount=db_query('SELECT * FROM '.db_prefix('mounts').' WHERE mountid=?'.($lock?' FOR UPDATE':''),true,[$id]);
    $pref=db_query('SELECT value FROM '.db_prefix('module_objprefs').' WHERE objtype=? AND objid=? AND modulename=? AND setting=?'.($lock?' FOR UPDATE':''),true,['mounts',$id,'darkhorse','findtavern']);
    if (count($mount)!==1 || ($pref[0]['value'] ?? '0')!=='1') throw new DomainException('Tavern unavailable.');
    return hash('sha256',json_encode([(int)$session['user']['acctid'],$mount[0],$pref[0]['value']],JSON_THROW_ON_ERROR));
}

function resurrection_darkhorse_enter(): bool {
    global $session;
    try { $context=resurrection_darkhorse_mount_context(); }
    catch (DomainException $error) { http_response_code(403); exit('Tavern unavailable.'); }
    $url='runmodule.php?module=darkhorse&op=enter';
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
        addnav('',$url);
        rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('darkhorse-enter',$context).'<button class="button">Enter the tavern</button></form>');
        return false;
    }
    resurrection_consume_action('darkhorse-enter',$context);
    try {
        resurrection_player_mutation(function () use ($context) {
            global $session;
            if (resurrection_darkhorse_mount_context(true)!==$context || !in_array($session['user']['specialinc'],['','module:darkhorse'],true)) throw new DomainException('Entry changed.');
            $session['user']['specialinc']='module:darkhorse';
        });
    } catch (DomainException $error) { http_response_code(409); exit('Tavern entry changed.'); }
    catch (Throwable $error) { http_response_code(500); exit('Tavern entry was not completed.'); }
    return true;
}

/** An encounter exit is an explicit mutation; an active wager must first be resolved/abandoned. */
function resurrection_darkhorse_leave(string $url): bool {
    global $session;
    require_once __DIR__.'/../src/Game/DarkHorseState.php';
    try {
        $state=\Resurrection\Game\DarkHorseState::read((string)$session['user']['specialmisc'],(int)$session['user']['acctid']);
        if ($state['active'] ?? false) throw new DomainException('Active wager.');
    } catch (DomainException $error) { http_response_code(409); exit('Resolve the current wager first.'); }
    $context=hash('sha256',json_encode([(int)$session['user']['acctid'],$url,$session['user']['specialinc'],$session['user']['specialmisc']],JSON_THROW_ON_ERROR));
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
        addnav('',$url);
        rawoutput('<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.resurrection_action_fields('darkhorse-leave',$context).'<button class="button">Leave the tavern</button></form>');
        return false;
    }
    resurrection_consume_action('darkhorse-leave',$context);
    try { resurrection_player_mutation(function () { global $session; $session['user']['specialinc']=''; }); }
    catch (DomainException $error) { http_response_code(409); exit('Tavern state changed.'); }
    catch (Throwable $error) { http_response_code(500); exit('Tavern exit was not completed.'); }
    unset($_SESSION['darkhorse_return']);
    return true;
}
