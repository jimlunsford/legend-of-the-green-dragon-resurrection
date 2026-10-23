<?php
require_once __DIR__ . '/player_mutation.php';

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
        page_header('A little history about yourself');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            resurrection_consume_action('race-onboarding', $context);
            if (array_diff(array_keys($_POST), ['csrf_token','action_token','onboarding','setrace']) !== [] ||
                array_diff(array_keys($_GET), ['continue','resurrection','c']) !== []) throw new InvalidArgumentException('Unexpected field.');
            \Resurrection\Http\Input::choice($_POST, 'onboarding', ['race'], '');
            $race = \Resurrection\Http\Input::choice($_POST, 'setrace', array_keys($choices), '');
            resurrection_player_mutation(function () use ($race, $context) {
                global $session;
                resurrection_race_eligible();
                $locked = resurrection_race_choices(true);
                $village = db_query('SELECT value FROM ' . db_prefix('settings') . ' WHERE setting=? FOR UPDATE', true, ['villagename']);
                if (count($village) !== 1) throw new DomainException('Village setting changed.');
                $GLOBALS['settings']['villagename'] = $village[0]['value'];
                if (resurrection_race_context($locked) !== $context || !isset($locked[$race])) throw new DomainException('Race state changed.');
                $session['user']['race'] = $race;
                $session['user']['location'] = getsetting('villagename', LOCATION_FIELDS);
                modulehook('setrace', [], false, $locked[$race]);
            });
            addnav('Continue', 'newday.php?continue=1' . $resline);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($choices === []) {
                output('No active bundled races are available. Please ask an administrator to activate a race.');
            } else {
                output('Where do you recall growing up?`n`n');
                foreach ($choices as $module) modulehook('chooserace', [], false, $module);
            }
        } else {
            resurrection_require_post();
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid race selection.');
    } catch (DomainException $error) {
        http_response_code(409); exit('Race selection is unavailable.');
    } catch (Throwable $error) {
        http_response_code(500); exit('Race selection was not completed. Request a fresh form.');
    }
    page_footer();
}
