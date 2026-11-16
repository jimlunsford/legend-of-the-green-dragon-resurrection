<?php
declare(strict_types=1);
namespace Resurrection\Http;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * Phase 6 task 20: one log line for a failure the game catches and answers with a 5xx. An uncaught error already gets
 * PHP's own line (its message, file and line, where log_errors sends it); a caught one, turned into "Nothing was
 * changed" for the player, left nothing at all, so a host saw a 500 and no reason. The line names the page, the
 * throwable's class, and the file (relative to the game's folder) and line where it was thrown: never its message,
 * arguments or trace, which can carry SQL, submitted values or a token (pvp.php's line, since Phase 3, is the model).
 */
final class FailureLog
{
    public static function line(\Throwable $error): string
    {
        $root = \dirname(__DIR__, 2) . '/';
        $file = $error->getFile();
        if (str_starts_with($file, $root)) $file = substr($file, \strlen($root));
        $page = \basename((string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? 'cli')));
        $clean = static fn (string $text): string => (string)preg_replace('/[^A-Za-z0-9_.\/\\\\-]/', '?', $text);
        return 'Resurrection: ' . $clean($page) . ' failed: ' . $clean(\get_class($error)) . ' at ' . $clean($file) . ':' . $error->getLine();
    }

    public static function record(\Throwable $error): void
    {
        \error_log(self::line($error));
    }
}
