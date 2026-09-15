<?php
// addnews ready
// mail ready
// translator ready
function game_dice_getmoduleinfo(){
	$info = array(
		"name"=>"Dice Game for DarkHorse",
		"author"=>"Eric Stevens",
		"version"=>"1.1",
		"category"=>"Darkhorse Game",
		"download"=>"core_module",
	);
	return $info;
}

function game_dice_install(){
	global $session;
	debug("Adding Hooks");
	module_addhook("darkhorsegame");
	return true;
}

function game_dice_uninstall(){
	output("Uninstalling this module.`n");
	return true;
}

function game_dice_dohook($hookname, $args){
	if ($hookname=="darkhorsegame"){
		$_SESSION['darkhorse_return'] = $args['return'];
		$ret = urlencode($args['return']);
		addnav("D?Play Dice Game",
				"runmodule.php?module=game_dice&ret=$ret");
	}
	return $args;
}

function game_dice_run(){
    global $session;
    require_once 'lib/darkhorse_game.php';
    $state=resurrection_darkhorse_game('game_dice');
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        resurrection_consume_action('game_dice',hash('sha256',(string)$session['user']['specialmisc']));
        try {
            if (array_diff(array_keys($_POST),['action','bet','csrf_token','action_token'])!==[]) throw new DomainException('Unknown field.');
            $action=\Resurrection\Http\Input::choice($_POST,'action',['bet','pass','keep'],'');
            $bet=\Resurrection\Http\Input::integer($_POST,'bet',0,1);
            $state=resurrection_player_mutation(function () use ($state,$action,$bet) {
                global $session;
                $owner=(int)$session['user']['acctid'];
                if ($action==='bet') {
                    if ($bet<1 || $bet>(int)$session['user']['gold'] || $bet>2147483647-(int)$session['user']['gold']) throw new DomainException('Invalid wager.');
                    $dice=['roll'=>\Resurrection\Game\DiceGame::roll('e_rand'),'tries'=>1,'opponent'=>0];
                    $state=\Resurrection\Game\DarkHorseState::start($state,$owner,'game_dice',$bet,json_encode($dice,JSON_THROW_ON_ERROR));
                    $session['user']['gold']-=$bet;
                } else {
                    if (!($state['active'] ?? false) || $state['game']!=='game_dice' || $bet!==0) throw new DomainException('No active dice wager.');
                    $dice=\Resurrection\Game\DiceGame::act(\Resurrection\Game\DiceGame::decode($state['data']),$action,'e_rand');
                    $state['data']=json_encode($dice,JSON_THROW_ON_ERROR);
                    if ($action==='keep') {
                        $comparison=$dice['roll']<=>$dice['opponent'];
                        $session['user']['gold']+=($comparison+1)*$state['wager'];
                        $state['stage']='complete'; $state['active']=false; $state['settled']=true;
                        $state['result']=$comparison>0 ? 'win' : ($comparison<0 ? 'loss' : 'tie');
                        debuglog('settled dice wager of '.$state['wager'].' gold: '.$state['result']);
                    }
                }
                $session['user']['specialmisc']=\Resurrection\Game\DarkHorseState::write($state,$owner);
                return $state;
            });
        } catch (DomainException|InvalidArgumentException $error) { http_response_code(400); exit('Invalid dice action.'); } catch (RuntimeException $error) { http_response_code(503); exit('Game temporarily unavailable.'); }
    } elseif (array_intersect(array_keys($_GET),['bet','try','what','action'])!==[]) {
        http_response_code(403); exit('Use the game form.');
    }
    page_header('A Game of Dice');
    output('You may keep a roll or pass, up to three rolls. The old man then rolls up to three times. Higher die wins the bet; a tie returns the stake.`n');
    if (($state['game'] ?? '')==='game_dice') {
        $dice=\Resurrection\Game\DiceGame::decode($state['data']);
        output('Your roll: %s. Rolls used: %s. Wager: %s gold.`n',$dice['roll'],$dice['tries'],$state['wager']);
        if ($state['settled']) output('Old man: %s. Result: %s.`n',$dice['opponent'],$state['result']);
    }
    $url='runmodule.php?module=game_dice'; addnav('',$url);
    rawoutput('<form method="POST" action="'.$url.'">');
    rawoutput(resurrection_action_fields('game_dice',hash('sha256',(string)$session['user']['specialmisc'])));
    if ($state['active'] ?? false) {
        rawoutput('<button name="action" value="keep" class="button">Keep</button>');
        if ($dice['tries']<3) rawoutput('<button name="action" value="pass" class="button">Pass</button>');
    } else rawoutput('<input name="bet" type="number" min="1"><button name="action" value="bet" class="button">Bet</button>');
    rawoutput('</form>'); resurrection_darkhorse_navigation(); page_footer();
}
