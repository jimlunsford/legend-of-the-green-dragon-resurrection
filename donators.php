<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/admin_authority.php");

check_su_access(SU_EDIT_DONATIONS);

tlschema("donation");

page_header("Donator's Page");
require_once("lib/superusernav.php");
superusernav();


try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'add1', 'add2'], '');
	$ret = \Resurrection\Http\Input::string($_GET, 'ret');
	$id = \Resurrection\Http\Input::integer($_GET, 'id', 0, 1);
	if ($id > RESURRECTION_ADMIN_MAX_ID || ($op === 'add2' && $id === 0)) throw new InvalidArgumentException('Invalid account.');
	// The form values: POSTed by the search form and the confirmation forms, or prefilled by a GET link
	// (the user editor and the petition viewer; 1.1.2's payment log, now removed, also linked here).
	$values = array();
	foreach (['name', 'amt', 'reason', 'txnid'] as $key) {
		$value = \Resurrection\Http\Input::string($_POST, $key);
		if ($value == "" && $op !== 'add2') $value = \Resurrection\Http\Input::string($_GET, $key);
		if (!mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException('Invalid text.');
		$values[$key] = $value;
	}
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid donation request.'); }
$return = resurrection_donation_return($ret);
tlschema("nav");
addnav("Return whence you came",$return);
tlschema();

$add = translate_inline("Add Donation");
rawoutput("<form action='donators.php?op=add1&ret=".rawurlencode($ret)."' method='POST'>");
addnav("","donators.php?op=add1&ret=".rawurlencode($ret)."");
$name = $values['name'];
$amt = $values['amt'];
$reason = $values['reason'];
$txnid = $values['txnid'];
if ($reason == "") $reason = translate_inline("manual donation entry");


output("`bAdd Donation Points:`b`n");
output("Character: ");
rawoutput("<input name='name' value=\"".htmlentities($name, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output("`nPoints: ");
rawoutput("<input name='amt' size='3' value=\"".htmlentities($amt, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output("`nReason: ");
rawoutput("<input name='reason' size='30' value=\"".htmlentities($reason, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
rawoutput("<input type='hidden' name='txnid' value=\"".htmlentities($txnid, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output_notl("`n");
if ($txnid>"") output("For transaction: %s`n",$txnid);
rawoutput("<input type='submit' class='button' value='$add'>");
rawoutput("</form>");

addnav("Donations");
if ($op=="add2"){
	// 1.1.2 credited on this GET link, with the account ID, points and transaction pasted into SQL.
	// Only the protected POST form from the search below credits, once.
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Use the donation form.'); }
	$amt = $values['amt'];
	$reason = $values['reason'];
	$txnid = $values['txnid'];
	try {
		resurrection_admin_fields(['amt', 'reason', 'txnid']);
		$points = resurrection_donation_amount($amt);
		if ($points === null) throw new InvalidArgumentException('Invalid points.');
		$row = resurrection_admin_account($id, 'acctid,name,donation,donationspent');
		if ($row === null) throw new OutOfBoundsException('Account not found.');
		// A transaction credit settles exactly one unprocessed payment log row.
		$payment = $txnid > "" ? resurrection_donation_payment($txnid) : null;
		if ($txnid > "" && $payment === null) {
			$known = db_query('SELECT payid FROM ' . db_prefix('paylog') . ' WHERE txnid=?', true, [$txnid]);
			if ($known === []) throw new OutOfBoundsException('Transaction not found.');
			throw new DomainException('Transaction already processed.');
		}
		// Removing more points than the account holds, or adding past the column's limit, writes nothing.
		if ($txnid == "" && !resurrection_donation_balance_fits($row, $points)) throw new InvalidArgumentException('Points out of range.');
	} catch (Throwable $error) { resurrection_admin_failure($error, 'the donator page'); }
	resurrection_consume_action('donation-add-' . $id, resurrection_donation_context($row, $points, $reason, $txnid, $payment));
	try {
		$credit = resurrection_donation_credit($row, $points, $reason, $txnid, $payment);
	} catch (Throwable $error) { resurrection_admin_failure($error, 'the donator page'); }
	output("%s donation points added to %s`0, reason: `^%s`0",$amt,$credit['name'],$reason);
	// 1.1.2 returned a transaction credit to the payment log, which is removed (payments are disabled).
	if ($txnid>""){
		redirect("donators.php");
	}
	httpset('op', "");
	$op = "";
}

if ($op==""){
	$sql = "SELECT name,donation,donationspent FROM " . db_prefix("accounts") . " WHERE donation>0 ORDER BY donation DESC LIMIT 25";
	$result = db_query($sql);

	$name = translate_inline("Name");
	$points = translate_inline("Points");
	$spent = translate_inline("Spent");

	rawoutput("<table border='0' cellpadding='3' cellspacing='1'".resurrection_presentation("grid").">");
	rawoutput("<tr class='trhead'><td>$name</td><td>$points</td><td>$spent</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
		rawoutput("<td>");
		output_notl("`^%s`0",$row['name']);
		rawoutput("</td><td>");
		output_notl("`@%s`0", number_format($row['donation']));
		rawoutput("</td><td>");
		output_notl("`%%s`0", number_format($row['donationspent']));
		rawoutput("</td>");
		rawoutput("</tr>");
	}
	rawoutput("</table>",true);
}else if ($op=="add1"){
	try {
		$rows = resurrection_donation_search($name);
	} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid donation request.'); }
	$points = resurrection_donation_amount($amt);
	// With a return page, 1.1.2's confirmation links left the transaction out.
	if ($ret != "") $txnid = "";
	$payment = $txnid > "" ? resurrection_donation_payment($txnid) : null;
	output("Confirm the addition of %s points to:`n",$amt);
	if ($reason) output("(Reason: `^`b`i%s`i`b`0)`n`n",$reason);
	if ($points === null) output("`\$Points must be a whole number other than 0, from -9,999,999 to 9,999,999.`0`n");
	if ($txnid > "" && $payment === null) output("`\$That transaction is unknown or has already been processed.`0`n");
	$short = translate_inline("(not enough points to remove)");
	foreach ($rows as $row) {
		if ($points !== null && ($txnid == "" || $payment !== null) && resurrection_donation_balance_fits($row, $txnid > "" ? 0 : $points)) {
			resurrection_donation_form($row, $points, $reason, $txnid, $payment, $ret);
		}else{
			output_notl("%s (%s/%s)", $row['name'], $row['donation'], $row['donationspent']);
			if ($points !== null && $txnid == "" && !resurrection_donation_balance_fits($row, $points)) output_notl(" `\$%s`0", $short);
		}
		output_notl("`n");
	}
}
page_footer();
?>