<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\DrinkInput;

final class DrinkInputTest extends TestCase
{
    public function testTypedEditorPreservesRawText(): void
    {
        $raw="`2O'Reilly \\ 🐉";
        $values=DrinkInput::parse(['name'=>$raw,'remarks'=>'<img src=x>','costperlevel'=>'15','hpmin'=>'-5','hpmax'=>'15','buffatkmod'=>'1.1']);
        self::assertSame($raw,$values['name']);
        self::assertSame('<img src=x>',$values['remarks']);
        self::assertSame(15,$values['costperlevel']);
        self::assertSame(-5,$values['hpmin']);
        self::assertSame('1.1',$values['buffatkmod']);
        self::assertArrayNotHasKey('drinkid',$values);
    }

    public function testEditorRejectsUnknownFieldsMalformedTypesAndRanges(): void
    {
        foreach ([['active'=>'1'],['superuser'=>'64'],['name'=>['injected']],['name'=>str_repeat('x',26)],
            ['costperlevel'=>'-1'],['costperlevel'=>'1e3'],['harddrink'=>'2'],['hpmin'=>'10','hpmax'=>'-1'],
            ['buffatkmod'=>'phpinfo()'],['buffatkmod'=>'1; DROP TABLE accounts'],['turnmin'=>'-6']] as $post) {
            try { DrinkInput::parse($post); self::fail('Invalid drink editor fields accepted'); }
            catch (\InvalidArgumentException $error) { self::assertNotEmpty($error->getMessage()); }
        }
    }
}
