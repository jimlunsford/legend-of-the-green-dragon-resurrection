<?php
require_once __DIR__ . '/../src/Http/Input.php';
require_once __DIR__ . '/../src/Security/Csrf.php';

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
