<?php

declare(strict_types=1);

namespace Resurrection\Configuration;

/** Transitional serialization of trusted local PHP configuration, not a secret store. */
final class LegacyConfig
{
    /** @param array<string, mixed> $values */
    public static function render(array $values): string
    {
        $names = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PREFIX',
            'DB_USEDATACACHE', 'DB_DATACACHEPATH'];
        $output = "<?php\n// Generated local configuration. Do not commit or expose this file.\n";
        foreach ($names as $name) {
            if (!array_key_exists($name, $values)) {
                throw new \InvalidArgumentException('Missing configuration field: ' . $name);
            }
            if ($name === 'DB_USEDATACACHE') {
                $value = (int) $values[$name];
            } else {
                if (!is_string($values[$name])) {
                    throw new \InvalidArgumentException('Configuration field must be a string: ' . $name);
                }
                $value = $values[$name];
            }
            $output .= '$' . $name . ' = ' . var_export($value, true) . ";\n";
        }
        return $output;
    }
}
