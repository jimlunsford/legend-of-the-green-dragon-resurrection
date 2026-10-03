<?php
// Reject request authority before bootstrap, including legacy victory and master overrides.
if (array_diff(array_keys($_GET), ['op','c']) !== [] ||
    array_diff(array_keys($_POST), ['csrf_token','action_token','rounds']) !== []) {
    http_response_code(400); exit('Invalid training action.');
}
try { require_once 'common.php'; }
catch (DomainException) { http_response_code(409); exit('Invalid stored training state. Stored state was preserved.'); }
catch (Throwable) { http_response_code(500); exit('Training could not be loaded. Stored state was preserved.'); }
require_once __DIR__ . '/lib/training_combat.php';
resurrection_training();
