<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

final class PlayerMutationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!getenv('RESURRECTION_TEST_DB_HOST')) self::markTestSkipped('Requires disposable installed database.');
        self::assertSame('resurrection_test',getenv('RESURRECTION_TEST_DB_NAME'));
        require_once 'lib/dbwrapper_pdo.php';
        require_once 'lib/player_mutation.php';
        require_once 'lib/buffs.php';
        self::assertTrue(db_connect(getenv('RESURRECTION_TEST_DB_HOST'),getenv('RESURRECTION_TEST_DB_USER'),getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        $GLOBALS['DB_PREFIX']='';
        $user=db_query('SELECT * FROM accounts WHERE login=?',true,['FixtureAdmin'])[0];
        $GLOBALS['baseaccount']=$user;
        $GLOBALS['session']=['loggedin'=>true,'user'=>$user,'bufflist'=>[]];
        $GLOBALS['companions']=[];
        $GLOBALS['buffreplacements']=[];
    }

    public function testPlayerAndRelatedWriteRollbackTogetherThenRetry(): void
    {
        $id=(int)$GLOBALS['session']['user']['acctid'];
        $gold=$GLOBALS['session']['user']['gold'];
        db_query('DELETE FROM settings WHERE setting=?',true,['fixture_mutation']);
        try {
            resurrection_player_mutation(function () {
                $GLOBALS['session']['user']['gold']+=10;
                db_query('INSERT INTO settings (setting,value) VALUES (?,?)',true,['fixture_mutation','credited']);
                throw new \RuntimeException('Synthetic failure');
            });
            self::fail('Failure was hidden');
        } catch (\RuntimeException $error) { self::assertSame('Synthetic failure',$error->getMessage()); }
        self::assertSame($gold,db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
        self::assertSame($gold,$GLOBALS['session']['user']['gold']);
        self::assertSame([],db_query('SELECT * FROM settings WHERE setting=?',true,['fixture_mutation']));
        resurrection_player_mutation(function () {
            $GLOBALS['session']['user']['gold']+=10;
            db_query('INSERT INTO settings (setting,value) VALUES (?,?)',true,['fixture_mutation','credited']);
        });
        self::assertSame((string)((int)$gold+10),db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
        self::assertSame('credited',db_query('SELECT value FROM settings WHERE setting=?',true,['fixture_mutation'])[0]['value']);
        db_query('UPDATE accounts SET gold=? WHERE acctid=?',true,[$gold,$id]);
        db_query('DELETE FROM settings WHERE setting=?',true,['fixture_mutation']);
    }

    public function testConcurrentChangedBalanceRefusesStaleMutation(): void
    {
        $id=(int)$GLOBALS['session']['user']['acctid'];
        $gold=$GLOBALS['session']['user']['gold'];
        db_query('UPDATE accounts SET gold=gold+1 WHERE acctid=?',true,[$id]);
        $called=false;
        try {
            resurrection_player_mutation(function () use (&$called) { $called=true; });
            self::fail('Stale account accepted');
        } catch (\DomainException) { self::assertFalse($called); }
        finally { db_query('UPDATE accounts SET gold=? WHERE acctid=?',true,[$gold,$id]); }
        self::assertFalse($GLOBALS['dbinfo']['connection']->inTransaction());
    }
}
