<?php
require_once __DIR__ . '/admin_authority.php';

/** 1.1.2's "Will Expire Within" navigation. Any other duration shows the default (two weeks) list. */
const RESURRECTION_BAN_LIST_DURATIONS = ['1 week', '2 weeks', '3 weeks', '4 weeks', '2 months', '3 months', '4 months',
    '5 months', '6 months', '1 year', '2 years', '4 years'];
/** The longest timed ban saveban accepts, in days. */
const RESURRECTION_BAN_MAX_DAYS = 36500;

/** The similar-IP list's prefix for LIKE ... ESCAPE '|': a stored % or _ matches only itself. */
function resurrection_ban_like_prefix(string $prefix): string {
    return strtr($prefix, ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
}

/** A value as a JavaScript string literal inside an HTML attribute. */
function resurrection_ban_js(string $value): string {
    return htmlspecialchars(json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
}

/** The list's "Will Expire Within" choice: a listed duration, "forever", or "" (the default list). */
function resurrection_ban_list_duration(mixed $duration): string {
    return is_string($duration) && ($duration === 'forever' || in_array($duration, RESURRECTION_BAN_LIST_DURATIONS, true)) ? $duration : '';
}

/** The setupban form's one-use intent. */
function resurrection_ban_fields(): string {
    return resurrection_action_fields('user-ban', 'ban');
}

/**
 * The posted ban, validated before the intent is spent. The columns bound the IP filter (15) and the ID
 * (32); 0 days is permanent; a negative or non-integer duration is refused.
 * @return array{type: string, value: string, days: int, reason: string}
 */
function resurrection_ban_posted(): array {
    $type = \Resurrection\Http\Input::choice($_POST, 'type', ['ip', 'id'], 'id');
    $value = \Resurrection\Http\Input::string($_POST, $type);
    // 1.1.2 cast the duration, so an empty field was a permanent ban (the form's confirm treats it as 0).
    $days = \Resurrection\Http\Input::string($_POST, 'duration') === '' ? 0 : \Resurrection\Http\Input::integer($_POST, 'duration');
    $reason = \Resurrection\Http\Input::string($_POST, 'reason');
    if ($days > RESURRECTION_BAN_MAX_DAYS || strlen($reason) > 255 || strlen($value) > ($type === 'ip' ? 15 : 32) || ($type === 'id' && $value === '')) {
        throw new InvalidArgumentException('Invalid ban.');
    }
    return ['type' => $type, 'value' => $value, 'days' => $days, 'reason' => $reason];
}

/**
 * saveban: 1.1.2's self-ban checks (the request IP as a prefix, the lgi cookie) and texts, then the row and
 * its debug log line in one transaction.
 */
function resurrection_ban_save(): void {
    try { $ban = resurrection_ban_posted(); }
    catch (InvalidArgumentException $error) { resurrection_admin_failure($error, 'the ban form'); }
    resurrection_consume_action('user-ban', 'ban');
    $cookie = is_string($_COOKIE['lgi'] ?? null) ? $_COOKIE['lgi'] : '';
    if ($ban['type'] === 'ip' && str_starts_with((string)($_SERVER['REMOTE_ADDR'] ?? ''), $ban['value'])) {
        output("You don't really want to ban yourself now do you??");
        output("That's your own IP address!");
        return;
    }
    if ($ban['type'] === 'id' && $cookie === $ban['value']) {
        output("You don't really want to ban yourself now do you??");
        output("That's your own ID!");
        return;
    }
    $expire = $ban['days'] === 0 ? null : date('Y-m-d', strtotime('+' . $ban['days'] . ' days'));
    try {
        $rows = resurrection_player_mutation(function () use ($ban, $expire) {
            global $session;
            db_query('INSERT INTO ' . db_prefix('bans') . ' (banner,' . ($ban['type'] === 'ip' ? 'ipfilter' : 'uniqueid') . ',banexpire,banreason) VALUES (?,?,?,?)', true,
                [$session['user']['name'], $ban['value'], $expire, $ban['reason']]);
            $rows = db_affected_rows();
            // 1.1.2 logged the zero date for a permanent ban.
            debuglog('entered a ban: ' . ($ban['type'] === 'ip' ? 'IP: ' : 'ID: ') . $ban['value'] . ' Ends after: ' . ($expire ?? '0000-00-00') . '  Reason: "' . $ban['reason'] . '"');
            return $rows;
        });
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the ban form'); }
    output("%s ban rows entered.`n`n", $rows);
}
