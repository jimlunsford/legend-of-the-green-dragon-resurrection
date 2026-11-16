<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 20 (R7): the game ships without vendor/, so src/autoload.php is its one class loader (PSR-4,
 * Resurrection\ to src/). Before it, every class under src/ needed a hand-written require, and the one that was missing
 * on the Dark Horse Tavern's exit path was a fatal error on Jim's rc.1 server (R6).
 *
 * Every entry point requires the loader as its second statement, right after it defines RESURRECTION_ENTRY; loading
 * just those two statements, with nothing else, registers it; and every Resurrection\ class, interface and enum the
 * shipped code names resolves through it to a file that declares it.
 */
final class AutoloadTest extends TestCase
{
    private const string ROOT_REQUIRE = "require_once __DIR__ . '/src/autoload.php';";
    private const string SCRIPTS_REQUIRE = "require_once __DIR__ . '/../src/autoload.php';";

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/src/autoload.php';
    }

    public function testEveryEntryPointRequiresTheLoaderRightAfterTheConstant(): void
    {
        $root = dirname(__DIR__, 2);
        $wrong = [];
        $entries = $this->entryPoints($root);
        foreach ($entries as $file) {
            $expected = str_contains($file, '/') ? self::SCRIPTS_REQUIRE : self::ROOT_REQUIRE;
            $statements = $this->firstStatements($root . '/' . $file, 2);
            if ($statements !== [EntryGuardTest::DEFINE, $expected]) $wrong[$file] = $statements;
        }
        self::assertSame([], $wrong, 'Each entry point: the constant, then the class loader');
        // The same 65 entry points EntryGuardTest counts, the commands among them.
        self::assertCount(65, $entries);
        foreach (['village.php', 'forest.php', 'runmodule.php', 'setup.php', 'installer.php', 'webcron.php', 'account.php', 'cron.php', 'scripts/install.php', 'scripts/upgrade.php'] as $entry) {
            self::assertContains($entry, $entries);
        }
        // PHPUnit's own entry point uses the game's loader first, Composer's (for the tests' classes) after it.
        self::assertSame([EntryGuardTest::DEFINE, "require __DIR__ . '/../src/autoload.php';"], $this->firstStatements($root . '/tests/bootstrap.php', 2));
    }

    public function testEachEntryPointsBootstrapAloneRegistersTheLoader(): void
    {
        $root = dirname(__DIR__, 2);
        $php = PHP_BINARY;
        $results = [];
        foreach ($this->entryPoints($root) as $file) {
            $path = $root . '/' . $file;
            // The entry point's own first lines, up to and including the loader's require, and nothing else: no
            // Composer, no common.php.
            $lines = explode("\n", (string)file_get_contents($path));
            $cut = array_search(str_contains($file, '/') ? self::SCRIPTS_REQUIRE : self::ROOT_REQUIRE, array_map('trim', $lines), true);
            self::assertIsInt($cut, $file);
            $head = str_replace('__DIR__', var_export(dirname($path), true), implode("\n", array_slice($lines, 0, $cut + 1)));
            $loader = dirname($path) . (str_contains($file, '/') ? '/../src/autoload.php' : '/src/autoload.php');
            $probe = $head . "\n" . 'require ' . var_export($loader, true) . ";\n"
                . 'echo json_encode([array_map(static fn ($f) => is_string($f) ? $f : "closure", spl_autoload_functions()), class_exists(\'Resurrection\Game\StonesState\'), class_exists(\'Resurrection\Game\DiceGame\'), class_exists(\'Resurrection\Game\FiveSixGame\'), class_exists(\'Resurrection\Nope\'), function_exists(\'db_query\')]);';
            $temp = tempnam(sys_get_temp_dir(), 'rz-autoload-');
            file_put_contents($temp, $probe);
            exec(escapeshellarg($php) . ' -n -d display_errors=stderr ' . escapeshellarg($temp) . ' 2>&1', $output, $status);
            unlink($temp);
            $results[$file] = [$status, implode("\n", $output)];
            $output = [];
        }
        // Registered exactly once (a second require of the file adds nothing), the Dark Horse wager classes load
        // through it, a name outside src/ is left alone, and nothing else came with it.
        $expected = [0, json_encode([['resurrection_autoload'], true, true, true, false, false])];
        self::assertSame(array_fill_keys(array_keys($results), $expected), $results);
    }

    public function testEveryNamedGameClassResolvesToTheFileThatDeclaresIt(): void
    {
        $root = dirname(__DIR__, 2);
        $declared = [];
        foreach ($this->shippedPhp($root) as $file) {
            foreach ($this->declarations($root . '/' . $file) as $name) $declared[$name] = $file;
        }
        $named = [];
        foreach ($this->shippedPhp($root) as $file) {
            foreach ($this->references($root . '/' . $file) as $name) $named[$name][] = $file;
        }
        $wrong = [];
        foreach ($named as $name => $files) {
            $resolved = \resurrection_autoload_file($name);
            $where = $resolved === null ? null : substr($resolved, strlen($root) + 1);
            if ($where === null || ($declared[$name] ?? null) !== $where) $wrong[$name] = [$where, $declared[$name] ?? null, array_values(array_unique($files))];
        }
        self::assertSame([], $wrong, 'Every named Resurrection\\ type: the loader\'s file, the file that declares it, where it is named');
        self::assertGreaterThan(40, count($named));
        self::assertArrayHasKey('Resurrection\\Game\\StonesState', $named);
        // And the other way round: every type src/ declares is where the loader looks for it.
        $misplaced = [];
        foreach ($declared as $name => $file) {
            if (!str_starts_with($file, 'src/')) continue;
            $resolved = \resurrection_autoload_file($name);
            if ($resolved === null || substr($resolved, strlen($root) + 1) !== $file) $misplaced[$name] = $file;
        }
        self::assertSame([], $misplaced);
        self::assertGreaterThan(45, count(array_filter($declared, static fn (string $file): bool => str_starts_with($file, 'src/'))));
    }

    public function testTheLoaderTakesOnlyTheGamesNamespaceAndRealFiles(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSame($root . '/src/Game/StonesState.php', \resurrection_autoload_file('Resurrection\\Game\\StonesState'));
        self::assertSame($root . '/src/Version.php', \resurrection_autoload_file('Resurrection\\Version'));
        foreach (['Resurrection\\Game\\NoSuchClass', 'Resurrection\\Tests\\AutoloadTest', 'Other\\Game\\StonesState', 'Resurrection\\..\\..\\common',
                  'Resurrection\\Game/StonesState', 'Resurrection\\', 'Resurrection\\Game\\', 'ResurrectionX\\Version', 'Resurrection\\autoload'] as $name) {
            self::assertNull(\resurrection_autoload_file($name), $name);
        }
        self::assertContains('resurrection_autoload', spl_autoload_functions());
        // The loader file keeps the S2 guard as its first statement, like every file under src/.
        self::assertSame(EntryGuardTest::GUARD, $this->firstStatements($root . '/src/autoload.php', 1)[0]);
    }

    /** @return list<string> The 65 entry points, as EntryGuardTest finds them. */
    private function entryPoints(string $root): array
    {
        return array_values(array_filter($this->shippedPhp($root), static fn (string $file): bool =>
            (preg_match('#\A[^/]+\.php\z#', $file) === 1 || preg_match('#\Ascripts/[^/]+\.php\z#', $file) === 1)
            && !in_array($file, ['common.php', 'battle.php', 'dbconnect.php', 'resurrection-setup.php', 'resurrection-setup-failures.php'], true)));
    }

    /** @return list<string> */
    private function shippedPhp(string $root): array
    {
        static $files = null;
        if ($files !== null) return $files;
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard -- "*.php"', $output, $status);
        self::assertSame(0, $status);
        return $files = array_values(array_filter(explode("\0", implode("\n", $output)), static fn (string $file): bool =>
            $file !== '' && !str_starts_with($file, 'tests/') && !str_starts_with($file, 'vendor/') && is_file($root . '/' . $file)));
    }

    /** @return list<string> The file's first statements after <?php, comments, declare(...) and namespace ...;. */
    private function firstStatements(string $path, int $count): array
    {
        $found = [];
        foreach (explode("\n", (string)file_get_contents($path)) as $n => $line) {
            $line = trim($line);
            if ($n === 0 && $line === '<?php') continue;
            if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, 'declare(strict_types=1);') || preg_match('/\Anamespace [A-Za-z\\\\]+;\z/', $line)) continue;
            $found[] = $line;
            if (count($found) === $count) break;
        }
        return $found;
    }

    /** @return list<string> The classes, interfaces, enums and traits the file declares, fully qualified. */
    private function declarations(string $path): array
    {
        $tokens = \PhpToken::tokenize((string)file_get_contents($path));
        $namespace = '';
        $names = [];
        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE)) $namespace = $this->nextName($tokens, $i) ?? '';
            if (!$token->is([T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT])) continue;
            $before = $this->previous($tokens, $i);
            if ($before !== null && $before->is([T_DOUBLE_COLON, T_NEW])) continue;   // Foo::class, new class
            $name = $this->nextName($tokens, $i);
            if ($name !== null) $names[] = ltrim($namespace . '\\' . $name, '\\');
        }
        return $names;
    }

    /**
     * @return list<string> Every Resurrection\ type the file names: fully qualified names, use imports, names that
     * resolve into the namespace from a namespaced file (new, ::, instanceof, extends, implements, catch, ::class), and
     * strings that are a class name. Names under Resurrection\Tests are the tests' own.
     */
    private function references(string $path): array
    {
        $tokens = \PhpToken::tokenize((string)file_get_contents($path));
        $namespace = '';
        $imports = [];
        $names = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_NAMESPACE)) { $namespace = $this->nextName($tokens, $i) ?? ''; $imports = []; $i = $this->indexAfterName($tokens, $i) ?? $i; continue; }
            if ($token->is(T_USE) && $this->braceDepth($tokens, $i) === 0) {
                $next = $this->nextSignificant($tokens, $i);
                if ($next !== null && $next->is([T_FUNCTION, T_CONST])) continue;
                $name = $this->nextName($tokens, $i);
                if ($name === null) continue;
                $alias = substr($name, (int)strrpos('\\' . $name, '\\'));
                $j = $this->indexAfterName($tokens, $i);
                if ($j !== null && isset($tokens[$j + 2]) && $tokens[$j + 1]->is(T_AS)) $alias = $tokens[$j + 2]->text;
                $imports[strtolower($alias)] = ltrim($name, '\\');
                $names[] = ltrim($name, '\\');
                $i = $j ?? $i;
                continue;
            }
            if ($token->is(T_NAME_FULLY_QUALIFIED)) { $names[] = ltrim($token->text, '\\'); continue; }
            if ($token->is(T_NAME_QUALIFIED)) {
                $first = strtolower(explode('\\', $token->text)[0]);
                if (isset($imports[$first])) $names[] = $imports[$first] . substr($token->text, strlen($first));
                elseif ($namespace === '') $names[] = $token->text;
                else $names[] = $namespace . '\\' . $token->text;
                continue;
            }
            if ($token->is(T_CONSTANT_ENCAPSED_STRING) && preg_match('/\A([\'"])\\\\{0,2}(Resurrection(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+)\1\z/', $token->text, $m)) {
                $names[] = str_replace('\\\\', '\\', $m[2]);
                continue;
            }
            if ($token->is(T_STRING) && $namespace !== '' && $this->namesAType($tokens, $i)) {
                $lower = strtolower($token->text);
                if (in_array($lower, ['self', 'static', 'parent'], true)) continue;
                $names[] = $imports[$lower] ?? $namespace . '\\' . $token->text;
            }
        }
        return array_values(array_filter(array_unique($names), static fn (string $name): bool =>
            str_starts_with($name, 'Resurrection\\') && !str_starts_with($name, 'Resurrection\\Tests\\')));
    }

    /** @param list<\PhpToken> $tokens */
    private function namesAType(array $tokens, int $i): bool
    {
        $before = $this->previous($tokens, $i);
        $after = $this->nextSignificant($tokens, $i);
        if ($after !== null && $after->is(T_DOUBLE_COLON)) return true;
        if ($before === null) return false;
        if ($before->is([T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS])) return true;
        if ($before->text === ',' || $before->text === '|') {
            // implements A, B / catch (A | B $e): walk back to what started the list.
            for ($k = $i - 1; $k >= 0; $k--) {
                $t = $tokens[$k];
                if ($t->is(T_IMPLEMENTS) || $t->is(T_CATCH)) return true;
                if ($t->text === '{' || $t->text === ';' || $t->text === ')' || $t->text === '=' || $t->is(T_FUNCTION)) return false;
            }
            return false;
        }
        if ($before->text === '(') {
            $catch = $this->previous($tokens, array_search($before, $tokens, true));
            return $catch !== null && $catch->is(T_CATCH);
        }
        return false;
    }

    /** @param list<\PhpToken> $tokens */
    private function previous(array $tokens, int $i): ?\PhpToken
    {
        for ($k = $i - 1; $k >= 0; $k--) if (!$tokens[$k]->isIgnorable()) return $tokens[$k];
        return null;
    }

    /** @param list<\PhpToken> $tokens */
    private function nextSignificant(array $tokens, int $i): ?\PhpToken
    {
        for ($k = $i + 1, $n = count($tokens); $k < $n; $k++) if (!$tokens[$k]->isIgnorable()) return $tokens[$k];
        return null;
    }

    /** @param list<\PhpToken> $tokens */
    private function nextName(array $tokens, int $i): ?string
    {
        $k = $this->indexAfterName($tokens, $i);
        return $k === null ? null : $tokens[$k]->text;
    }

    /** @param list<\PhpToken> $tokens The index of the name token after $i, or null. */
    private function indexAfterName(array $tokens, int $i): ?int
    {
        for ($k = $i + 1, $n = count($tokens); $k < $n; $k++) {
            if ($tokens[$k]->isIgnorable()) continue;
            return $tokens[$k]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) ? $k : null;
        }
        return null;
    }

    /** @param list<\PhpToken> $tokens */
    private function braceDepth(array $tokens, int $i): int
    {
        $depth = 0;
        for ($k = 0; $k < $i; $k++) {
            $text = $tokens[$k]->text;
            if ($text === '{' || $tokens[$k]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
            elseif ($text === '}') $depth--;
        }
        return $depth;
    }
}
