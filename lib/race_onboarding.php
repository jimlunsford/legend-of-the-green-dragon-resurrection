<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/newday_daily.php';

/**
 * The shipped race identities, resolved only through active installed modules.
 * @return array<string, string>
 */
function resurrection_race_choices(bool $lock = false): array {
    $bundled = ['racehuman'=>'Human', 'raceelf'=>'Elf', 'racedwarf'=>'Dwarf', 'racetroll'=>'Troll'];
    $rows = db_query('SELECT modulename,active FROM ' . db_prefix('modules') .
        ' WHERE modulename IN (?,?,?,?) ORDER BY modulename' . ($lock ? ' FOR UPDATE' : ''), true, array_keys($bundled));
    $choices = [];
    foreach ($rows as $row) {
        if ((int)$row['active'] === 1 && injectmodule($row['modulename'])) {
            $choices[$bundled[$row['modulename']]] = $row['modulename'];
        }
    }
    return $choices;
}

/** The race 1.1.2 gave a player when no race module was installed (lib/newday/setrace.php). */
const RESURRECTION_RACE_FALLBACK = 'Human';

/**
 * The one Continue of a new day question that has nothing to choose (Phase 6 task 28): a POST form with this question's
 * action token that sets 1.1.2's fallback, so the page is never a dead end and a GET changes nothing.
 */
function resurrection_onboarding_fallback_form(string $question, string $field, string $value, string $context): void {
    global $resline;
    $url = 'newday.php?continue=1' . $resline;
    addnav('', $url);
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
        resurrection_action_fields($question . '-onboarding', $context) .
        '<input type="hidden" name="onboarding" value="' . $question . '"><input type="hidden" name="' . $field . '" value="' .
        htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"><button class="button">' .
        htmlspecialchars(translate_inline('Continue'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>');
}

/** @param array<string, string> $choices */
function resurrection_race_context(array $choices): string {
    global $session;
    $state = [];
    foreach (['acctid','race','specialty','lasthit','age','dragonkills','dragonpoints','specialinc','location','alive'] as $key) {
        $state[$key] = $session['user'][$key];
    }
    return hash('sha256', json_encode([$state,$choices,getsetting('villagename', LOCATION_FIELDS)], JSON_THROW_ON_ERROR));
}

function resurrection_race_form(string $race): void {
    global $resline;
    $choices = resurrection_race_choices();
    if (!isset($choices[$race])) return;
    $url = 'newday.php?continue=1' . $resline;
    addnav('', $url);
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
        resurrection_action_fields('race-onboarding', resurrection_race_context($choices)) .
        '<input type="hidden" name="onboarding" value="race"><input type="hidden" name="setrace" value="' .
        htmlspecialchars($race, ENT_QUOTES, 'UTF-8') . '"><button class="button">' .
        htmlspecialchars(translate_inline($race), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>');
}

function resurrection_race_eligible(): void {
    global $session;
    if (empty($session['loggedin']) || empty($session['user']['acctid']) ||
        !in_array($session['user']['race'], ['', '0', RACE_UNKNOWN], true) ||
        $session['user']['specialinc'] !== '' ||
        count($session['user']['dragonpoints']) < (int)$session['user']['dragonkills']) {
        throw new DomainException('Race selection is unavailable.');
    }
}

/** Shared HTTP choice boundary; the four race hooks keep their own story/runtime. */
function resurrection_race_onboarding(): void {
    global $session, $resline;
    try {
        resurrection_race_eligible();
        $choices = resurrection_race_choices();
        $context = resurrection_race_context($choices);
        // With no race module active, 1.1.2 made the player Human and went on (lib/newday/setrace.php, "No Races
        // Installed"); so does this page, by its one Continue (Phase 6 task 28).
        page_header($choices === [] ? 'No Races Installed' : 'A little history about yourself');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            resurrection_consume_action('race-onboarding', $context);
            if (array_diff(array_keys($_POST), ['csrf_token','action_token','onboarding','setrace']) !== [] ||
                array_diff(array_keys($_GET), ['continue','resurrection','c']) !== []) throw new InvalidArgumentException('Unexpected field.');
            \Resurrection\Http\Input::choice($_POST, 'onboarding', ['race'], '');
            $race = \Resurrection\Http\Input::choice($_POST, 'setrace', $choices === [] ? [RESURRECTION_RACE_FALLBACK] : array_keys($choices), '');
            resurrection_player_mutation(function () use ($race, $context) {
                global $session;
                resurrection_race_eligible();
                $locked = resurrection_race_choices(true);
                $village = db_query('SELECT value FROM ' . db_prefix('settings') . ' WHERE setting=? FOR UPDATE', true, ['villagename']);
                if (count($village) !== 1) throw new DomainException('Village setting changed.');
                $GLOBALS['settings']['villagename'] = $village[0]['value'];
                if (resurrection_race_context($locked) !== $context) throw new DomainException('Race state changed.');
                if ($locked === []) {
                    // 1.1.2's fallback: no race module to tell, so no setrace hook.
                    if ($race !== RESURRECTION_RACE_FALLBACK) throw new DomainException('Race state changed.');
                } elseif (!isset($locked[$race])) throw new DomainException('Race state changed.');
                $session['user']['race'] = $race;
                $session['user']['location'] = getsetting('villagename', LOCATION_FIELDS);
                if ($locked !== []) modulehook('setrace', [], false, $locked[$race]);
            });
            // The fallback has no race's story to show: on to the rest of the new day, as 1.1.2's one Continue went.
            if ($choices === []) redirect('newday.php?continue=1' . $resline, 'No race module active.');
            addnav('Continue', 'newday.php?continue=1' . $resline);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($choices === []) {
                output("No races were installed in this game. So we'll call you a 'human' and get on with it.");
                resurrection_onboarding_fallback_form('race', 'setrace', RESURRECTION_RACE_FALLBACK, $context);
            } else {
                output('Where do you recall growing up?`n`n');
                foreach ($choices as $module) modulehook('chooserace', [], false, $module);
            }
        } else {
            resurrection_require_post();
        }
    } catch (InvalidArgumentException $error) {
        resurrection_newday_refuse(400, 'Invalid race selection.');
    } catch (DomainException $error) {
        resurrection_newday_refuse(409, 'Race selection is unavailable.');
    } catch (Throwable $error) {
        \Resurrection\Http\FailureLog::record($error); resurrection_newday_refuse(500, 'Race selection was not completed. Nothing was changed.');
    }
    page_footer();
}
