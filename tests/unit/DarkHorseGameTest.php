<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\{DarkHorseState,DiceGame,FiveSixGame,StonesState};
final class DarkHorseGameTest extends TestCase
{
    public function testAccountGameAndAbandonmentAuthority(): void
    {
        $dice=json_encode(['roll'=>2,'tries'=>1,'opponent'=>0],JSON_THROW_ON_ERROR);
        $s=DarkHorseState::start([],7,'game_dice',10,$dice);
        self::assertSame($s,DarkHorseState::read(DarkHorseState::write($s,7),7));
        foreach ([fn()=>DarkHorseState::read(DarkHorseState::write($s,7),8),
            fn()=>DarkHorseState::start($s,7,'game_dice',20,$dice),
            fn()=>DarkHorseState::start($s,7,'game_fivesix',5,'[1,2,3,4,5]'),
            fn()=>DarkHorseState::abandon([],7,'game_dice'),
            fn()=>DarkHorseState::abandon($s,7,'game_stones')] as $act) {
            try { $act(); self::fail('Accepted invalid wager transition.'); } catch (\DomainException) { self::assertTrue(true); }
        }
        $completed=$s; $completed['stage']='complete'; $completed['active']=false; $completed['settled']=true; $completed['result']='tie';
        $completed['data']='{"roll":2,"tries":1,"opponent":2}';
        try { DarkHorseState::abandon($completed,7,'game_dice'); self::fail('Abandoned completed wager.'); } catch (\DomainException) { self::assertTrue(true); }
        $ended=DarkHorseState::abandon($s,7,'game_dice');
        self::assertSame(10,$ended['wager']);
        self::assertSame('abandoned',$ended['result']);
        self::assertFalse($ended['active']);
        self::assertTrue($ended['settled']);
        self::assertSame('game_fivesix',DarkHorseState::start($ended,7,'game_fivesix',5,'[1,2,3,4,5]')['game']);
        $this->expectException(\DomainException::class);
        DarkHorseState::abandon($ended,7,'game_dice');
    }
    public function testMalformedAndInconsistentPersistedStateFailsClosed(): void
    {
        foreach (['O:8:"stdClass":0:{}','1','{}','null',str_repeat('x',2049)] as $json) {
            try { DarkHorseState::read($json,7); self::fail('Accepted malformed state.'); } catch (\DomainException) { self::assertTrue(true); }
        }
        $s=DarkHorseState::start([],7,'game_stones',0,StonesState::encode(['red'=>6,'blue'=>10,'player'=>0,'oldman'=>0,'side'=>'likepair']));
        foreach (['game'=>'forged','owner'=>8,'wager'=>1,'settled'=>true,'active'=>false,'stage'=>'complete','result'=>'win','extra'=>1,'data'=>'[]'] as $key=>$value) {
            $bad=$s; $bad[$key]=$value;
            try { DarkHorseState::write($bad,7); self::fail('Accepted inconsistent '.$key); } catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testDiceHistoricalOpponentStoppingAndFiniteTries(): void
    {
        // Exact old-man branching, including first-roll tie requiring another roll.
        foreach ([[4,[5],5,1],[6,[6],6,1],[4,[4,4],4,2],[4,[1,2,3],3,3]] as [$roll,$sequence,$expected,$calls]) {
            $used=0;
            $random=static function(int $min,int $max) use (&$used,$sequence):int { return $sequence[$used++]; };
            $s=DiceGame::act(['roll'=>$roll,'tries'=>1,'opponent'=>0],'keep',$random);
            self::assertSame($expected,$s['opponent']); self::assertSame($calls,$used);
            try { DiceGame::act($s,'keep',$random); self::fail('Repeated opponent roll.'); } catch (\DomainException) { self::assertSame($calls,$used); }
        }
        $s=['roll'=>1,'tries'=>1,'opponent'=>0];
        $random=static fn(int $min,int $max):int=>6;
        $s=DiceGame::act($s,'pass',$random); $s=DiceGame::act($s,'pass',$random);
        self::assertSame(['roll'=>6,'tries'=>3,'opponent'=>0],$s);
        foreach (['pass','forged','bet'] as $action) {
            try { DiceGame::act($s,$action,$random); self::fail('Invalid action'); } catch (\DomainException) { self::assertSame(3,$s['tries']); }
        }
    }
    public function testFiveSixExactJackpotBranchesAndBounds(): void
    {
        foreach ([[[6,6,6,6,6],105,100,5],[[6,6,6,6,1],11,94,4],[[6,6,6,1,1],5,100,3],[[6,6,1,1,1],0,105,2]] as [$dice,$pay,$pot,$sixes]) {
            self::assertSame(['payout'=>$pay,'jackpot'=>$pot,'sixes'=>$sixes],FiveSixGame::result($dice,100,5,5000));
        }
        self::assertSame(5000,FiveSixGame::result([6,6,6,6,6],4999,5,5000)['payout']);
        foreach ([[[1,2,3,4,7],100,5,5000],[[1,2],100,5,5000],[[1,2,3,4,5],100,-1,5000],[[1,2,3,4,5],-1,5,5000],[[1,2,3,4,5],100,5,99]] as $args) {
            try { FiveSixGame::result(...$args); self::fail('Invalid jackpot accepted.'); } catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
