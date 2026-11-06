<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready

/*
 * The mail popup (Phase 5 task 11, docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md): the inbox as a list, a message as
 * subject, sender, date, body and its actions, drawn by lib/mail/case_default.php and case_read.php. What each page does
 * is unchanged from 1.1.2: the same rows, the same links, the same forms posting the same fields. (1.1.2's tables were
 * kept for the 1.1.2 skins until Phase 5 task 15.)
 */

require_once __DIR__ . '/sanitize.php';

/**
 * Translated text without its colour, bold and italic codes, escaped for HTML. With $breaks, each `n inside the text
 * becomes a line break (leading and trailing ones are dropped).
 *
 * @param string $text
 * @param bool $breaks
 * @return string
 */
function resurrection_mail_plain($text, $breaks = false){
	$text = (string)$text;
	if (!$breaks) return resurrection_plain_text($text);
	$lines = array_map(
		fn ($line) => htmlspecialchars(trim(full_sanitize($line)), ENT_QUOTES, 'UTF-8'),
		explode('`n', trim($text))
	);
	return implode('<br>', array_filter($lines, fn ($line) => $line !== ''));
}

/**
 * A row's sender and subject as the inbox and the read view show them: System for the game's own mail (with its stored
 * subject and body translated), the sender's name for a player's.
 *
 * @param array<string, mixed> $row
 * @param list<string> $fields the stored fields to translate on the game's own mail
 * @return array<string, mixed>
 */
function resurrection_mail_row(array $row, array $fields){
	if ((string)$row['msgfrom'] === '0') {
		$row['name'] = translate_inline('`i`^System`0`i');
		foreach ($fields as $field) {
			$payload = \Resurrection\Security\MailContent::stored($row[$field]);
			if ($payload !== null) $row[$field] = call_user_func_array('sprintf_translate', $payload);
		}
	} elseif (!ctype_digit((string)$row['msgfrom'])) {
		$row['name'] = $row['msgfrom'];
	}
	return $row;
}

/**
 * The inbox: one list item a message, unread first. An unread message carries a New badge; the subject is the row's
 * link; the sender and the date follow in --muted. Each row's checkbox is labelled; Check All (assets/ui/showform.js)
 * and Delete Checked are secondary buttons of the same form 1.1.2 posted.
 *
 * @param mixed $result the inbox query's result
 * @return void
 */
function resurrection_mail_inbox($result){
	$charset = 'UTF-8';
	$nosubject = resurrection_mail_plain(translate_inline("`i(No Subject)`i"));
	$new = htmlspecialchars(translate_inline("New"), ENT_QUOTES, $charset);
	$select = translate_inline("Select");
	rawoutput("<form action='mail.php?op=process' method='post' class='rz-inbox-form'>");
	rawoutput(resurrection_csrf_field());
	rawoutput("<ul class='rz-inbox'>");
	while ($row = db_fetch_assoc($result)) {
		$row = resurrection_mail_row($row, ['subject']);
		$id = (int)$row['messageid'];
		$subject = trim((string)$row['subject']) !== '' ? resurrection_mail_plain($row['subject']) : $nosubject;
		$unread = !$row['seen'];
		rawoutput("<li class='rz-mail" . ($unread ? " rz-mail-unread" : "") . "'>"
			. "<label class='rz-mail-pick'><input type='checkbox' name='msg[]' value='$id'>"
			. "<span class='rz-sr'>" . htmlspecialchars($select, ENT_QUOTES, $charset) . ": $subject</span></label>"
			. "<div class='rz-mail-main'>"
			. ($unread ? "<span class='rz-badge rz-badge-new'>$new</span> " : "")
			. "<a class='rz-mail-subject' href='mail.php?op=read&id=$id'>$subject</a>"
			. "<span class='rz-mail-meta'><span class='rz-mail-from'>" . resurrection_mail_plain($row['name'] ?? '') . "</span> "
			. "<span class='rz-mail-date'>" . date("M d, h:i a", strtotime($row['sent'])) . "</span></span>"
			. "</div></li>");
	}
	rawoutput("</ul>");
	$checkall = htmlspecialchars(translate_inline("Check All"), ENT_QUOTES, $charset);
	$delchecked = htmlspecialchars(translate_inline("Delete Checked"), ENT_QUOTES, $charset);
	rawoutput("<div class='rz-mail-actions'><button type='button' class='button' data-rz-checkall='msg[]'>$checkall</button>"
		. "<input type='submit' class='button' value=\"$delchecked\"></div>");
	rawoutput("</form>");
	rawoutput(resurrection_form_script());
}

/**
 * One message: its subject (with New while unread), the sender and the date in --muted, the body, then Reply (the one
 * primary action, on a player's mail), Delete, Mark Unread and Report to Admin as 1.1.2 offered them, and Previous and
 * Next.
 *
 * @param array<string, mixed> $row the message, after resurrection_mail_row()
 * @param string $problem the abuse report's text
 * @param int $pid the previous message's id, or 0
 * @param int $nid the next message's id, or 0
 * @return void
 */
function resurrection_mail_read(array $row, $problem, $pid, $nid){
	$charset = 'UTF-8';
	$id = (int)$row['messageid'];
	$subject = trim((string)$row['subject']) !== '' ? resurrection_mail_plain($row['subject']) : resurrection_mail_plain(translate_inline("`i(No Subject)`i"));
	$text = fn ($label) => htmlspecialchars(translate_inline($label), ENT_QUOTES, $charset);
	rawoutput("<article class='rz-mail-message'>");
	rawoutput("<h2 class='rz-mail-heading'>" . (!$row['seen'] ? "<span class='rz-badge rz-badge-new'>" . $text("New") . "</span> " : "") . $subject . "</h2>");
	rawoutput("<p class='rz-mail-meta'><span class='rz-mail-label'>" . $text("From:") . "</span> <span class='rz-mail-from'>" . resurrection_mail_plain($row['name'] ?? '') . "</span>"
		. " <span class='rz-mail-label'>" . $text("Sent:") . "</span> <span class='rz-mail-date'>" . htmlspecialchars((string)$row['sent'], ENT_QUOTES, $charset) . "</span></p>");
	rawoutput("<div class='rz-mail-body'>" . resurrection_appoencode_closed(str_replace("\n", "`n", (string)$row['body'])) . "</div>");
	rawoutput("<div class='rz-mail-actions'>");
	if ($row['msgfrom'] > 0 && is_numeric($row['msgfrom'])) {
		rawoutput("<a href='mail.php?op=write&replyto=$id' class='button rz-button-primary'>" . $text("Reply") . "</a>");
	}
	foreach (['del' => 'Delete', 'unread' => 'Mark Unread'] as $action => $label) {
		rawoutput("<form action='mail.php?op=$action' method='post' class='rz-inline'>" . resurrection_csrf_field()
			. "<input type='hidden' name='id' value='$id'><button type='submit' class='button'>" . $text($label) . "</button></form>");
	}
	// Don't allow reporting of system messages as abuse.
	if ((string)$row['msgfrom'] !== '0') {
		rawoutput("<a href=\"petition.php?problem=" . rawurlencode($problem) . "&abuse=yes\" class='button'>" . $text("Report to Admin") . "</a>");
	}
	rawoutput("</div>");
	rawoutput("<nav class='rz-pager' aria-label='" . $text("Messages") . "'>");
	foreach ([[$pid, "< Previous"], [$nid, "Next >"]] as [$target, $label]) {
		rawoutput($target > 0
			? "<a href='mail.php?op=read&id=" . (int)$target . "' class='button'>" . $text($label) . "</a>"
			: "<span class='button rz-pager-off' aria-disabled='true'>" . $text($label) . "</span>");
	}
	rawoutput("</nav></article>");
}
