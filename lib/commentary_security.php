<?php
require_once __DIR__ . '/../src/Security/CommentSnapshot.php';
require_once __DIR__ . '/../src/Http/Input.php';
require_once __DIR__ . '/../src/Security/Csrf.php';

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $csrfState
 * @param array<string, mixed> $post
 */
function resurrection_delete_comment(array $actor, array $csrfState, string $method, array $post): bool {
    if (empty($actor['loggedin']) || (int)($actor['user']['acctid'] ?? 0) < 1 ||
        (((int)($actor['user']['superuser'] ?? 0) & SU_EDIT_COMMENTS) === 0)) {
        throw new DomainException('Comment moderation is not authorized.');
    }
    \Resurrection\Security\Csrf::requirePost($csrfState, $method, $post['csrf_token'] ?? null);
    $id = \Resurrection\Http\Input::integer($post, 'removecomment', 0, 1);
    if ($id < 1) { throw new InvalidArgumentException('Invalid comment ID.'); }
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
        $section = resurrection_archive_comment((int)$actor['user']['acctid'], $id);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
    if ($section === null) { return false; }
    invalidatedatacache('comments-' . $section);
    invalidatedatacache('comments-or11');
    return true;
}

/**
 * Snapshot one comment for the audit and delete it, inside the caller's transaction. The section, or null
 * when the comment is already gone.
 */
function resurrection_archive_comment(int $moderator, int $id): ?string {
    $rows = db_query('SELECT * FROM ' . db_prefix('commentary') . ' WHERE commentid=? FOR UPDATE', true, [$id]);
    $comment = db_fetch_assoc($rows);
    if (!$comment) { return null; }
    $authors = db_query('SELECT a.name,a.login,a.clanrank,c.clanshort FROM ' . db_prefix('accounts') . ' a LEFT JOIN ' . db_prefix('clans') . ' c ON c.clanid=a.clanid WHERE a.acctid=?', true, [(int)$comment['author']]);
    $comment += db_fetch_assoc($authors) ?: ['name' => 'System', 'login' => '', 'clanrank' => 0, 'clanshort' => ''];
    db_query('INSERT INTO ' . db_prefix('moderatedcomments') . ' (moderator,moddate,comment) VALUES (?,?,?)', true,
        [$moderator, date('Y-m-d H:i:s'), \Resurrection\Security\CommentSnapshot::encode($comment)]);
    db_query('DELETE FROM ' . db_prefix('commentary') . ' WHERE commentid=?', true, [$id]);
    return (string)$comment['section'];
}

/**
 * moderate.php's "Delete Checked Comments" and "Delete Checked & Ban (3 days)": the bans, the logouts, the
 * audit snapshots and the deletes commit together. As in 1.1.2, no ban is added (and the author stays logged
 * in) when a ban at least as long already exists for the author's ID; a NULL expiry is permanent.
 * @param array<string, mixed> $actor
 * @param list<int> $ids
 */
function resurrection_moderate_comments(array $actor, array $ids, ?string $reason): void {
    $bits = (int)($actor['user']['superuser'] ?? 0);
    if (empty($actor['loggedin']) || (int)($actor['user']['acctid'] ?? 0) < 1 || ($bits & SU_EDIT_COMMENTS) === 0 ||
        ($reason !== null && ($bits & SU_EDIT_USERS) === 0)) {
        throw new DomainException('Comment moderation is not authorized.');
    }
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
        if ($reason !== null) {
            $until = date('Y-m-d H:i:s', strtotime('+3 days'));
            foreach ($ids as $id) {
                $rows = db_query('SELECT a.uniqueid,a.acctid FROM ' . db_prefix('commentary') . ' c JOIN ' . db_prefix('accounts') . ' a ON a.acctid=c.author WHERE c.commentid=? FOR UPDATE', true, [$id]);
                $account = db_fetch_assoc($rows);
                if (!$account || (string)$account['uniqueid'] === '') { continue; }
                $longer = false;
                foreach (db_query('SELECT banexpire FROM ' . db_prefix('bans') . ' WHERE uniqueid=? FOR UPDATE', true, [$account['uniqueid']]) as $ban) {
                    if ($ban['banexpire'] === null || (string)$ban['banexpire'] >= $until) { $longer = true; }
                }
                if ($longer) { continue; }
                db_query('INSERT INTO ' . db_prefix('bans') . ' (uniqueid,banexpire,banreason,banner) VALUES (?,?,?,?)', true,
                    [$account['uniqueid'], $until, $reason, $actor['user']['name']]);
                db_query('UPDATE ' . db_prefix('accounts') . ' SET loggedin=0 WHERE acctid=?', true, [(int)$account['acctid']]);
            }
        }
        $sections = [];
        foreach ($ids as $id) {
            $section = resurrection_archive_comment((int)$actor['user']['acctid'], $id);
            if ($section !== null) { $sections[$section] = true; }
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
    foreach (array_keys($sections) as $section) { invalidatedatacache('comments-' . $section); }
    if ($sections !== []) { invalidatedatacache('comments-or11'); }
}

/** Validate every selected key before any batch mutation.
 * @return list<int>
 */
function resurrection_comment_ids(mixed $selected): array {
    if (!is_array($selected) || count($selected) > 100) { throw new InvalidArgumentException('Invalid selection.'); }
    $ids = [];
    foreach (array_keys($selected) as $key) {
        $ids[] = \Resurrection\Http\Input::integer(['id' => (string)$key], 'id', 0, 1);
    }
    return $ids;
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $csrfState
 * @param array<string, mixed> $post
 */
function resurrection_restore_comment(array $actor, array $csrfState, string $method, array $post): bool {
    if (empty($actor['loggedin']) || (int)($actor['user']['acctid'] ?? 0) < 1 ||
        (((int)($actor['user']['superuser'] ?? 0) & SU_AUDIT_MODERATION) === 0)) {
        throw new DomainException('Comment auditing is not authorized.');
    }
    \Resurrection\Security\Csrf::requirePost($csrfState, $method, $post['csrf_token'] ?? null);
    $id = \Resurrection\Http\Input::integer($post, 'modid', 0, 1);
    if ($id < 1) { throw new InvalidArgumentException('Invalid audit ID.'); }
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
        $rows = db_query('SELECT comment FROM ' . db_prefix('moderatedcomments') . ' WHERE modid=? FOR UPDATE', true, [$id]);
        $row = db_fetch_assoc($rows);
        if (!$row) { $db->commit(); return false; }
        $comment = \Resurrection\Security\CommentSnapshot::read($row['comment']);
        db_query('INSERT INTO ' . db_prefix('commentary') . ' (commentid,postdate,section,author,comment) VALUES (?,?,?,?,?)', true,
            [(int)$comment['commentid'], $comment['postdate'], $comment['section'], (int)$comment['author'], $comment['comment']]);
        db_query('DELETE FROM ' . db_prefix('moderatedcomments') . ' WHERE modid=?', true, [$id]);
        $db->commit();
        invalidatedatacache('comments-' . $comment['section']);
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}
