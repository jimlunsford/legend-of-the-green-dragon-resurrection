<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/systemmail.php");
require_once("lib/sanitize.php");
require_once("lib/http.php");
require_once("lib/villagenav.php");
require_once("lib/bank_authority.php");

tlschema("bank");

page_header("Ye Olde Bank");
$op = httpget('op');
// Only the protected POST of a finishing step moves gold; GET (a reload, a restored page) shows its form.
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$display = array('depositfinish'=>'deposit', 'withdrawfinish'=>'withdraw', 'transfer2'=>'transfer', 'transfer3'=>'transfer');
if (!$posted && isset($display[$op])) $op = $display[$op];
// 1.1.2 enforced these only by hiding the navigation.
if (in_array($op, array('transfer','transfer2','transfer3'), true) && !resurrection_bank_transfers_allowed()) {
	http_response_code(403);
	exit('Transfers are not available.');
}
$indebt = translate_inline("`@Elessa`6 tells you that she refuses to transfer money for someone who is in debt.");
if ($op==""){
  checkday();
  output("`6As you approach the pair of impressive carved rock crystal doors, they part to allow you entrance into the bank.");
  output("You find yourself standing in a room of exquisitely vaulted ceilings of carved stone.");
  output("Light filters through tall windows in shafts of soft radiance.");
  output("About you, clerks are bustling back and forth.");
  output("The sounds of gold being counted can be heard, though the treasure is nowhere to be seen.`n`n");
  output("You walk up to a counter of jet black marble.`n`n");
  output("`@Elessa`6, a petite woman in an immaculately tailored business dress, greets you from behind reading spectacles with polished silver frames.`n`n");
  output("`6\"`5Greetings, my good lady,`6\" you greet her, \"`5Might I inquire as to my balance this fine day?`6\"`n`n");
  output("`@Elessa`6 blinks for a moment and then smiles, \"`@Hmm, `&%s`@, let's see.....`6\" she mutters as she scans down a page in her ledger.",$session['user']['name']);
	if ($session['user']['goldinbank']>=0){
		output("`6\"`@Aah, yes, here we are.  You have `^%s gold`@ in our prestigious bank.  Is there anything else I can do for you?`6\"",$session['user']['goldinbank']);
	}else{
		output("`6\"`@Aah, yes, here we are.  You have a `&debt`@ of `^%s gold`@ in our prestigious bank.  Is there anything else I can do for you?`6\"",abs($session['user']['goldinbank']));
	}
}elseif($op=="transfer"){
	output("`6`bTransfer Money`b:`n");
	if ($session['user']['goldinbank']>=0){
		output("`@Elessa`6 tells you, \"`@Just so that you are fully aware of our policies, you may only transfer `^%s`@ gold per the recipient's level.",getsetting("transferperlevel",25));
		$maxout = $session['user']['level']*getsetting("maxtransferout",25);
		output("Similarly, you may transfer no more than `^%s`@ gold total during the day.`6\"`n",$maxout);
		if ($session['user']['amountouttoday'] > 0) {
			output("`6She scans her ledgers briefly, \"`@For your knowledge, you have already transferred `^%s`@ gold today.`6\"`n",$session['user']['amountouttoday']);
		}
		output_notl("`n");
		$preview = resurrection_bank_attribute(translate_inline("Preview Transfer"));
		rawoutput("<form action='bank.php?op=transfer2' method='POST'>");
		output("Transfer how much: ");
		rawoutput("<input name='amount' id='amount' width='5'>");
		output_notl("`n");
		output("To: ");
		rawoutput("<input name='to'>");
		output(" (partial names are ok, you will be asked to confirm the transaction before it occurs).`n");
		rawoutput("<input type='submit' class='button' value='$preview'></form>");
		rawoutput("<script language='javascript'>document.getElementById('amount').focus();</script>");
		addnav("","bank.php?op=transfer2");
	}else{
		output_notl($indebt);
	}
}elseif($op=="transfer2"){
	output("`6`bConfirm Transfer`b:`n");
	try {
		resurrection_bank_allow(array('to','amount'));
		$to = \Resurrection\Http\Input::string($_POST, 'to');
		$string = resurrection_bank_search_pattern($to);
		$amt = resurrection_bank_amount();
	} catch (InvalidArgumentException $error) { resurrection_bank_failure($error); }
	// Bound; at most 101 rows are needed to tell "more than 100". Debtors are refused, as on the transfer page.
	$result = $session['user']['goldinbank']<0 ? array() :
		db_query("SELECT name,login FROM " . db_prefix("accounts") . " WHERE name LIKE ? ESCAPE '|' AND locked=0 ORDER BY login=? DESC, name=? DESC, login LIMIT 101", true, [$string, $to, $to]);
	if ($session['user']['goldinbank']<0){
		output_notl($indebt);
	}elseif (db_num_rows($result)==1){
		$row = db_fetch_assoc($result);
		$msg = resurrection_bank_attribute(translate_inline("Complete Transfer"));
		rawoutput("<form action='bank.php?op=transfer3' method='POST'>".resurrection_bank_fields('bank-transfer', $amt));
		output("`6Transfer `^%s`6 to `&%s`6.",$amt,$row['name']);
		rawoutput("<input type='hidden' name='to' value='".resurrection_bank_attribute($row['login'])."'><input type='hidden' name='amount' value='$amt'><input type='submit' class='button' value='$msg'></form>",true);
		addnav("","bank.php?op=transfer3");
	}elseif(db_num_rows($result)>100){
		output("`@Elessa`6 looks at you disdainfully and coldly, but politely, suggests you try narrowing down the field of who you want to send money to just a little bit!`n`n");
		$msg = resurrection_bank_attribute(translate_inline("Preview Transfer"));
		rawoutput("<form action='bank.php?op=transfer2' method='POST'>");
		output("Transfer how much: ");
		rawoutput("<input name='amount' id='amount' width='5' value='$amt'><br>");
		output("To: ");
		rawoutput("<input name='to' value='".resurrection_bank_attribute($to)."'>");
		output(" (partial names are ok, you will be asked to confirm the transaction before it occurs).`n");
		rawoutput("<input type='submit' class='button' value='$msg'></form>");
		rawoutput("<script language='javascript'>document.getElementById('amount').focus();</script>",true);
		addnav("","bank.php?op=transfer2");
	}elseif(db_num_rows($result)>1){
		rawoutput("<form action='bank.php?op=transfer3' method='POST'>".resurrection_bank_fields('bank-transfer', $amt));
		output("`6Transfer `^%s`6 to ",$amt);
		rawoutput("<select name='to' class='input'>");
		$number=db_num_rows($result);
		for ($i=0;$i<$number;$i++){
			$row = db_fetch_assoc($result);
			rawoutput("<option value=\"".resurrection_bank_attribute($row['login'])."\">".resurrection_bank_attribute(full_sanitize($row['name']))."</option>");
		}
		$msg = resurrection_bank_attribute(translate_inline("Complete Transfer"));
		rawoutput("</select><input type='hidden' name='amount' value='$amt'><input type='submit' class='button' value='$msg'></form>",true);
		addnav("","bank.php?op=transfer3");
	}else{
		output("`@Elessa`6 blinks at you from behind her spectacles, \"`@I'm sorry, but I can find no one matching that name who does business with our bank!  Please try again.`6\"");
	}
}elseif($op=="transfer3"){
	try {
		resurrection_bank_allow(array('to','amount'));
		$to = \Resurrection\Http\Input::string($_POST, 'to');
		$amt = resurrection_bank_amount();
	} catch (InvalidArgumentException $error) { resurrection_bank_failure($error); }
	output("`6`bTransfer Completion`b`n");
	resurrection_consume_action('bank-transfer', resurrection_bank_context('bank-transfer', $amt));
	// The recipient is found by login, then locked with the sender in ascending account order.
	$found = db_query("SELECT acctid FROM " . db_prefix("accounts") . " WHERE login=?", true, [$to]);
	$recipient = count($found) == 1 ? (int)$found[0]['acctid'] : 0;
	try {
		$transfer = resurrection_bank_mutation(function () use ($amt, $recipient) {
			global $session;
			if (!resurrection_bank_transfers_allowed()) throw new DomainException('Transfers unavailable.');
			if ($session['user']['goldinbank']<0) return array('refused'=>'debt');
			if ($session['user']['gold']+$session['user']['goldinbank']<$amt) return array('refused'=>'funds');
			$rows = $recipient > 0 ? db_query("SELECT name,acctid,level,transferredtoday,locked FROM " . db_prefix("accounts") . " WHERE acctid=? FOR UPDATE", true, [$recipient]) : array();
			// The search never offers a locked account; neither does completion.
			if (count($rows) != 1 || $rows[0]['locked']) return array('refused'=>'missing');
			$row = $rows[0];
			$maxout = $session['user']['level']*getsetting("maxtransferout",25);
			$maxtfer = $row['level']*getsetting("transferperlevel",25);
			if ($session['user']['amountouttoday']+$amt > $maxout) return array('refused'=>'maxout', 'limit'=>$maxout);
			if ($maxtfer<$amt) return array('refused'=>'maxtfer', 'name'=>$row['name'], 'limit'=>$maxtfer);
			if ($row['transferredtoday']>=getsetting("transferreceive",3)) return array('refused'=>'received', 'name'=>$row['name']);
			if ($amt<(int)$session['user']['level']) return array('refused'=>'small');
			if ($row['acctid']==$session['user']['acctid']) return array('refused'=>'self');
			debuglog("transferred $amt gold to", $row['acctid']);
			$session['user']['gold']-=$amt;
			if ($session['user']['gold']<0){
				//withdraw in case they don't have enough on hand.
				$session['user']['goldinbank']+=$session['user']['gold'];
				$session['user']['gold']=0;
			}
			$session['user']['amountouttoday']+= $amt;
			db_query("UPDATE ". db_prefix("accounts") . " SET goldinbank=goldinbank+?,transferredtoday=transferredtoday+1 WHERE acctid=?", true, [$amt, (int)$row['acctid']]);
			$subj = array("`^You have received a money transfer!`0");
			$body = array("`&%s`6 has transferred `^%s`6 gold to your bank account!",$session['user']['name'],$amt);
			systemmail((int)$row['acctid'],$subj,$body);
			return array();
		}, $recipient > 0 ? array($recipient) : array());
	} catch (Throwable $error) { resurrection_bank_failure($error); }
	switch ($transfer['refused'] ?? '') {
		case 'debt': output_notl($indebt); break;
		case 'funds': output("`@Elessa`6 stands up to her full, but still diminutive height and glares at you, \"`@How can you transfer `^%s`@ gold when you only possess `^%s`@?`6\"",$amt,$session['user']['gold']+$session['user']['goldinbank']); break;
		case 'missing': output("`@Elessa`6 looks up from her ledger with a bit of surprise on her face, \"`@I'm terribly sorry, but I seem to have run into an accounting error, would you please try telling me what you wish to transfer again?`6\""); break;
		case 'maxout': output("`@Elessa`6 shakes her head, \"`@I'm sorry, but I cannot complete that transfer; you are not allowed to transfer more than `^%s`@ gold total per day.`6\"",$transfer['limit']); break;
		case 'maxtfer': output("`@Elessa`6 shakes her head, \"`@I'm sorry, but I cannot complete that transfer; `&%s`@ may only receive up to `^%s`@ gold per day.`6\"",$transfer['name'],$transfer['limit']); break;
		case 'received': output("`@Elessa`6 shakes her head, \"`@I'm sorry, but I cannot complete that transfer; `&%s`@ has received too many transfers today, you will have to wait until tomorrow.`6\"",$transfer['name']); break;
		case 'small': output("`@Elessa`6 shakes her head, \"`@I'm sorry, but I cannot complete that transfer; you might want to send a worthwhile transfer, at least as much as your level.`6\""); break;
		case 'self': output("`@Elessa`6 glares at you, her eyes flashing dangerously, \"`@You may not transfer money to yourself!  That makes no sense!`6\""); break;
		default: output("`@Elessa`6 smiles, \"`@The transfer has been completed!`6\"");
	}
}elseif($op=="deposit"){
	output("`0");
	rawoutput("<form action='bank.php?op=depositfinish' method='POST'>".resurrection_bank_fields('bank-deposit'));
	$balance = translate_inline("`@Elessa`6 says, \"`@You have a balance of `^%s`@ gold in the bank.`6\"`n");
	$debt = translate_inline("`@Elessa`6 says, \"`@You have a `\$debt`@ of `^%s`@ gold to the bank.`6\"`n");
	output_notl($session['user']['goldinbank']>=0?$balance:$debt,abs($session['user']['goldinbank']));
	output("`6Searching through all your pockets and pouches, you calculate that you currently have `^%s`6 gold on hand.`n`n", $session['user']['gold']);
	$dep = translate_inline("`^Deposit how much?");
	$pay = translate_inline("`^Pay off how much?");
	output_notl($session['user']['goldinbank']>=0?$dep:$pay);
	$dep = resurrection_bank_attribute(translate_inline("Deposit"));
	rawoutput(" <input id='input' name='amount' width=5 > <input type='submit' class='button' value='$dep'>");
	output("`n`iEnter 0 or nothing to deposit it all`i");
	rawoutput("</form>");
	rawoutput("<script language='javascript'>document.getElementById('input').focus();</script>",true);
  addnav("","bank.php?op=depositfinish");
}elseif($op=="depositfinish"){
	try {
		resurrection_bank_allow(array('amount'));
		$amount = resurrection_bank_amount();
	} catch (InvalidArgumentException $error) { resurrection_bank_failure($error); }
	resurrection_consume_action('bank-deposit', resurrection_bank_context('bank-deposit'));
	$notenough = translate_inline("`\$ERROR: Not enough gold in hand to deposit.`n`n`^You plunk your `&%s`^ gold on the counter and declare that you would like to deposit all `&%s`^ gold of it.`n`n`@Elessa`6 stares blandly at you for a few seconds until you become self conscious and recount your money, realizing your mistake.");
	$depositdebt = translate_inline("`@Elessa`6 records your deposit of `^%s `6gold in her ledger. \"`@Thank you, `&%s`@.  You now have a debt of `\$%s`@ gold to the bank and `^%s`@ gold in hand.`6\"");
	$depositbalance= translate_inline("`@Elessa`6 records your deposit of `^%s `6gold in her ledger. \"`@Thank you, `&%s`@.  You now have a balance of `^%s`@ gold in the bank and `^%s`@ gold in hand.`6\"");
	try {
		$deposit = resurrection_bank_mutation(function () use ($amount) {
			global $session;
			if ($amount==0){
				$amount=(int)$session['user']['gold'];
			}
			if ($amount>$session['user']['gold']) return array('short'=>$amount);
			debuglog("deposited " . $amount . " gold in the bank");
			$session['user']['goldinbank']+=$amount;
			$session['user']['gold']-=$amount;
			return array('deposited'=>$amount);
		});
	} catch (Throwable $error) { resurrection_bank_failure($error); }
	if (isset($deposit['short'])){
		output_notl($notenough,$session['user']['gold'],$deposit['short']);
	}else{
		output_notl($session['user']['goldinbank']>=0?$depositbalance:$depositdebt,$deposit['deposited'],$session['user']['name'], abs($session['user']['goldinbank']),$session['user']['gold']);
	}
}elseif($op=="borrow"){
	$maxborrow = $session['user']['level']*getsetting("borrowperlevel",20);
	$borrow = translate_inline("Borrow");
	$balance = translate_inline("`@Elessa`6 scans through her ledger, \"`@You have a balance of `^%s`@ gold in the bank.`6\"`n");
	$debt = translate_inline("`@Elessa`6 scans through her ledger, \"`@You have a `\$debt`@ of `^%s`@ gold to the bank.`6\"`n");
	$borrow = resurrection_bank_attribute($borrow);
	rawoutput("<form action='bank.php?op=withdrawfinish' method='POST'>".resurrection_bank_fields('bank-borrow'));
	output_notl($session['user']['goldinbank']>=0?$balance:$debt,abs($session['user']['goldinbank']));
	output("`6\"`@How much would you like to borrow `&%s`@?  At your level, you may borrow up to a total of `^%s`@ from the bank.`6\"`n`n",$session['user']['name'], $maxborrow);
	rawoutput(" <input id='input' name='amount' width=5 > <input type='hidden' name='borrow' value='x'><input type='submit' class='button' value='$borrow'>");
	output("`n(Money will be withdrawn until you have none left, the remainder will be borrowed)");
	rawoutput("</form>");
	rawoutput("<script language='javascript'>document.getElementById('input').focus();</script>");
	addnav("","bank.php?op=withdrawfinish");
}elseif($op=="withdraw"){
	$withdraw = translate_inline("Withdraw");
	$balance = translate_inline("`@Elessa`6 scans through her ledger, \"`@You have a balance of `^%s`@ gold in the bank.`6\"`n");
	$debt = translate_inline("`@Elessa`6 scans through her ledger, \"`@You have a `\$debt`@ of `^%s`@ gold in the bank.`6\"`n");
	$withdraw = resurrection_bank_attribute($withdraw);
	rawoutput("<form action='bank.php?op=withdrawfinish' method='POST'>".resurrection_bank_fields('bank-withdraw'));
	output_notl($session['user']['goldinbank']>=0?$balance:$debt,abs($session['user']['goldinbank']));
	output("`6\"`@How much would you like to withdraw `&%s`@?`6\"`n`n",$session['user']['name']);
	rawoutput("<input id='input' name='amount' width=5 > <input type='submit' class='button' value='$withdraw'>");
	output("`n`iEnter 0 or nothing to withdraw it all`i");
	rawoutput("</form>");
	rawoutput("<script language='javascript'>document.getElementById('input').focus();</script>");
	addnav("","bank.php?op=withdrawfinish");
}elseif($op=="withdrawfinish"){
	try {
		resurrection_bank_allow(array('amount','borrow'));
		$amount = resurrection_bank_amount();
		$borrowing = \Resurrection\Http\Input::string($_POST, 'borrow') != "";
	} catch (InvalidArgumentException $error) { resurrection_bank_failure($error); }
	// The withdraw form cannot borrow; only the borrow form's intent may.
	resurrection_consume_action($borrowing ? 'bank-borrow' : 'bank-withdraw', resurrection_bank_context($borrowing ? 'bank-borrow' : 'bank-withdraw'));
	try {
		$w = resurrection_bank_mutation(function () use ($amount, $borrowing) {
			global $session;
			$maxborrow = $session['user']['level']*getsetting("borrowperlevel",20);
			$w = resurrection_bank_withdrawal((int)$session['user']['gold'], (int)$session['user']['goldinbank'], $amount, $borrowing, (int)$maxborrow);
			$w['before'] = (int)$session['user']['goldinbank'];
			$w['maxborrow'] = $maxborrow;
			if ($w['outcome']=='short' || $w['outcome']=='limit') return $w;
			if ($w['withdrew'] > 0 || $w['outcome']=='withdraw') debuglog("withdrew {$w['amount']} gold from the bank");
			if ($w['outcome']=='borrow') debuglog("borrows {$w['borrowed']} gold from the bank");
			$session['user']['gold'] = $w['gold'];
			$session['user']['goldinbank'] = $w['bank'];
			return $w;
		});
	} catch (Throwable $error) { resurrection_bank_failure($error); }
	if ($w['outcome']=='short') {
		output("`\$ERROR: Not enough gold in the bank to withdraw.`^`n`n");
		output("`6Having been informed that you have `^%s`6 gold in your account, you declare that you would like to withdraw all `^%s`6 of it.`n`n", $w['before'], $w['amount']);
		output("`@Elessa`6 looks at you for a few moments without blinking, then advises you to take basic arithmetic.  You realize your folly and think you should try again.");
	}else if($w['outcome']=='limit'){
		output("`6Considering the `^%s`6 gold in your account, you ask to borrow `^%s`6. `@Elessa`6 peers through her ledger, runs a few calculations and then informs you that, at your level, you may only borrow up to a total of `^%s`6 gold.", $w['before'], $w['amount']-$w['before'], $w['maxborrow']);
	}else if($w['outcome']=='withdraw'){
		output("`@Elessa`6 records your withdrawal of `^%s `6gold in her ledger. \"`@Thank you, `&%s`@.  You now have a balance of `^%s`@ gold in the bank and `^%s`@ gold in hand.`6\"", $w['amount'],$session['user']['name'], abs($session['user']['goldinbank']),$session['user']['gold']);
	}else{
		if ($w['withdrew'] > 0) output("`6You withdraw your remaining `^%s`6 gold.", $w['withdrew']);
		if ($w['outcome']=='refused'){
			// Unreachable: the same inequality as the 'limit' refusal (1.1.2 printed the undefined $leftoborrow here).
			if ($w['withdrew'] > 0) {
				output("`6Additionally, you ask to borrow `^%s`6 gold.", $w['borrowed']);
			} else {
				output("`6You ask to borrow `^%s`6 gold.", $w['borrowed']);
			}
			output("`@Elessa`6 looks up your account and informs you that you may only borrow up to `^%s`6 gold.", $w['maxborrow']);
		}else{
			if ($w['withdrew'] > 0) {
				output("`6Additionally, you borrow `^%s`6 gold.", $w['borrowed']);
			} else {
				output("`6You borrow `^%s`6 gold.", $w['borrowed']);
			}
			output("`@Elessa`6 records your withdrawal of `^%s `6gold in her ledger. \"`@Thank you, `&%s`@.  You now have a debt of `\$%s`@ gold to the bank and `^%s`@ gold in hand.`6\"", $w['amount'],$session['user']['name'], abs($session['user']['goldinbank']),$session['user']['gold']);
		}
	}
}
villagenav();
addnav("Money");
if ($session['user']['goldinbank']>=0){
	addnav("W?Withdraw","bank.php?op=withdraw");
	addnav("D?Deposit","bank.php?op=deposit");
	if (getsetting("borrowperlevel",20)) addnav("L?Take out a Loan","bank.php?op=borrow");
}else{
	addnav("D?Pay off Debt","bank.php?op=deposit");
	if (getsetting("borrowperlevel",20)) addnav("L?Borrow More","bank.php?op=borrow");
}
if (getsetting("allowgoldtransfer",1)){
	if ($session['user']['level']>=getsetting("mintransferlev",3) || $session['user']['dragonkills']>0){
		addnav("M?Transfer Money","bank.php?op=transfer");
	}
}

page_footer();

?>