<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\MotdPoll;

final class MotdPollTest extends TestCase
{
    public function testHistoricalPollsAndChoices(): void
    {
        // 1.1.2 stored the slashed request values; the five option fields always posted, blank ones included.
        $poll = ['body'=>"Which is best? It\\'s <b>yours</b> 雪", 'opt'=>['Red', '  ', "O\\'Reilly", '', 'Blue']];
        self::assertSame($poll, MotdPoll::read(serialize($poll)));
        self::assertSame(serialize($poll), MotdPoll::encode($poll));
        self::assertSame([0=>'Red', 2=>"O\\'Reilly", 4=>'Blue'], MotdPoll::choices($poll));
        $sparse = ['body'=>'', 'opt'=>[3=>'Three', 7=>'Seven']];
        self::assertSame($sparse, MotdPoll::read(serialize($sparse)));
        self::assertSame([], MotdPoll::choices(['body'=>'x', 'opt'=>[]]));
    }

    public function testMalformedPollsAreRefused(): void
    {
        foreach ([['opt'=>[], 'body'=>'x'], ['body'=>'x'], ['body'=>'x', 'opt'=>false], ['body'=>5, 'opt'=>[]], ['body'=>'x', 'opt'=>['a'=>'x']],
            ['body'=>'x', 'opt'=>[-1=>'x']], ['body'=>'x', 'opt'=>[['x']]], ['body'=>'x', 'opt'=>[5]], ['body'=>'x', 'opt'=>[], 'extra'=>1],
            ['body'=>'x', 'opt'=>array_fill(0, MotdPoll::MAX_OPTIONS + 1, 'x')], ['body'=>str_repeat('x', MotdPoll::MAX_BYTES), 'opt'=>[]]] as $poll) {
            try { MotdPoll::read(serialize($poll)); self::fail('Malformed poll accepted: ' . json_encode($poll)); }
            catch (\DomainException) { self::assertTrue(true); }
            try { MotdPoll::encode($poll); self::fail('Malformed poll stored.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (['', 'broken', 'b:0;', 'O:8:"stdClass":0:{}', null, 5] as $encoded) {
            try { MotdPoll::read($encoded); self::fail('Malformed poll accepted.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
