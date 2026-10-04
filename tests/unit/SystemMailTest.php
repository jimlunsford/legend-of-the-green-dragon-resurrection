<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\GameMasterMailSender;

require_once __DIR__.'/../../lib/systemmail.php';

final class SystemMailTest extends TestCase
{
    protected function setUp(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) self::markTestSkipped('Requires installed disposable CI database.');
        self::assertSame('resurrection_test',getenv('RESURRECTION_TEST_DB_NAME'));
        self::assertTrue(db_connect($host,getenv('RESURRECTION_TEST_DB_USER'),getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        $GLOBALS['DB_PREFIX']=''; $GLOBALS['DB_USEDATACACHE']=0; $GLOBALS['DB_DATACACHEPATH']='';
        $GLOBALS['settings']=null; $GLOBALS['session']=['user'=>['acctid'=>1]];
    }

    public function testMissingAndMalformedRecipientsAndSendersNeverInsert(): void
    {
        $before=db_query('SELECT COUNT(*) n FROM mail')[0]['n'];
        self::assertFalse(systemmail(4294967295,['Subject'],['Body'],0,true));
        foreach ([0,-1,'1 OR 1=1','01','999999999999999999',[],false] as $to) {
            try { systemmail($to,['Subject'],['Body'],0,true); self::fail('Invalid recipient accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        foreach (['System','Ramius','1e0','01',[],false,4294967295] as $from) {
            try { systemmail(1,'Subject','Body',$from,true); self::fail('Unresolved sender accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        self::assertSame($before,db_query('SELECT COUNT(*) n FROM mail')[0]['n']);
    }

    public function testTranslatedSchemaAndExplicitTransactionNotificationOwnership(): void
    {
        $db=$GLOBALS['dbinfo']['connection']; $before=db_query('SELECT COUNT(*) n FROM mail')[0]['n'];
        $db->beginTransaction();
        try {
            try { systemmail(1,['Subject'],['Body']); self::fail('Unowned notification transaction'); }
            catch (\LogicException $e) { self::assertNotEmpty($e->getMessage()); }
            $GLOBALS['mail_notifications']=[];
            self::assertTrue(systemmail(1,['`%Referral'],['Hello %s, %s%%','Jim',5],0,true));
            self::assertCount(1,$GLOBALS['mail_notifications']);
            $row=db_query('SELECT * FROM mail ORDER BY messageid DESC LIMIT 1')[0];
            self::assertSame('0',$row['msgfrom']);self::assertSame(serialize(['`%Referral']),$row['subject']);
            self::assertSame(serialize(['Hello %s, %s%%','Jim',5]),$row['body']);
            foreach ([[[]],['%s',[]],['%s'],['%s',new \stdClass]] as $bad) {
                try { systemmail(1,['Subject'],$bad,0,true);self::fail('Bad business payload accepted'); }
                catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
            }
            self::assertCount(1,$GLOBALS['mail_notifications']);
        } finally {$db->rollBack();unset($GLOBALS['mail_notifications']);}
        self::assertSame($before,db_query('SELECT COUNT(*) n FROM mail')[0]['n']);
    }

    public function testPlainPlayerAndGmPayloadsNeverBecomeSystemTranslation(): void
    {
        $db=$GLOBALS['dbinfo']['connection']; $db->beginTransaction();$GLOBALS['mail_notifications']=[];
        try {
            db_query('UPDATE accounts SET prefs=?,superuser=? WHERE acctid=1',true,['a:1:{s:10:"dirtyemail";b:1;}',SU_IS_GAMEMASTER]);
            $literal=serialize(['Plain-looking translation']);
            self::assertTrue(systemmail(1,$literal,$literal,1,true));
            $row=db_query('SELECT * FROM mail ORDER BY messageid DESC LIMIT 1')[0];
            self::assertSame('1',$row['msgfrom']); self::assertSame($literal,$row['subject']);self::assertSame($literal,$row['body']);
            $actor=db_query('SELECT acctid,superuser,locked FROM accounts WHERE acctid=1')[0];
            $gm=GameMasterMailSender::resolve($actor,'Ramius');
            self::assertTrue(systemmail(1,$literal,$literal,$gm,true));
            self::assertSame('Ramius',db_query('SELECT msgfrom FROM mail ORDER BY messageid DESC LIMIT 1')[0]['msgfrom']);
            foreach ($GLOBALS['mail_notifications'] as $note) {self::assertSame(0,$note[6]);self::assertTrue(resurrection_systemmail_notification(...$note));}
            db_query('UPDATE accounts SET superuser=0 WHERE acctid=1');
            try {systemmail(1,'Subject','Body',$gm,true);self::fail('Revoked GM accepted');}
            catch (\DomainException $e) {self::assertNotEmpty($e->getMessage());}
            try {systemmail(1,['Subject'],['Body'],1,true);self::fail('Player array accepted');}
            catch (\DomainException $e) {self::assertNotEmpty($e->getMessage());}
        } finally {$db->rollBack();unset($GLOBALS['mail_notifications']);}
    }
}
