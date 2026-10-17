<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// HTTP input stays raw. Bind SQL values and encode output at their destinations.
error_reporting(E_ALL);
ini_set('zend.exception_ignore_args', '1');

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    // Do not serialize requests or print argument-bearing backtraces.
    throw new ErrorException('Runtime error in ' . basename($file) . ':' . $line, 0, $severity, $file, $line);
});
