<?php
function drinks_run_private(){
	require_once("modules/drinks/misc_functions.php");
	require_once("lib/partner.php");

	global $session;
	$partner = get_partner();
	$act = httpget('act');
	if ($act=="editor"){
		drinks_editor();
	}elseif ($act=="buy"){
        require_once 'modules/drinks/security.php';
        try { $id = \Resurrection\Http\Input::integer($_GET,'id',0,1); }
        catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid drink ID.'); }
        $rows=db_query('SELECT * FROM ' . db_prefix('drinks') . ' WHERE drinkid=? AND active=1',true,[$id]);
        if (count($rows)!==1) { http_response_code(404); exit('Drink unavailable.'); }
        $row=$rows[0];
        $result=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            resurrection_consume_action('drinks-buy',(string)$id);
            try { $result=drinks_purchase($id); }
            catch (DomainException $error) { http_response_code(400); exit('Drink unavailable, limit reached, or insufficient funds.'); }
            $row=$result['drink'];
        }
        $texts=drinks_gettexts();
        $drinktext=modulehook('drinks-text',$texts);
        tlschema($drinktext['schemas']['title']);
        page_header($drinktext['title']);
        rawoutput("<span style='color: #9900FF'>");
        output_notl('`c`b'); output($drinktext['title']); output_notl('`b`c'); tlschema();
        if ($result===null) {
            output('Buy %s for %s gold?', $row['name'], (int)$session['user']['level']*(int)$row['costperlevel']);
            $url='runmodule.php?module=drinks&act=buy&id='.$id;
            addnav('',$url);
            rawoutput('<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.resurrection_action_fields('drinks-buy',(string)$id).'<button class="button">Buy</button></form>');
        } else {
            $remark=str_replace(['{lover}','{barkeep}'],[$partner.'`0',$drinktext['barkeep'].'`0'],$row['remarks']);
            if (count($drinktext['drinksubs'])>0) $remark=preg_replace(array_keys($drinktext['drinksubs']),array_values($drinktext['drinksubs']),$remark);
            output($remark); output_notl('`n`n');
            if ($result['turns']>0) output('`&You feel vigorous!`n');
            elseif ($result['turns']<0) output('`&You feel lethargic!`n');
            if ($result['hp']>0) output('`&You feel healthy!`n');
            elseif ($result['hp']<0) output('`&You feel sick!`n');
        }
		rawoutput("</span>");
		if ($drinktext['return']>""){
			tlschema($drinktext['schemas']['return']);
			addnav($drinktext['return'],$drinktext['returnlink']);
			tlschema();
		}else{
			tlschema($drinktext['schemas']['return']);
			addnav("I?Return to the Inn","inn.php");
			addnav(array("Go back to talking to %s`0", getsetting("barkeep", "`tCedrik")),"inn.php?op=bartender");
			tlschema();
		}
		require_once("lib/villagenav.php");
		villagenav();
		page_footer();
	}
}
?>
