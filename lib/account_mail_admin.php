<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Game Settings' Account mail section (Phase 6 task 13a; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, section
 * 2.5), for SU_EDIT_CONFIG: the status, the test mail (to the signed-in administrator's own address only), and the
 * switch. Every change is a POST with the CSRF token and a one-use action token. The page holds no secret: the test
 * mail's link goes only into the mail.
 */
require_once __DIR__ . '/account_mail.php';
require_once __DIR__ . '/player_mutation.php';

/** The action tokens' context: the account acting and the state the page showed. */
function resurrection_account_mail_admin_context(int $acctid, bool $switch): string {
    return $acctid . ':' . ($switch ? 'on' : 'off');
}

/**
 * Handle a POST op (accountmail_test, accountmail_on, accountmail_off) and return the line to show above the section.
 */
function resurrection_account_mail_admin_handle(string $op, int $acctid): string {
    resurrection_consume_action('account-mail', resurrection_account_mail_admin_context($acctid, resurrection_account_mail_switch()));
    if ($op === 'accountmail_test') {
        return match (resurrection_account_mail_test_send($acctid)) {
            'sent' => 'The test mail was handed to the mail transport. Open it and press Confirm on the page its link shows.',
            'refused' => 'The mail transport refused the test mail. Nothing was sent. See the host guide, section Mail.',
            'no-address' => 'Your account has no valid email address. Add one in Preferences first.',
            'not-ready' => 'No test mail was sent: the Server URL must be a valid https:// address and Admin Email a real address that is not the default.',
            'limited' => 'No test mail was sent: at most 5 test mails an hour, and 5 account mails a day to one address. Try again later.',
            default => 'No test mail was sent.',
        };
    }
    if ($op === 'accountmail_on') {
        if (!resurrection_account_mail_turn_on()) return 'Account mail was not turned on: see the conditions below.';
        gamelog('Turned on account mail', 'settings');
        return 'Account mail is on.';
    }
    resurrection_account_mail_turn_off();
    gamelog('Turned off account mail', 'settings');
    return 'Account mail is off.';
}

/** Why a condition fails, in one line each. */
function resurrection_account_mail_admin_reason(string $blocker): string {
    return match ($blocker) {
        'serverurl' => 'The Server URL is not a valid https:// address. Links in account mail are built from it only.',
        'sender' => 'Admin Email is not a usable sender: it must be a plain address on a real domain, not the default postmaster@localhost.com.',
        'no-proof' => 'No test mail has been confirmed yet. Send one and open its link.',
        'stale-proof' => 'The Server URL or Admin Email changed since the last confirmed test mail. Send a new one and open its link.',
        default => 'A condition is not met.',
    };
}

/** The section (configuration.php?op=accountmail), with $notice above it after a change. */
function resurrection_account_mail_admin_render(int $acctid, string $notice = ''): void {
    $h = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $status = resurrection_account_mail_status();
    rawoutput('<section class="rz-webcron rz-accountmail" aria-labelledby="rz-accountmail-title"><h2 id="rz-accountmail-title">Account mail</h2>');
    if ($notice !== '') rawoutput('<p class="rz-setting-refused" role="status">' . $h($notice) . '</p>');
    rawoutput('<p>Account mail is what password recovery and email verification send. It stays off until the game has proved that its mail arrives: send a test mail to your own account\'s address, open it, and press Confirm on the page its link shows. Then turn account mail on.</p>');
    $state = $status['on'] ? 'On' : ($status['switch'] ? 'Switched on, but not in effect (see below)' : 'Off');
    $rows = db_query('SELECT emailaddress FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$acctid]);
    $own = (string)($rows[0]['emailaddress'] ?? '');
    rawoutput('<dl class="rz-webcron-status">');
    rawoutput('<dt>Account mail</dt><dd>' . $h($state) . '</dd>');
    rawoutput('<dt>Mail transport</dt><dd>PHP mail() (the server\'s own mail program)</dd>');
    rawoutput('<dt>Server URL</dt><dd>' . $h($status['serverurl'] === '' ? 'not set' : $status['serverurl']) . ' (' . ($status['base'] !== null ? 'usable for account mail' : 'not usable: it must be a valid https:// address') . ')</dd>');
    rawoutput('<dt>Sender (Admin Email)</dt><dd>' . $h($status['sender'] === '' ? 'not set' : $status['sender']) . ' (' . (in_array('sender', $status['blockers'], true) ? 'not usable' : 'usable') . ')</dd>');
    rawoutput('<dt>Your address</dt><dd>' . $h(resurrection_mail_address_valid($own) ? $own : 'none: add one in Preferences') . '</dd>');
    $last = $status['last_test'];
    rawoutput('<dt>Last test mail</dt><dd>' . $h($last === null ? 'none' : $last['date'] . ', ' . ($last['result'] === 'sent' ? 'handed to the mail transport' : 'refused by the mail transport')) . '</dd>');
    rawoutput('<dt>Confirmed test mail</dt><dd>' . $h($status['proof'] === null ? 'none' : $status['proof']['time'] . ' UTC, by ' . ($status['proof_login'] === '' ? 'a removed account' : $status['proof_login'])
        . (in_array('stale-proof', $status['blockers'], true) ? ' (for an earlier Server URL or Admin Email)' : '')) . '</dd>');
    rawoutput('<dt>Last 24 hours</dt><dd>' . $h($status['sent'] . ' account mail(s) handed to the mail transport, ' . $status['refused'] . ' refused') . '</dd>');
    rawoutput('<dt>Hourly limit</dt><dd>' . $h($status['hourly'] . ' account mails an hour (Standard settings, Account Mail)') . '</dd>');
    rawoutput('</dl>');
    if ($status['blockers'] !== []) {
        rawoutput('<ul class="rz-accountmail-conditions">');
        foreach ($status['blockers'] as $blocker) rawoutput('<li>' . $h(resurrection_account_mail_admin_reason($blocker)) . '</li>');
        rawoutput('</ul>');
    }
    $context = resurrection_account_mail_admin_context($acctid, $status['switch']);
    $form = static function (string $op, string $label) use ($h, $context): void {
        $url = 'configuration.php?op=' . $op;
        addnav('', $url);
        rawoutput('<form class="rz-inline" action="' . $h($url) . '" method="POST">' . resurrection_action_fields('account-mail', $context) . '<button type="submit" class="button">' . $h($label) . '</button></form> ');
    };
    rawoutput('<div class="rz-webcron-actions">');
    $form('accountmail_test', 'Send a test mail to my address');
    if ($status['switch']) $form('accountmail_off', 'Turn account mail off');
    elseif ($status['blockers'] === []) $form('accountmail_on', 'Turn account mail on');
    rawoutput('</div>');
    rawoutput('<p>A confirmed test mail proves that the server\'s mail program accepted a message from this sender, that it reached one real inbox, and that its link reached this game over HTTPS. It does not prove that mail reaches every provider or keeps working: the counts above show refusals, and the game log has a line for each.</p>');
    rawoutput('</section>');
}
