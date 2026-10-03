<?php
declare(strict_types=1);
namespace Resurrection\Game;
final class FiveSixGame
{
    /** @return list<int> */
    public static function decode(string $json): array
    {
        try { $dice=json_decode($json,true,3,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \DomainException('Invalid dice.'); }
        if (!is_array($dice) || !array_is_list($dice) || count($dice)!==5) throw new \DomainException('Invalid dice.');
        foreach ($dice as $die) if (!is_int($die) || $die<1 || $die>6) throw new \DomainException('Invalid die.');
        return $dice;
    }
    /** @param list<int> $dice
     * @return array{payout:int,jackpot:int,sixes:int}
     */
    public static function result(array $dice, int $pot, int $cost, int $maximum): array
    {
        self::decode(json_encode($dice,JSON_THROW_ON_ERROR));
        if ($cost<1 || $cost>1073741823 || $maximum<100 || $maximum>1073741823 || $pot<0 || $pot>$maximum) throw new \DomainException('Invalid jackpot settings.');
        $pot=min($maximum,$pot+$cost);
        $sixes=count(array_filter($dice,static fn(int $die):bool=>$die===6));
        $payout=match($sixes) {5=>$pot,4=>(int)round($pot*.1),3=>(int)round($pot*.05),default=>0};
        return ['payout'=>$payout,'jackpot'=>$sixes===5 ? 100 : $pot-$payout,'sixes'=>$sixes];
    }
}
