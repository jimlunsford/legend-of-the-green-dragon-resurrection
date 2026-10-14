<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\MailContent;
use Resurrection\Security\GameMasterMailSender;

final class MailContentTest extends TestCase
{
    public function testSubjectAndUtf8ByteBoundaries(): void
    {
        foreach (['','hello',str_repeat('雪',255)] as $text) self::assertSame($text, MailContent::subject($text));
        self::assertSame('abc',MailContent::subject("a`n\r\nb\0\tc"));
        foreach (['', 'abc', 'abcd', 'abcdef'] as $text) self::assertSame(substr($text,0,4),MailContent::body($text,4));
        self::assertSame("a\nb\nc\nd\ne",MailContent::body("a\r\nb\rc\nd`ne",100));
        self::assertSame('雪',MailContent::body('雪雪',5));
        self::assertSame(chr(92),MailContent::body(chr(92),1024));
        foreach ([str_repeat('x',256),str_repeat('雪',256),"\xff",[]] as $bad) {
            try { MailContent::subject($bad); self::fail('Invalid subject accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        foreach ([0,-1,65536] as $bad) {
            try { MailContent::body('text',$bad); self::fail('Invalid limit accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
    }
    public function testTranslationBusinessSchemaAndPlainPayload(): void
    {
        foreach ([['hello'],['%s %d %.2f %%','name',2,1.5],['%s',true]] as $valid) {
            self::assertSame($valid,MailContent::translated($valid));
            self::assertSame($valid,MailContent::stored(serialize($valid)));
        }
        foreach ([[],['x'=>1],[1],['%s',[]],['%s',new \stdClass],['%s',null],['%s',INF],['%s'],['bad %q',1],['plain',1],['%999999s','x'],array_fill(0,34,'x')] as $bad) {
            try { MailContent::translated($bad); self::fail('Invalid translation accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        self::assertNull(MailContent::stored('a:1:{i:0;a:0:{}}'));
        $literal='a:1:{i:0;s:5:"hello";}';
        self::assertSame($literal,MailContent::subject($literal));
        self::assertSame($literal,MailContent::body($literal,1024));
    }
    /** Remaining legacy System callers: fixed formats, scalar arguments, worst-case persisted values. */
    public function testLegacySystemCallerEnvelopesFitStorage(): void
    {
        require_once __DIR__.'/../../lib/nltoappon.php';
        $name=str_repeat("\u{1F409}",60); // accounts.name VARCHAR(60), four-byte characters
        $reminder="`&Did you remember to read the description of the clan of your choice before applying?  Note that some clans may have requirements that you have to fulfill before you can become a member.  If you are not accepted into the clan of your choice anytime soon, it may be because you have not fulfilled these requirements.  For your convenience, the description of the clan you are applying to is reproduced below.`n`n`c`#%s`@ <`^%s`@>`0`c`n%s";
        $subjects=[['`)You have been haunted'],['`@Clan App: `&%s`0',$name],['Clan Application Reminder'],['`$Clan Withdraw: `&%s`0',$name],
            ['`^You have received a money transfer!`0'],['Your bio has been blocked'],['Your bio has been unblocked'],['Donation Point Added'],['Donation Points Added']];
        $bodies=[['`)You have been haunted by `&%s`).',$name],
            ['`^You have a new clan applicant!  `&%s`^ has completed a membership application for your clan!',$name],
            // clan_motd.php stores at most 4096 description bytes; newline expansion doubles them.
            [$reminder,str_repeat("\u{1F409}",255),str_repeat("\u{1F409}",5),nltoappon(str_repeat("\n",4096))],
            ['`^One of your clan members has resigned their membership.  `&%s`^ has surrendered their position within your clan!',$name],
            ['`&%s`6 has transferred `^%s`6 gold to your bank account!',$name,PHP_INT_MAX],
            ['The system administrators have decided that your bio entry is inappropriate, so it has been blocked.`n`nIf you wish to appeal this decision, you may do so with the petition link.'],
            ['The system administrators have decided to unblock your bio.  You can once again enter a bio entry.'],
            ['`2You have received a donation point for %s.',str_repeat('雪',21000)],
            ['`2You have received %d donation points for %s.','2147483647',str_repeat('雪',21000)]];
        foreach ($subjects as $subject) self::assertLessThanOrEqual(255,mb_strlen(serialize(MailContent::translated($subject)),'UTF-8'));
        foreach ($bodies as $body) self::assertLessThanOrEqual(65535,strlen(serialize(MailContent::translated($body))));
        self::assertSame('',nltoappon((string)null)); // New clans have a NULL description and receive no reminder.
        foreach ([[$reminder,'Clan','PRB',null],['`2You have received a donation point for %s.',['reason']]] as $bad) {
            try { MailContent::translated($bad); self::fail('Unsupported legacy argument accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
    }
    public function testStrictTransportAndSenderClasses(): void
    {
        MailContent::transport('to=A&body=O%27Reilly', ['to'=>'A','body'=>"O'Reilly"], ['to','body']);
        self::assertSame(42,MailContent::id('42')); self::assertSame(0,MailContent::id(0,true));
        foreach (['0',0,-1,'1e2','System','01','4294967296',[],false,1.5] as $bad) {
            try { MailContent::id($bad); self::fail('Invalid account accepted'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        foreach (['to=a&to=b','to[]=x','to.x=x','to%20x=x','msgfrom=0','to','to=x;body=y'] as $raw) {
            try { MailContent::transport($raw,['to'=>'b'],['to']); self::fail('Malformed transport accepted'); }
            catch (\InvalidArgumentException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        $gm=['acctid'=>7,'superuser'=>SU_IS_GAMEMASTER,'locked'=>0];
        self::assertSame('Ramius',GameMasterMailSender::resolve($gm,'Ramius')->label);
        foreach (['System','`^System','0','1','1e2','1foo','',"bad\nlabel",'<img>',str_repeat('a',256)] as $label) {
            try { GameMasterMailSender::resolve($gm,$label); self::fail('Invalid display identity'); }
            catch (\DomainException $e) { self::assertNotEmpty($e->getMessage()); }
        }
        $gm['superuser']=0;
        $this->expectException(\DomainException::class); GameMasterMailSender::resolve($gm,'Ramius');
    }
}
