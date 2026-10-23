<?php
declare(strict_types=1);
namespace Resurrection\Tests;

/**
 * Finds SQL text built by interpolation or concatenation in PHP source, and the values put into it.
 *
 * A string piece is SQL when it starts with a statement keyword (any case), contains an upper-case clause keyword,
 * or ends in a column comparison (`acctid=` or `name='`) right before a value. For each such piece the scanner takes
 * the whole expression around it (the concatenation, or the double-quoted string with its interpolations) and reports
 * every part that is not a literal. Bound parameters never appear, because they are not part of the SQL text.
 */
final class SqlInterpolationScanner
{
    private const STATEMENT = '/^\s*\(?\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|OPTIMIZE|SHOW|DESCRIBE|LOCK\s+TABLES|UNLOCK|RENAME)\s/i';
    private const CLAUSE = '/\b(WHERE|FROM|ORDER BY|GROUP BY|LIMIT|VALUES|SET|AND|OR|IN|JOIN|LIKE|HAVING|BETWEEN|ON DUPLICATE KEY)\b/';
    private const COMPARISON = '/(^|[\s(,])[a-z_][a-z0-9_.`]*`?\s*(=|<>|!=|<=|>=|<|>)\s*[\'"]?$/i';
    private const STOP_BOTH = [';', ',', '?', ':', '=', '.='];
    /** Values every site may use without an entry: a literal table name, a named constant, a number. */
    public const BY_RULE = '/\A(db_prefix\(\s*(\'[a-z0-9_]+\'|"[a-z0-9_]+")\s*\)|[A-Z][A-Z0-9_]*|\d+)\z/';

    /**
     * @return list<array{file: string, line: int, value: string}>
     */
    public static function scanFile(string $root, string $file): array
    {
        $tokens = token_get_all((string) file_get_contents($root . '/' . $file));
        $pairs = self::stringPairs($tokens);
        $sites = [];
        $done = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            // A query argument concatenated from values alone (db_query($sql . $extra)) has no SQL piece to find.
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && in_array(strtolower($tokens[$i][1]), ['db_query', 'db_query_cached'], true)) {
                $open = self::significant($tokens, $i, 1);
                $first = $open !== null && $tokens[$open] === '(' ? self::significant($tokens, $open, 1) : null;
                if ($first !== null && !in_array($tokens[$first], [')', '"'], true) && self::pieceText($tokens[$first]) === null) {
                    [$left, $right] = self::bounds($tokens, $pairs, $first);
                    $values = self::operands($tokens, $pairs, $left, $right);
                    if (count($values) > 1 && !isset($done[$left . ':' . $right])) {
                        $done[$left . ':' . $right] = true;
                        foreach ($values as $value) { $sites[] = ['file' => $file, 'line' => $tokens[$i][2], 'value' => $value]; }
                    }
                }
                continue;
            }
            $text = self::pieceText($tokens[$i]);
            if ($text === null) { continue; }
            $keyword = preg_match(self::STATEMENT, $text) || preg_match(self::CLAUSE, $text);
            if (!$keyword && !(self::nextIsValue($tokens, $i) && preg_match(self::COMPARISON, $text))) { continue; }
            [$left, $right] = self::bounds($tokens, $pairs, $i);
            // A comparison alone in markup (value='...'), a link (?op=) or script (window.location=) is not SQL.
            // A bare `key=` (a link parameter) counts only when assigned to a variable named for SQL.
            $literal = self::literalText($tokens, $left, $right);
            // Markup (an HTML tag, or an attribute written `name="`) is output, whatever words it holds, unless the
            // expression starts as a statement.
            if (!preg_match(self::STATEMENT, $literal) && (preg_match('#<(/?[a-z]+[\s>/]|/?[a-z]+\z)#i', $literal)
                || (!$keyword && $tokens[$i][1][0] === "'" && str_ends_with($text, '="')))) { continue; }
            if (!$keyword && (preg_match('/[<>?&]|\bhref\s*=|\.php|window\./i', $literal)
                || (!preg_match('/[\s\'"]/', $literal) && !self::assignedToSqlName($tokens, $left)))) { continue; }
            if (isset($done[$left . ':' . $right])) { continue; }
            $done[$left . ':' . $right] = true;
            $line = is_array($tokens[$i]) ? $tokens[$i][2] : 0;
            foreach (self::operands($tokens, $pairs, $left, $right) as $value) {
                $sites[] = ['file' => $file, 'line' => $line, 'value' => $value];
            }
        }
        return $sites;
    }

    /** @param list<mixed> $tokens */
    private static function literalText(array $tokens, int $left, int $right): string
    {
        $text = '';
        for ($j = $left; $j <= $right; $j++) { $text .= self::pieceText($tokens[$j]) ?? ''; }
        return $text;
    }

    private static function pieceText(mixed $token): ?string
    {
        if (!is_array($token)) { return null; }
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) { return substr($token[1], 1, -1); }
        if ($token[0] === T_ENCAPSED_AND_WHITESPACE) { return $token[1]; }
        return null;
    }

    /** @param list<mixed> $tokens */
    private static function nextIsValue(array $tokens, int $i): bool
    {
        $next = $tokens[$i + 1] ?? null;
        if (is_array($next) && in_array($next[0], [T_VARIABLE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) { return true; }
        if ($next === '"' || (is_array($next) && $next[0] === T_END_HEREDOC)) { return false; }
        // A quoted piece followed by a concatenated value.
        $j = self::significant($tokens, $i, 1);
        return $j !== null && $tokens[$j] === '.';
    }

    /** @param list<mixed> $tokens */
    private static function significant(array $tokens, int $i, int $step): ?int
    {
        for ($i += $step; isset($tokens[$i]); $i += $step) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            return $i;
        }
        return null;
    }

    /**
     * Matching open and close index of every double-quoted string and heredoc.
     *
     * @param list<mixed> $tokens
     * @return array<int, int>
     */
    private static function stringPairs(array $tokens): array
    {
        $pairs = [];
        $open = null;
        foreach ($tokens as $i => $token) {
            $isOpen = $token === '"' || (is_array($token) && $token[0] === T_START_HEREDOC);
            $isClose = $token === '"' || (is_array($token) && $token[0] === T_END_HEREDOC);
            if ($open === null && $isOpen) { $open = $i; continue; }
            if ($open !== null && $isClose) { $pairs[$open] = $i; $pairs[$i] = $open; $open = null; }
        }
        return $pairs;
    }

    /**
     * @param list<mixed> $tokens
     * @param array<int, int> $pairs
     * @return array{0: int, 1: int}
     */
    private static function bounds(array $tokens, array $pairs, int $i): array
    {
        // A piece inside a double-quoted string starts from the whole string.
        $left = $i;
        $right = $i;
        foreach ($pairs as $open => $close) {
            if ($open < $i && $close > $i && $open < $close) { $left = $open; $right = $close; break; }
        }
        $depth = 0;
        for ($j = $left - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if (isset($pairs[$j]) && $pairs[$j] < $j) { $j = $pairs[$j]; $left = $j; continue; }
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            if (in_array($text, [')', ']', '}'], true)) { $depth++; $left = $j; continue; }
            if (in_array($text, ['(', '['], true) || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES || $text === '{') {
                if ($depth === 0) { break; }
                $depth--; $left = $j; continue;
            }
            if ($depth === 0 && (in_array($text, self::STOP_BOTH, true) || self::isStopToken($id))) { break; }
            $left = $j;
        }
        $depth = 0;
        $count = count($tokens);
        for ($j = $right + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (isset($pairs[$j]) && $pairs[$j] > $j) { $j = $pairs[$j]; $right = $j; continue; }
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            if (in_array($text, ['(', '[', '{'], true) || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) { $depth++; $right = $j; continue; }
            if (in_array($text, [')', ']', '}'], true)) {
                if ($depth === 0) { break; }
                $depth--; $right = $j; continue;
            }
            if ($depth === 0 && (in_array($text, self::STOP_BOTH, true) || self::isStopToken($id))) { break; }
            $right = $j;
        }
        return [$left, $right];
    }

    /** @param list<mixed> $tokens */
    private static function assignedToSqlName(array $tokens, int $left): bool
    {
        $operator = self::significant($tokens, $left, -1);
        if ($operator === null || !($tokens[$operator] === '=' || (is_array($tokens[$operator]) && $tokens[$operator][0] === T_CONCAT_EQUAL))) { return false; }
        $target = self::significant($tokens, $operator, -1);
        return $target !== null && is_array($tokens[$target]) && $tokens[$target][0] === T_VARIABLE
            && preg_match('/sql|where|cond|query|filter|clause|order|limit/i', $tokens[$target][1]) === 1;
    }

    private static function isStopToken(?int $id): bool
    {
        return $id !== null && in_array($id, [
            T_RETURN, T_ECHO, T_PRINT, T_CONCAT_EQUAL, T_PLUS_EQUAL, T_COALESCE_EQUAL, T_DOUBLE_ARROW, T_OPEN_TAG, T_CLOSE_TAG,
            T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL,
            T_IS_NOT_IDENTICAL, T_CASE, T_ELSE, T_INLINE_HTML,
        ], true);
    }

    /**
     * @param list<mixed> $tokens
     * @param array<int, int> $pairs
     * @return list<string>
     */
    private static function operands(array $tokens, array $pairs, int $left, int $right): array
    {
        $values = [];
        $current = [];
        $depth = 0;
        for ($j = $left; $j <= $right; $j++) {
            $token = $tokens[$j];
            if (isset($pairs[$j]) && $pairs[$j] > $j && $depth === 0) {
                // A double-quoted string or heredoc: each interpolation is a value.
                foreach (self::interpolations($tokens, $j + 1, $pairs[$j] - 1) as $value) { $values[] = $value; }
                $j = $pairs[$j];
                continue;
            }
            $text = is_array($token) ? $token[1] : $token;
            if (in_array($text, ['(', '['], true)) { $depth++; }
            if (in_array($text, [')', ']'], true)) { $depth--; }
            if ($text === '.' && $depth === 0) {
                if (!self::isLiteral($current)) { $values[] = self::normalize($current); }
                $current = [];
                continue;
            }
            $current[] = $token;
        }
        if (!self::isLiteral($current)) { $values[] = self::normalize($current); }
        return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
    }

    /** A part made only of one plain string or number literal (whitespace aside) adds nothing to the SQL. */
    private static function isLiteral(array $tokens): bool
    {
        $parts = array_values(array_filter($tokens, static fn (mixed $token): bool =>
            !(is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))));
        if ($parts === []) { return true; }
        return count($parts) === 1 && is_array($parts[0]) && in_array($parts[0][0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true);
    }

    /**
     * @param list<mixed> $tokens
     * @return list<string>
     */
    private static function interpolations(array $tokens, int $from, int $to): array
    {
        $values = [];
        $current = [];
        $depth = 0;
        for ($j = $from; $j <= $to; $j++) {
            $token = $tokens[$j];
            $id = is_array($token) ? $token[0] : null;
            if ($depth === 0 && ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES)) {
                if ($current !== []) { $values[] = self::normalize($current); $current = []; }
                $depth = 1;
                if ($id === T_DOLLAR_OPEN_CURLY_BRACES) { $current[] = '$'; }
                continue;
            }
            if ($depth > 0) {
                if ($token === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) { $depth++; }
                if ($token === '}') {
                    $depth--;
                    if ($depth === 0) { $values[] = self::normalize($current); $current = []; continue; }
                }
                $current[] = $token;
                continue;
            }
            if ($id === T_ENCAPSED_AND_WHITESPACE) {
                if ($current !== []) { $values[] = self::normalize($current); $current = []; }
                continue;
            }
            // Simple interpolation: $var, $var[key], $var->prop.
            if ($id === T_VARIABLE && $current !== []) { $values[] = self::normalize($current); $current = []; }
            $current[] = $token;
        }
        if ($current !== []) { $values[] = self::normalize($current); }
        return $values;
    }

    /** @param list<mixed> $tokens */
    private static function normalize(array $tokens): string
    {
        $text = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            $text .= is_array($token) ? $token[1] : $token;
        }
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        // Outer parentheses around a whole value do not change it.
        while (str_starts_with($text, '(') && str_ends_with($text, ')') && self::wrapped($text)) {
            $text = trim(substr($text, 1, -1));
        }
        return $text;
    }

    private static function wrapped(string $text): bool
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] === '(') { $depth++; }
            if ($text[$i] === ')') { $depth--; if ($depth === 0 && $i < $length - 1) { return false; } }
        }
        return true;
    }

    /**
     * Shipped PHP: every tracked PHP file except tests, tooling and the lab.
     *
     * @return list<string>
     */
    public static function shippedFiles(string $root): array
    {
        $output = [];
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z -- "*.php"', $output, $status);
        if ($status !== 0) { throw new \RuntimeException('git ls-files failed'); }
        $files = array_filter(explode("\0", implode("\n", $output)), static fn (string $file): bool =>
            $file !== '' && !preg_match('#\A(tests|scripts|lab)/#', $file));
        sort($files, SORT_STRING);
        return array_values($files);
    }
}
