<?php
return [
    'description' => 'Never applied: the test records 0002 alone',
    'apply' => static function (): void { throw new LogicException('An out-of-order database is refused before any step runs.'); },
];
