<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/dhms.php");
require_once("lib/http.php");

tlschema("referers");

check_su_access(SU_EDIT_CONFIG);

require_once("lib/admin_tools.php");

// 1.1.2's housekeeping, kept: referers.php is the only place expired referers are removed (there is no maintenance
// cleanup for them), so every view deletes those not seen for `expirecontent` days. Only server values reach it, bound;
// 1.1.2 also ran an undefined query when the setting was 0.
$expire = (int)getsetting("expirecontent",180);
if ($expire > 0) db_query("DELETE FROM " . db_prefix("referers") . " WHERE last<?", true, [date("Y-m-d H:i:s",strtotime("-".$expire." days"))]);
$op = httpget('op');

/** 1.1.2's site of a referring URL: the text after http:// up to the first slash, as the 50-character column keeps it. */
function resurrection_referer_site(string $uri): string {
	$site = str_replace("http://","",$uri);
	if (strpos($site,"/")) $site = substr($site,0,strpos($site,"/"));
	// 1.1.2's non-strict MySQL cut a longer value to the column; strict SQL would refuse the whole rebuild.
	return mb_substr($site, 0, 50, 'UTF-8');
}

if ($op=="rebuild"){
	// 1.1.2 rebuilt every site on a GET link, writing with addslashes(). GET now asks; the POST, with CSRF and a one-use
	// intent, rewrites every site with bound values in one transaction.
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
		resurrection_tool_post();
		resurrection_consume_action('referers-rebuild', 'rebuild');
		resurrection_tool_transaction(function () {
			$rows = db_query("SELECT refererid,uri FROM " . db_prefix("referers") . " ORDER BY refererid FOR UPDATE", true);
			foreach ($rows as $row) {
				db_query("UPDATE " . db_prefix("referers") . " SET site=? WHERE refererid=?", true, [resurrection_referer_site((string)$row['uri']), (int)$row['refererid']]);
			}
		}, 'the referers');
	}
}
require_once("lib/superusernav.php");
superusernav();
addnav("Referer Options");
addnav("",$_SERVER['REQUEST_URI']);
// The order comes from the sort links only (1.1.2 pasted `sort` into ORDER BY). On the per-site summary, "URL" sorts by
// the site: the summary groups by site, and MySQL 8.4 refuses an ungrouped column there.
$orders = [''=>['count DESC','count DESC'], 'count DESC'=>['count DESC','count DESC'], 'count'=>['count','count'],
	'uri'=>['site','uri'], 'uri DESC'=>['site DESC','uri DESC'], 'last'=>['last','last'], 'last DESC'=>['last DESC','last DESC']];
$sort = httpget('sort');
if ($sort === false) $sort = "";
if (!is_string($sort) || !isset($orders[$sort])) { http_response_code(400); exit('Invalid sort.'); }
addnav("Refresh","referers.php?sort=".URLEncode($sort)."");
addnav("C?Sort by Count","referers.php?sort=count".($sort=="count DESC"?"":"+DESC"));
addnav("U?Sort by URL","referers.php?sort=uri".($sort=="uri"?"+DESC":""));
addnav("T?Sort by Time","referers.php?sort=last".($sort=="last DESC"?"":"+DESC"));

addnav("Rebuild Sites","referers.php?op=rebuild");

page_header("Referers");
if ($op=="rebuild" && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	$rebuild = htmlspecialchars(translate_inline("Rebuild Sites"), ENT_QUOTES, 'UTF-8');
	addnav("", "referers.php?op=rebuild");
	rawoutput("<form action='referers.php?op=rebuild' method='POST'>".resurrection_action_fields('referers-rebuild', 'rebuild')."<input type='submit' class='button' value='$rebuild'></form>");
}
[$order, $siteorder] = $orders[$sort];
$sql = "SELECT SUM(count) AS count, MAX(last) AS last,site FROM " . db_prefix("referers") . " GROUP BY site ORDER BY $order LIMIT 100";
$count = translate_inline("Count");
$last = translate_inline("Last");
$dest = translate_inline("Destination");
$none = translate_inline("`iNone`i");
$notset = translate_inline("`iNot set`i");
$skipped = translate_inline("`i%s records skipped (over a week old)`i");
rawoutput("<table border=0 cellpadding=2 cellspacing=1><tr class='trhead'><td>$count</td><td>$last</td><td>URL</td><td>$dest</td><td>IP</td></tr>");
$result = db_query($sql);
$number=db_num_rows($result);
for ($i=0;$i<$number;$i++){
	$row = db_fetch_assoc($result);

	rawoutput("<tr class='trdark'><td valign='top'>");
	output_notl("`b".$row['count']."`b");
	rawoutput("</td><td valign='top'>");
	$diffsecs = strtotime("now")-strtotime($row['last']);
	//output((int)($diffsecs/86400)."d ".(int)($diffsecs/3600%3600)."h ".(int)($diffsecs/60%60)."m ".(int)($diffsecs%60)."s");
	output_notl("`b".dhms($diffsecs)."`b");
	rawoutput("</td><td valign='top' colspan='3'>");
	output_notl("`b".($row['site']==""?$none:$row['site'])."`b");
	rawoutput("</td></tr>");

	$sql = "SELECT count,last,uri,dest,ip FROM " . db_prefix("referers") . " WHERE site=? ORDER BY {$siteorder} LIMIT 25";
	$result1 = db_query($sql, true, [$row['site']]);
	$skippedcount=0;
	$skippedtotal=0;
	// Its own count: 1.1.2 reused the site loop's $number here, so the list stopped after the first site whose URL
	// count was not above that site's position (with one URL each, only the first site was ever shown).
	$urls=db_num_rows($result1);
	for ($k=0;$k<$urls;$k++){
		$row1=db_fetch_assoc($result1);
		$diffsecs = strtotime("now")-strtotime($row1['last']);
		if ($diffsecs<=604800){
			rawoutput("<tr class='trlight'><td>");
			output_notl($row1['count']);
			rawoutput("</td><td valign='top'>");
			//output((int)($diffsecs/86400)."d".(int)($diffsecs/3600%3600)."h".(int)($diffsecs/60%60)."m".(int)($diffsecs%60)."s");
			output_notl(dhms($diffsecs));
			rawoutput("</td><td valign='top'>");
			// Quotes escaped in the attribute (1.1.2's ENT_COMPAT left a single quote able to end it); only http and
			// https addresses are links.
			if ($row1['uri']>"" && preg_match('#\Ahttps?://#i', $row1['uri']))
				rawoutput("<a href='".htmlspecialchars($row1['uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."' target='_blank'>".htmlspecialchars(mb_substr($row1['uri'],0,100,'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</a>");
			elseif ($row1['uri']>"")
				rawoutput(htmlspecialchars(mb_substr($row1['uri'],0,100,'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
			else
				output_notl($none);
			output_notl("`n");
			rawoutput("</td><td valign='top'>");
			output_notl($row1['dest']==''?$notset:$row1['dest']);
			rawoutput("</td><td valign='top'>");
			output_notl($row1['ip']==''?$notset:$row1['ip']);
			rawoutput("</td></tr>");
		}else{
			$skippedcount++;
			$skippedtotal+=$row1['count'];
		}
	}
	if ($skippedcount>0){
		rawoutput("<tr class='trlight'><td>$skippedtotal</td><td valign='top' colspan='4'>");
		output_notl(sprintf($skipped,$skippedcount));
		rawoutput("</td></tr>");
	}
	//output("</td></tr>",true);
}
rawoutput("</table>");
page_footer();
?>