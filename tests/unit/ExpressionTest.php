<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\Expression;
use Resurrection\Game\CreatureAi;

final class ExpressionTest extends TestCase
{
    public function testEveryBundledNumericExpression(): void
    {
        self::assertSame(100.0, Expression::evaluate('return 100;'));
        self::assertSame(20.0, Expression::evaluate('return 20;'));
        self::assertSame(1.0, Expression::evaluate('return true;'));
        self::assertSame(0.0, Expression::evaluate('return false;'));
        foreach (['raceelf'=>'defense', 'racetroll'=>'attack'] as $module=>$stat) {
            $source = file_get_contents('modules/' . $module . '.php');
            preg_match('/"(?:atkmod|defmod)"=>"([^"]+)"/', $source, $match);
            self::assertNotEmpty($match, $module);
            self::assertSame(1.3, Expression::evaluate($match[1], [$stat=>10, 'level'=>10]));
            self::assertSame(0.0, Expression::evaluate($match[1], [$stat=>0, 'level'=>10]));
            self::assertSame(1.3, Expression::evaluate('debug:' . $match[1], [$stat=>'10', 'level'=>'10']));
        }
    }

    public function testRejectsUnsupportedSyntaxAndInjection(): void
    {
        foreach (['', 'return system("id");', '1;phpinfo()', '$session["user"]["gold"]',
            '<password>', '<missing|pref>', '1/0', 'floor(1)', 'new stdClass', 'include "x"',
            '(function(){return 1;})()', 'true || system("id")', '100' . str_repeat('0', 510)] as $expression) {
            try { Expression::evaluate($expression); self::fail('Unsupported expression accepted.'); }
            catch (\DomainException $error) { self::assertSame('Unsupported game expression.', $error->getMessage()); }
        }
        $this->expectException(\DomainException::class);
        Expression::evaluate('(<attack>?(1+((1+floor(<level>/5))/<attack>)):0)', ['attack'=>[], 'level'=>1]);
    }

    public function testHistoricalAiAndNamedBehaviorPreserveOneTimeTheft(): void
    {
        $legacy = file_get_contents('tests/fixtures/gypsy-bandit-ai.txt');
        foreach ([$legacy, str_replace("\n", "\r\n", $legacy), 'bundled:gypsy-bandit'] as $script) {
            $player = ['gold'=>2000]; $creature = ['creaturegold'=>499];
            self::assertSame(0.0, CreatureAi::apply($script, $player, $creature, 1));
            self::assertSame(400.0, CreatureAi::apply($script, $player, $creature, 0));
            self::assertSame(1600.0, $player['gold']);
            self::assertSame(899.0, $creature['creaturegold']);
            self::assertSame(0.0, CreatureAi::apply($script, $player, $creature, 0));
        }
        $player = ['gold'=>1000]; $creature = ['creaturegold'=>499];
        self::assertSame(0.0, CreatureAi::apply('bundled:gypsy-bandit', $player, $creature, 0));
        foreach ([$legacy . ';phpinfo();', 'system("id");', '1', 'bundled:unknown'] as $script) {
            self::assertFalse(CreatureAi::supported($script));
            try { CreatureAi::apply($script, $player, $creature, 0); self::fail('Unknown AI accepted.'); }
            catch (\DomainException $error) { self::assertSame('Unsupported creature behavior.', $error->getMessage()); }
            self::assertSame(1000, $player['gold']);
        }
    }
}
