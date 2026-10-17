<?php
$thispage_superuser_level = 0;
function check_su_access($level){
    global $session,$thispage_superuser_level;
    $thispage_superuser_level |= $level;
    if (empty($session['loggedin']) || !((int)($session['user']['superuser'] ?? 0) & $level)) {
        http_response_code(403);
        exit('This action is not authorized.');
    }
    $result = modulehook('check_su_access', ['enabled' => true, 'level' => $level]);
    if (empty($result['enabled'])) { http_response_code(403); exit('This action is not authorized.'); }
}
