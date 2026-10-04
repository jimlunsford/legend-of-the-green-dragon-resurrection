<?php
require_once __DIR__ . '/../src/Game/StonesState.php';
// addnews ready
// mail ready
// translator ready
function game_stones_getmoduleinfo(){
	$info = array(
		"name"=>"Stones Game for DarkHorse",
		"author"=>"Eric Stevens",
		"version"=>"1.1",
		"category"=>"Darkhorse Game",
		"download"=>"core_module",
	);
	return $info;
}

function game_stones_install(){
	global $session;
	debug("Adding Hooks");
	module_addhook("darkhorsegame");
	return true;
}

function game_stones_uninstall(){
	output("Uninstalling this module.`n");
	return true;
}

function game_stones_dohook($hookname, $args){
	if ($hookname=="darkhorsegame"){
		$_SESSION['darkhorse_return'] = $args['return'];
		$ret = urlencode($args['return']);
		addnav("S?Play Stones Game",
				"runmodule.php?module=game_stones&ret=$ret");
	}
	return $args;
}

function game_stones_run(){
    global $session;
    require_once 'lib/darkhorse_game.php';
    require_once 'src/Game/StonesGame.php';
    // Return locations are application-owned. Never reflect a submitted URL.
    $return = $_SESSION['darkhorse_return'] ?? '';
    if (!in_array($return, ['forest.php', 'travel.php'], true) || ($session['user']['specialinc'] ?? '') !== 'module:darkhorse') {
        http_response_code(403); exit('Game unavailable.');
    }
    if (empty($session['loggedin']) || !is_module_active('darkhorse')) {
        http_response_code(403); exit('Game unavailable.');
    }
    $encoded = (string)$session['user']['specialmisc'];
    $context = hash('sha256', $encoded);
    try {
        $wager = \Resurrection\Game\DarkHorseState::read($encoded, (int)$session['user']['acctid']);
        if (($wager['active'] ?? false) && $wager['game'] !== 'game_stones') throw new DomainException('Wrong game.');
        $stones = ($wager['active'] ?? false) ? \Resurrection\Game\StonesState::decode($wager['data']) : [];
    } catch (DomainException $error) {
        http_response_code(409); exit('Invalid Stones state. Return to the tavern before starting a new game.');
    }
    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        resurrection_consume_action('game_stones', $context);
        try {
            $action = \Resurrection\Http\Input::choice($_POST, 'action', ['choose','bet','draw','settle'], '');
            if (array_diff(array_keys($_POST), ['action','csrf_token','action_token', ...($action === 'choose' ? ['side'] : ($action === 'bet' ? ['bet'] : []))]) !== []) throw new DomainException('Unknown field.');
            $side = \Resurrection\Http\Input::string($_POST, 'side');
            $bet = \Resurrection\Http\Input::integer($_POST, 'bet', 0, 1);
            $result = resurrection_player_mutation(function () use ($stones, $wager, $action, $side, $bet) {
                global $session;
                $result = \Resurrection\Game\StonesGame::act($stones, (int)$session['user']['gold'], $action, $side, $bet, 'e_rand');
                $owner = (int)$session['user']['acctid'];
                $data = \Resurrection\Game\StonesState::encode($result['state']);
                if ($action === 'choose') {
                    $wager = \Resurrection\Game\DarkHorseState::start($wager, $owner, 'game_stones', 0, $data);
                } else {
                    $wager['data'] = $data;
                    if ($action === 'bet') { $wager['wager']=$bet; $wager['stage']='play'; }
                    if ($result['settled']) {
                        $wager['active']=false; $wager['settled']=true; $wager['stage']='complete';
                        $wager['result']=$result['change'] > 0 ? 'win' : ($result['change'] < 0 ? 'loss' : 'tie');
                    }
                }
                $session['user']['specialmisc'] = \Resurrection\Game\DarkHorseState::write($wager, $owner);
                $session['user']['gold'] = $result['gold'];
                return $result;
            });
            $stones = $result['state'];
        } catch (InvalidArgumentException|DomainException $error) {
            http_response_code(400); exit('Invalid Stones action.');
        } catch (RuntimeException $error) { http_response_code(503); exit('Game temporarily unavailable.'); }
    } elseif (isset($_GET['side']) || isset($_GET['bet']) || isset($_GET['action'])) {
        http_response_code(403); exit('Use the game form.');
    }
    page_header('A Game of Stones');
    if ($result !== null && $result['drawn'] !== []) {
        output('`3The old man reaches into his bag and withdraws two stones. They are %s and %s.`n`n', $result['drawn'][0], $result['drawn'][1]);
    }
    if ($result !== null && $result['settled']) {
        if ($result['change'] > 0) output('`3Having defeated the old man at his game, you claim your `^%s`3 gold.', $result['change']);
        elseif ($result['change'] < 0) output('`3Having defeated you at his game, the old man claims your `^%s`3 gold.', -$result['change']);
        else output('`3Having tied the old man, you call it a draw.');
    }
    $url = 'runmodule.php?module=game_stones';
    addnav('', $url);
    rawoutput('<form action="' . $url . '" method="POST">');
    rawoutput(resurrection_action_fields('game_stones', hash('sha256', (string)$session['user']['specialmisc'])));
    if ($stones === []) {
        output('`3The old man explains his game, "`7I have a bag with 6 red stones, and 10 blue stones in it. You can choose between like pair or unlike pair. I will draw pairs of stones. Matching colors go to like pair, different colors to unlike pair. Whoever has the most stones wins. If we tie, neither of us wins.`3"`n');
        rawoutput('<input type="hidden" name="action" value="choose"><button name="side" value="likepair" class="button">Like Pair</button> <button name="side" value="unlikepair" class="button">Unlike Pair</button>');
    } elseif (!isset($stones['bet'])) {
        output('`3"`7How much do you bet?`3"');
        rawoutput('<input type="hidden" name="action" value="bet"><input name="bet" type="number" min="1"><button class="button">Bet</button>');
    } else {
        output('Your bet is `^%s`3. You have %s stones, the old man has %s. There are %s red and %s blue stones in the bag.`n', $stones['bet'], $stones['player'], $stones['oldman'], $stones['red'], $stones['blue']);
        $action = $stones['red'] + $stones['blue'] === 0 || $stones['player'] > 8 || $stones['oldman'] > 8 ? 'settle' : 'draw';
        rawoutput('<button name="action" value="' . $action . '" class="button">Continue</button>');
    }
    rawoutput('</form>');
    {
        addnav('Other Games', $return . '?op=oldman');
        addnav('Return to Main Room', $return . '?op=tavern');
    }
    page_footer();
}
