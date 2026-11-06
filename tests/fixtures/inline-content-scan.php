<?php
declare(strict_types=1);
// Phase 6 task 3 (S1), for the HTTP suite's test server only (tests/tooling/test_web_application.py loads it through
// auto_prepend_file): every HTML page the server sends is parsed, and anything the Content Security Policy refuses is
// written to the file RESURRECTION_INLINE_LOG names, one JSON line per page: a <script> without src, a <style>
// element, an on* attribute, a style attribute, a javascript: URL. The suite fails when any page had one. A sandboxed
// page (the user editor's last hit, which shows a player's stored page as it was) is left out: it runs nothing.
(static function (): void {
    $log = getenv('RESURRECTION_INLINE_LOG');
    if (!is_string($log) || $log === '' || PHP_SAPI !== 'cli-server') return;
    $page = '';
    ob_start(static function (string $buffer, int $phase) use ($log, &$page): string {
        $page .= $buffer;
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL)) return $buffer;
        try {
            $type = 'text/html';
            $policy = '';
            foreach (headers_list() as $header) {
                [$name, $value] = array_map('trim', explode(':', $header, 2) + [1 => '']);
                if (strcasecmp($name, 'Content-Type') === 0) $type = strtolower($value);
                if (strcasecmp($name, 'Content-Security-Policy') === 0) $policy = $value;
            }
            if (!str_starts_with($type, 'text/html') || trim($page) === '') return $buffer;
            if (in_array('sandbox', array_map('trim', explode(';', $policy)), true)) return $buffer;
            $found = [];
            $document = \Dom\HTMLDocument::createFromString($page, LIBXML_NOERROR);
            foreach ($document->getElementsByTagName('*') as $element) {
                $tag = strtolower($element->localName);
                if ($tag === 'script' && !$element->hasAttribute('src')) $found[] = 'script: ' . substr(trim((string)$element->textContent), 0, 80);
                if ($tag === 'style') $found[] = 'style element';
                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->localName);
                    if (str_starts_with($name, 'on')) $found[] = "$tag $name=" . substr($attribute->value, 0, 60);
                    if ($name === 'style') $found[] = "$tag style=" . substr($attribute->value, 0, 60);
                    if (in_array($name, ['href', 'src', 'action', 'formaction'], true) && preg_match('/\A\s*javascript:/i', $attribute->value)) $found[] = "$tag $name=javascript:";
                }
            }
            file_put_contents($log, json_encode(['page' => $_SERVER['REQUEST_URI'] ?? '', 'found' => array_values(array_unique($found))]) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $error) {
            file_put_contents($log, json_encode(['page' => $_SERVER['REQUEST_URI'] ?? '', 'found' => ['scan failed: ' . get_class($error)]]) . "\n", FILE_APPEND | LOCK_EX);
        }
        return $buffer;
    }, 1); // A chunk size of 1 passes each write straight on: the page is sent as without this buffer, headers included.
})();
