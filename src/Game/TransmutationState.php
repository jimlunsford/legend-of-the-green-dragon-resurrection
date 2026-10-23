<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Exact business schema within the existing bounded scalar buff container. */
final class TransmutationState
{
    /** @return array<string,int|float|string> */
    public static function create(int $rounds,float $attack,float $defense,int $survive): array
    {
        if ($rounds<1 || $rounds>2147483647 || !is_finite($attack) || !is_finite($defense) ||
            $attack<.1 || $attack>2 || $defense<.1 || $defense>2 || !in_array($survive,[0,1],true)) throw new \DomainException('Invalid transmutation.');
        return ['name'=>'`6Transmutation Sickness','rounds'=>$rounds,
            'wearoff'=>'You stop puking your guts up.  Literally.', 'atkmod'=>$attack,'defmod'=>$defense,
            'roundmsg'=>'Bits of skin and bone reshape themselves like wax.','survivenewday'=>$survive,
            'newdaymessage'=>'`6Due to the effects of the Transmutation Potion, you still feel `2ill`6.',
            'schema'=>'module-cedrikspotions'];
    }

    /** @return array<string,int|float|string|bool> */
    public static function read(mixed $value): array
    {
        if (!is_array($value)) throw new \DomainException('Invalid transmutation.');
        $base=self::create(1,.75,.75,1);
        if (array_diff(array_keys($base),array_keys($value)) || array_diff(array_keys($value),[...array_keys($base),'used','suspended','fields_calculated','tempstats_calculated'])) throw new \DomainException('Invalid transmutation keys.');
        foreach (['rounds','atkmod','defmod','survivenewday'] as $key) {
            if (!is_int($value[$key]) && !is_float($value[$key]) && !is_string($value[$key])) throw new \DomainException('Invalid transmutation type.');
            if (!preg_match('/\A(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)\z/',(string)$value[$key])) throw new \DomainException('Invalid transmutation number.');
        }
        if (filter_var($value['rounds'],FILTER_VALIDATE_INT)===false || filter_var($value['survivenewday'],FILTER_VALIDATE_INT)===false) throw new \DomainException('Invalid transmutation integer.');
        $result=self::create((int)$value['rounds'],(float)$value['atkmod'],(float)$value['defmod'],(int)$value['survivenewday']);
        foreach ($base as $key=>$expected) if (is_string($expected) && $value[$key]!==$expected) throw new \DomainException('Invalid transmutation text.');
        foreach (['used','suspended','fields_calculated','tempstats_calculated'] as $key) {
            if (isset($value[$key])) {
                if (!in_array($value[$key],[0,1,false,true],true)) throw new \DomainException('Invalid runtime flag.');
                $result[$key]=$value[$key];
            }
        }
        return $result;
    }
}
