<?php
tlschema("petition");
popup_header("Petition for Help");
$post = httpallpost();
if ($op === 'submit' || count($post)>0){
    resurrection_require_post();
    try {
        $submitted = [];
        foreach (['charname'=>64, 'email'=>254, 'description'=>4096, 'abuse'=>4096] as $key=>$limit) {
            $submitted[$key] = \Resurrection\Http\Input::string($post, $key);
            if (strlen($submitted[$key]) > $limit) throw new InvalidArgumentException('Petition field too long.');
        }
        if (trim($submitted['description']) === '') throw new InvalidArgumentException('Description required.');
    } catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid petition.'); }
    $post = $submitted;
    if (!empty($session['loggedin'])) {
        $post['charname'] = $session['user']['name'];
        $post['email'] = $session['user']['emailaddress'];
    } else { $post['unverified'] = 'Character is not logged in.'; }
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? substr($address, 0, strrpos($address, '.') + 1) . '%' : $address;
    $rows = db_query('SELECT COUNT(petitionid) AS c FROM ' . db_prefix('petitions') . ' WHERE (ip LIKE ? OR (author=? AND author<>0)) AND date>?', true,
        [$ip, (int)$session['user']['acctid'], date('Y-m-d H:i:s', strtotime('-1 day'))]);
    $row = $rows[0];
	if ($row['c'] < 5 || (isset($session['user']['superuser']) && $session['user']['superuser']&~SU_DOESNT_GIVE_GROTTO)){
		$date = date("Y-m-d H:i:s");
		$post['cancelpetition'] = false;
		$post['cancelreason'] = 'The admins here decided they didn\'t like something about how you submitted your petition.  They were also too lazy to give a real reason.';
		$post = modulehook("addpetition",$post);
		if (!$post['cancelpetition']){
			unset($post['cancelpetition'], $post['cancelreason']);
            db_query('INSERT INTO ' . db_prefix('petitions') . ' (author,date,body,pageinfo,ip,id) VALUES (?,?,?,?,?,?)', true,
                [(int)$session['user']['acctid'], $date, output_array($post), 'Session diagnostics intentionally omitted.', $address, '']);
			// Fix the counter
			invalidatedatacache("petitioncounts");
			// If the admin wants it, email the petitions to them.
			if (getsetting("emailpetitions", 0)) {
				// Yeah, the format of this is ugly.
				require_once("lib/sanitize.php");
				$name = color_sanitize($session['user']['name']);
				$url = getsetting("serverurl",
					"http://".$_SERVER['SERVER_NAME'] .
					($_SERVER['SERVER_PORT']==80?"":":".$_SERVER['SERVER_PORT']) .
					dirname($_SERVER['REQUEST_URI']));
				if (!preg_match("/\\/$/", $url)) {
					$url = $url . "/";
					savesetting("serverurl", $url);
				}
				$tl_server = translate_inline("Server");
				$tl_author = translate_inline("Author");
				$tl_date = translate_inline("Date");
				$tl_body = translate_inline("Body");
				$tl_subject = sprintf_translate("New LoGD Petition at %s", $url);

				$msg  = "$tl_server: $url\n";
				$msg .= "$tl_author: $name\n";
				$msg .= "$tl_date : $date\n";
				$msg .= "$tl_body :\n".output_array($post)."\n";
				mail(getsetting("gameadminemail","postmaster@localhost.com"),$tl_subject, $msg);
			}
			output("Your petition has been sent to the server admin.");
			output("Please be patient, most server admins have jobs and obligations beyond their game, so sometimes responses will take a while to be received.");
		} else {
			output("`\$There was a problem with your petition!`n");
			output("`@Please read the information below carefully; there was a problem with your petition, and it was not submitted.\n");
			rawoutput("<blockquote>");
			output($post['cancelreason']);
			rawoutput("</blockquote>");
		}
	}else{
		output("`\$`bError:`b There have already been %s petitions filed from your network in the last day; to prevent abuse of the petition system, you must wait until there have been 5 or fewer within the last 24 hours.",$row['c']);
		output("If you have multiple issues to bring up with the staff of this server, you might think about consolidating those issues to reduce the overall number of petitions you file.");
	}
}else{
	output("`c`b`\$Before sending a petition, please make sure you have read the motd.`n");
	output("Petitions about problems we already know about just take up time we could be using to fix those problems.`b`c`n");
	rawoutput("<form action='petition.php?op=submit' method='POST'>");
    rawoutput(resurrection_csrf_field());
	if ($session['user']['loggedin']) {
		output("Your Character's Name: ");
		output_notl("%s", $session['user']['name']);
		rawoutput("<input type='hidden' name='charname' value=\"".htmlentities($session['user']['name'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
		output("`nYour email address: ");
		output_notl("%s", htmlentities($session['user']['emailaddress']));
		rawoutput("<input type='hidden' name='email' value=\"".htmlentities($session['user']['emailaddress'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
	} else {
		output("Your Character's Name: ");
		rawoutput("<input name='charname' value=\"".htmlentities($session['user']['name'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" size='46'>");
		output("`nYour email address: ");
		rawoutput("<input name='email' value=\"".htmlentities($session['user']['emailaddress'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" size='50'>");
		$nolog = translate_inline("Character is not logged in!!");
		rawoutput("<input name='unverified' type='hidden' value='$nolog'>");
	}
	output("`nDescription of the problem:`n");
    try { $problem = \Resurrection\Http\Input::string($_GET, 'problem'); }
    catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid petition.'); }
    $problem = htmlspecialchars($problem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (httpget('abuse') === 'yes') {
        rawoutput("<textarea name='description' cols='55' rows='7' class='input'></textarea>");
        rawoutput("<input type='hidden' name='abuse' value=\"$problem\"><br><hr><pre>$problem</pre><hr><br>");
    } else {
        rawoutput("<textarea name='description' cols='55' rows='7' class='input'>$problem</textarea>");
    }
	modulehook("petitionform",array());
	$submit = translate_inline("Submit");
	rawoutput("<br/><input type='submit' class='button' value='$submit'><br/>");
	output("Please be as descriptive as possible in your petition.");
	output("If you have questions about how the game works, please check out the <a href='petition.php?op=faq'>FAQ</a>.", true);
	output("Petitions about game mechanics will more than likely not be answered unless they have something to do with a bug.");
	output("Remember, if you are not signed in, and do not provide an email address, we have no way to contact you.");
	rawoutput("</form>");
}
?>
