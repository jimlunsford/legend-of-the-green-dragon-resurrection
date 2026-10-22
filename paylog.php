<?php
require_once __DIR__ . '/src/Security/PaymentInfo.php';
// mail ready
// addnews ready
// translator ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/player_mutation.php");

tlschema("paylog");

check_su_access(SU_EDIT_PAYLOG);
/*
+-----------+---------------------+------+-----+---------+----------------+
| Field     | Type                | Null | Key | Default | Extra          |
+-----------+---------------------+------+-----+---------+----------------+
| payid     | int(11)             |      | PRI | NULL    | auto_increment |
| info      | text                |      |     |         |                |
| response  | text                |      |     |         |                |
| txnid     | varchar(32)         |      | MUL |         |                |
| amount    | float(9,2)          |      |     | 0.00    |                |
| name      | varchar(50)         |      |     |         |                |
| acctid    | int(11) unsigned    |      |     | 0       |                |
| processed | tinyint(4) unsigned |      |     | 0       |                |
| filed     | tinyint(4) unsigned |      |     | 0       |                |
| txfee     | float(9,2)          |      |     | 0.00    |                |
+-----------+---------------------+------+-----+---------+----------------+
*/
page_header("Payment Log");
require_once("lib/superusernav.php");
superusernav();

/**
 * 1.1.2 stamped each payment that had no process date with its own payment date on every view of this page.
 * That write is now this protected POST. The fresh schema's "not yet" date is NULL (1.1.2 used the zero date).
 * A record whose info fails its schema, or has no payment date, is left as it is.
 * @return array{rows:list<array{payid:int,date:string}>,skipped:int,context:string}
 */
function resurrection_paylog_pending(bool $lock = false): array {
	$rows = db_query("SELECT payid,info FROM ".db_prefix("paylog")." WHERE processdate IS NULL ORDER BY payid".($lock ? " FOR UPDATE" : ""), true);
	$pending = []; $skipped = 0;
	foreach ($rows as $row) {
		try { $date = \Resurrection\Security\PaymentInfo::date(\Resurrection\Security\PaymentInfo::read($row['info'])); }
		catch (DomainException $error) { $date = null; }
		if ($date === null) { $skipped++; continue; }
		$pending[] = ['payid'=>(int)$row['payid'], 'date'=>$date];
	}
	return ['rows'=>$pending, 'skipped'=>$skipped, 'context'=>hash('sha256', json_encode($pending, JSON_THROW_ON_ERROR))];
}

$op = httpget('op');
if ($op=="process"){
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Use the payment date form.'); }
	if (array_diff(array_keys($_POST), ['csrf_token','action_token']) !== []) { http_response_code(400); exit('Invalid request.'); }
	$pending = resurrection_paylog_pending();
	resurrection_consume_action('paylog-process', $pending['context']);
	$db = $GLOBALS['dbinfo']['connection'];
	$db->beginTransaction();
	try {
		$locked = resurrection_paylog_pending(true);
		if ($locked['context'] !== $pending['context']) throw new DomainException('Payment log changed.');
		foreach ($locked['rows'] as $row) {
			db_query("UPDATE ".db_prefix('paylog')." SET processdate=? WHERE payid=? AND processdate IS NULL", true, [$row['date'], $row['payid']]);
		}
		$db->commit();
	} catch (Throwable $error) {
		if ($db->inTransaction()) $db->rollBack();
		if ($error instanceof DomainException) { http_response_code(409); exit('This changed. Reload the payment log before acting.'); }
		http_response_code(500); exit('Nothing was changed. Reload the payment log and try again.');
	}
	redirect("paylog.php");
}
if ($op==""){
	$pending = resurrection_paylog_pending();
	if ($pending['rows'] !== []) {
		addnav("", "paylog.php?op=process");
		rawoutput("<form action='paylog.php?op=process' method='POST'>".resurrection_action_fields('paylog-process', $pending['context']));
		output("`^%s payments have no process date yet.`0 ", count($pending['rows']));
		rawoutput("<input type='submit' class='button' value='".htmlspecialchars(translate_inline("Record their payment dates"), ENT_QUOTES, 'UTF-8')."'></form>");
	}
	// MySQL 8 removed GROUP BY ... DESC; the order is the same. Payments not yet dated have no month.
	$sql = "SELECT substring(processdate,1,7) AS month, sum(amount)-sum(txfee) AS profit FROM ".db_prefix('paylog')." WHERE processdate IS NOT NULL GROUP BY month ORDER BY month DESC";
	$result = db_query($sql);
	addnav("Months");
	while ($row = db_fetch_assoc($result)){
		addnav(array("%s %s %s", date("M Y",strtotime($row['month']."-01")), getsetting("paypalcurrency", "USD"), $row['profit']),"paylog.php?month={$row['month']}");
	}
	$month = httpget('month');
	if ($month=="") $month = date("Y-m");
	if (!is_string($month) || !preg_match('/\A[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $month)) { http_response_code(400); exit('Invalid month.'); }
	$startdate = $month."-01 00:00:00";
	$enddate = date("Y-m-d H:i:s",strtotime("+1 month",strtotime($startdate)));
	$sql = "SELECT " . db_prefix("paylog") . ".*," . db_prefix("accounts") . ".name," . db_prefix("accounts") . ".donation," . db_prefix("accounts") . ".donationspent FROM " . db_prefix("paylog") . " LEFT JOIN " . db_prefix("accounts") . " ON " . db_prefix("paylog") . ".acctid = " . db_prefix("accounts") . ".acctid WHERE processdate>=? AND processdate < ? ORDER BY payid DESC";
	$result = db_query($sql, true, [$startdate, $enddate]);
	rawoutput("<table border='0' cellpadding='2' cellspacing='1' bgcolor='#999999'>");
	$type = translate_inline("Type");
	$gross = translate_inline("Gross");
	$fee = translate_inline("Fee");
	$net = translate_inline("Net");
	$processed = translate_inline("Processed");
	$id = translate_inline("Transaction ID");
	$who = translate_inline("Who");
	rawoutput("<tr class='trhead'><td>Date</td><td>$id</td><td>$type</td><td>$gross</td><td>$fee</td><td>$net</td><td>$processed</td><td>$who</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		// A record whose info fails its schema is listed with its own columns and blank payment fields.
		try { $info = \Resurrection\Security\PaymentInfo::read($row['info']); }
		catch (DomainException $error) { $info = []; }
		$paid = \Resurrection\Security\PaymentInfo::date($info);
		foreach (['txn_type','mc_gross','mc_currency','mc_fee','item_number'] as $field) $info[$field] = \Resurrection\Security\PaymentInfo::field($info, $field);
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'><td nowrap>");
		output_notl($paid === null ? "" : date("m/d H:i",strtotime($paid)));
		rawoutput("</td><td>");
		output_notl("%s",$row['txnid']);
		rawoutput("</td><td>");
		output_notl("%s", $info['txn_type']);
		rawoutput("</td><td nowrap>");
		output_notl("%.2f %s", $info['mc_gross'], $info['mc_currency']);
		rawoutput("</td><td>");
		output_notl("%s", $info['mc_fee']);
		rawoutput("</td><td>");
		output_notl("%.2f", (float)$info['mc_gross'] - (float)$info['mc_fee']);
		rawoutput("</td><td>");
		output_notl("%s", translate_inline($row['processed']?"`@Yes`0":"`\$No`0"));
		rawoutput("</td><td nowrap>");
		if ($row['name']>"") {
			rawoutput("<a href='user.php?op=edit&userid={$row['acctid']}'>");
			output_notl("`&%s`0 (%d/%d)", $row['name'],  $row['donationspent'],
					$row['donation']);
			rawoutput("</a>");
			addnav("","user.php?op=edit&userid={$row['acctid']}");
		}else{
			$amt = round((float)$info['mc_gross'] * 100,0);
			$memo = "";
			if (isset($info['memo'])) {
				$memo = $info['memo'];
			}
			$link = "donators.php?op=add1&name=".rawurlencode($memo)."&amt=$amt&txnid={$row['txnid']}";
			rawoutput("-=( <a href='$link' title=\"".htmlentities($info['item_number'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" alt=\"".htmlentities($info['item_number'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">[".htmlentities($memo, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."]</a> )=-");
			addnav("",$link);
		}
		rawoutput("</td></tr>");
	}
	rawoutput("</table>");
	addnav("Refresh","paylog.php");
}
page_footer();
?>