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
}
