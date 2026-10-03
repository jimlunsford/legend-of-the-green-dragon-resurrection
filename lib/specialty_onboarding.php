<?php
require_once __DIR__ . '/player_mutation.php';

/**
 * The shipped specialty identities, resolved only through active installed modules.
 * @return array<string, string>
 */
function resurrection_specialty_choices(bool $lock = false): array {
    $bundled = ['specialtydarkarts'=>'DA', 'specialtymysticpower'=>'MP', 'specialtythiefskills'=>'TS'];
    $rows = db_query('SELECT modulename,active FROM ' . db_prefix('modules') .
        ' WHERE modulename IN (?,?,?) ORDER BY modulename' . ($lock ? ' FOR UPDATE' : ''), true, array_keys($bundled));
    $choices = [];
    foreach ($rows as $row) {
        if ((int)$row['active'] === 1 && injectmodule($row['modulename'])) {
            $choices[$bundled[$row['modulename']]] = $row['modulename'];
        }
    }
    return $choices;
}

/** @param array<string, string> $choices */
function resurrection_specialty_context(array $choices): string {
    global $session;
    $state = [];
    foreach (['acctid','race','specialty','lasthit','age','dragonkills','dragonpoints','specialinc','location','alive'] as $key) {
        $state[$key] = $session['user'][$key];
    }
    return hash('sha256', json_encode([$state,$choices,resurrection_specialty_preferences($choices)], JSON_THROW_ON_ERROR));
}

function resurrection_specialty_form(string $specialty): void {
    global $resline;
    $choices = resurrection_specialty_choices();
    if (!isset($choices[$specialty])) return;
    $url = 'newday.php?continue=1' . $resline;
    addnav('', $url);
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
        resurrection_action_fields('specialty-onboarding', resurrection_specialty_context($choices)) .
        '<input type="hidden" name="onboarding" value="specialty"><input type="hidden" name="setspecialty" value="' .
        htmlspecialchars($specialty, ENT_QUOTES, 'UTF-8') . '"><button class="button">' .
        htmlspecialchars(translate_inline(['DA'=>'Dark Arts','MP'=>'Mystical Powers','TS'=>'Thieving Skills'][$specialty]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>');
}

/**
 * Read defaults without persisting them during form display. Lock before mutation.
 * @param array<string, string> $choices
 * @return array<string, array<string, int>>
 */
function resurrection_specialty_preferences(array $choices, bool $lock = false): array {
    global $session;
    $values = [];
    foreach ($choices as $module) {
        $values[$module] = ['skill'=>0, 'uses'=>0];
        $rows = db_query('SELECT setting,value FROM ' . db_prefix('module_userprefs') .
            ' WHERE userid=? AND modulename=? AND setting IN (?,?) ORDER BY setting' . ($lock ? ' FOR UPDATE' : ''),
            true, [(int)$session['user']['acctid'], $module, 'skill', 'uses']);
        foreach ($rows as $row) {
            if (!is_string($row['value']) || !preg_match('/^(0|[1-9][0-9]{0,8})$/D', $row['value'])) {
                throw new DomainException('Invalid specialty preference.');
            }
            $values[$module][$row['setting']] = (int)$row['value'];
        }
    }
    return $values;
}

function resurrection_specialty_eligible(): void {
    global $session;
    if (empty($session['loggedin']) || empty($session['user']['acctid']) ||
        $session['user']['specialty'] !== '' ||
        in_array($session['user']['race'], ['', '0', RACE_UNKNOWN], true) ||
        $session['user']['specialinc'] !== '' ||
        count($session['user']['dragonpoints']) < (int)$session['user']['dragonkills']) {
        throw new DomainException('Specialty selection is unavailable.');
    }
}

/** Shared HTTP choice boundary; shipped hooks retain their story and runtime. */
function resurrection_specialty_onboarding(): void {
    global $session, $resline;
    try {
        resurrection_specialty_eligible();
        $choices = resurrection_specialty_choices();
        $context = resurrection_specialty_context($choices);
        page_header('A little history about yourself');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            resurrection_consume_action('specialty-onboarding', $context);
            if (array_diff(array_keys($_POST), ['csrf_token','action_token','onboarding','setspecialty']) !== [] ||
                array_diff(array_keys($_GET), ['continue','resurrection','c']) !== []) throw new InvalidArgumentException('Unexpected field.');
            \Resurrection\Http\Input::choice($_POST, 'onboarding', ['specialty'], '');
            $specialty = \Resurrection\Http\Input::choice($_POST, 'setspecialty', array_keys($choices), '');
            resurrection_player_mutation(function () use ($specialty, $context) {
                global $session;
                resurrection_specialty_eligible();
                $locked = resurrection_specialty_choices(true);
                $preferences = resurrection_specialty_preferences($locked, true);
                if (resurrection_specialty_context($locked) !== $context || !isset($locked[$specialty])) throw new DomainException('Specialty state changed.');
                $session['user']['specialty'] = $specialty;
                foreach ($preferences[$locked[$specialty]] as $key => $value) {
                    set_module_pref($key, $value, $locked[$specialty]);
                }
                modulehook('set-specialty', [], false, $locked[$specialty]);
            });
            addnav('Continue', 'newday.php?continue=1' . $resline);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($choices === []) {
                output('No active bundled specialties are available. Please ask an administrator to activate a specialty.');
            } else {
                output('What do you recall doing as a child?`n`n');
                foreach ($choices as $module) modulehook('choose-specialty', [], false, $module);
            }
        } else {
            resurrection_require_post();
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid specialty selection.');
    } catch (DomainException $error) {
        http_response_code(409); exit('Specialty selection is unavailable.');
    } catch (Throwable $error) {
        http_response_code(500); exit('Specialty selection was not completed. Request a fresh form.');
    }
    page_footer();
}
