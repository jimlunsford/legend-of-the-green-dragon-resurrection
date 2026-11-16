<?php
declare(strict_types=1);
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/*
 * Phase 6 task 20 (R7): the game's one class loader. The game ships without vendor/, so before this nothing loaded a
 * class under src/ but a hand-written require, and one missing require was a fatal error on a live page (R6: leaving
 * the Dark Horse Tavern after a game, Class "Resurrection\Game\StonesState" not found). Every entry point requires this
 * file as its second statement, right after it defines RESURRECTION_ENTRY (tests/unit/AutoloadTest.php).
 *
 * PSR-4: Resurrection\Name\Space\Class is src/Name/Space/Class.php. Only that namespace, only names whose every part
 * is a capitalised PHP identifier (so never this file or src/Compatibility/array_cursor.php), and only files that
 * exist; anything else is left to the next loader (Composer's, in the tests).
 * The files it loads keep their own RESURRECTION_ENTRY check, so a direct request for one is still an empty 404.
 * The hand-written requires stay: each still loads what its file needs, and this loader is the net under them.
 */

// Declared once even if the file is required again with a plain require.
if (!\function_exists('resurrection_autoload')) {
    /** The file a Resurrection\ class lives in, or null when the name is not one of the game's. */
    function resurrection_autoload_file(string $class): ?string
    {
        if (!str_starts_with($class, 'Resurrection\\')) return null;
        $relative = substr($class, strlen('Resurrection\\'));
        if (!preg_match('/\A[A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)*\z/', $relative)) return null;
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        return is_file($file) ? $file : null;
    }

    function resurrection_autoload(string $class): void
    {
        $file = resurrection_autoload_file($class);
        if ($file !== null) require_once $file;
    }
}

if (!in_array('resurrection_autoload', spl_autoload_functions(), true)) spl_autoload_register('resurrection_autoload');
