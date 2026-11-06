<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The web setup page's token (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.3, and the
 * decisions with amendment T23). The host proves control of the site's files by creating resurrection-setup.php and
 * typing the value it holds on the page.
 *
 * - Place: beside the LOGD_DBCONNECT file when that is set, else the game's folder. Never both.
 * - Contents: line 1 is the fixed guard line, which ends PHP with an empty 404 if the file is requested; line 2 is
 *   "rzsetup-" and 32 base32 characters; an optional line 3 is exactly "https-terminated-upstream". One trailing
 *   newline at most (LF or CRLF line ends). At most 128 bytes, read with a bounded read, never included.
 * - Refused: fewer than 12 distinct characters, a modification time older than 60 minutes or more than 5 minutes
 *   ahead, a value whose SHA-256 is recorded in setup_tokens_used (single use; the first 96 bits of each of the last
 *   10, since a setting holds at most 255 characters).
 * - The token never goes in a URL, a log line, an error page or a saved page; every function that takes it marks the
 *   parameter #[\SensitiveParameter].
 */

/** The token file's name. */
const RESURRECTION_SETUP_TOKEN_FILE = 'resurrection-setup.php';

/** The failure counter's file name (install mode), beside the token file (section 3.9). */
const RESURRECTION_SETUP_FAILURE_FILE = 'resurrection-setup-failures.php';

/** Line 1 of both files: requested over the web, PHP answers an empty 404 and shows nothing after it. */
const RESURRECTION_SETUP_GUARD = '<?php http_response_code(404); exit; ?>';

/** The optional line 3 (T23): the host's statement that TLS ends at a proxy in front of the site. */
const RESURRECTION_SETUP_UPSTREAM = 'https-terminated-upstream';

/** The token's prefix. */
const RESURRECTION_SETUP_PREFIX = 'rzsetup-';

/** The file's size cap, in bytes. */
const RESURRECTION_SETUP_TOKEN_CAP = 128;

/** A token file older than this, in seconds, is refused. */
const RESURRECTION_SETUP_TOKEN_AGE = 3600;

/** A token file whose time is further ahead than this, in seconds, is refused. */
const RESURRECTION_SETUP_TOKEN_AHEAD = 300;

/** The fewest distinct characters a token's 32 characters may have. */
const RESURRECTION_SETUP_TOKEN_DIVERSITY = 12;

/** The setting that holds the SHA-256 of the last used tokens (a JSON list). */
const RESURRECTION_SETUP_USED_SETTING = 'setup_tokens_used';

/** How many used tokens it keeps. */
const RESURRECTION_SETUP_USED_KEEP = 10;

/**
 * How many hex characters of each used token's SHA-256 it keeps: 96 bits. A settings value holds at most 255
 * characters, and ten full hashes need 649. A replayed token always matches its own prefix; a new token that matched
 * one by chance (2^-96) would only be refused.
 */
const RESURRECTION_SETUP_USED_LENGTH = 24;

/**
 * The folder the token file belongs in: the directory of the LOGD_DBCONNECT file when that is set (S12: a file in the
 * game's folder never wins over it), else the game's folder.
 */
function resurrection_setup_token_directory(?string $located, string $gameDirectory): string {
    return $located !== null ? dirname($located) : $gameDirectory;
}

/** RFC 4648 base32, lower case, no padding: 20 bytes give 32 characters. */
function resurrection_setup_base32(#[\SensitiveParameter] string $bytes): string {
    $alphabet = 'abcdefghijklmnopqrstuvwxyz234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) { $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) { $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))]; }
    return $out;
}

/** A new token: "rzsetup-" and 160 random bits in base32. The page shows it as a suggestion and stores nothing. */
function resurrection_setup_token_new(): string {
    return RESURRECTION_SETUP_PREFIX . resurrection_setup_base32(random_bytes(20));
}

/** Whether a value has the token's form: the prefix, 32 characters from a-z and 2-7, at least 12 of them distinct. */
function resurrection_setup_token_well_formed(#[\SensitiveParameter] string $value): bool {
    if (preg_match('/\Arzsetup-([a-z2-7]{32})\z/', $value, $match) !== 1) return false;
    return count(array_unique(str_split($match[1]))) >= RESURRECTION_SETUP_TOKEN_DIVERSITY;
}

/** The stored form of a token: its SHA-256, hex. */
function resurrection_setup_token_hash(#[\SensitiveParameter] string $token): string {
    return hash('sha256', $token);
}

/**
 * Whether the typed value is the file's token. Both are checked for form first; the comparison is hash_equals(). The
 * typed value may carry the spaces or line end a paste adds.
 */
function resurrection_setup_token_matches(#[\SensitiveParameter] string $fileToken, #[\SensitiveParameter] mixed $typed): bool {
    if (!is_string($typed)) return false;
    $typed = trim($typed);
    return resurrection_setup_token_well_formed($fileToken) && resurrection_setup_token_well_formed($typed) && hash_equals($fileToken, $typed);
}

/**
 * Read and check the token file, never including it.
 *
 * The result's status is one of: ok, missing, unreadable, oversize, guard (line 1 is not the guard line), format
 * (line 2 or 3 is wrong, or there are other lines), weak (too few distinct characters), old, ahead, used. key is the
 * first 16 hex characters of the SHA-256 of the file's bytes (the failure limit's key; a new file starts a new count),
 * null when the file cannot be read. token and upstream are set only for ok.
 *
 * @param list<string> $used the SHA-256 of tokens already used
 * @return array{status: string, key: ?string, token: ?string, upstream: bool}
 */
function resurrection_setup_token_read(string $path, int $now, array $used): array {
    $result = ['status' => 'missing', 'key' => null, 'token' => null, 'upstream' => false];
    clearstatcache(true, $path);
    if (is_link($path)) { $result['status'] = 'unreadable'; return $result; }
    if (!file_exists($path)) return $result;
    if (!is_file($path) || !is_readable($path)) { $result['status'] = 'unreadable'; return $result; }
    $size = filesize($path);
    if ($size === false) { $result['status'] = 'unreadable'; return $result; }
    if ($size > RESURRECTION_SETUP_TOKEN_CAP) { $result['status'] = 'oversize'; return $result; }
    try {
        $handle = fopen($path, 'rb');
        $bytes = $handle === false ? false : fread($handle, RESURRECTION_SETUP_TOKEN_CAP + 1);
        if ($handle !== false) fclose($handle);
    } catch (Throwable) {
        $bytes = false;
    }
    if (!is_string($bytes)) { $result['status'] = 'unreadable'; return $result; }
    $result['key'] = substr(hash('sha256', $bytes), 0, 16);
    if (strlen($bytes) > RESURRECTION_SETUP_TOKEN_CAP) { $result['status'] = 'oversize'; return $result; }
    $lines = explode("\n", str_replace("\r\n", "\n", $bytes));
    if (end($lines) === '') array_pop($lines);
    if (($lines[0] ?? null) !== RESURRECTION_SETUP_GUARD) { $result['status'] = 'guard'; return $result; }
    if (count($lines) < 2 || count($lines) > 3 || (count($lines) === 3 && $lines[2] !== RESURRECTION_SETUP_UPSTREAM)
            || preg_match('/\Arzsetup-[a-z2-7]{32}\z/', $lines[1]) !== 1) {
        $result['status'] = 'format';
        return $result;
    }
    if (!resurrection_setup_token_well_formed($lines[1])) { $result['status'] = 'weak'; return $result; }
    $time = filemtime($path);
    if ($time === false || $now - $time > RESURRECTION_SETUP_TOKEN_AGE) { $result['status'] = 'old'; return $result; }
    if ($time - $now > RESURRECTION_SETUP_TOKEN_AHEAD) { $result['status'] = 'ahead'; return $result; }
    if (in_array(resurrection_setup_used_key(resurrection_setup_token_hash($lines[1])), $used, true)) { $result['status'] = 'used'; return $result; }
    return ['status' => 'ok', 'key' => $result['key'], 'token' => $lines[1], 'upstream' => count($lines) === 3];
}

/** What setup_tokens_used keeps of a used token's SHA-256 (hex): its first 96 bits. */
function resurrection_setup_used_key(string $hash): string {
    return substr($hash, 0, RESURRECTION_SETUP_USED_LENGTH);
}

/**
 * The tokens already used, from the database the page is connected to (setup_tokens_used): each one's
 * resurrection_setup_used_key(), oldest first. Empty when there is no database yet, no settings table, or nothing
 * recorded. Read straight from the table: getsetting() would write a setting it does not find.
 *
 * @return list<string>
 */
function resurrection_setup_used_tokens(): array {
    if (!isset($GLOBALS['dbinfo']['connection']) || !db_table_exists(db_prefix('settings'))) return [];
    $rows = db_query('SELECT value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', true, [RESURRECTION_SETUP_USED_SETTING]);
    $list = explode(' ', (string)($rows[0]['value'] ?? ''));
    return array_values(array_filter($list, static fn (string $key): bool => preg_match('/\A[0-9a-f]{' . RESURRECTION_SETUP_USED_LENGTH . '}\z/', $key) === 1));
}

/** Record a used token in setup_tokens_used (its key, space separated), keeping the last 10: 249 characters at most. */
function resurrection_setup_record_used(string $hash): void {
    $key = resurrection_setup_used_key($hash);
    $list = array_values(array_diff(resurrection_setup_used_tokens(), [$key]));
    $list[] = $key;
    $value = implode(' ', array_slice($list, -RESURRECTION_SETUP_USED_KEEP));
    db_query('INSERT INTO ' . db_identifier(db_prefix('settings')) . ' (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true,
        [RESURRECTION_SETUP_USED_SETTING, $value, $value]);
    if (is_array($GLOBALS['settings'] ?? null)) $GLOBALS['settings'][RESURRECTION_SETUP_USED_SETTING] = $value;
}

/**
 * Delete the token file after a success. False when it cannot be deleted (the page then says so first); its hash is
 * recorded either way, so it cannot be used again.
 */
function resurrection_setup_token_delete(string $path): bool {
    try {
        return !file_exists($path) || unlink($path);
    } catch (Throwable) {
        return false;
    }
}
