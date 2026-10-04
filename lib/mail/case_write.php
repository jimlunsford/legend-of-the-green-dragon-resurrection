<?php
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if ($mailDraft['candidates'] === []) {
    output('No one was found who matches your search. Please try again.`n');
    require 'lib/mail/case_address.php';
    return;
}
$reply = $mailDraft['reply'];
$subject = $_GET['subject'] ?? $_POST['subject'] ?? '';
$body = $_GET['body'] ?? '';
if ($reply !== null) {
    $subject = $reply['subject'];
    if ($subject !== '' && !str_starts_with($subject, 'RE: ')) $subject = 'RE: '.$subject;
    // Prefixing a maximum-size subject must still yield an editable persistable subject.
    $subject = mb_substr($subject, 0, 255, 'UTF-8');
    $body = $reply['body'] === '' ? '' : "\n\n---".sprintf_translate('Original Message from %s (%s)',
        sanitize($mailDraft['candidates'][0]['name']), date('Y-m-d H:i:s', strtotime($reply['sent'])))."---\n".$reply['body'];
}
$subject = \Resurrection\Security\MailContent::subject($subject);
$body = \Resurrection\Security\MailContent::text($body);
$_SESSION['player_mail_draft'] = $mailDraft;
rawoutput("<form action='mail.php?op=send' method='post'>");
rawoutput(resurrection_action_fields('player-mail', resurrection_mail_context($mailDraft)));
if ($mailDraft['sender_label'] !== '') output('`2From: `^%s`n', $mailDraft['sender_label']);
output('`2To: ');
$warnings = [];
if (count($mailDraft['candidates']) > 1) rawoutput('<select name="to" id="to">');
foreach ($mailDraft['candidates'] as $recipient) {
    if (count($mailDraft['candidates']) === 1) {
        rawoutput('<input type="hidden" name="to" id="to" value="'.$escape($recipient['login']).'">');
        output_notl('%s`n', $recipient['name']);
    } else rawoutput('<option value="'.$escape($recipient['login']).'">'.$escape(full_sanitize($recipient['name'])).'</option>');
    if (($recipient['superuser'] & SU_GIVES_YOM_WARNING) && !($recipient['superuser'] & SU_OVERRIDE_YOM_WARNING)) $warnings[] = $recipient['login'];
}
if (count($mailDraft['candidates']) > 1) rawoutput('</select>');
rawoutput('<div id="warning" hidden>');
output('`2Before sending: `^%s`n', $mailDraft['settings']['superuseryommessage']);
rawoutput('</div>');
output('`n`2Subject: ');
rawoutput('<input name="subject" maxlength="255" value="'.$escape($subject).'"><br>');
output('`2Body:`n');
require_once 'lib/forms.php';
previewfield('body', '`^', false, false, ['type'=>'textarea','class'=>'input','cols'=>'60','rows'=>'9'], $escape($body));
output('`nMaximum message size: %s UTF-8 bytes. Longer messages are shortened to this limit.`n', $mailDraft['settings']['mailsizelimit']);
rawoutput('<button type="submit">'.$escape(translate_inline('Send')).'</button></form>');
rawoutput('<script>const mailWarnings='.json_encode($warnings, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';const mailTo=document.getElementById("to");function mailWarning(){document.getElementById("warning").hidden=!mailWarnings.includes(mailTo.value);}mailTo.addEventListener("change",mailWarning);mailWarning();</script>');
