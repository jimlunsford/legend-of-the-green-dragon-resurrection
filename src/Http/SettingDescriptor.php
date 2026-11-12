<?php
declare(strict_types=1);
namespace Resurrection\Http;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/** Small typed adapter for the shipped form declarations, not a second settings store. */
final class SettingDescriptor
{
    /** The exception code for a whole number outside the bounds its declaration names ("int,LOW,HIGH"). */
    public const OUT_OF_BOUNDS = 4001;

    /**
 * @param array<array-key,mixed> $layout
 * @return array<string,array<string,mixed>> */
    public static function declare(array $layout): array
    {
        $result = [];
        foreach ($layout as $key => $declaration) {
            if (!is_string($key)) continue;
            if (is_array($declaration)) $declaration = $declaration[0] ?? null;
            if (!is_string($declaration)) throw new \DomainException('Invalid declaration.');
            [$form, $default] = array_pad(explode('|', $declaration, 2), 2, null);
            $parts = array_map('trim', explode(',', $form));
            $type = $parts[1] ?? '';
            // A locked setting (Phase 6 task 7) is held at one value and never saved: undeclared here, so a form that
            // sends it is refused (patch()). The settings editor shows it locked.
            if (in_array($type, ['title','note','viewonly','invisible','hidden','bitfield','locked'], true)) continue;
            $descriptor = ['label'=>$parts[0], 'type'=>$type, 'default'=>$default, 'min'=>-2147483647, 'max'=>2147483647, 'length'=>255];
            if (in_array($type, ['', 'text','string','textarea'], true)) {
                $descriptor['type'] = 'string';
                $descriptor['length'] = $type === 'textarea' ? 4096 : ($type === 'string' ? (int)($parts[2] ?? 255) : 255);
                $descriptor['default'] ??= '';
            } elseif ($type === 'bool') {
                $descriptor['default'] ??= '0';
            } elseif ($type === 'int' || $type === 'range' || $type === 'floatrange' || $type === 'amount' ) {
                if ($type !== 'int' && $type !== 'amount') {
                    $descriptor['min'] = (float)($parts[2] ?? 0);
                    $descriptor['max'] = (float)($parts[3] ?? 0);
                } elseif ($type === 'int' && isset($parts[2], $parts[3])) {
                    // Phase 6 task 8: a bounded whole number, "int,LOW,HIGH" (LOGINTIMEOUT; lib/settings.php).
                    $low = filter_var($parts[2], FILTER_VALIDATE_INT);
                    $high = filter_var($parts[3], FILTER_VALIDATE_INT);
                    if ($low === false || $high === false || $low > $high) throw new \DomainException('Invalid bounds.');
                    $descriptor['min'] = $low;
                    $descriptor['max'] = $high;
                    $descriptor['bounded'] = true;
                }
                $descriptor['default'] ??= (string)max(0, $descriptor['min']);
            } elseif ($type === 'enum' || $type === 'enumpretrans') {
                // 1.1.2's theme type (a select of the skins in templates/) went with the skins (Phase 5 task 15).
                $descriptor['type'] = 'enum'; $options = [];
                for ($i=2; $i+1<count($parts); $i+=2) $options[$parts[$i]] = $parts[$i+1];
                $descriptor['options'] = $options;
                $descriptor['default'] ??= (string)array_key_first($options);
            } else {
                throw new \DomainException('Unsupported setting declaration.');
            }
            $result[$key] = $descriptor;
        }
        return $result;
    }

    /**
 * @param array<string,mixed> $descriptor */
    public static function value(array $descriptor, mixed $value): string
    {
        if (!is_string($value) || strlen($value)>4096 || preg_match('//u', $value)!==1 || str_contains($value, "\0")) throw new \InvalidArgumentException('Invalid setting value.');
        $type = $descriptor['type'];
        if ($type === 'string') {
            if (strlen($value)>$descriptor['length']) throw new \InvalidArgumentException('Text too long.');
        } elseif ($type === 'bool') {
            if (!in_array($value, ['0','1'], true)) throw new \InvalidArgumentException('Invalid boolean.');
        } elseif ($type === 'enum') {
            if (!array_key_exists($value, $descriptor['options'])) throw new \InvalidArgumentException('Invalid choice.');
        } else {
            $number=$type==='amount' ? rtrim($value,'%') : $value;
            if ($type==='amount' && !preg_match('/\A-?(?:0|[1-9][0-9]*)%?\z/',$value)) throw new \InvalidArgumentException('Invalid amount.');
            $pattern = $type === 'floatrange' ? '/\A-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)\z/' : '/\A-?(?:0|[1-9][0-9]*)\z/';
            if (!preg_match($pattern, $number) || !is_finite((float)$number)) throw new \InvalidArgumentException('Invalid number.');
            if ((float)$number<$descriptor['min'] || (float)$number>$descriptor['max']) {
                if (!empty($descriptor['bounded'])) throw new \InvalidArgumentException(self::boundsLine($descriptor), self::OUT_OF_BOUNDS);
                throw new \InvalidArgumentException('Invalid number.');
            }
        }
        return $value;
    }

    /**
     * The one line Game Settings shows for a bounded whole number: its range, and why a value outside it is refused.
     * @param array<string,mixed> $descriptor */
    public static function boundsLine(array $descriptor): string
    {
        return 'Not saved: "'.$descriptor['label'].'" must be a whole number from '.$descriptor['min'].' to '.$descriptor['max'].'.';
    }

    /**
 * @param array<string,array<string,mixed>> $schema
 * @param array<array-key,mixed> $input
 * @return array<string,string> */
    public static function patch(array $schema, array $input): array
    {
        unset($input['csrf_token'], $input['action_token']);
        $result=[];
        foreach ($input as $key=>$value) {
            if (!isset($schema[$key])) throw new \InvalidArgumentException('Undeclared setting.');
            $result[$key] = self::value($schema[$key], $value);
        }
        if ($result === []) throw new \InvalidArgumentException('Empty mutation.');
        return $result;
    }
}
