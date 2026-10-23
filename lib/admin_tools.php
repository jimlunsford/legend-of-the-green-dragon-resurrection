<?php
require_once __DIR__ . '/player_mutation.php';

/**
 * Shared guards for the small admin tools closed by final rescan task 2 (news delete, the word filter editor, the
 * referer list and the translator tools). Each mutation is a POST with CSRF and a one-use intent, checked and written
 * inside one database transaction.
 */

/** A mutation route: only POST, and only the protected fields plus the named form fields. 405 / 400 otherwise. */
function resurrection_tool_post(array $fields = []): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Use the form on the page.'); }
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$fields]) !== []) { http_response_code(400); exit('Invalid request.'); }
}

/**
 * A request value that must be text: missing is '', an array or invalid UTF-8 is 400, as is text over $limit bytes.
 * @param array<string, mixed> $source
 */
function resurrection_tool_text(array $source, string $name, int $limit, bool $utf8 = true): string {
    $value = $source[$name] ?? '';
    if (!is_string($value) || strlen($value) > $limit || ($utf8 && !mb_check_encoding($value, 'UTF-8'))) { http_response_code(400); exit('Invalid request.'); }
    return $value;
}

/** One transaction around a tool's checks and writes. A DomainException is a stale form (409); anything else is 500. */
function resurrection_tool_transaction(callable $action, string $page): mixed {
    $db = $GLOBALS['dbinfo']['connection'];
    if ($db->inTransaction()) throw new LogicException('Nested tool transaction.');
    $db->beginTransaction();
    try {
        $result = $action();
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($error instanceof DomainException) { http_response_code(409); exit("This changed. Reload $page before acting."); }
        http_response_code(500); exit("Nothing was changed. Reload $page and try again.");
    }
}

/** A hash of stored values a form was shown for. */
function resurrection_tool_context(array $values): string {
    return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The news line a Del button was shown for. The delete is bound to the whole stored row.
 * @param array<string, mixed> $row
 */
function resurrection_news_delete_context(array $row): string {
    return resurrection_tool_context([(string)$row['newsid'], (string)$row['newstext'], (string)$row['newsdate'], (string)$row['accountid'],
        (string)$row['arguments'], (string)$row['tlschema']]);
}
