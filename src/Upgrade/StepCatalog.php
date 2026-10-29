<?php
declare(strict_types=1);
namespace Resurrection\Upgrade;

/**
 * The numbered upgrade steps in one directory (upgrades/ for the game; tests pass their own). A step is a file named
 * NNNN-name.php that only returns its definition, so including it, or requesting it over HTTP, changes nothing:
 *
 *     return ['description' => 'What it changes', 'apply' => static function (): void { ... }];
 *
 * Its ID is the file name without .php. Steps are applied in number order, and each is applied once per database.
 */
final class StepCatalog
{
    public const string FILE = '/\A([0-9]{4})-([a-z0-9]+(?:-[a-z0-9]+)*)\.php\z/';

    /** Files in a step directory that are not steps. */
    private const array OTHER = ['.', '..', 'README.md'];

    /**
     * The step IDs, in the order they apply. Reads names only, so common.php can call it on every request.
     * @return list<string>
     */
    public static function ids(string $directory): array
    {
        $names = is_dir($directory) ? scandir($directory) : false;
        if ($names === false) { throw new \UnexpectedValueException('The upgrade step directory cannot be read.'); }
        $ids = [];
        $numbers = [];
        foreach ($names as $name) {
            if (in_array($name, self::OTHER, true)) { continue; }
            if (!preg_match(self::FILE, $name, $parts) || strlen($name) - 4 > 64) {
                throw new \UnexpectedValueException('Not an upgrade step file name: ' . $name);
            }
            if (isset($numbers[$parts[1]])) { throw new \UnexpectedValueException('Two upgrade steps share the number ' . $parts[1] . '.'); }
            $numbers[$parts[1]] = true;
            $ids[] = substr($name, 0, -4);
        }
        sort($ids, SORT_STRING);
        return $ids;
    }

    /**
     * One step's definition, checked.
     * @return array{description: string, apply: \Closure(): void}
     */
    public static function load(string $directory, string $id): array
    {
        if (!preg_match(self::FILE, $id . '.php')) { throw new \UnexpectedValueException('Not an upgrade step ID.'); }
        $definition = (static fn (string $file): mixed => require $file)($directory . '/' . $id . '.php');
        if (!is_array($definition) || array_keys($definition) !== ['description', 'apply']
            || !is_string($definition['description']) || trim($definition['description']) === ''
            || !$definition['apply'] instanceof \Closure) {
            throw new \UnexpectedValueException('Upgrade step ' . $id . ' must return only a description and an apply function.');
        }
        /** @var array{description: string, apply: \Closure(): void} $definition */
        return $definition;
    }
}
