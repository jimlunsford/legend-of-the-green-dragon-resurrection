<?php
declare(strict_types=1);
namespace Resurrection\Tests;

/**
 * The game clock the page-rendering tests pin while they run (Phase 5 task 11, docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md).
 *
 * A game day rolls over every 24 / daysperday real hours; with the installer's midnight epoch and 4 days a day, at 00:00,
 * 06:00, 12:00 and 18:00 UTC. A test that signs a player in with a last hit a minute ahead and then renders a page sees
 * a new day (and a redirect to newday.php) if a boundary falls in between. The web shards pin the clock the same way
 * (tests/tooling/test_web_application.py, _game_clock): the epoch moves to a minute before now, minus 30 days, so the
 * current game day began a minute ago and a whole game day of headroom is left.
 */
final class GameClock
{
    /** @return array<string, string> the settings to store while a test runs */
    public static function settings(?int $now = null): array
    {
        $now ??= time();
        return ['game_epoch' => gmdate('Y-m-d H:i:s O', $now - 60 - 30 * 86400), 'gameoffsetseconds' => '0', 'daysperday' => '4'];
    }
}
