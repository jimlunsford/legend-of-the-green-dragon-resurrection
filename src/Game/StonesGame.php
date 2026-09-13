<?php
declare(strict_types=1);
namespace Resurrection\Game;

final class StonesGame
{
    /**
     * @param array<string, int|string> $state
     * @param callable(int,int): int $random
     * @return array{state: array<string,int|string>, gold: int, drawn: list<string>, settled: bool, change: int}
     */
    public static function act(array $state, int $gold, string $action, string $side, int $bet, callable $random): array
    {
        StonesState::encode($state);
        if ($gold < 0 || $gold > 2147483647) { throw new \DomainException('Invalid balance.'); }
        $drawn = []; $settled = false; $change = 0;
        if ($action === 'choose' && $state === [] && in_array($side, ['likepair','unlikepair'], true)) {
            $state = ['red'=>6,'blue'=>10,'player'=>0,'oldman'=>0,'side'=>$side];
        } elseif ($action === 'bet' && isset($state['side']) && !isset($state['bet']) && $bet > 0 && $bet <= $gold && $bet <= 2147483647 - $gold) {
            $state['bet'] = $bet;
        } elseif ($action === 'draw' && isset($state['bet']) && $state['bet'] <= $gold &&
            $state['red'] + $state['blue'] > 0 && $state['player'] <= 8 && $state['oldman'] <= 8) {
            for ($i = 0; $i < 2; $i++) {
                $roll = $random(1, (int)$state['red'] + (int)$state['blue']);
                if ($roll < 1 || $roll > $state['red'] + $state['blue']) { throw new \DomainException('Invalid draw.'); }
                $color = $roll <= $state['red'] ? 'red' : 'blue';
                $state[$color]--; $drawn[] = $color;
            }
            $playerWins = ($state['side'] === 'likepair') === ($drawn[0] === $drawn[1]);
            $state[$playerWins ? 'player' : 'oldman'] += 2;
        } elseif ($action === 'settle' && isset($state['bet']) &&
            ($state['red'] + $state['blue'] === 0 || $state['player'] > 8 || $state['oldman'] > 8)) {
            $change = ($state['player'] <=> $state['oldman']) * (int)$state['bet'];
            if ($gold + $change < 0 || $gold + $change > 2147483647) { throw new \DomainException('Invalid balance.'); }
            $gold += $change; $settled = true; $state = [];
        } else {
            throw new \DomainException('Invalid Stones action.');
        }
        StonesState::encode($state);
        return ['state'=>$state,'gold'=>$gold,'drawn'=>$drawn,'settled'=>$settled,'change'=>$change];
    }
}
