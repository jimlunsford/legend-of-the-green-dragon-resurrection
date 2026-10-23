<?php
/**
 * 1.1.2's referer tracking, run by common.php on every page.
 *
 * A visit that arrives from an off-site http:// page counts one row per referring URL in `referers`: the URL (`uri`,
 * text), the referring host (`site`, varchar(50)), this host and the request path (`dest`, varchar(255)) and the
 * visitor's address (`ip`, varchar(40)). Any visitor chooses the Referer header and the path, so:
 *
 * - every value is bound;
 * - each value is cut to its column, in characters, as 1.1.2's non-strict MySQL stored a longer value (the URL to the
 *   text column's 65535 bytes, on a character boundary); the cut URL is also the one looked up, so a long URL still
 *   counts on one row;
 * - a value that is not valid UTF-8 cannot be stored in the utf8mb4 columns faithfully, so that visit is not tracked;
 * - any failure while tracking (the database refusing a value, a lost connection) is swallowed: the visitor still gets
 *   the page they asked for, and nothing is half written, because each statement stands alone as in 1.1.2.
 *
 * 1.1.2 also deleted up to 300 referers older than a month, with a game log line, on one new row in a hundred. That
 * cleanup is kept, bound.
 */

const RESURRECTION_REFERER_SITE = 50;
const RESURRECTION_REFERER_DEST = 255;
const RESURRECTION_REFERER_IP = 40;
const RESURRECTION_REFERER_URI_BYTES = 65535;

/**
 * The row 1.1.2 would write for this request, fitted to the columns, or null when the visit is not tracked.
 * @return array{uri: string, site: string, dest: string, ip: string}|null
 */
function resurrection_referer_row(string $referer, string $serverName, string $serverPort, string $scriptUri, string $requestUri, string $httpHost, string $remoteAddr): ?array {
    // 1.1.2's own-site test, kept as written (including its `dirname()` of the request URI).
    $url = 'http://' . $serverName . dirname($scriptUri);
    $url = substr($url, 0, strlen($url) - 1);
    $urlport = 'http://' . $serverName . ':' . $serverPort . dirname($scriptUri);
    $urlport = substr($urlport, 0, strlen($urlport) - 1);
    if (substr($referer, 0, strlen($url)) == $url || substr($referer, 0, strlen($urlport)) == $urlport ||
            $referer == '' || strtolower(substr($referer, 0, 7)) != 'http://') {
        return null;
    }
    $site = str_replace('http://', '', $referer);
    if (strpos($site, '/')) $site = substr($site, 0, strpos($site, '/'));
    $host = str_replace(':80', '', $httpHost);
    if ($site == $host) return null;
    $row = ['uri' => $referer, 'site' => $site, 'dest' => $host . '/' . $requestUri, 'ip' => $remoteAddr];
    foreach ($row as $value) {
        if (!mb_check_encoding($value, 'UTF-8')) return null;
    }
    return [
        'uri' => mb_strcut($row['uri'], 0, RESURRECTION_REFERER_URI_BYTES, 'UTF-8'),
        'site' => mb_substr($row['site'], 0, RESURRECTION_REFERER_SITE, 'UTF-8'),
        'dest' => mb_substr($row['dest'], 0, RESURRECTION_REFERER_DEST, 'UTF-8'),
        'ip' => mb_substr($row['ip'], 0, RESURRECTION_REFERER_IP, 'UTF-8'),
    ];
}

/** Count this request's referer, if any. Never throws and never stops the page. */
function resurrection_track_referer(): void {
    global $REQUEST_URI;
    try {
        $server = static fn (string $key): string => is_string($_SERVER[$key] ?? null) ? $_SERVER[$key] : '';
        $row = resurrection_referer_row($server('HTTP_REFERER'), $server('SERVER_NAME'), $server('SERVER_PORT'), $server('REQUEST_URI'),
            is_string($REQUEST_URI ?? null) ? $REQUEST_URI : $server('REQUEST_URI'), $server('HTTP_HOST'), $server('REMOTE_ADDR'));
        if ($row === null) return;
        $table = db_prefix('referers');
        $found = db_query('SELECT refererid FROM ' . $table . ' WHERE uri=?', false, [$row['uri']]);
        if (!is_array($found)) return;
        $now = date('Y-m-d H:i:s');
        if ($found !== []) {
            db_query('UPDATE ' . $table . ' SET count=count+1,last=?,site=?,dest=?,ip=? WHERE refererid=?', false,
                [$now, $row['site'], $row['dest'], $row['ip'], (int)$found[0]['refererid']]);
            return;
        }
        if (e_rand(1, 100) == 2) {
            $timestamp = date('Y-m-d H:i:s', strtotime('-1 month'));
            if (db_query('DELETE FROM ' . $table . ' WHERE last < ? LIMIT 300', false, [$timestamp]) !== false) {
                require_once __DIR__ . '/gamelog.php';
                gamelog('Deleted ' . db_affected_rows() . ' records from ' . $table . " older than $timestamp.", 'maintenance');
            }
        }
        db_query('INSERT INTO ' . $table . ' (uri,last,site,dest,ip,count) VALUES (?,?,?,?,?,1)', false,
            [$row['uri'], $now, $row['site'], $row['dest'], $row['ip']]);
    } catch (Throwable $error) {
        // Tracking is bookkeeping: whatever went wrong, the visitor's page goes on.
        return;
    }
}
