<?php
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");

check_su_access(SU_EDIT_COMMENTS);

tlschema("badword");

require_once("lib/admin_tools.php");

$op = httpget('op');

/**
 * The stored word list of one type ('good' or 'nasty'), as 1.1.2 read it: the first row's words split on spaces.
 * @return array{words: list<string>, context: string}
 */
function resurrection_badword_list(string $type, bool $lock = false): array {
	$rows = db_query("SELECT words FROM " . db_prefix("nastywords") . " WHERE type=?" . ($lock ? " FOR UPDATE" : ""), true, [$type]);
	$words = explode(" ", (string)($rows[0]['words'] ?? ""));
	return ['words'=>$words, 'context'=>resurrection_tool_context([$type, array_map(fn($row) => (string)$row['words'], $rows)])];
}

/** 1.1.2's add and remove rules for one list. The posted word is raw: magic quotes, which 1.1.2's stripslashes() undid, are gone. */
function resurrection_badword_apply(string $op, array $words, string $word): array {
	if ($op=="add" || $op=="addgood"){
		// The nasty list automagically escapes every unescaped single quote (its words become patterns); the good list
		// does not. Line feeds and carriage returns are removed.
		if ($op=="add") $word = preg_replace('/(?<!\\\\)\'/', '\\\'', $word);
		$word = str_replace("\n", '', $word);
		$word = str_replace("\r", '', $word);
		if ($word !== '') array_push($words, $word);
	} else {
		// false if not found; $removekey can be 0
		$removekey = array_search($word, $words);
		if ($removekey !== false) unset($words[$removekey]);
	}
	return array_values($words);
}

// 1.1.2 rewrote a list on any request to an add or remove op, without CSRF, with addslashes() and with the delete and
// insert as separate statements. Each change is now a POST with CSRF and a one-use intent bound to the list as it was
// shown, and the list is locked, rewritten with bound values and committed in one transaction.
$types = ['add'=>'nasty', 'remove'=>'nasty', 'addgood'=>'good', 'removegood'=>'good'];
if (in_array($op, ['add', 'remove', 'addgood', 'removegood'], true)) {
	resurrection_tool_post(['word']);
	$word = resurrection_tool_text($_POST, 'word', 65535);
	$type = $types[$op];
	$shown = resurrection_badword_list($type);
	resurrection_consume_action('badword-' . $op, $shown['context']);
	resurrection_tool_transaction(function () use ($op, $type, $word, $shown) {
		$list = resurrection_badword_list($type, true);
		if ($list['context'] !== $shown['context']) throw new DomainException('Word list changed.');
		$joined = join(" ", resurrection_badword_apply($op, $list['words'], $word));
		if (strlen($joined) > 65535) throw new DomainException('Word list too long.');
		db_query("DELETE FROM " . db_prefix("nastywords") . " WHERE type=?", true, [$type]);
		db_query("INSERT INTO " . db_prefix("nastywords") . " (words,type) VALUES (?,?)", true, [$joined, $type]);
	}, 'the word list');
	invalidatedatacache($type == "good" ? "goodwordlist" : "nastywordlist");
}

//yuck, this page is a mess, but it gets the job done.
page_header("Bad word editor");

require_once("lib/superusernav.php");
superusernav();
addnav("Bad Word Editor");

addnav("Refresh the list","badword.php");
output("`7Here you can edit the words that the game filters.  Using * at the start or end of a word will be a wildcard matching anything else attached to the word.  These words are only filtered if bad word filtering is turned on in the game settings page.`n`n`0");

$test = htmlspecialchars(translate_inline("Test"), ENT_QUOTES, 'UTF-8');
rawoutput("<form action='badword.php?op=test' method='POST'>".resurrection_csrf_field());
addnav("","badword.php?op=test");
output("`7Test a word:`0");
rawoutput("<input name='word'><input type='submit' class='button' value='$test'></form>");
if ($op=="test"){
	// Writes nothing; a POST with CSRF, as the form is.
	resurrection_require_post();
	if (array_diff(array_keys($_POST), ['csrf_token', 'word']) !== []) { http_response_code(400); exit('Invalid request.'); }
	$word = resurrection_tool_text($_POST, 'word', 65535);
	$return = soap($word,true);
	if ($return == $word)
		output("`7\"%s\" does not trip any filters.`0`n`n", $word);
	else
		output("`7%s`0`n`n", $return);
}

/** An add or remove form, bound to the list it was shown with. */
function resurrection_badword_form(string $op, string $label, string $context): void {
	rawoutput("<form action='badword.php?op=$op' method='POST'>".resurrection_action_fields('badword-' . $op, $context));
	addnav("","badword.php?op=$op");
	output("`7$label`0");
	rawoutput("<input name='word'><input type='submit' class='button' value='".htmlspecialchars(translate_inline($op == "add" || $op == "addgood" ? "Add" : "Remove"), ENT_QUOTES, 'UTF-8')."'></form>");
}

output_notl(resurrection_presentation("larger"), true);
output("`7`bGood Words`b`0");
rawoutput(resurrection_presentation("larger-end"));
output("`7 (bad word exceptions)`0`n");

$good = resurrection_badword_list('good');
resurrection_badword_form('addgood', "Add a word:", $good['context']);
resurrection_badword_form('removegood', "Remove a word:", $good['context']);
show_word_list($good['words']);

output_notl("`0`n`n");
rawoutput(resurrection_presentation("larger"));
output("`7`bNasty Words`b`0");
rawoutput(resurrection_presentation("larger-end"));
output_notl("`n");

$nasty = resurrection_badword_list('nasty');
resurrection_badword_form('add', "Add a word:", $nasty['context']);
resurrection_badword_form('remove', "Remove a word:", $nasty['context']);
show_word_list($nasty['words']);
output_notl("`0");
page_footer();

function show_word_list($words){
	sort($words);
	$lastletter="";
	while (list($key,$val)=resurrection_array_next($words)){
		if (trim($val)==""){
			unset($words[$key]);
		}else{
			if (substr($val,0,1)!=$lastletter){
				$lastletter = substr($val,0,1);
				output_notl("`n`n`^`b%s`b`@`n", strtoupper($lastletter));
			}
			output_notl("%s ", $val);
		}
	}
}
?>