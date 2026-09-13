<?php
/*Five Sixes tavern game
  version 1.7
  - Added periods at the end of News announcements
  version 1.6
  - Fixed newday reset
  version 1.5
  - Added win function for rolling 3 sixes
  - Included winner list for 4 and 3 sixes on the entry page
 */
function game_fivesix_getmoduleinfo(){
	$info = array(
		"name"=>"Five Sixes Dice Game",
		"author"=>"`4Talisman",
		"version"=>"1.7",
		"category"=>"Darkhorse Game",
		"download"=>"core_module",
		"settings"=>array(
			"Five Sixes Dice Game,title",
			"cost"=>"Cost to play,int|5",
			"dailyuses"=>"Plays per  day (0=unlimited),int|10",
			"jackpot"=>"Gold in the pot,int|100",
			"maxjackpot"=>"Maximum Payout,int|5000",
			"lastwin5"=>"Last jackpot winner|Nobody...yet",
			"lastpot5"=>"Last Jackpot Won,int|0",
			"lastwin4"=>"Last winner of 4 sixes|Nobody...yet",
			"lastpot4"=>"Last Jackpot Won,int|0",
			"lastwin3"=>"Last winner of 3 sixes|Nobody...yet",
			"lastpot3"=>"Last Jackpot Won,int|0",
		),
		"prefs"=>array(
			"Five Sixes Dice, title",
			"playstoday"=>"Times played today,int|0",
		)
	);
	return $info;
}

function game_fivesix_install(){
	global $session;
	module_addhook("darkhorsegame");
	module_addhook("newday");
	return true;
}

function game_fivesix_uninstall(){
	return true;
}

function game_fivesix_dohook($hookname, $args){

	global $session;
	switch($hookname){
	case "newday":
		set_module_pref("playstoday",0);
		break;

	case "darkhorsegame":
		$_SESSION['darkhorse_return'] = $args['return'];
		$ret = urlencode($args['return']);
		addnav("Play Sixes Dice Game",
				"runmodule.php?module=game_fivesix&ret=$ret&what=play");
	}
	return $args;
}

function game_fivesix_run(){
    global $session;
    require_once 'lib/darkhorse_game.php';
    $state=resurrection_darkhorse_game('game_fivesix');
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        resurrection_consume_action('game_fivesix',hash('sha256',(string)$session['user']['specialmisc']));
        try {
            if (array_diff(array_keys($_POST),['action','csrf_token','action_token'])!==[] ||
                \Resurrection\Http\Input::string($_POST,'action')!=='roll') throw new DomainException('Invalid roll.');
            $state=resurrection_player_mutation(function () use ($state) {
                global $session;
                // A real installed module row serializes players even if a setting row is missing.
                $module=db_query('SELECT active FROM '.db_prefix('modules').' WHERE modulename=? FOR UPDATE',true,['game_fivesix']);
                if (count($module)!==1 || !(int)$module[0]['active']) throw new DomainException('Game unavailable.');
                $rows=db_query('SELECT setting,value FROM '.db_prefix('module_settings').' WHERE modulename=? FOR UPDATE',true,['game_fivesix']);
                $settings=['cost'=>'5','dailyuses'=>'10','jackpot'=>'100','maxjackpot'=>'5000'];
                foreach ($rows as $row) $settings[$row['setting']]=$row['value'];
                foreach (['cost'=>1,'dailyuses'=>0,'jackpot'=>0,'maxjackpot'=>100] as $key=>$min) {
                    $value=filter_var($settings[$key] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>1073741823]]);
                    if ($value===false) throw new DomainException('Invalid game settings.');
                    $settings[$key]=$value;
                }
                $visits=filter_var(get_module_pref('playstoday'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483646]]);
                if ($visits===false || ($settings['dailyuses']!==0 && $visits >= $settings['dailyuses']) ||
                    (int)$session['user']['gold']<$settings['cost']) throw new DomainException('Cannot play.');
                $dice=[];
                for ($i=0;$i<5;$i++) $dice[]=\Resurrection\Game\DiceGame::roll('e_rand');
                $result=\Resurrection\Game\FiveSixGame::result($dice,$settings['jackpot'],$settings['cost'],$settings['maxjackpot']);
                $owner=(int)$session['user']['acctid'];
                $state=\Resurrection\Game\DarkHorseState::start($state,$owner,'game_fivesix',$settings['cost'],json_encode($dice,JSON_THROW_ON_ERROR));
                $session['user']['gold']-=$settings['cost'];
                set_module_pref('playstoday',$visits+1);
                set_module_setting('jackpot',$result['jackpot']);
                $session['user']['gold']+=$result['payout'];
                if ($result['sixes']>=3) {
                    set_module_setting('lastpot'.$result['sixes'],$result['payout']);
                    set_module_setting('lastwin'.$result['sixes'],$session['user']['name']);
                    addnews('%s won %s gold after rolling %s sixes in the %s.',$session['user']['name'],$result['payout'],$result['sixes'],get_module_setting('tavernname','darkhorse'));
                }
                debuglog('spent '.$settings['cost'].' gold and won '.$result['payout'].' gold at sixes');
                $state['active']=false; $state['settled']=true; $state['stage']='complete';
                $state['result']=$result['payout']>0 ? 'win' : 'loss';
                $session['user']['specialmisc']=\Resurrection\Game\DarkHorseState::write($state,$owner);
                return $state;
            });
        } catch (DomainException|InvalidArgumentException $error) { http_response_code(400); exit('Invalid or unavailable roll.'); }
    } elseif (array_intersect(array_keys($_GET),['action','bet','try','result','jackpot'])!==[] || (isset($_GET['what']) && $_GET['what']!=='play')) {
        http_response_code(403); exit('Use the game form.');
    }
    page_header('A Game of Dice');
    output('Roll five dice. Five sixes wins the jackpot; four wins ten percent, three wins five percent.`n');
    output('Cost: %s gold. Current jackpot: %s gold.`n',get_module_setting('cost'),get_module_setting('jackpot'));
    if (($state['game'] ?? '')==='game_fivesix') {
        $dice=\Resurrection\Game\FiveSixGame::decode($state['data']);
        output('Your dice: %s. Result: %s.`n',implode(' ',$dice),$state['result']);
    }
    $url='runmodule.php?module=game_fivesix'; addnav('',$url);
    rawoutput('<form method="POST" action="'.$url.'">');
    rawoutput(resurrection_action_fields('game_fivesix',hash('sha256',(string)$session['user']['specialmisc'])));
    rawoutput('<button name="action" value="roll" class="button">Roll the Dice</button></form>');
    resurrection_darkhorse_navigation(); page_footer();
}
