<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Exact bundled specialty buff vocabulary above the unchanged scalar reader.
 * Text is trusted source text, including LoGD colors and substitution markers.
 * null entries below are numeric producer fields checked separately.
 */
final class SpecialtyBuffState
{
    private const SHAPES = [
        'da1'=>[
            "startmsg"=>"`\$You call on the spirits of the dead, and skeletal hands claw at {badguy} from beyond the grave.",
            "name"=>"`\$Skeleton Crew",
            "rounds"=>5,
            "wearoff"=>"Your skeleton minions crumble to dust.",
            "minioncount"=>null,
            "maxbadguydamage"=>null,
            "effectmsg"=>"`)An undead minion hits {badguy}`) for `^{damage}`) damage.",
            "effectnodmgmsg"=>"`)An undead minion tries to hit {badguy}`) but `\$MISSES`)!",
            "schema"=>"module-specialtydarkarts",
        ],
        'da2'=>[
            "startmsg"=>"`\$You pull out a tiny doll that looks like {badguy}.",
            "effectmsg"=>"You thrust a pin into the {badguy} doll hurting it for `^{damage}`) points!",
            "rounds"=>1,
            "minioncount"=>1,
            "maxbadguydamage"=>null,
            "minbadguydamage"=>null,
            "schema"=>"module-specialtydarkarts",
        ],
        'da3'=>[
            "startmsg"=>"`\$You place a curse on {badguy}'s ancestors.",
            "name"=>"`\$Curse Spirit",
            "rounds"=>5,
            "wearoff"=>"Your curse has faded.",
            "badguydmgmod"=>0.5,
            "roundmsg"=>"{badguy} staggers under the weight of your curse, and deals only half damage.",
            "schema"=>"module-specialtydarkarts",
        ],
        'da5'=>[
            "startmsg"=>"`\$You hold out your hand and {badguy} begins to bleed from its ears.",
            "name"=>"`\$Wither Soul",
            "rounds"=>5,
            "wearoff"=>"Your victim's soul has been restored.",
            "badguyatkmod"=>0,
            "badguydefmod"=>0,
            "roundmsg"=>"{badguy} claws at its eyes, trying to release its own soul, and cannot attack or defend.",
            "schema"=>"module-specialtydarkarts",
        ],
        'da0'=>[
            "startmsg"=>"Exhausted, you try your darkest magic, a bad joke.  {badguy} looks at you for a minute, thinking, and finally gets the joke.  Laughing, it swings at you again.",
            "rounds"=>1,
            "schema"=>"module-specialtydarkarts",
        ],
        'mp1'=>[
            "startmsg"=>"`^You begin to regenerate!",
            "name"=>"`%Regeneration",
            "rounds"=>5,
            "wearoff"=>"You have stopped regenerating.",
            "regen"=>null,
            "effectmsg"=>"You regenerate for {damage} health.",
            "effectnodmgmsg"=>"You have no wounds to regenerate.",
            "aura"=>true,
            "auramsg"=>"`5Your {companion}`5 regenerates for `^{damage} health`5 due to your healing aura.",
            "schema"=>"module-specialtymysticpower",
        ],
        'mp2'=>[
            "startmsg"=>"`^{badguy}`% is clutched by a fist of earth and slammed to the ground!",
            "name"=>"`%Earth Fist",
            "rounds"=>5,
            "wearoff"=>"The earthen fist crumbles to dust.",
            "minioncount"=>1,
            "effectmsg"=>"A huge fist of earth pummels {badguy} for `^{damage}`) points.",
            "minbadguydamage"=>1,
            "maxbadguydamage"=>null,
            "areadamage"=>true,
            "schema"=>"module-specialtymysticpower",
        ],
        'mp3'=>[
            "startmsg"=>"`^Your weapon glows with an unearthly presence.",
            "name"=>"`%Siphon Life",
            "rounds"=>5,
            "wearoff"=>"Your weapon's aura fades.",
            "lifetap"=>1,
            "effectmsg"=>"You are healed for {damage} health.",
            "effectnodmgmsg"=>"You feel a tingle as your weapon tries to heal your already healthy body.",
            "effectfailmsg"=>"Your weapon wails as you deal no damage to your opponent.",
            "schema"=>"module-specialtymysticpower",
        ],
        'mp5'=>[
            "startmsg"=>"`^Your skin sparkles as you assume an aura of lightning.",
            "name"=>"`%Lightning Aura",
            "rounds"=>5,
            "wearoff"=>"With a fizzle, your skin returns to normal.",
            "damageshield"=>2,
            "effectmsg"=>"{badguy} recoils as lightning arcs out from your skin, hitting for `^{damage}`) damage.",
            "effectnodmgmsg"=>"{badguy} is slightly singed by your lightning, but otherwise unharmed.",
            "effectfailmsg"=>"{badguy} is slightly singed by your lightning, but otherwise unharmed.",
            "schema"=>"module-specialtymysticpower",
        ],
        'mp0'=>[
            "startmsg"=>"You furrow your brow and call on the powers of the elements.  A tiny flame appears.  {badguy} lights a cigarette from it, giving you a word of thanks before swinging at you again.",
            "rounds"=>1,
            "schema"=>"module-specialtymysticpower",
        ],
        'ts1'=>[
            "startmsg"=>"`^You call {badguy} a bad name, making it cry.",
            "name"=>"`^Insult",
            "rounds"=>5,
            "wearoff"=>"Your victim stops crying and wipes its nose.",
            "roundmsg"=>"{badguy} feels dejected and cannot attack as well.",
            "badguyatkmod"=>0.5,
            "schema"=>"module-specialtythiefskills",
        ],
        'ts2'=>[
            "startmsg"=>"`^You apply some poison to your {weapon}.",
            "name"=>"`^Poison Attack",
            "rounds"=>5,
            "wearoff"=>"Your victim's blood has washed the poison from your {weapon}.",
            "atkmod"=>2,
            "roundmsg"=>"Your attack is multiplied!",
            "schema"=>"module-specialtythiefskills",
        ],
        'ts3'=>[
            "startmsg"=>"`^With the skill of an expert thief, you virtually disappear, and attack {badguy} from a safer vantage point.",
            "name"=>"`^Hidden Attack",
            "rounds"=>5,
            "wearoff"=>"Your victim has located you.",
            "roundmsg"=>"{badguy} cannot locate you, and swings wildly!",
            "badguyatkmod"=>0,
            "schema"=>"module-specialtythiefskills",
        ],
        'ts5'=>[
            "startmsg"=>"`^Using your skills as a thief, you disappear behind {badguy} and slide a thin blade between its vertebrae!",
            "name"=>"`^Backstab",
            "rounds"=>5,
            "wearoff"=>"Your victim won't be so likely to let you get behind it again!",
            "atkmod"=>3,
            "defmod"=>3,
            "roundmsg"=>"Your attack is multiplied, as is your defense!",
            "schema"=>"module-specialtythiefskills",
        ],
        'ts0'=>[
            "startmsg"=>"You try to attack {badguy} by putting your best thievery skills into practice, but instead, you trip over your feet.",
            "rounds"=>1,
            "schema"=>"module-specialtythiefskills",
        ],
    ];

    /** Other named buffs retain their array representation, not certification.
     * @return array<int|string,array<mixed>>
     */
    public static function collection(mixed $state): array
    {
        if (!is_array($state)) throw new \DomainException('Invalid buff collection.');
        foreach ($state as $id=>$buff) {
            if (!is_array($buff)) throw new \DomainException('Invalid buff entry.');
            $source = $buff['schema'] ?? null;
            if (isset(self::SHAPES[$id]) ||
                (is_string($id) && preg_match('/^(da|mp|ts)[0-9]/D',$id)) ||
                in_array($source,['module-specialtydarkarts','module-specialtymysticpower','module-specialtythiefskills'],true)) {
                self::validate($id,$buff);
            }
        }
        return $state;
    }

    public static function validate(int|string $id, mixed $buff): void
    {
        $shape = self::SHAPES[$id] ?? null;
        if ($shape === null || !is_array($buff)) throw new \DomainException('Invalid specialty buff.');
        $optional = ['startmsg','used','suspended','fields_calculated','tempstats_calculated'];
        if (array_diff(array_keys($buff),[...array_keys($shape),...$optional]) !== [] ||
            array_diff(array_keys($shape),['startmsg',...array_keys($buff)]) !== []) {
            throw new \DomainException('Invalid specialty buff fields.');
        }
        foreach ($shape as $key=>$expected) {
            if ($key === 'startmsg' && !array_key_exists($key,$buff)) continue;
            $value = $buff[$key];
            if ($key === 'rounds') {
                if (!is_int($value) || $value < 1 || $value > $expected) throw new \DomainException('Invalid specialty duration.');
            } elseif ($expected === null) {
                self::number($key === 'regen' && is_string($value) && preg_match('/^[1-9][0-9]*$/D',$value) ? (float)$value : $value);
            } elseif ($value !== $expected) {
                throw new \DomainException('Invalid specialty effect.');
            }
        }
        foreach (['suspended','fields_calculated','tempstats_calculated'] as $key) {
            if (array_key_exists($key,$buff) && !is_bool($buff[$key])) throw new \DomainException('Invalid specialty flag.');
        }
        if (array_key_exists('used',$buff) && !in_array($buff['used'],[0,1],true)) throw new \DomainException('Invalid specialty use flag.');
        // Numeric fields are creation-time values. Do not rebind persistent buffs
        // to the current player level/attack after training or a stat change.
        if ($id === 'mp1' && $buff['regen'] < 1) throw new \DomainException('Invalid regeneration.');
        if ($id === 'mp2' && ($buff['maxbadguydamage'] < 3 || fmod($buff['maxbadguydamage'],3) != 0)) throw new \DomainException('Invalid Earth Fist.');
        if ($id === 'da2' && (fmod($buff['maxbadguydamage'],3) != 0 || $buff['minbadguydamage'] != round($buff['maxbadguydamage']/2))) throw new \DomainException('Invalid Voodoo bounds.');
        if ($id === 'da1') {
            $valid = false;
            $center = ($buff['minioncount']-1)*3;
            foreach ([$center-1,$center,$center+1] as $level) {
                if ($level >= 1 && $buff['minioncount'] == round($level/3)+1 &&
                    $buff['maxbadguydamage'] == round($level/2)+1) $valid = true;
            }
            if (!$valid) throw new \DomainException('Invalid skeleton minions.');
        }
    }

    private static function number(mixed $value): void
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) ||
            $value < 0 || $value > 2147483647 || floor((float)$value) != $value) {
            throw new \DomainException('Invalid specialty statistic.');
        }
    }
}
