<?php
/** Account-owned mailbox mutations. Uses the existing CSRF contract. */
require_once __DIR__ . '/../src/Security/Csrf.php';
require_once __DIR__ . '/../src/Http/Input.php';

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $csrf
 * @param array<string, mixed> $post
 */
function resurrection_mutate_mailbox(array $actor, array &$csrf, string $method, array $post, string $operation): int {
    if (empty($actor['loggedin']) || (int)($actor['user']['acctid'] ?? 0) < 1) throw new DomainException('Mailbox access is not authorized.');
    \Resurrection\Security\Csrf::requirePost($csrf, $method, $post['csrf_token'] ?? null);
    if (!in_array($operation, ['del','process','unread'], true)) throw new InvalidArgumentException('Invalid mailbox operation.');
    $values = $operation === 'process' ? ($post['msg'] ?? []) : [$post['id'] ?? ''];
    if (!is_array($values) || count($values) < 1 || count($values) > 100) throw new InvalidArgumentException('Invalid message selection.');
    $ids = [];
    foreach ($values as $value) $ids[] = \Resurrection\Http\Input::integer(['id'=>$value], 'id', 0, 1);
    $ids = array_values(array_unique($ids));
    $owner = (int)$actor['user']['acctid'];
    $sql = ($operation === 'unread' ? 'UPDATE ' . db_prefix('mail') . ' SET seen=0' : 'DELETE FROM ' . db_prefix('mail'))
        . ' WHERE msgto=? AND messageid IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    db_query($sql, true, [$owner, ...$ids]);
    $changed = db_affected_rows();
    invalidatedatacache('mail-' . $owner);
    return $changed;
}
