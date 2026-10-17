<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testTranslatorButtonsTolerateAnEmptiedSession(): void
    {
        require_once 'lib/constants.php';
        require_once 'lib/translator.php';
        $saved = [$GLOBALS['session'] ?? null, $GLOBALS['translation_is_enabled'] ?? null, $GLOBALS['seentlbuttons'] ?? null, $GLOBALS['translatorbuttons'] ?? null];
        try {
            $GLOBALS['translation_is_enabled'] = true;
            $GLOBALS['translatorbuttons'] = [];
            $text = '`n`4You fall under a ban currently in place on this website:`n';
            // checkban empties the session before it translates the ban message.
            foreach ([[], ['user' => []], ['user' => ['superuser' => 0]]] as $session) {
                $GLOBALS['session'] = $session;
                self::assertFalse(tlbutton_push($text, false, 'ban'));
                self::assertSame('', tlbutton_pop());
                self::assertSame('', tlbutton_clear());
            }
            // A translator still gets the button.
            $GLOBALS['session'] = ['user' => ['superuser' => SU_IS_TRANSLATOR]];
            $GLOBALS['seentlbuttons'] = ['ban' => [$text => true]];
            self::assertTrue(tlbutton_push($text, false, 'ban'));
            self::assertSame('', tlbutton_clear());
            self::assertSame([], $GLOBALS['translatorbuttons']);
        } finally {
            [$GLOBALS['session'], $GLOBALS['translation_is_enabled'], $GLOBALS['seentlbuttons'], $GLOBALS['translatorbuttons']] = $saved;
        }
    }
}
