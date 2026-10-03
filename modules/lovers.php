<?php
//addnews ready
// translator ready
// mail ready
require_once("lib/buffs.php");
require_once("lib/partner.php");
require_once("lib/player_mutation.php");
//should we move charm here?
//should we move marriedto here?

function lovers_getmoduleinfo(){
	$info = array(
		"name"=>"Violet and Seth Lovers",
		"author"=>"Eric Stevens",
		"version"=>"1.0",
		"category"=>"Inn",
		"download"=>"core_module",
		"prefs"=>array(
			"Lover Module User Preferences,title",
			"seenlover"=>"Visited Lover Today?,bool|0"
		)
	);
	return $info;
}

function lovers_install(){
	module_addhook("newday");
	module_addhook("inn");

	$sql = "DESCRIBE " . db_prefix("accounts");
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)){
		if ($row['Field']=="seenlover"){
			$sql = "SELECT seenlover,acctid FROM " . db_prefix("accounts") . " WHERE seenlover>0";
			$result1 = db_query($sql);
			debug("Migrating seenlover.`n");
			while ($row1 = db_fetch_assoc($result1)){
				$sql = "INSERT INTO " . db_prefix("module_userprefs") . " (modulename,setting,userid,value) VALUES ('lovers','seenlover',{$row1['acctid']},{$row1['seenlover']})";
				db_query($sql);
			}//end while
			debug("Dropping seenlover column from the user table.`n");
			$sql = "ALTER TABLE " . db_prefix("accounts") . " DROP seenlover";
			db_query($sql);
			//drop it from the user's session too.
			unset($session['user']['seenlover']);
		}//end if
	}//end while
	return true;
}

function lovers_uninstall(){
	return true;
}

function lovers_dohook($hookname, $args){
	global $session;
	$partner = get_partner();
	switch($hookname){
	case "newday":
		set_module_pref("seenlover",0);
		if ($session['user']['marriedto'] == 4294967295){
			$dk = $session['user']['dragonkills'];


			// 0.7 seemed to be a perfect balance of no loss of charm.
			// 1.0 was too much.
			$dk = max(1, round(.85 * sqrt($dk), 0));
			$charmloss= e_rand(1,$dk);
			$session['user']['charm'] -= $charmloss;
			output("`n`%You're  married,  so there's no reason to keep up that perfect image, and you let yourself go a little today ( You lose `\$%s charmpoint(s)`%).`n",$charmloss);
			if ($session['user']['charm']<=0){
				output("`bWhen  you  wake  up, you find a note next to you, reading`n`5Dear %s`5,`n",$session['user']['name']);
				output("Despite  many  great  kisses, I find that I'm simply no longer attracted to you the way I used to be.`n`n");
				output("Call  me fickle, call me flakey, but I need to move on.");
				output("There are other warriors in the land, and I think some of them are really hot.");
				output("So it's not you, it's me, etcetera etcetera.`n`n");
				output("No hard feelings, Love,`n%s`b`n",$partner);
				addnews("`\$%s`\$ has left %s`\$ to pursue \"other interests.\"`0",$partner, $session['user']['name']);
				$session['user']['marriedto']=0;
				$session['user']['charm']=0;
			}
		}
		break;
	case "inn":
		addnav("Things to do");
		if ($session['user']['sex']==SEX_MALE){
			addnav(array("F?Flirt with %s", $partner),
					"runmodule.php?module=lovers&op=flirt");
			addnav(array("Chat with %s",translate_inline(getsetting("bard", "`^Seth"))),
					"runmodule.php?module=lovers&op=chat");
		}else{
			addnav(array("F?Flirt with %s", $partner),
					"runmodule.php?module=lovers&op=flirt");
			addnav(array("Gossip with %s",translate_inline(getsetting("barmaid", "`%Violet"))),
					"runmodule.php?module=lovers&op=chat");
		}
		break;
	}
	return $args;
}

function lovers_run(){
	global $session;
	require_once("lib/villagenav.php");
	$iname = getsetting("innname", LOCATION_INN);
	page_header($iname);
	rawoutput("<span style='color: #9900FF'>");
	output_notl("`c`b");
	output($iname);
	output_notl("`b`c");
    try {
        $op = \Resurrection\Http\Input::choice($_GET, 'op', ['flirt','chat'], 'flirt');
        if (array_diff(array_keys($_GET), ['module','op','act','c']) !== []) throw new InvalidArgumentException('Unexpected query.');
        if ($op === 'flirt') {
            if (isset($_GET['act'])) throw new InvalidArgumentException('Unexpected path.');
            $state = lovers_state();
            $context = hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                resurrection_consume_action('lovers', $context);
                if (array_diff(array_keys($_POST), ['csrf_token','action_token','action','flirt']) !== []) throw new InvalidArgumentException('Unexpected form field.');
                // PHP collapses repeated scalar form keys; reject ambiguity before choosing an effect.
                $keys = [];
                foreach (explode('&', file_get_contents('php://input')) as $field) {
                    $key = urldecode(explode('=', $field, 2)[0]);
                    if (isset($keys[$key])) throw new InvalidArgumentException('Repeated form field.');
                    $keys[$key] = true;
                }
                $married = (int)$session['user']['marriedto'] === INT_MAX;
                \Resurrection\Http\Input::choice($_POST, 'action', [$married ? 'visit' : 'flirt'], '');
                $choice = null;
                if ($married) {
                    if (isset($_POST['flirt'])) throw new InvalidArgumentException('Married visit has no flirt choice.');
                } else {
                    $choice = \Resurrection\Http\Input::integer($_POST, 'flirt', 0, 1);
                    if ($choice < 1 || $choice > 7) throw new InvalidArgumentException('Invalid flirt choice.');
                }
                resurrection_player_mutation(function () use ($state, $choice) {
                    if (lovers_state(true) !== $state) throw new DomainException('Lovers state changed.');
                    lovers_story($choice);
                });
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if ((int)$session['user']['marriedto'] === INT_MAX) {
                    output('Spend some time with %s`0?', get_partner());
                    lovers_form('Visit', null);
                } else {
                    lovers_story(null);
                }
            } else {
                resurrection_require_post();
            }
        } else {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') resurrection_require_post();
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new InvalidArgumentException('Chat is read-only.');
            \Resurrection\Http\Input::choice($_GET, 'act', $session['user']['sex'] == SEX_MALE ? ['', 'armor', 'sports'] : ['', 'fat', 'gossip'], '');
            if ($session['user']['sex'] == SEX_MALE) {
                require_once('modules/lovers/lovers_chat_seth.php');
                lovers_chat_seth();
            } else {
                require_once('modules/lovers/lovers_chat_violet.php');
                lovers_chat_violet();
            }
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid Lovers action.');
    } catch (DomainException $error) {
        http_response_code(409); exit('Lovers is unavailable.');
    } catch (Throwable $error) {
        http_response_code(500); exit('Lovers interaction was not completed. Request a fresh form.');
    }
	addnav("Return");
	addnav("I?Return to the Inn","inn.php");
	villagenav();
	rawoutput("</span>");
	page_footer();
}

function lovers_getbuff(){
	global $session;
	$partner = get_partner();
	$buff = array(
		"name"=>"`!Lover's Protection",
		"rounds"=>60,
		"wearoff"=>
		array("`!You miss %s`!.`0",$partner),
		"defmod"=>1.2,
		"roundmsg"=>"Your lover inspires you to keep safe!",
		"schema"=>"module-lovers",
	);
	return $buff;
}
/** Read business state without creating the default preference on GET. */
function lovers_state(bool $lock = false): array {
    global $session;
    $user = $session['user'];
    if (empty($session['loggedin']) || empty($user['acctid']) || (int)$user['alive'] !== 1 ||
        (int)$user['hitpoints'] <= 0 || $user['specialinc'] !== '') throw new DomainException('Ineligible player.');
    $suffix = $lock ? ' FOR UPDATE' : '';
    $module = db_query('SELECT active FROM ' . db_prefix('modules') . ' WHERE modulename=?' . $suffix, true, ['lovers']);
    if (count($module) !== 1 || (int)$module[0]['active'] !== 1) throw new DomainException('Inactive module.');
    $rows = db_query('SELECT value FROM ' . db_prefix('module_userprefs') . ' WHERE modulename=? AND setting=? AND userid=?' . $suffix,
        true, ['lovers','seenlover',(int)$user['acctid']]);
    $seen = count($rows) === 0 ? '0' : (string)$rows[0]['value'];
    if ($seen !== '0') throw new DomainException('Daily visit unavailable.');
    $state = [];
    foreach (['acctid','lasthit','sex','marriedto','charm','turns','alive','hitpoints','specialinc','location','bufflist'] as $key) {
        $state[$key] = (string)$user[$key];
    }
    $state['seenlover'] = $seen;
    return $state;
}

function lovers_form(string $label, ?int $choice): void {
    $url = 'runmodule.php?module=lovers&op=flirt';
    addnav('', $url);
    $context = hash('sha256', json_encode(lovers_state(), JSON_THROW_ON_ERROR));
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
        resurrection_action_fields('lovers', $context) . '<input type="hidden" name="action" value="' .
        ($choice === null ? 'visit' : 'flirt') . '">' . ($choice === null ? '' :
        '<input type="hidden" name="flirt" value="' . $choice . '">') . '<button class="button">' .
        htmlspecialchars(translate_inline($label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>');
}

function lovers_story(?int $choice): void {
    global $session;
    if ($session['user']['sex'] == SEX_MALE) {
        require_once('modules/lovers/lovers_violet.php');
        lovers_violet($choice);
    } else {
        require_once('modules/lovers/lovers_seth.php');
        lovers_seth($choice);
    }
}
