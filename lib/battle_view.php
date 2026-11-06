<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The battle screen (Phase 5 task 10, docs/PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md).
 *
 * battle.php and the combat routes print as they always did; this file sorts what they print into the screen's parts
 * and page_footer() puts them back in this order:
 *
 *  1. the fighter cards: the player, then each foe, with their hitpoint bars (show_enemies() registers the foes);
 *  2. the actions: Fight (the one primary) and Run in a row, then automatic fighting, the specialty skills and the
 *     superuser actions, each a labelled group of secondary buttons (resurrection_battle_action());
 *  3. the battle log: the lines before the first round, then an ordered list, one item per round, each line a row;
 *  4. the result block: what was printed after the last round (victory or defeat, gold, experience, gems, death).
 *
 * What the page printed before the fight comes first, as it was; what a route prints after resurrection_battle_close()
 * comes last. Until Phase 5 task 15 a 1.1.2 skin got battle.php's output as 1.1.2 printed it (resurrection_battle_ui()
 * was false there); the skins and that check are gone.
 */

$resurrection_battle = null;

/**
 * Starts the battle screen on this page, once: what the page printed so far is kept before it, and the page buffer
 * starts empty for what the fight prints.
 */
function resurrection_battle_open(): void {
	global $resurrection_battle, $output;
	if ($resurrection_battle !== null) return;
	$resurrection_battle = array(
		'prefix'=>(string)$output, 'phase'=>'after', 'intro'=>'', 'rounds'=>array(), 'after'=>'', 'tail'=>'',
		'enemies'=>null, 'fight'=>'', 'counter'=>1, 'actions'=>array(), 'notes'=>array(), 'victory'=>false, 'defeat'=>false,
	);
	$output = '';
}

/** Moves what the page buffer holds to the part of the screen the fight is in, and empties the buffer. */
function resurrection_battle_flush(): void {
	global $resurrection_battle, $output;
	if ($resurrection_battle === null) return;
	$phase = $resurrection_battle['phase'];
	if (is_int($phase)) $resurrection_battle['rounds'][$phase] .= (string)$output;
	elseif ($phase === 'skip') { /* A second battle on one page: its opening lines are already shown. */ }
	else $resurrection_battle[$phase] .= (string)$output;
	$output = '';
}

/**
 * The foes the cards show (show_enemies(), and battle.php after its rounds). The last call wins.
 *
 * @param array<int|string, array<string, mixed>> $enemies
 */
function resurrection_battle_enemies(array $enemies): void {
	global $resurrection_battle, $enemycounter, $options;
	resurrection_battle_open();
	$resurrection_battle['enemies'] = $enemies;
	// The fight's type picks each foe's portrait (Phase 5 task 14): battle.php's $options, as the route stored them.
	$resurrection_battle['fight'] = is_array($options ?? null) && is_string($options['type'] ?? null) ? $options['type'] : '';
	$resurrection_battle['counter'] = (int)($enemycounter ?? count($enemies));
}

/** battle.php begins: what it prints before its first round opens the log. */
function resurrection_battle_begin(): void {
	global $resurrection_battle;
	resurrection_battle_open();
	resurrection_battle_flush();
	$resurrection_battle['phase'] = $resurrection_battle['rounds'] === array() ? 'intro' : 'skip';
}

/**
 * battle.php has printed what a skill or a new target said, before the encounter lines: it is held back and follows
 * them (resurrection_battle_release()), so the log opens with "You have encountered ...".
 */
function resurrection_battle_hold(): void {
	global $resurrection_battle;
	if ($resurrection_battle === null) return;
	resurrection_battle_flush();
	$resurrection_battle['held'] = $resurrection_battle['phase'] === 'intro' ? $resurrection_battle['intro'] : '';
	if ($resurrection_battle['phase'] === 'intro') $resurrection_battle['intro'] = '';
}

/** The held lines follow the encounter lines. */
function resurrection_battle_release(): void {
	global $resurrection_battle, $output;
	if ($resurrection_battle === null) return;
	$output .= $resurrection_battle['held'] ?? '';
	$resurrection_battle['held'] = '';
	resurrection_battle_flush();
}

/** battle.php starts a round: one more item in the log. */
function resurrection_battle_round(): void {
	global $resurrection_battle;
	if ($resurrection_battle === null) return;
	resurrection_battle_flush();
	$resurrection_battle['rounds'][] = '';
	$resurrection_battle['phase'] = count($resurrection_battle['rounds']) - 1;
}

/** battle.php's rounds are over: what follows is the result. */
function resurrection_battle_rounds_end(bool $victory, bool $defeat): void {
	global $resurrection_battle;
	if ($resurrection_battle === null) return;
	resurrection_battle_flush();
	$resurrection_battle['phase'] = 'after';
	$resurrection_battle['victory'] = $resurrection_battle['victory'] || $victory;
	$resurrection_battle['defeat'] = $resurrection_battle['defeat'] || $defeat;
}

/**
 * Ends the result block: what the route prints next (the graveyard's page, the Dragon's epilogue form) follows the
 * battle screen as ordinary page content. Nothing on a page without a battle screen.
 */
function resurrection_battle_close(): void {
	global $resurrection_battle;
	if ($resurrection_battle === null) return;
	resurrection_battle_flush();
	$resurrection_battle['phase'] = 'tail';
}

/**
 * Everything the fight has printed so far, in print order, for the battle-victory and battle-defeat hooks' fightoutput
 * (a module reads the page as 1.1.2 printed it). With no battle screen open, the page buffer itself.
 */
function resurrection_battle_fightoutput(): string {
	global $resurrection_battle, $output;
	if ($resurrection_battle === null) return (string)$output;
	return $resurrection_battle['prefix'].$resurrection_battle['intro'].implode('', $resurrection_battle['rounds'])
		.$resurrection_battle['after'].(string)$output;
}

/**
 * One of the fight's action forms: it joins its group in the action bar: 'main' (Fight and Run, in a row), 'auto'
 * (automatic fighting), 'special' (specialty skills) or 'other' (superuser actions).
 */
function resurrection_battle_action(string $group, string $html): void {
	global $resurrection_battle;
	resurrection_battle_open();
	$resurrection_battle['actions'][$group][] = $html;
}

/** A line under a group's heading (the specialty's uses left). */
function resurrection_battle_note(string $group, string $text): void {
	global $resurrection_battle;
	resurrection_battle_open();
	$resurrection_battle['notes'][$group] = $text;
}

/** A form's button class: the one primary action (Fight) and every other, secondary. */
function resurrection_battle_button(bool $primary = false): string {
	return resurrection_button_class($primary);
}

/**
 * HTML split into its lines at each <br>, each line balanced: a tag open at a line break is closed at its end and
 * opened again at the start of the next line, a closing tag with nothing open is dropped. Lines with no text are left
 * out. With $split false, the whole HTML as one balanced piece.
 *
 * @return list<string>
 */
function resurrection_battle_lines(string $html, bool $split = true): array {
	static $void = array('br'=>1, 'img'=>1, 'input'=>1, 'hr'=>1, 'meta'=>1, 'link'=>1, 'wbr'=>1, 'area'=>1, 'source'=>1, 'col'=>1);
	$lines = array();
	/** @var list<array{0: string, 1: string}> $stack the open tags: name, and the tag as written */
	$stack = array();
	$line = '';
	foreach (preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: array() as $piece) {
		if ($piece[0] !== '<') { $line .= $piece; continue; }
		if ($split && preg_match('/^<br\b[^>]*>$/i', $piece)) {
			$lines[] = $line.resurrection_battle_closing($stack);
			$line = resurrection_battle_reopening($stack);
			continue;
		}
		if (preg_match('/^<\/([a-z][a-z0-9]*)/i', $piece, $m)) {
			$name = strtolower($m[1]);
			$found = -1;
			for ($i = count($stack) - 1; $i >= 0; $i--) if ($stack[$i][0] === $name) { $found = $i; break; }
			if ($found < 0) continue;
			while (count($stack) > $found) { $open = array_pop($stack); $line .= '</'.$open[0].'>'; }
			continue;
		}
		$line .= $piece;
		if (preg_match('/^<([a-z][a-z0-9]*)/i', $piece, $m) && !isset($void[strtolower($m[1])]) && substr($piece, -2) !== '/>') {
			$stack[] = array(strtolower($m[1]), $piece);
		}
	}
	$lines[] = $line.resurrection_battle_closing($stack);
	if (!$split) return array($lines[0]);
	$kept = array();
	foreach ($lines as $one) {
		$text = trim(str_replace(array('&nbsp;', "\xc2\xa0"), ' ', strip_tags($one, '<img><input><button><select><textarea>')));
		if ($text !== '') $kept[] = trim($one);
	}
	return $kept;
}

/**
 * The closing tags for the open tags, innermost first.
 *
 * @param list<array{0: string, 1: string}> $stack
 */
function resurrection_battle_closing(array $stack): string {
	$out = '';
	foreach (array_reverse($stack) as $open) $out .= '</'.$open[0].'>';
	return $out;
}

/**
 * The open tags as written, outermost first, to open them again on the next line.
 *
 * @param list<array{0: string, 1: string}> $stack
 */
function resurrection_battle_reopening(array $stack): string {
	$out = '';
	foreach ($stack as $open) $out .= $open[1];
	return $out;
}

/**
 * A foe's maximum hitpoints, when the game knows them: recorded when the fight started (creaturemaxhealth: the forest
 * and the Dragon, since task 10), the master's (trainingmaxhp) or the PvP victim's (pvpmaxhp) as their routes record
 * them, or a graveyard torment's, which 1.1.2 always created with the player's level × 5 + 50. Null otherwise (a fight
 * that started before task 10, an enemy a script added during the fight): the card shows the number with no bar.
 *
 * @param array<string, mixed> $enemy
 */
function resurrection_battle_foe_max(array $enemy, bool $alive): ?float {
	foreach (array('creaturemaxhealth', 'trainingmaxhp', 'pvpmaxhp') as $key) {
		if (isset($enemy[$key]) && is_numeric($enemy[$key]) && (float)$enemy[$key] > 0) return (float)$enemy[$key];
	}
	if (!$alive && isset($enemy['creaturelevel']) && is_numeric($enemy['creaturelevel'])) return (float)$enemy['creaturelevel'] * 5 + 50;
	return null;
}

/** Plain text of a name or weapon with color codes, escaped for HTML. */
function resurrection_battle_plain(mixed $text): string {
	require_once __DIR__ . '/sanitize.php';
	return htmlspecialchars(trim(full_sanitize((string)$text)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * One fighter's hitpoints: a bar with "current / max" when the maximum is known, else the number alone.
 */
function resurrection_battle_hp(string $label, int|float|string $current, ?float $max, ?string $text = null): string {
	$num = function ($value) { return (string)round((float)$value); };
	if ($text !== null || $max === null) {
		return templatereplace('fighterhp', array('label'=>$label, 'value'=>$text ?? $num($current)));
	}
	// The fill's class (rz-fill-0 to rz-fill-100), as the character panel's bars (Phase 6 task 3).
	$fill = $max > 0 ? resurrection_fill_percent((float)$current / $max * 100) : "0";
	return templatereplace('fighterbar', array('label'=>$label, 'percent'=>$fill,
		'value'=>$num(max(0, (float)$current)).' / '.$num($max)));
}

/**
 * A foe's portrait on its card (Phase 5 task 14), through the template's fighterportrait part, or nothing: by the
 * fight's type, the creature's, the master's, the Green Dragon's or a PvP victim's race portrait (Art::foe()); a
 * creature with no portrait of its own gets the fallback of the type a module names through the `creatureportrait`
 * hook, or the unknown one. The alt text is the foe's name as the card shows it, its color codes dropped.
 *
 * @param array<array-key, mixed> $enemy
 */
function resurrection_battle_portrait(array $enemy, string $fight): string {
	require_once __DIR__ . '/sanitize.php';
	require_once __DIR__ . '/../src/Ui/Art.php';
	$alt = trim(full_sanitize((string)($enemy['creaturename'] ?? '')));
	$art = \Resurrection\Ui\Art::foe($enemy, $fight, $alt, function (array $args) { return modulehook('creatureportrait', $args); });
	if ($art === null) return '';
	return templatereplace('fighterportrait', array(
		'src'=>htmlspecialchars($art['src'], ENT_QUOTES, 'UTF-8'),
		'alt'=>htmlspecialchars($art['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
		'width'=>(string)$art['width'],
		'height'=>(string)$art['height'],
	));
}

/** The fighter cards: the player, then each foe, as the fight left them. */
function resurrection_battle_cards(): string {
	global $resurrection_battle, $session;
	$u = $session['user'];
	$alive = !empty($u['alive']);
	tlschema('battle');
	$level = function ($value) { return sprintf_translate('Level %s', (int)$value); };
	$cards = '';
	if (!empty($session['loggedin']) && !empty($u['loggedin'])) {
		$hp = $alive
			? resurrection_battle_hp(translate_inline('HP', 'common'), $u['hitpoints'], (float)$u['maxhitpoints'])
			: resurrection_battle_hp(translate_inline('Soul', 'common'), $u['soulpoints'], (float)($u['level'] * 5 + 50));
		$cards .= templatereplace('fighter', array('kind'=>'player', 'state'=>'', 'sub'=>htmlspecialchars($level($u['level']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			'hp'=>$hp, 'name'=>resurrection_battle_plain($u['name']), 'portrait'=>''));
	}
	foreach ($resurrection_battle['enemies'] ?? array() as $enemy) {
		if (!is_array($enemy)) continue;
		$sub = htmlspecialchars($level($enemy['creaturelevel'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		if (trim((string)($enemy['creatureweapon'] ?? '')) !== '') $sub .= ' &middot; <i>'.resurrection_battle_plain($enemy['creatureweapon']).'</i>';
		$label = $alive ? translate_inline('HP', 'common') : translate_inline('Soul', 'common');
		$state = '';
		$text = null;
		$max = resurrection_battle_foe_max($enemy, $alive);
		if ((float)($enemy['creaturehealth'] ?? 0) <= 0) {
			$state = ' rz-fighter-down';
			$text = resurrection_battle_plain(translate_inline($alive ? '`7DEAD`0' : '`7DEFEATED`0'));
			if ($max !== null) $text = '0 / '.round($max).' &middot; '.$text;
		} elseif (!empty($enemy['hidehitpoints'])) {
			$text = '???';
		}
		$target = '';
		if (!empty($enemy['istarget']) && $resurrection_battle['counter'] > 1) {
			$state .= ' rz-fighter-target';
			$target = ' <span class="rz-fighter-mark">'.htmlspecialchars(translate_inline('Target'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';
		}
		$cards .= templatereplace('fighter', array('kind'=>'foe', 'state'=>$state, 'sub'=>$sub,
			'hp'=>resurrection_battle_hp($label, $enemy['creaturehealth'] ?? 0, $max, $text),
			'name'=>resurrection_battle_plain($enemy['creaturename'] ?? '').$target,
			'portrait'=>resurrection_battle_portrait($enemy, (string)$resurrection_battle['fight'])));
	}
	tlschema();
	return $cards === '' ? '' : templatereplace('fighters', array('cards'=>$cards));
}

/** The action bar: Fight and Run in a row, then each labelled group. */
function resurrection_battle_actions(): string {
	global $resurrection_battle;
	$actions = $resurrection_battle['actions'];
	if ($actions === array()) return '';
	// The headings fightnav() gave these groups as nav headings, in its translation schema.
	$titles = array('auto'=>'Automatic Fighting', 'special'=>'Special Abilities', 'other'=>'`&Super user`0');
	$main = isset($actions['main']) ? templatereplace('battlemain', array('buttons'=>implode('', $actions['main']))) : '';
	$groups = '';
	foreach ($titles as $group=>$title) {
		if (empty($actions[$group])) continue;
		$note = isset($resurrection_battle['notes'][$group])
			? templatereplace('battlenote', array('text'=>htmlspecialchars($resurrection_battle['notes'][$group], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))) : '';
		$groups .= templatereplace('battlegroup', array('id'=>$group, 'note'=>$note, 'buttons'=>implode('', $actions[$group]),
			'title'=>resurrection_battle_plain(translate_inline($title, 'fightnav'))));
	}
	return templatereplace('battleactions', array('main'=>$main, 'groups'=>$groups));
}

/** Lines as rows of the log or the result block. */
function resurrection_battle_rows(string $html): string {
	$out = '';
	foreach (resurrection_battle_lines($html) as $line) $out .= templatereplace('battleline', array('text'=>$line));
	return $out;
}

/** The battle log: the opening lines, then one item per round. */
function resurrection_battle_log(): string {
	global $resurrection_battle;
	$intro = resurrection_battle_rows($resurrection_battle['intro']);
	$rounds = '';
	tlschema('battle');
	foreach ($resurrection_battle['rounds'] as $round) {
		$rows = resurrection_battle_rows($round);
		// A round that printed nothing (a foe past its attacks) shows no item.
		if ($rows === '') continue;
		$rounds .= templatereplace('battleround', array('lines'=>$rows));
	}
	$label = translate_inline('Battle log');
	tlschema();
	if ($intro === '' && $rounds === '') return '';
	return templatereplace('battlelog', array('intro'=>$intro === '' ? '' : templatereplace('battleintro', array('lines'=>$intro)),
		'rounds'=>$rounds === '' ? '' : templatereplace('battlerounds', array('rounds'=>$rounds)),
		'label'=>htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
}

/** The result block: what followed the last round, headed Victory or Defeat when the fight ended. */
function resurrection_battle_result(): string {
	global $resurrection_battle;
	$rows = resurrection_battle_rows($resurrection_battle['after']);
	if ($rows === '') return '';
	if (!$resurrection_battle['victory'] && !$resurrection_battle['defeat']) return templatereplace('battleafter', array('lines'=>$rows));
	tlschema('battle');
	$title = translate_inline($resurrection_battle['victory'] ? 'Victory' : 'Defeat');
	tlschema();
	return templatereplace('battleresult', array('kind'=>$resurrection_battle['victory'] ? 'victory' : 'defeat', 'lines'=>$rows,
		'title'=>htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
}

/**
 * page_footer(): puts the page back together when it has a battle screen: what came before the fight, the cards, the
 * actions, the log, the result, then what the route printed after the battle. Each piece is balanced, so no color or
 * bold the fight left open runs into the next.
 */
function resurrection_battle_assemble(): void {
	global $resurrection_battle, $output, $nestedtags;
	if ($resurrection_battle === null) return;
	resurrection_battle_flush();
	$prefix = resurrection_battle_lines($resurrection_battle['prefix'], false)[0];
	$tail = resurrection_battle_lines($resurrection_battle['tail'], false)[0];
	$screen = resurrection_battle_cards().resurrection_battle_actions().resurrection_battle_log().resurrection_battle_result();
	$output = $prefix.templatereplace('battle', array('screen'=>$screen)).$tail;
	if (is_array($nestedtags)) foreach ($nestedtags as $key=>$open) $nestedtags[$key] = false;
	$resurrection_battle = null;
}
?>
