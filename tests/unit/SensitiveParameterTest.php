<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The web maintenance secret never shows in a stack trace (Phase 6 task 5a): every function that takes it, or a request
 * array that carries it, marks the parameter #[\SensitiveParameter], so PHP replaces the value in a trace with a
 * SensitiveParameterValue. The install service's password and values and the database credentials are marked too.
 */
final class SensitiveParameterTest extends TestCase
{
    /** @var array<string, list<string>> function => its parameters that carry a secret */
    private const array SENSITIVE = [
        'resurrection_webcron_presented' => ['server', 'post', 'get'],
        'resurrection_webcron_hash' => ['secret'],
        'resurrection_webcron_matches' => ['presented'],
        'resurrection_webcron_gate' => ['presented'],
        'resurrection_webcron_render' => ['secret'],
        'resurrection_install_run' => ['password', 'values'],
        'resurrection_database_open' => ['database'],
        // Phase 6 task 5b: the setup page's token, the administrator's password, and a page that shows a secret.
        'resurrection_webcron_once_html' => ['address', 'secret'],
        'resurrection_secret_output' => ['html', 'secrets'],
        'resurrection_secret_register' => ['add'],
        'resurrection_secret_kept_copy' => ['page'],
        'resurrection_setup_base32' => ['bytes'],
        'resurrection_setup_token_well_formed' => ['value'],
        'resurrection_setup_token_hash' => ['token'],
        'resurrection_setup_token_matches' => ['fileToken', 'typed'],
        'resurrection_setup_grant' => ['token'],
        'resurrection_setup_grant_new' => ['token'],
        'resurrection_setup_install' => ['token'],
        'resurrection_setup_upgrade' => ['token'],
        'resurrection_setup_administrator' => ['password'],
        'resurrection_setup_page' => ['secrets'],
        'resurrection_setup_screen_form' => ['typed'],
        'resurrection_setup_screen_maintenance' => ['secret', 'address'],
    ];

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/install_run', 'webcron',
                'pageparts', 'upgrade', 'setup/run'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
    }

    /** @return list<string> */
    private static function marked(string $function): array
    {
        $marked = [];
        foreach ((new \ReflectionFunction($function))->getParameters() as $parameter) {
            if ($parameter->getAttributes(\SensitiveParameter::class)) { $marked[] = $parameter->getName(); }
        }
        return $marked;
    }

    public function testEveryParameterThatCarriesTheSecretIsMarked(): void
    {
        foreach (self::SENSITIVE as $function => $parameters) {
            self::assertSame($parameters, self::marked($function), $function);
        }
    }

    public function testNoWebMaintenanceFunctionTakesTheSecretUnmarked(): void
    {
        // A guard for later additions: in lib/webcron.php, a parameter named for the secret is always marked.
        $source = (string)file_get_contents('lib/webcron.php');
        preg_match_all('/^function (resurrection_webcron_\w+)\(/m', $source, $functions);
        self::assertGreaterThanOrEqual(10, count($functions[1]));
        foreach ($functions[1] as $function) {
            foreach ((new \ReflectionFunction($function))->getParameters() as $parameter) {
                if (preg_match('/secret|presented|key|server|post|get/i', $parameter->getName())) {
                    self::assertNotSame([], $parameter->getAttributes(\SensitiveParameter::class), $function . '($' . $parameter->getName() . ')');
                }
            }
        }
    }

    public function testNoSetupFunctionTakesASecretUnmarked(): void
    {
        // Phase 6 task 5b, the same guard for the setup page: in lib/setup/, a parameter named for the token, a
        // password, a secret or the address that carries it is always marked.
        $count = 0;
        foreach (glob('lib/setup/*.php') ?: [] as $file) {
            preg_match_all('/^function (resurrection_setup_\w+)\(/m', (string)file_get_contents($file), $functions);
            foreach ($functions[1] as $function) {
                $count++;
                foreach ((new \ReflectionFunction($function))->getParameters() as $parameter) {
                    if (preg_match('/\A(token|fileToken|typed|password|secret|secrets|address|bytes)\z/', $parameter->getName())) {
                        self::assertNotSame([], $parameter->getAttributes(\SensitiveParameter::class), $function . '($' . $parameter->getName() . ')');
                    }
                }
            }
        }
        self::assertGreaterThan(40, $count);
    }

    public function testTheSecretIsReplacedInATrace(): void
    {
        // An invalid table prefix makes the gate throw before any query; the trace keeps arguments for this check.
        $secret = 'Synthetic-webcron-secret-must-not-show-in-a-trace';
        $ignore = ini_get('zend.exception_ignore_args');
        $prefix = $GLOBALS['DB_PREFIX'] ?? null;
        ini_set('zend.exception_ignore_args', '0');
        $GLOBALS['DB_PREFIX'] = 'not a prefix ';
        try {
            resurrection_webcron_gate($secret, 1);
            self::fail('The gate did not throw.');
        } catch (\InvalidArgumentException $error) {
            $frames = array_values(array_filter($error->getTrace(), static fn (array $frame): bool => ($frame['function'] ?? '') === 'resurrection_webcron_gate'));
            self::assertCount(1, $frames);
            self::assertInstanceOf(\SensitiveParameterValue::class, $frames[0]['args'][0]);
            self::assertSame(1, $frames[0]['args'][1]);
            self::assertStringNotContainsString($secret, $error->getTraceAsString());
            self::assertStringNotContainsString($secret, print_r($error->getTrace(), true));
        } finally {
            ini_set('zend.exception_ignore_args', (string)$ignore);
            $GLOBALS['DB_PREFIX'] = $prefix;
        }
    }
}
