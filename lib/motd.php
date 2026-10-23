<?php
require_once __DIR__ . '/../src/Security/MotdPoll.php';
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
require_once __DIR__ . '/../src/Http/Input.php';
require_once __DIR__ . '/player_mutation.php';
// addnews ready
// translator ready
// mail ready

/** A request's MoTD item number: 0 for none (a new item), otherwise an existing row's ID. */
function resurrection_motd_id(mixed $id): int {
	if ($id === false || $id === '') return 0;
	if (!is_string($id) || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $id) || (int)$id > 4294967295) {
		throw new InvalidArgumentException('Invalid item.');
	}
	return (int)$id;
}

/** @return array<string, mixed>|null */
function resurrection_motd_row(int $id, bool $lock = false): ?array {
	if ($id < 1) return null;
	$rows = db_query("SELECT motditem,motdtitle,motdbody,motddate,motdtype,motdauthor FROM " . db_prefix("motd") . " WHERE motditem=?" . ($lock ? " FOR UPDATE" : ""), true, [$id]);
	return count($rows) === 1 ? $rows[0] : null;
}

/** A form is bound to the item it was shown with (or to "new"). @param array<string, mixed>|null $row */
function resurrection_motd_context(?array $row): string {
	return hash('sha256', json_encode($row === null ? ['new'] : [(string)$row['motditem'], (string)$row['motdtitle'], (string)$row['motdbody'],
		(string)$row['motddate'], (string)$row['motdtype'], (string)$row['motdauthor']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/** @param list<string> $fields */
function resurrection_motd_fields(array $fields): void {
	if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$fields]) !== []) throw new InvalidArgumentException('Unexpected field.');
}

/** Subject and body as stored: motdtitle is varchar(200), motdbody TEXT, both UTF-8. */
function resurrection_motd_text(mixed $value, int $characters, int $bytes): string {
	if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $characters || strlen($value) > $bytes) {
		throw new InvalidArgumentException('Invalid text.');
	}
	return $value;
}

function resurrection_motd_failure(Throwable $error): never {
	if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid request.'); }
	if ($error instanceof OutOfBoundsException) { http_response_code(404); exit('That item no longer exists.'); }
	if ($error instanceof DomainException) { http_response_code(409); exit('This changed. Reload the MoTD before acting.'); }
	http_response_code(500); exit('Nothing was changed. Reload the MoTD and try again.');
}

/** One transaction for a MoTD write; the caches are cleared after commit. */
function resurrection_motd_transaction(callable $action): void {
	$db = $GLOBALS['dbinfo']['connection'];
	$db->beginTransaction();
	try {
		$action();
		$db->commit();
	} catch (Throwable $error) {
		if ($db->inTransaction()) $db->rollBack();
		resurrection_motd_failure($error);
	}
	invalidatedatacache("motd");
	invalidatedatacache("lastmotd");
	invalidatedatacache("motddate");
}

function motd_admin($id, $poll=false) {
	global $session;
	if ($session['user']['superuser'] & SU_POST_MOTD) {
		$ed = translate_inline("Edit");
		$del = translate_inline("Del");
		$confirm = translate_inline("Are you sure you want to delete this item?");
		output_notl("[ ");
		if (!$poll) {
			rawoutput("<a href='motd.php?op=add".($poll?"poll":"")."&id=$id'>$ed</a> | ");
		}
		// 1.1.2's Del link deleted on GET; it is now a protected POST bound to the item.
		$row = resurrection_motd_row((int)$id);
		if ($row !== null) {
			rawoutput("<form action='motd.php?op=del&id=".(int)$id."' method='POST' style='display:inline' onSubmit=\"return confirm('".htmlspecialchars(addslashes($confirm), ENT_QUOTES, 'UTF-8')."');\">".
				resurrection_action_fields('motd-del-'.(int)$id, resurrection_motd_context($row)).
				"<button type='submit' class='button'>".htmlspecialchars($del, ENT_QUOTES, 'UTF-8')."</button></form> ]");
		}
	}
}

function motditem($subject,$body,$author,$date,$id){
	if ($date)
		rawoutput("<a name='motd".date("YmdHis",strtotime($date))."'>");
	output_notl("`b`^%s`0`b", $subject);
	if ($id > "") {
		motd_admin($id);
	}
	if ($date || $author) output_notl("`n");
	if ($author > "") {
		output_notl("`3%s`0", $author);
	}
	if ($date>"")
		output_notl("`0 &#150; `#%s`0", $date, true);
	if ($date || $author) output_notl("`n");

	// Colour and format codes as in 1.1.2; HTML in the stored text is shown as text.
	output_notl("`2%s`0", nltoappon($body));
	if ($date) rawoutput("</a>");
	rawoutput("<hr>");
}

function pollitem($id,$subject,$body,$author,$date,$showpoll=true){
	global $session;
	$id = (int)$id;
	$sql = "SELECT count(resultid) AS c, MAX(choice) AS choice FROM " . db_prefix("pollresults") . " WHERE motditem=? AND account=?";
	$result = db_query($sql, true, [$id, (int)($session['user']['acctid'] ?? 0)]);
	$row = db_fetch_assoc($result);
	$choice = $row['choice'];
	// A stored poll that fails its schema is listed by its subject only, without a ballot.
	try { $body = \Resurrection\Security\MotdPoll::read($body); }
	catch (DomainException $error) { $body = null; }
	$showform = $session['user']['loggedin'] && $showpoll && $body !== null;

	$poll = translate_inline("Poll:");
	output_notl("`b`&%s `^%s`0`b", $poll, $subject);
	if ($showpoll) motd_admin($id, true);
	// The ballot opens after the admin controls: the Del form cannot sit inside it.
	if ($showform) {
		rawoutput("<form action='motd.php?op=vote' method='POST'>");
		rawoutput(resurrection_action_fields('motd-vote-'.$id, resurrection_motd_context(resurrection_motd_row($id))));
		rawoutput("<input type='hidden' name='motditem' value='$id'>",true);
	}
	output_notl("`n`3%s`0", $author);
	output_notl(" &#150; `#%s`0`n", $date, true);
	if ($body === null) {
		rawoutput("<hr>",true);
		return;
	}
	output_notl("`2%s`0`n", stripslashes($body['body']));
	$sql = "SELECT count(resultid) AS c, choice FROM " . db_prefix("pollresults") . " WHERE motditem=? GROUP BY choice ORDER BY choice";
	$result = db_query_cached($sql,"poll-$id", 900, [$id]);
	$choices=array();
	$totalanswers=0;
	$maxitem = 0;
	while ($row = db_fetch_assoc($result)) {
		$choices[$row['choice']]=$row['c'];
		$totalanswers+=$row['c'];
		if ($row['c']>$maxitem) $maxitem = $row['c'];
	}
	while (list($key,$val)=resurrection_array_next($body['opt'])){
		if (trim($val)!=""){
			if ($totalanswers<=0) $totalanswers=1;
			$percent = 0;
			if(isset($choices[$key])) {
				$percent = round($choices[$key] / $totalanswers * 100,1);
			}
			if ($showform) {
				rawoutput("<input type='radio' name='choice' value='$key'".($choice==$key?" checked":"").">");
			}
			output_notl("%s (%s - %s%%)`n", stripslashes($val),
					(isset($choices[$key])?(int)$choices[$key]:0), $percent);
			if ($maxitem==0 || !isset($choices[$key])){
				$width=1;
			} else {
				$width = round(($choices[$key]/$maxitem) * 400,0);
			}
			$width = max($width,1);
			rawoutput("<img src='images/rule.gif' width='$width' height='2' alt='$percent'><br>");
		}
	}
	if ($showform) {
		$vote = translate_inline("Vote");
		rawoutput("<input type='submit' class='button' value='$vote'></form>");
	}
	rawoutput("<hr>",true);
}

/**
 * The vote must name a choice the poll lists. A stored poll that fails its schema refuses every vote (409).
 * @param array<string, mixed> $poll
 */
function resurrection_motd_choice(array $poll, int $choice): void {
	if (!array_key_exists($choice, \Resurrection\Security\MotdPoll::choices(\Resurrection\Security\MotdPoll::read($poll['motdbody'])))) {
		throw new InvalidArgumentException('Not a choice of this poll.');
	}
}

/**
 * A vote: a protected POST from a signed-in player, for an existing poll and one of the choices it lists.
 * As in 1.1.2, a player has one vote per poll, and voting again replaces it.
 */
function motd_vote(): never {
	global $session;
	resurrection_require_post();
	if (empty($session['loggedin']) || (int)($session['user']['acctid'] ?? 0) < 1) { http_response_code(403); exit('Sign in to vote.'); }
	try {
		resurrection_motd_fields(['motditem', 'choice']);
		$item = \Resurrection\Http\Input::integer($_POST, 'motditem', 0, 1);
		$choice = \Resurrection\Http\Input::integer($_POST, 'choice', -1, 0);
		if ($item < 1 || $item > 4294967295 || $choice < 0) throw new InvalidArgumentException('Invalid vote.');
		$poll = resurrection_motd_row($item);
		if ($poll === null || (int)$poll['motdtype'] !== 1) throw new OutOfBoundsException('Unknown poll.');
		resurrection_motd_choice($poll, $choice);
	} catch (Throwable $error) { resurrection_motd_failure($error); }
	$context = resurrection_motd_context($poll);
	resurrection_consume_action('motd-vote-'.$item, $context);
	$account = (int)$session['user']['acctid'];
	$db = $GLOBALS['dbinfo']['connection'];
	$db->beginTransaction();
	try {
		// The poll row's lock orders concurrent votes, so a player never holds two.
		$locked = resurrection_motd_row($item, true);
		if ($locked === null) throw new OutOfBoundsException('Unknown poll.');
		if (resurrection_motd_context($locked) !== $context) throw new DomainException('Poll changed.');
		resurrection_motd_choice($locked, $choice);
		db_query("DELETE FROM " . db_prefix("pollresults") . " WHERE motditem=? AND account=?", true, [$item, $account]);
		db_query("INSERT INTO " . db_prefix("pollresults") . " (choice,account,motditem) VALUES (?,?,?)", true, [$choice, $account, $item]);
		$db->commit();
	} catch (Throwable $error) {
		if ($db->inTransaction()) $db->rollBack();
		resurrection_motd_failure($error);
	}
	invalidatedatacache("poll-$item");
	header("Location: motd.php");
	exit();
}

/**
 * A request for an op that needs SU_POST_MOTD from someone without it. 1.1.2 penalized a signed-in player on
 * any request, GET included, so a link on another site could cost a player experience. The penalty now needs
 * the player's own protected POST; any other such request is refused and changes nothing.
 */
function motd_refuse(): void {
	global $session;
	if (empty($session['user']['loggedin']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		http_response_code(403); exit('This action is not authorized.');
	}
	resurrection_require_post();
	try {
		resurrection_player_mutation(function () {
			global $session;
			$session['user']['experience'] =
				(int)round($session['user']['experience']*0.9,0);
			addnews("%s was penalized for attempting to defile the gods.",
					$session['user']['name']);
		});
	} catch (Throwable $error) { resurrection_motd_failure($error); }
	output("You've attempted to defile the gods.  You are struck with a wand of forgetfulness.  Some of what you knew, you no longer know.");
}

function motd_form($id) {
	global $session;
	try {
		$id = resurrection_motd_id($id);
		$row = resurrection_motd_row($id);
		// Polls cannot be edited after they are begun.
		if ($row !== null && (int)$row['motdtype'] !== 0) throw new InvalidArgumentException('Polls cannot be edited.');
		$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
		if ($post) {
			resurrection_require_post();
			resurrection_motd_fields(['subject', 'body', 'preview', 'changeauthor', 'changedate']);
			foreach (['subject', 'body', 'preview', 'changeauthor', 'changedate'] as $field) \Resurrection\Http\Input::string($_POST, $field);
		}
		$subject = $post ? resurrection_motd_text(httppost('subject') === false ? '' : httppost('subject'), 200, 800) : '';
		$body = $post ? resurrection_motd_text(httppost('body') === false ? '' : httppost('body'), 65535, 65535) : '';
		$preview = httppost('preview');
	} catch (Throwable $error) { resurrection_motd_failure($error); }
	if ($subject=="" || $body=="" || $preview>""){
		$edit = translate_inline("Edit a MoTD");
		$add = translate_inline("Add a MoTD");
		$ret = translate_inline("Return");

		if ($row !== null){
			$authors = db_query("SELECT name FROM " . db_prefix("accounts") . " WHERE acctid=?", true, [(int)$row['motdauthor']]);
			$row['motdauthorname'] = $authors[0]['name'] ?? '';
			$msg = $edit;
		}else{
			$row = array(
				"motditem"=>0,
				"motdauthorname"=>"",
				"motdtitle"=>"",
				"motdbody"=>"",
			);
			$msg = $add;
		}
		output_notl("`b%s`b", $msg);
		rawoutput("[ <a href='motd.php'>$ret</a> ]<br>");

		rawoutput("<form action='motd.php?op=add&id={$row['motditem']}' method='POST'>");
		rawoutput(resurrection_action_fields('motd-edit-'.(int)$row['motditem'], resurrection_motd_context((int)$row['motditem'] > 0 ? resurrection_motd_row((int)$row['motditem']) : null)));
		addnav("","motd.php?op=add&id={$row['motditem']}");
		if ($row['motdauthorname']>"")
			output("Originally by `@%s`0 on %s`n", $row['motdauthorname'],
					$row['motddate']);
		// 1.1.2's stripslashes() here undid magic quotes, which no longer apply to request values.
		if ($subject>"") $row['motdtitle'] = $subject;
		if ($body>"") $row['motdbody'] = $body;
		if ($preview>""){
			if (httppost('changeauthor') || $row['motdauthorname']=="")
				$row['motdauthorname']=$session['user']['name'];
			if (httppost('changedate') || !isset($row['motddate']) || $row['motddate']=="")
				$row['motddate']=date("Y-m-d H:i:s");
			motditem($row['motdtitle'], $row['motdbody'],
					$row['motdauthorname'],$row['motddate'], "");
		}
		output("Subject: ");
		rawoutput("<input type='text' size='50' name='subject' value=\"".HTMLEntities(stripslashes($row['motdtitle']), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"><br/>");
		output("Body:`n");
		rawoutput("<textarea align='right' class='input' name='body' cols='37' rows='5'>".HTMLEntities(stripslashes($row['motdbody']), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea><br/>");
		if ($row['motditem']>0){
			output("Options:`n");
			rawoutput("<input type='checkbox' value='1' name='changeauthor'".(httppost('changeauthor')?" checked":"").">");
			output("Change Author`n");
			rawoutput("<input type='checkbox' value='1' name='changedate'".(httppost('changedate')?" checked":"").">");
			output("Change Date (force popup again)`n");
		}
		$prev = translate_inline("Preview");
		$sub = translate_inline("Submit");
		rawoutput("<input type='submit' class='button' name='preview' value='$prev'> <input type='submit' class='button' value='$sub'></form>");
	}else{
		// Save: bound to the item as the form showed it; an item edited or deleted meanwhile is refused.
		resurrection_consume_action('motd-edit-'.$id, resurrection_motd_context($row));
		$changeauthor = (bool)httppost('changeauthor');
		$changedate = (bool)httppost('changedate');
		resurrection_motd_transaction(function () use ($id, $row, $subject, $body, $changeauthor, $changedate) {
			global $session;
			if ($row === null) {
				db_query("INSERT INTO " . db_prefix("motd") . " (motdtitle,motdbody,motddate,motdauthor) VALUES (?,?,?,?)", true,
					[$subject, $body, date("Y-m-d H:i:s"), (int)$session['user']['acctid']]);
				return;
			}
			$locked = resurrection_motd_row($id, true);
			if ($locked === null || resurrection_motd_context($locked) !== resurrection_motd_context($row)) throw new DomainException('Item changed.');
			$sets = ['motdtitle=?', 'motdbody=?']; $values = [$subject, $body];
			if ($changeauthor) { $sets[] = 'motdauthor=?'; $values[] = (int)$session['user']['acctid']; }
			if ($changedate) { $sets[] = 'motddate=?'; $values[] = date("Y-m-d H:i:s"); }
			$values[] = $id;
			db_query("UPDATE " . db_prefix("motd") . " SET " . implode(',', $sets) . " WHERE motditem=?", true, $values);
		});
		header("Location: motd.php");
		exit();
	}
}

function motd_poll_form() {
	global $session;
	try {
		$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
		if ($post) {
			resurrection_require_post();
			resurrection_motd_fields(['subject', 'body', 'opt']);
			foreach (['subject', 'body'] as $field) \Resurrection\Http\Input::string($_POST, $field);
		}
		$subject = $post ? resurrection_motd_text(httppost('subject') === false ? '' : httppost('subject'), 200, 800) : '';
		$body = $post ? resurrection_motd_text(httppost('body') === false ? '' : httppost('body'), 65535, 65535) : '';
	} catch (Throwable $error) { resurrection_motd_failure($error); }
	if ($subject=="" || $body==""){
		output("`\$NOTE:`^ Polls cannot be edited after they are begun in order to ensure fairness and accuracy of results.`0`n`n");
		rawoutput("<form action='motd.php?op=addpoll' method='POST'>".resurrection_action_fields('motd-poll', resurrection_motd_context(null)));
		addnav("","motd.php?op=add");
		output("Subject: ");
		rawoutput("<input type='text' size='50' name='subject' value=\"".HTMLEntities($subject, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"><br/>");
		output("Body:`n");
		rawoutput("<textarea class='input' name='body' cols='37' rows='5'>".HTMLEntities($body, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea><br/>");
		$option = translate_inline("Option");
		output("Choices:`n");
		$pollitem = "$option <input name='opt[]'><br/>";
		rawoutput($pollitem);
		rawoutput($pollitem);
		rawoutput($pollitem);
		rawoutput($pollitem);
		rawoutput($pollitem);
		rawoutput("<div id='hidepolls'>");
		rawoutput("</div>");
		rawoutput("<script language='JavaScript'>document.getElementById('hidepolls').innerHTML = '';</script>",true);
		$addi = translate_inline("Add Poll Item");
		$add = translate_inline("Add");
		rawoutput("<a href=\"#\" onClick=\"javascript:document.getElementById('hidepolls').innerHTML += '".addslashes($pollitem)."'; return false;\">$addi</a><br>");
		rawoutput("<input type='submit' class='button' value='$add'></form>");
	}else{
		try {
			$opt = $_POST['opt'] ?? [];
			if (!is_array($opt) || !array_is_list($opt)) throw new InvalidArgumentException('Invalid options.');
			foreach ($opt as $label) resurrection_motd_text($label, 65535, 65535);
			// The stored form 1.1.2 wrote: request values carrying magic quotes' slashes, shown through stripslashes().
			$encoded = \Resurrection\Security\MotdPoll::encode(array("body"=>addslashes($body),"opt"=>array_map('addslashes', $opt)));
		} catch (DomainException $error) { resurrection_motd_failure(new InvalidArgumentException('Invalid poll.')); }
		catch (Throwable $error) { resurrection_motd_failure($error); }
		resurrection_consume_action('motd-poll', resurrection_motd_context(null));
		resurrection_motd_transaction(function () use ($subject, $encoded) {
			global $session;
			db_query("INSERT INTO " . db_prefix("motd") . " (motdtitle,motdbody,motddate,motdtype,motdauthor) VALUES (?,?,?,1,?)", true,
				[$subject, $encoded, date("Y-m-d H:i:s"), (int)$session['user']['acctid']]);
		});
		header("Location: motd.php");
		exit();
	}
}

function motd_del($id) {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Use the Del button.'); }
	try {
		$id = resurrection_motd_id($id);
		resurrection_motd_fields([]);
		$row = resurrection_motd_row($id);
		if ($row === null) throw new OutOfBoundsException('Unknown item.');
	} catch (Throwable $error) { resurrection_motd_failure($error); }
	resurrection_consume_action('motd-del-'.$id, resurrection_motd_context($row));
	resurrection_motd_transaction(function () use ($id, $row) {
		$locked = resurrection_motd_row($id, true);
		if ($locked === null || resurrection_motd_context($locked) !== resurrection_motd_context($row)) throw new DomainException('Item changed.');
		db_query("DELETE FROM " . db_prefix("motd") . " WHERE motditem=?", true, [$id]);
	});
	header("Location: motd.php");
	exit();
}

?>
