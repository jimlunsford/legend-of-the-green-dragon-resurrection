<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\Passwords;
use Resurrection\Security\Csrf;

final class WebSecurityTest extends TestCase
{
    public function testPasswordsRejectReplayAndMalformedCredentials(): void
    {
        $password = "Synthetic O'Reilly \\ password";
        $hash = Passwords::hash($password);
        self::assertTrue(Passwords::verify($password, $hash));
        self::assertFalse(Passwords::verify('wrong password', $hash));
        self::assertFalse(Passwords::verify($hash, $hash));
        self::assertFalse(Passwords::verify('!md52!' . $hash, $hash));
        self::assertFalse(Passwords::verify([], $hash));
        self::assertFalse(Passwords::verify(null, $hash));
        self::assertFalse(Passwords::verify("bad\0password", $hash));
        self::assertFalse(Passwords::verify($password, md5(md5($password))));
        self::assertFalse(password_needs_rehash($hash, PASSWORD_DEFAULT));
    }
    public function testHashingDoesNotSilentlyTruncate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Passwords::hash(str_repeat('a', 73));
    }
    public function testCsrfIsSessionBoundAndDoesNotAcceptArrays(): void
    {
        $one = $two = [];
        $token = Csrf::token($one);
        self::assertSame($token, Csrf::token($one));
        self::assertNotSame($token, Csrf::token($two));
        self::assertTrue(Csrf::valid($one, $token));
        self::assertFalse(Csrf::valid($two, $token));
        self::assertFalse(Csrf::valid([], $token));
        self::assertFalse(Csrf::valid($one, [$token]));
        self::assertFalse(Csrf::valid($one, ''));
        self::assertStringContainsString($token, Csrf::field($one));
        Csrf::requirePost($one, 'POST', $token);
    }
    public function testCsrfRejectsGetEvenWithValidToken(): void
    {
        $state = [];
        $token = Csrf::token($state);
        $this->expectException(\DomainException::class);
        Csrf::requirePost($state, 'GET', $token);
    }
    public function testCsrfRejectsPostWithWrongToken(): void
    {
        $state = [];
        Csrf::token($state);
        $this->expectException(\DomainException::class);
        Csrf::requirePost($state, 'POST', str_repeat('0', 64));
    }
    public function testPlayerIdCookieKeepsOnlyTheGeneratedFormat(): void
    {
        require_once 'lib/web_security.php';
        $valid = md5('1.1.2 generated this from microtime()');
        $stored = bin2hex(random_bytes(16));
        // A valid cookie is the player's ID and is not resent.
        self::assertSame([$valid, false], resurrection_player_id($valid, $stored));
        self::assertSame([strtoupper($valid), false], resurrection_player_id(strtoupper($valid), ''));
        $invalid = ['', str_repeat('a', 31), str_repeat('a', 33), str_repeat('f', 64), str_repeat('g', 32), "AAAAAAAAA'><svg/onload=alert(1)>",
            ' ' . substr($valid, 1), substr($valid, 1) . "\n", null, [$valid], 12345678901234567890123456789012];
        foreach ($invalid as $cookie) {
            // Anything else is no cookie: the stored ID when it is valid, else a new one; either is resent.
            self::assertSame([$stored, true], resurrection_player_id($cookie, $stored));
            foreach ([null, '', '"><img src=y>', str_repeat('b', 40), $cookie] as $bad) {
                [$id, $send] = resurrection_player_id($cookie, $bad);
                self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $id);
                self::assertTrue($send);
            }
        }
        self::assertNotSame(resurrection_player_id(null, null)[0], resurrection_player_id(null, null)[0]);
    }
}
