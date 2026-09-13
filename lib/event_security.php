<?php
require_once __DIR__ . '/player_mutation.php';

function resurrection_secured_event(string $module): bool {
    return in_array($module,['crazyaudrey','fairy','findgem','findgold','foilwench','glowingstream','goldmine'],true);
}

/** Create only from server-selected module metadata or a persisted current event. */
function resurrection_event_context(string $module,string $type): void {
    $_SESSION['event_action']=['module'=>$module,'type'=>$type,'generation'=>bin2hex(random_bytes(32))];
}

/** Dispatch a single current-event action. GET only presents an explicit confirmation. */
function resurrection_run_event(string $module,string $type,string $baseLink,callable $run): void {
    global $session;
    $operations=[
        'crazyaudrey'=>['','search','play','run'], 'fairy'=>['','search','give','dont'],
        'findgem'=>['','search'], 'findgold'=>['','search'], 'foilwench'=>['','search','give','dont'],
        'glowingstream'=>['','search','drink','nodrink'], 'goldmine'=>['','search','mine','no'],
    ];
    $context=$_SESSION['event_action'] ?? null;
    if (empty($session['loggedin']) || !is_module_active($module) || !is_array($context) ||
        ($context['module'] ?? '')!==$module || ($context['type'] ?? '')!==$type ||
        !in_array($baseLink,['forest.php?','travel.php?'],true)) {
        http_response_code(403); exit('Event unavailable.');
    }
    try { $op=\Resurrection\Http\Input::choice($_GET,'op',$operations[$module],''); }
    catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid event action.'); }
    // Selection can arrive from forest search; the selected event begins at its own entrance.
    if ($op==='search') $op='';
    $url=$baseLink.'op='.rawurlencode($op);
    $intent=$context['generation'].':'.$op;
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
        $session['user']['specialinc']='module:'.$module;
        output('Continue this encounter?');
        addnav('',$url);
        rawoutput('<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.resurrection_action_fields('forest-event',$intent).'<button class="button">Continue</button></form>');
        return;
    }
    resurrection_consume_action('forest-event',$intent);
    try {
        resurrection_player_mutation(function () use ($run,$op) {
            global $session;
            $session['user']['specialinc']='';
            httpset('op',$op);
            $run();
        });
    } catch (DomainException $error) { http_response_code(409); exit('Event state changed.'); }
    if ($session['user']['specialinc']==='') unset($_SESSION['event_action']);
}
