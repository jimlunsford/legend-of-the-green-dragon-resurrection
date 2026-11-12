<?php
// The PHPUnit tests' mail transport (Phase 6 task 13a): PHP's mail() runs this as sendmail_path. It appends the message
// it is handed to the file named by its first argument, as one JSON line, sends nothing, and exits with its second
// argument (0: accepted, anything else: refused).
//
//   sendmail_path="php -n tests/support/mail_capture.php CAPTURE-FILE EXIT-CODE"
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$message = (string)stream_get_contents(STDIN);
file_put_contents((string)($argv[1] ?? ''), json_encode(['message' => $message], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
exit((int)($argv[2] ?? 1));
