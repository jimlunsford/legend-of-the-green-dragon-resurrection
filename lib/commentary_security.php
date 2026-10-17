<?php
require_once __DIR__ . '/../src/Security/ScalarState.php';
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
        $rows = db_query('SELECT * FROM ' . db_prefix('commentary') . ' WHERE commentid=? FOR UPDATE', true, [$id]);
        $comment = db_fetch_assoc($rows);
        if (!$comment) { $db->commit(); return false; }
        $authors = db_query('SELECT a.name,a.login,a.clanrank,c.clanshort FROM ' . db_prefix('accounts') . ' a LEFT JOIN ' . db_prefix('clans') . ' c ON c.clanid=a.clanid WHERE a.acctid=?', true, [(int)$comment['author']]);
        $comment += db_fetch_assoc($authors) ?: ['name' => 'System', 'login' => '', 'clanrank' => 0, 'clanshort' => ''];
        db_query('INSERT INTO ' . db_prefix('moderatedcomments') . ' (moderator,moddate,comment) VALUES (?,?,?)', true,
            [(int)$actor['user']['acctid'], date('Y-m-d H:i:s'), serialize($comment)]);
        db_query('DELETE FROM ' . db_prefix('commentary') . ' WHERE commentid=?', true, [$id]);
        $db->commit();
        invalidatedatacache('comments-' . $comment['section']);
        invalidatedatacache('comments-or11');
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
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
        $comment = \Resurrection\Security\ScalarState::read($row['comment']);
        if (!is_array($comment)) { throw new DomainException('Invalid audit record.'); }
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
