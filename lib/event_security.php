<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/player_mutation.php';

function resurrection_secured_event(string $module): bool {
    return in_array($module,['crazyaudrey','fairy','findgem','findgold','foilwench','glowingstream','goldmine'],true);
}

/** Create only from server-selected module metadata or a persisted current event. */
function resurrection_event_context(string $module,string $type): void {
    $_SESSION['event_action']=['module'=>$module,'type'=>$type,'generation'=>bin2hex(random_bytes(32))];
}

/**
 * A forest event's refusal in the game's frame (Phase 6 task 27, no dead ends): the same status and message, with one
 * way out, back to the forest, whose GET shows the event still pending (its confirmation) or the forest; the village
 * for an event outside the forest, the Shades for the dead.
 */
function resurrection_event_refuse(int $status, string $message, string $baseLink = 'forest.php?'): never {
    global $session;
    resurrection_refuse_page($status, $message, $baseLink === 'forest.php?' ? 'The Forest' : 'Something Special!', $message,
        !empty($session['user']['alive']) && $baseLink === 'forest.php?' ? ['Return to the forest', 'forest.php'] : null);
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
        resurrection_event_refuse(403, 'Event unavailable.', $baseLink);
    }
    try { $op=\Resurrection\Http\Input::choice($_GET,'op',$operations[$module],''); }
    catch (InvalidArgumentException $error) { resurrection_event_refuse(400, 'Invalid event action.', $baseLink); }
    // Selection can arrive from forest search; the selected event begins at its own entrance.
    if ($op==='search') $op='';
    $url=$baseLink.'op='.rawurlencode($op);
    $intent=$context['generation'].':'.$op;
    if ($module==='goldmine') {
        try { $intent.=':'.hash('sha256',json_encode(goldmine_authority(),JSON_THROW_ON_ERROR)); }
        catch (DomainException $error) { resurrection_event_refuse(409, 'Invalid mine state.', $baseLink); }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
        $session['user']['specialinc']='module:'.$module;
        output('Continue this encounter?');
        addnav('',$url);
        rawoutput('<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.resurrection_action_fields('forest-event',$intent).'<button class="button">Continue</button></form>');
        return;
    }
    resurrection_consume_action('forest-event',$intent);
    try {
        resurrection_player_mutation(function () use ($run,$op,$module,$intent,$context) {
            global $session;
            if ($module==='goldmine') {
                $locked=goldmine_authority(true);
                if ($intent!==$context['generation'].':'.$op.':'.hash('sha256',json_encode($locked,JSON_THROW_ON_ERROR))) throw new DomainException('Mine state changed.');
            }
            $session['user']['specialinc']='';
            httpset('op',$op);
            $run();
        });
    } catch (DomainException $error) { resurrection_event_refuse(409, 'Event state changed.', $baseLink); }
    catch (Throwable $error) { \Resurrection\Http\FailureLog::record($error); resurrection_event_refuse(500, 'Event could not be completed. Nothing was changed.', $baseLink); }
    if ($session['user']['specialinc']==='') unset($_SESSION['event_action']);
}
