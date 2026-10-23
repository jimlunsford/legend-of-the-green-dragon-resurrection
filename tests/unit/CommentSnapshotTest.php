<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\CommentSnapshot;

final class CommentSnapshotTest extends TestCase
{
    private function row(): array
    {
        return ['commentid'=>'12', 'section'=>'village', 'author'=>'5', 'comment'=>"says \"O'Reilly \\ 雪 <b>\"", 'postdate'=>'2026-10-22 10:00:00'];
    }

    public function testBothHistoricalShapesAndTheModernWriter(): void
    {
        // moderate.php: name, login, clanrank, clanshort (NULL without a clan); the commentary Del link: name, acctid, clanrank, clanshort.
        $moderate = $this->row() + ['name'=>'`2Joe', 'login'=>'Joe', 'clanrank'=>'0', 'clanshort'=>null];
        $del = $this->row() + ['name'=>'`2Joe', 'acctid'=>'5', 'clanrank'=>'31', 'clanshort'=>'ABC'];
        $system = ['commentid'=>'13', 'section'=>null, 'author'=>'0', 'comment'=>str_repeat('雪', 200), 'postdate'=>null] +
            ['name'=>'System', 'login'=>'', 'clanrank'=>0, 'clanshort'=>''];
        foreach ([$moderate, $del, $system, $this->row()] as $snapshot) {
            self::assertSame($snapshot, CommentSnapshot::read(serialize($snapshot)));
            self::assertSame(serialize($snapshot), CommentSnapshot::encode($snapshot));
        }
    }

    public function testMalformedSnapshotsAreRefused(): void
    {
        $row = $this->row();
        $bad = [];
        foreach (array_keys($row) as $key) { $missing = $row; unset($missing[$key]); $bad[] = $missing; }
        foreach ([['commentid'=>'0'], ['commentid'=>'1 OR 1'], ['commentid'=>[]], ['author'=>'-1'], ['author'=>'4294967296'], ['author'=>1.0],
            ['section'=>str_repeat('x', 21)], ['section'=>5], ['comment'=>str_repeat('x', 201)], ['comment'=>"\xff"], ['comment'=>null],
            ['postdate'=>'yesterday'], ['postdate'=>5], ['name'=>['x']], ['login'=>str_repeat('x', 256)], ['clanrank'=>'256'],
            ['clanrank'=>'x'], ['acctid'=>'-5'], ['clanshort'=>5], ['extra'=>'x']] as $change) {
            $bad[] = array_replace($row, $change);
        }
        foreach ($bad as $snapshot) {
            try { CommentSnapshot::read(serialize($snapshot)); self::fail('Malformed snapshot accepted: ' . json_encode($snapshot)); }
            catch (\DomainException) { self::assertTrue(true); }
            try { CommentSnapshot::encode($snapshot); self::fail('Malformed snapshot stored.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (['', 'broken', 'b:0;', serialize([]), serialize(['x']), 'O:8:"stdClass":0:{}', null, 5] as $encoded) {
            try { CommentSnapshot::read($encoded); self::fail('Malformed snapshot accepted.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
