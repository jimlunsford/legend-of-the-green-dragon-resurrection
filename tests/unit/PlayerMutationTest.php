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

    public function testInvalidCurrencyEffectsRollBackAccountAndRelatedWrites(): void
    {
        $id=(int)$GLOBALS['session']['user']['acctid'];
        $before=db_query('SELECT gold,gems FROM accounts WHERE acctid=?',true,[$id]);
        foreach ([['gold',-1],['gems',-1],['gold',2147483648],['gems',1.5]] as [$field,$value]) {
            try {
                resurrection_player_mutation(function () use ($field,$value) {
                    $GLOBALS['session']['user'][$field]=$value;
                    db_query('INSERT INTO settings (setting,value) VALUES (?,?)',true,['fixture_currency','uncommitted']);
                });
                self::fail('Invalid currency committed');
            } catch (\DomainException $error) { self::assertSame('Invalid currency balance.',$error->getMessage()); }
            self::assertSame($before,db_query('SELECT gold,gems FROM accounts WHERE acctid=?',true,[$id]));
            self::assertSame([],db_query('SELECT value FROM settings WHERE setting=?',true,['fixture_currency']));
        }
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

    public function testDagPlacementEligibilityFundsQuotaAndClaimReplay(): void
    {
        require_once 'lib/modules.php';
        require_once 'lib/accounts.php';
        require_once 'lib/e_rand.php';
        require_once 'modules/dag/security.php';
        $id=(int)$GLOBALS['session']['user']['acctid'];
        $GLOBALS['session']['user']['loggedin']=true;
        $GLOBALS['module_settings']=[]; $GLOBALS['module_prefs']=[];
        $GLOBALS['injected_modules']=[0=>[],1=>[]];
        $GLOBALS['DB_USEDATACACHE']=0;
        db_query('UPDATE modules SET active=1 WHERE modulename=?',true,['dag']);
        $target=resurrection_insert_account('BountyFixture',$GLOBALS['baseaccount']['password'],'');
        db_query('UPDATE accounts SET level=5,age=10 WHERE acctid=?',true,[$target]);
        $originalGold=$GLOBALS['baseaccount']['gold'];
        db_query('UPDATE accounts SET gold=10000 WHERE acctid=?',true,[$id]);
        $GLOBALS['baseaccount']=db_query('SELECT * FROM accounts WHERE acctid=?',true,[$id])[0];
        $GLOBALS['session']['user']=$GLOBALS['baseaccount'];
        $GLOBALS['session']['user']['loggedin']=true;
        set_module_pref('bounties',0,'dag');
        try {
            foreach ([[$id,250],[999999,250],[$target,-1],[$target,1],[$target,1001]] as [$who,$amount]) {
                try { dag_place_bounty($who,$amount); self::fail('Invalid bounty accepted'); }
                catch (\DomainException) { self::assertSame('10000',db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']); }
            }
            self::assertSame(275,dag_place_bounty($target,250));
            self::assertSame('9725',db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
            self::assertSame('1',db_query('SELECT value FROM module_userprefs WHERE modulename=? AND setting=? AND userid=?',true,['dag','bounties',$id])[0]['value']);
            self::assertCount(1,db_query('SELECT * FROM bounty WHERE target=?',true,[$target]));
            set_module_pref('bounties',5,'dag');
            try { dag_place_bounty($target,250); self::fail('Quota bypass'); }
            catch (\DomainException $error) { self::assertSame('Daily bounty limit reached.',$error->getMessage()); }
            // Authorized admin placements are historically funded by the game.
            db_query('UPDATE bounty SET setdate=? WHERE target=?',true,['2099-01-01 00:00:00',$target]);
            self::assertSame(0,dag_place_bounty($target,250,true));
            self::assertSame([250,0],dag_claim_bounties($target)); // own bounty still delayed
            self::assertSame([0,0],dag_claim_bounties($target));
            self::assertSame('9975',db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
            db_query('UPDATE bounty SET setdate=? WHERE target=? AND setter=?',true,['2020-01-01 00:00:00',$target,$id]);
            self::assertSame([0,250],dag_claim_bounties($target));
            self::assertSame('9975',db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
            $GLOBALS['session']['user']['superuser']=0;
            try { dag_place_bounty($target,250,true); self::fail('Unprivileged administrator action'); }
            catch (\DomainException $error) { self::assertSame('Bounty administration is not authorized.',$error->getMessage()); }
        } finally {
            db_query('DELETE FROM bounty WHERE target=?',true,[$target]);
            db_query('DELETE FROM accounts_output WHERE acctid=?',true,[$target]);
            db_query('DELETE FROM accounts WHERE acctid=?',true,[$target]);
            db_query('UPDATE accounts SET gold=? WHERE acctid=?',true,[$originalGold,$id]);
            db_query('UPDATE modules SET active=0 WHERE modulename=?',true,['dag']);
            db_query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',true,['dag',$id]);
            $GLOBALS['module_prefs']=[];
        }
    }

    public function testDrinkPriceLimitsActivationAndRollback(): void
    {
        require_once 'lib/modules.php';
        require_once 'modules/drinks/security.php';
        $id=(int)$GLOBALS['session']['user']['acctid'];
        $original=$GLOBALS['baseaccount'];
        $GLOBALS['session']['user']['loggedin']=true;
        $GLOBALS['module_settings']=[]; $GLOBALS['module_prefs']=[];
        $GLOBALS['injected_modules']=[0=>[],1=>[]];
        $GLOBALS['DB_USEDATACACHE']=0;
        db_query('UPDATE modules SET active=1 WHERE modulename=?',true,['drinks']);
        $drink=db_query('SELECT * FROM drinks WHERE harddrink=1 ORDER BY drinkid LIMIT 1')[0];
        $drinkId=(int)$drink['drinkid'];
        $settingRows=db_query('SELECT setting,value FROM module_settings WHERE modulename=?',true,['drinks']);
        db_query('UPDATE accounts SET gold=10000,level=5 WHERE acctid=?',true,[$id]);
        $GLOBALS['baseaccount']=db_query('SELECT * FROM accounts WHERE acctid=?',true,[$id])[0];
        $GLOBALS['session']['user']=$GLOBALS['baseaccount'];
        $GLOBALS['session']['user']['loggedin']=true;
        set_module_pref('drunkeness',0,'drinks'); set_module_pref('harddrinks',0,'drinks');
        try {
            $result=drinks_purchase($drinkId);
            self::assertSame($drinkId,(int)$result['drink']['drinkid']);
            self::assertSame((string)(10000-5*(int)$drink['costperlevel']),db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
            self::assertSame(1,(int)get_module_pref('harddrinks','drinks'));
            set_module_pref('harddrinks',3,'drinks'); set_module_pref('drunkeness',0,'drinks');
            $gold=$GLOBALS['session']['user']['gold'];
            try { drinks_purchase($drinkId); self::fail('Hard drink limit bypass'); }
            catch (\DomainException $error) { self::assertSame('Drink limit reached.',$error->getMessage()); }
            self::assertSame($gold,$GLOBALS['session']['user']['gold']);
            set_module_pref('harddrinks',0,'drinks');
            set_module_setting('hardlimit',1,'drinks'); set_module_setting('maxdrunk',0,'drinks');
            drinks_purchase($drinkId); // Exactly at maxdrunk remains allowed historically.
            self::assertSame(1,(int)get_module_pref('harddrinks','drinks'));
            try { drinks_purchase($drinkId); self::fail('Configured quota bypass'); }
            catch (\DomainException $error) { self::assertSame('Drink limit reached.',$error->getMessage()); }
            set_module_pref('harddrinks',0,'drinks'); set_module_pref('drunkeness',1,'drinks');
            try { drinks_purchase($drinkId); self::fail('Configured drunkenness bypass'); }
            catch (\DomainException $error) { self::assertSame('Drink limit reached.',$error->getMessage()); }
            set_module_setting('hardlimit',2147483647,'drinks'); set_module_setting('maxdrunk',100,'drinks');
            set_module_pref('drunkeness',100,'drinks'); drinks_purchase($drinkId);
            self::assertSame(100,(int)get_module_pref('drunkeness','drinks'));
            foreach ([['maxdrunk',101],['maxdrunk',-1],['hardlimit',-1],['hardlimit','1e2']] as [$key,$value]) {
                set_module_setting($key,$value,'drinks');
                $before=db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id]);
                try { drinks_purchase($drinkId); self::fail('Invalid configured limit accepted'); }
                catch (\DomainException $error) { self::assertSame('Invalid drink limits.',$error->getMessage()); }
                self::assertSame($before,db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id]));
                set_module_setting('hardlimit',3,'drinks'); set_module_setting('maxdrunk',66,'drinks');
            }
            $GLOBALS['session']['user']['prefs']=[]; // Match normal authenticated hydration before output-producing hooks.
            $turns=(int)$GLOBALS['session']['user']['turns'];
            resurrection_player_mutation(static function () { modulehook('newday',['turnstoday'=>''],false,'drinks'); });
            self::assertSame(0,(int)get_module_pref('drunkeness','drinks'));
            self::assertSame(0,(int)get_module_pref('harddrinks','drinks'));
            self::assertSame(max(0,$turns-1),(int)$GLOBALS['session']['user']['turns']);
            resurrection_player_mutation(static function () { modulehook('newday',['turnstoday'=>''],false,'drinks'); });
            self::assertSame(max(0,$turns-1),(int)$GLOBALS['session']['user']['turns']);
            $gold=$GLOBALS['session']['user']['gold'];
            db_query('UPDATE drinks SET active=0 WHERE drinkid=?',true,[$drinkId]);
            try { drinks_purchase($drinkId); self::fail('Inactive drink purchased'); }
            catch (\DomainException $error) { self::assertSame('Drink unavailable.',$error->getMessage()); }
            db_query('UPDATE drinks SET active=1,costperlevel=-1 WHERE drinkid=?',true,[$drinkId]);
            try { drinks_purchase($drinkId); self::fail('Negative price purchased'); }
            catch (\DomainException $error) { self::assertSame('Insufficient funds.',$error->getMessage()); }
            self::assertSame((string)$gold,db_query('SELECT gold FROM accounts WHERE acctid=?',true,[$id])[0]['gold']);
            self::assertSame(0,(int)get_module_pref('harddrinks','drinks'));
        } finally {
            db_query('DELETE FROM module_settings WHERE modulename=?',true,['drinks']);
            foreach ($settingRows as $row) db_query('INSERT INTO module_settings(modulename,setting,value) VALUES (?,?,?)',true,['drinks',$row['setting'],$row['value']]);
            $GLOBALS['module_settings']=[];
            db_query('UPDATE drinks SET active=?,costperlevel=? WHERE drinkid=?',true,[$drink['active'],$drink['costperlevel'],$drinkId]);
            $fields=['gold','level','hitpoints','turns','bufflist','loggedin','prefs'];
            db_query('UPDATE accounts SET '.implode(',',array_map(static fn($key)=>db_identifier($key).'=?',$fields)).' WHERE acctid=?',true,[...array_map(static fn($key)=>$original[$key],$fields),$id]);
            db_query('UPDATE modules SET active=0 WHERE modulename=?',true,['drinks']);
            db_query('DELETE FROM module_userprefs WHERE modulename=? AND userid=?',true,['drinks',$id]);
            $GLOBALS['module_prefs']=[];
        }
    }
}
