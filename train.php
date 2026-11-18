<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/autoload.php';
// Reject request authority before bootstrap, including legacy victory and master overrides.
if (array_diff(array_keys($_GET), ['op','c']) !== [] ||
    array_diff(array_keys($_POST), ['csrf_token','action_token','rounds']) !== []) {
    http_response_code(400); exit('Invalid training action.');
}
// A refusal while common.php loads answers in the game's frame when the page got that far (Phase 6 task 26, R12), with
// training's way out; before that, the plain text, as before. The 400 above comes before common.php and stays plain.
$resurrection_train_refusal = null;
try { require_once 'common.php'; }
catch (DomainException) { $resurrection_train_refusal = [409, 'Invalid stored training state. Nothing was changed.']; }
catch (Throwable $error) { \Resurrection\Http\FailureLog::record($error); $resurrection_train_refusal = [500, 'Training could not be loaded. Nothing was changed.']; }
if ($resurrection_train_refusal !== null) {
    if (function_exists('resurrection_refusal_framed') && resurrection_refusal_framed()) {
        require_once __DIR__ . '/lib/training_combat.php';
        resurrection_training_refuse(...$resurrection_train_refusal);
    }
    http_response_code($resurrection_train_refusal[0]); exit($resurrection_train_refusal[1]);
}
require_once __DIR__ . '/lib/training_combat.php';
resurrection_training();
