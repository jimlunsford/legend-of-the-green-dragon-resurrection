<?php
// Presentation only. All allocation authority is in dragon_points.php.
page_header('Dragon Points');
output('`@You earn one dragon point each time you slay the dragon.');
output('Advancements made by spending dragon points are permanent!`n`n');
output('You have `^%s`@ unspent dragon points. How do you wish to spend them?`n`n', $unspent);
$url = 'newday.php?continue=1'.$resline;
addnav('', $url);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if ($unspent === 1) {
    foreach ($schema['buy'] as $type => $buyable) {
        if (!$buyable) continue;
        rawoutput('<form method="POST" action="'.$escape($url).'">'.resurrection_action_fields('dragon-points', $context).
            '<input type="hidden" name="allocation" value="single"><input type="hidden" name="type" value="'.$escape($type).'">'.
            '<button class="button">'.$escape(translate_inline($schema['desc'][$type])).'</button></form>');
    }
} else {
    rawoutput('<form id="dkForm" method="POST" action="'.$escape($url).'">'.resurrection_action_fields('dragon-points', $context).
        '<input type="hidden" name="allocation" value="bulk">');
    foreach ($schema['buy'] as $type => $buyable) {
        if (!$buyable) continue;
        rawoutput('<p><label>'.$escape(translate_inline($schema['desc'][$type])).
            ' <input name="'.$escape($type).'" type="number" min="0" max="'.$unspent.'" step="1" value="0" required></label></p>');
    }
    rawoutput('<p id="amtLeft"></p><button class="button">'.$escape(translate_inline('Spend')).'</button>'.
        '<button type="reset">'.$escape(translate_inline('Reset')).'</button></form>');
    rawoutput('<script>const dpForm=document.getElementById("dkForm");function pointsLeft(){let left='.$unspent.';'.
        'dpForm.querySelectorAll("input[type=number]").forEach(input=>{left-=Number(input.value)||0;});'.
        'document.getElementById("amtLeft").textContent=left+" points left to spend.";}'.
        'dpForm.addEventListener("input",pointsLeft);dpForm.addEventListener("reset",()=>setTimeout(pointsLeft,0));pointsLeft();</script>');
}
$distribution = array_fill_keys(array_keys($schema['desc']), 0);
foreach ($session['user']['dragonpoints'] as $type) $distribution[isset($distribution[$type]) ? $type : 'unknown']++;
output('`n`nCurrently, the dragon points you have already spent are distributed in the following manner.');
rawoutput('<table>');
foreach ($distribution as $type => $count) {
    if ($type === 'unknown' && $count === 0) continue;
    rawoutput('<tr><td>'.$escape(translate_inline($schema['desc'][$type])).'</td><td>'.$count.'</td></tr>');
}
rawoutput('</table>');
