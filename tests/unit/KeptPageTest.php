<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\ActionToken;
use Resurrection\Security\Csrf;
use Resurrection\Security\KeptPage;

require_once __DIR__ . '/../../src/Security/KeptPage.php';

/** The kept copy of a page keeps no form token, and a replay fills them from the session that shows it (Phase 6 task 9). */
final class KeptPageTest extends TestCase
{
    /** @param array<string, mixed> $state */
    private static function page(array &$state): string
    {
        return '<form action="newday.php?continue=1" method="POST"><input type="hidden" name="csrf_token" value="' . Csrf::token($state) .
            '"><input type="hidden" name="action_token" value="' . ActionToken::issue($state, 'race-onboarding', "ctx 'one' \"<&>\"") .
            '"></form><form><input type="hidden" name="csrf_token" value="' . Csrf::token($state) .
            '"><input type="hidden" name="action_token" value="' . ActionToken::issue($state, 'forest-fight', str_repeat('a', 64)) . '"></form>';
    }

    public function testTheCopyHoldsMarkersAndNoToken(): void
    {
        $state = [];
        $page = self::page($state);
        $kept = KeptPage::keep($page, $state);
        self::assertStringNotContainsString($state['csrf'], $kept);
        foreach ($state['actions'] as $entry) self::assertStringNotContainsString($entry['token'], $kept);
        self::assertSame(2, substr_count($kept, 'value="' . KeptPage::CSRF_MARKER . '"'));
        self::assertSame(2, substr_count($kept, KeptPage::ACTION_MARKER));
        self::assertDoesNotMatchRegularExpression('/value="[a-f0-9]{64}"/', $kept);
        // A page with no form, or a session with no token, is kept as it is.
        self::assertSame('<p>Village</p>', KeptPage::keep('<p>Village</p>', $state));
        self::assertSame($page, KeptPage::keep($page, []));
    }

    public function testTheSameSessionGetsThePageBackUnchanged(): void
    {
        $state = [];
        $page = self::page($state);
        $kept = KeptPage::keep($page, $state);
        $before = $state;
        self::assertSame($page, KeptPage::replay($kept, $state, false));
        self::assertSame($before, $state);
        // A used token is never issued again in the same session: its field is left empty, and refused.
        ActionToken::consume($state, 'race-onboarding', "ctx 'one' \"<&>\"", $before['actions']['race-onboarding']['token']);
        $replayed = KeptPage::replay($kept, $state, false);
        self::assertStringContainsString('name="action_token" value=""', $replayed);
        self::assertArrayNotHasKey('race-onboarding', $state['actions']);
        self::assertStringContainsString($before['actions']['forest-fight']['token'], $replayed);
    }

    public function testANewSignInGetsFreshTokensAndTheOldOnesStayRefused(): void
    {
        $old = [];
        $page = self::page($old);
        $kept = KeptPage::keep($page, $old);
        $new = [];
        $replayed = KeptPage::replay($kept, $new, true);
        self::assertNotSame($old['csrf'], $new['csrf']);
        self::assertStringContainsString('value="' . $new['csrf'] . '"', $replayed);
        foreach (['race-onboarding' => "ctx 'one' \"<&>\"", 'forest-fight' => str_repeat('a', 64)] as $scope => $context) {
            self::assertSame($context, $new['actions'][$scope]['context']);
            self::assertNotSame($old['actions'][$scope]['token'], $new['actions'][$scope]['token']);
            self::assertStringContainsString($new['actions'][$scope]['token'], $replayed);
            self::assertStringNotContainsString($old['actions'][$scope]['token'], $replayed);
            self::assertFalse(Csrf::valid($new, $old['csrf']));
            try {
                $copy = $new;
                ActionToken::consume($copy, $scope, $context, $old['actions'][$scope]['token']);
                self::fail('An earlier session\'s action token was accepted.');
            } catch (\DomainException $error) {
                self::assertSame('Expired or repeated action.', $error->getMessage());
            }
            ActionToken::consume($new, $scope, $context, $new['actions'][$scope]['token']);
        }
        // Without the sign-in's one reissue, a new session gets no action at all.
        $other = [];
        self::assertSame(2, substr_count(KeptPage::replay($kept, $other, false), 'name="action_token" value=""'));
        self::assertArrayNotHasKey('actions', $other);
    }

    public function testDamagedMarkersAndOldCopies(): void
    {
        $state = [];
        $marker = KeptPage::ACTION_MARKER;
        foreach ([$marker . 'zz', $marker . 'abc', $marker . bin2hex('"x"'), $marker . bin2hex('[1,2]'), $marker . bin2hex('["a","b","c"]')] as $damaged) {
            $replayed = KeptPage::replay('value="' . $damaged . '"', $state, true);
            self::assertDoesNotMatchRegularExpression('/value="[a-f0-9]{64}"/', $replayed, $damaged);
        }
        self::assertArrayNotHasKey('actions', $state);
        // A copy kept before this rule has its tokens in it and is shown as it was.
        $legacy = '<input type="hidden" name="csrf_token" value="' . str_repeat('b', 64) . '">';
        self::assertSame($legacy, KeptPage::replay($legacy, $state, true));
    }

    public function testTheUserEditorViewShowsNoToken(): void
    {
        $state = [];
        $kept = KeptPage::keep(self::page($state), $state);
        $view = KeptPage::view($kept);
        self::assertStringNotContainsString('rz-kept', $view);
        self::assertSame(4, substr_count($view, 'value=""'));
    }
}
