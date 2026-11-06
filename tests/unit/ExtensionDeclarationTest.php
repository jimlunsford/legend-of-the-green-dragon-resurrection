<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 3 (S6): every PHP extension the shipped code calls is declared in composer.json's require, so Composer
 * (and a host reading it) knows the game needs it. Before the task the game called ctype_* and filter_var() with
 * neither ext-ctype nor ext-filter declared; a PHP built without them failed at the first call.
 *
 * The scan reads every shipped PHP file (not vendor/, tests/ or lab/), finds each function call and each class it
 * names (new, ::, instanceof, catch, extends and implements), and asks PHP which extension provides it. A function
 * or class that is neither the tree's own nor known to this PHP fails too: its extension cannot be checked.
 */
final class ExtensionDeclarationTest extends TestCase
{
    /** The extensions every PHP 8.4 and 8.5 build has; they cannot be left out, so there is nothing to declare. */
    private const array ALWAYS_PRESENT = ['Core', 'date', 'hash', 'json', 'pcre', 'random', 'Reflection', 'SPL', 'standard'];

    public function testEveryExtensionTheGameCallsIsDeclared(): void
    {
        $root = dirname(__DIR__, 2);
        $declared = $this->declared($root);
        [$used, $unknown] = self::extensionsUsed($root, self::shippedFiles($root));
        self::assertSame([], $unknown, 'Neither defined in the tree nor known to this PHP: its extension cannot be checked');
        $missing = [];
        foreach ($used as $extension => $where) {
            if (in_array($extension, self::ALWAYS_PRESENT, true) || in_array(strtolower($extension), $declared, true)) continue;
            $missing[] = 'ext-' . strtolower($extension) . ' (' . implode(', ', array_slice($where, 0, 3)) . ')';
        }
        self::assertSame([], $missing, 'Add these to composer.json "require"');
        // The ones the task added, and the ones the game already declared.
        foreach (['ctype', 'filter', 'mbstring', 'pdo', 'pdo_mysql', 'session', 'json'] as $extension) {
            self::assertContains($extension, $declared);
        }
    }

    public function testTheScanFindsWhatItLooksFor(): void
    {
        $dir = sys_get_temp_dir() . '/resurrection-ext-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            file_put_contents("$dir/a.php", '<?php function own_helper() {} class own_class {} own_helper(); if (ctype_digit("1")) {} $x = filter_var("1", FILTER_VALIDATE_INT);'
                . ' $s->mb_strlen("x"); own_class::iconv(); $o = new \DateTimeImmutable(); try {} catch (\PDOException $e) {} \mb_strlen("x");'
                . ' // gzcompress("comment")' . "\n" . '$t = "imagecreate(1, 1)"; no_such_function_anywhere();');
            [$used, $unknown] = self::extensionsUsed($dir, ['a.php']);
            self::assertSame(['ctype', 'date', 'filter', 'mbstring', 'PDO'], array_keys($used));
            self::assertSame(['a.php: no_such_function_anywhere()'], $unknown);
        } finally {
            unlink("$dir/a.php");
            rmdir($dir);
        }
    }

    /** @return list<string> The ext-* names composer.json requires, lower case, without "ext-". */
    private function declared(string $root): array
    {
        $composer = json_decode((string)file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $names = [];
        foreach (array_keys($composer['require'] ?? []) as $package) {
            if (str_starts_with((string)$package, 'ext-')) $names[] = strtolower(substr((string)$package, 4));
        }
        return $names;
    }

    /** @return list<string> Tracked and untracked (not ignored) PHP files outside tests/, lab/ and vendor/. */
    private static function shippedFiles(string $root): array
    {
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard -- "*.php"', $output, $status);
        if ($status !== 0) throw new \RuntimeException('git ls-files failed');
        $files = array_filter(explode("\0", implode("\n", $output)), static fn (string $file): bool =>
            $file !== '' && !preg_match('#\A(tests|lab|vendor)/#', $file) && is_file($root . '/' . $file));
        sort($files, SORT_STRING);
        return array_values($files);
    }

    /**
     * @param list<string> $files
     * @return array{0: array<string, list<string>>, 1: list<string>} extension => where its names are used; unknown names
     */
    private static function extensionsUsed(string $root, array $files): array
    {
        $tokens = [];
        $own = [];
        foreach ($files as $file) {
            $tokens[$file] = \PhpToken::tokenize((string)file_get_contents($root . '/' . $file));
            foreach ($tokens[$file] as $n => $token) {
                if ($token->is([T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                    $next = self::next($tokens[$file], $n);
                    if ($next !== null && $next->is(T_STRING)) $own[strtolower($next->text)] = true;
                }
            }
        }
        $used = [];
        $unknown = [];
        $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
        foreach ($tokens as $file => $list) {
            foreach ($list as $n => $token) {
                if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED])) continue;
                $name = ltrim($token->text, '\\');
                $previous = self::previous($list, $n);
                $next = self::next($list, $n);
                $isClass = $previous !== null && $previous->is([T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS, T_CATCH])
                    || ($next !== null && $next->is(T_DOUBLE_COLON))
                    || ($previous !== null && ($previous->text === '(' || $previous->text === '|') && self::insideCatch($list, $n));
                $isCall = !$isClass && $next !== null && $next->text === '('
                    && !($previous !== null && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST]));
                if (!$isClass && !$isCall) continue;
                $short = strtolower(str_contains($name, '\\') ? substr($name, (int)strrpos($name, '\\') + 1) : $name);
                if (in_array($short, ['self', 'static', 'parent', 'list', 'array', 'isset', 'unset', 'empty', 'exit', 'die', 'eval', 'fn', 'function', 'match'], true)) continue;
                if ($isClass) {
                    if (isset($own[$short])) continue;
                    if (!class_exists($name) && !interface_exists($name) && !enum_exists($name)) {
                        // A class of the tree's own namespace that this scan did not see declared is the tree's.
                        if (str_starts_with($name, 'Resurrection\\') || !str_contains($name, '\\') && isset($own[$short])) continue;
                        $unknown[] = "$file: class $name";
                        continue;
                    }
                    $reflection = new \ReflectionClass($name);
                    if (!$reflection->isInternal()) continue;
                    $extension = (string)$reflection->getExtensionName();
                } else {
                    if (isset($own[$short])) continue;
                    if (!function_exists($short)) {
                        $unknown[] = "$file: $short()";
                        continue;
                    }
                    $reflection = new \ReflectionFunction($short);
                    if (!$reflection->isInternal()) continue;
                    $extension = (string)$reflection->getExtensionName();
                }
                $used[$extension][] = $file;
            }
        }
        ksort($used, SORT_STRING | SORT_FLAG_CASE);
        foreach ($used as $extension => $where) $used[$extension] = array_values(array_unique($where));
        return [$used, array_values(array_unique($unknown))];
    }

    /** @param list<\PhpToken> $list */
    private static function next(array $list, int $n): ?\PhpToken
    {
        for ($i = $n + 1; $i < count($list); $i++) if (!$list[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) return $list[$i];
        return null;
    }

    /** @param list<\PhpToken> $list */
    private static function previous(array $list, int $n): ?\PhpToken
    {
        for ($i = $n - 1; $i >= 0; $i--) if (!$list[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) return $list[$i];
        return null;
    }

    /** Whether the name at $n is a type in a catch (A | B $e). @param list<\PhpToken> $list */
    private static function insideCatch(array $list, int $n): bool
    {
        for ($i = $n - 1; $i >= 0; $i--) {
            $token = $list[$i];
            if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED]) || $token->text === '|') continue;
            if ($token->text === '(') {
                $before = self::previous($list, $i);
                return $before !== null && $before->is(T_CATCH);
            }
            return false;
        }
        return false;
    }
}
