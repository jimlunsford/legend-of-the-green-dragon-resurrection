<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\StonesGame;
use Resurrection\Security\ActionToken;
use Resurrection\Security\Csrf;

final class StonesGameTest extends TestCase
{
    public function testFullGamePreservesDrawsPayoutAndRejectsSettlementReplay(): void
    {
        $random = static fn(int $min, int $max): int => $min;
        $result = StonesGame::act([],100,'choose','likepair',0,$random);
        $result = StonesGame::act($result['state'],100,'bet','',10,$random);
        for ($i=0; $i<5; $i++) {
            $before = $result['state'];
            $result = StonesGame::act($before,100,'draw','',0,$random);
            self::assertSame(2, count($result['drawn']));
            self::assertSame($before['red']+$before['blue']-2,$result['state']['red']+$result['state']['blue']);
            self::assertSame(100,$result['gold']);
        }
        self::assertSame(10,$result['state']['player']);
        $result = StonesGame::act($result['state'],100,'settle','',0,$random);
        self::assertSame(110,$result['gold']);
        self::assertTrue($result['settled']);
        self::assertSame([],$result['state']);
        $this->expectException(\DomainException::class);
        StonesGame::act($result['state'],110,'settle','',0,$random);
    }

    public function testLossDrawAndInvalidBusinessActions(): void
    {
        $random = static fn(int $min, int $max): int => $min;
        $lose = ['red'=>0,'blue'=>6,'player'=>0,'oldman'=>10,'side'=>'unlikepair','bet'=>10];
        self::assertSame(90,StonesGame::act($lose,100,'settle','',0,$random)['gold']);
        $tie = ['red'=>0,'blue'=>0,'player'=>8,'oldman'=>8,'side'=>'likepair','bet'=>10];
        self::assertSame(100,StonesGame::act($tie,100,'settle','',0,$random)['gold']);
        $ready = ['red'=>6,'blue'=>10,'player'=>0,'oldman'=>0,'side'=>'likepair'];
        foreach ([[$ready,100,'bet','',-1],[$ready,100,'bet','',101],[$ready,100,'bet','',0],
            [$ready,2147483647,'bet','',1],[$ready,100,'choose','unlikepair',0],
            [$ready,100,'settle','',0],[$ready,100,'draw','',0],[[],100,'choose','forged',0],
            [$lose,0,'settle','',0],[$ready+['bet'=>10],100,'bet','',20]] as $args) {
            try { StonesGame::act(...[...$args,$random]); self::fail('Accepted invalid action'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testOneTimeIntentRequiresMatchingScopeContextAndCsrf(): void
    {
        $session=[]; $csrf=Csrf::token($session);
        $nonce=ActionToken::issue($session,'stones','state1');
        foreach ([['other','state1',$nonce],['stones','state2',$nonce],['stones','state1',str_repeat('0',64)],['stones','state1',[]]] as $args) {
            try { ActionToken::consume($session,...$args); self::fail('Accepted forged intent'); }
            catch (\DomainException) { self::assertArrayHasKey('stones',$session['actions']); }
        }
        foreach ([['GET',$csrf],['POST',''],['POST',[]]] as $args) {
            try { Csrf::requirePost($session,...$args); self::fail('Accepted forged form'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        Csrf::requirePost($session,'POST',$csrf);
        ActionToken::consume($session,'stones','state1',$nonce);
        self::assertArrayNotHasKey('stones',$session['actions']);
        $this->expectException(\DomainException::class);
        ActionToken::consume($session,'stones','state1',$nonce);
    }
}
