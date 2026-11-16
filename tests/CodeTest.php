<?php

declare(strict_types=1);

namespace Dotclear\Tests\Plugin\TemplateHelper;

use ArrayObject;
use Closure;
use Dotclear\App;
use Dotclear\Core\Config as CoreConfig;
use Dotclear\Core\Core;
use Dotclear\Exception\TemplateException;
use Dotclear\Helper\Container\Container;
use Dotclear\Interface\Core\ConfigInterface;
use Dotclear\Plugin\TemplateHelper\Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

require_once dirname(__DIR__) . '/src/Code.php';

#[CoversClass(Code::class)]
class CodeTest extends TestCase
{
    public function testGetPHPCodeReplacesVariablesWithoutMatchingPrefixes(): void
    {
        $code = Code::getPHPCode(
            [self::class, 'templateCode'],
            ['first', 'second', '<strong>value</strong>'],
            false,
        );

        $this->assertSame(
            "echo 'first';\n        echo 'second';\n        echo <strong>value</strong>;",
            $code,
        );
    }

    public function testGetPHPCodeCanUseAFirstClassCallableAndPHPtags(): void
    {
        $code = Code::getPHPCode(
            self::templateCode(...),
            ['first', 'second', '<strong>value</strong>'],
        );

        $this->assertStringStartsWith('<?php ', $code);
        $this->assertStringEndsWith(' ?>', $code);
        $this->assertStringContainsString("echo 'first';", $code);
    }

    public function testGetPHPTemplateBlockCodeAddsContentFiltersAndCurrentTag(): void
    {
        App::task()->addContext('Frontend');

        $code = Code::getPHPTemplateBlockCode(
            [self::class, 'blockCode'],
            ['value'],
            'content',
            new ArrayObject(['flag' => 'yes']),
            false,
        );

        $this->assertStringContainsString("echo 'value';", $code);
        $this->assertStringContainsString("echo 'content';", $code);
        $this->assertStringContainsString("'flag' => 'yes'", $code);
    }

    public function testGetPHPTemplateValueCodeAddsFiltersAndCurrentTag(): void
    {
        App::task()->addContext('Frontend');

        $code = Code::getPHPTemplateValueCode(
            [self::class, 'valueCode'],
            ['value'],
            ['upper_case' => '1'],
            false,
        );

        $this->assertStringContainsString("echo 'value';", $code);
        $this->assertStringContainsString("'upper_case' => '1'", $code);
    }

    public function testGetPHPCodeReplacesArrayObjectAndArrayValues(): void
    {
        $objectCode = Code::getPHPCode(
            [self::class, 'arrayObjectCode'],
            [new ArrayObject(['name' => 'Ada'])],
            false,
        );
        $arrayCode = Code::getPHPCode(
            [self::class, 'arrayObjectCode'],
            [['name' => 'Ada']],
            false,
        );

        $this->assertStringContainsString("'name' => 'Ada'", $objectCode);
        $this->assertSame($objectCode, $arrayCode);
    }

    public function testGetPHPCodeRemovesMarkedLines(): void
    {
        $code = Code::getPHPCode(
            [self::class, 'codeWithMarkers'],
            ['kept'],
            false,
        );

        $this->assertSame("echo 'kept';", $code);
    }

    public function testGetPHPCodeReturnsEmptyCodeForMalformedMethodWhenNotDebugging(): void
    {
        if (App::config()->debugMode() || App::config()->devMode()) {
            $this->expectException(TemplateException::class);
            Code::getPHPCode('not-a-method');

            return;
        }

        $this->assertSame('', Code::getPHPCode('not-a-method', [], false));
        $this->assertSame('<?php  ?>', Code::getPHPCode('::method'));
        $this->assertSame('<?php  ?>', Code::getPHPCode([self::class, '']));
    }

    public function testGetPHPCodeHandlesInsufficientVariablesWhenNotDebugging(): void
    {
        if (App::config()->debugMode() || App::config()->devMode()) {
            $this->expectException(TemplateException::class);
            Code::getPHPCode([self::class, 'templateCode'], ['only-one']);

            return;
        }

        $this->assertSame(
            '<?php  ?>',
            Code::getPHPCode([self::class, 'templateCode'], ['only-one']),
        );
    }

    public function testGetPHPCodeHandlesInternalAndUnknownMethodsWhenNotDebugging(): void
    {
        if (App::config()->debugMode() || App::config()->devMode()) {
            $this->expectException(TemplateException::class);
            Code::getPHPCode([self::class, 'missingMethod']);

            return;
        }

        $this->assertSame('', Code::getPHPCode('DateTime::format', [], false));
        $this->assertStringContainsString(
            'CodeTest::missingMethod()',
            Code::getPHPCode([self::class, 'missingMethod'], [], false),
        );
        $this->assertStringContainsString(
            'CodeTest-&gt;missingMethod()',
            Code::getPHPCode([new self(), 'missingMethod'], [], false),
        );
    }

    public function testGetPHPCodeRejectsMethodWithoutBodyInDebugMode(): void
    {
        $core                             = (new ReflectionProperty(Core::class, 'instance'))->getValue();
        $servicesProperty                 = new ReflectionProperty(Container::class, 'services');
        $services                         = $servicesProperty->getValue($core);
        $originalConfig                   = App::config();
        $services[ConfigInterface::class] = new DebugConfig();
        $servicesProperty->setValue($core, $services);

        try {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessage('method code empty');
            Code::getPHPCode([AbstractCodeFixture::class, 'render'], [], false);
        } finally {
            $services                         = $servicesProperty->getValue($core);
            $services[ConfigInterface::class] = $originalConfig;
            $servicesProperty->setValue($core, $services);
        }
    }

    public function testGetPHPCodeGracefullyHandlesErrorsOutsideDebugMode(): void
    {
        $core                             = (new ReflectionProperty(Core::class, 'instance'))->getValue();
        $servicesProperty                 = new ReflectionProperty(Container::class, 'services');
        $services                         = $servicesProperty->getValue($core);
        $originalConfig                   = App::config();
        $services[ConfigInterface::class] = new NonDebugConfig();
        $servicesProperty->setValue($core, $services);

        try {
            $this->assertSame('', Code::getPHPCode('not-a-method', [], false));
            $this->assertSame('', Code::getPHPCode('::method', [], false));
            $this->assertSame('', Code::getPHPCode(self::class . '::', [], false));
            $this->assertSame('', Code::getPHPCode([self::class, ''], [], false));
            $this->assertSame('', Code::getPHPCode([self::class, 'templateCode'], ['one'], false));
            $this->assertSame('', Code::getPHPCode('DateTime::format', [], false));
            $this->assertSame('', Code::getPHPCode(Closure::fromCallable('strlen'), [], false));

            $this->assertStringContainsString(
                'Error processing method template code for',
                Code::getPHPCode(static function (): void {
                }, [], false),
            );

            $filename = tempnam(sys_get_temp_dir(), 'template-helper-');
            $this->assertNotFalse($filename);
            if ($filename === false) {
                return;
            }

            $className = 'UnreadableTemplateCode' . bin2hex(random_bytes(4));
            $source    = "<?php\nnamespace Dotclear\\Tests\\Plugin\\TemplateHelper;\nclass {$className} { public static function render(string \$value): void { echo \$value; } }\n";
            $this->assertNotFalse(file_put_contents($filename, $source));
            require $filename;
            unlink($filename);

            set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => true, E_WARNING);

            try {
                $this->assertSame('', Code::getPHPCode([__NAMESPACE__ . '\\' . $className, 'render'], ['value'], false));
            } finally {
                restore_error_handler();
            }

            $callableName = new ReflectionMethod(Code::class, 'callableName');
            $this->assertSame('123()', $callableName->invoke(null, 123));
        } finally {
            $services                         = $servicesProperty->getValue($core);
            $services[ConfigInterface::class] = $originalConfig;
            $servicesProperty->setValue($core, $services);
        }
    }

    public function testGetPHPCodeRejectsUnscopedClosureInDebugMode(): void
    {
        $closure = Closure::fromCallable('strlen');

        if (!App::config()->debugMode() && !App::config()->devMode()) {
            $this->assertSame('', Code::getPHPCode($closure, [], false));

            return;
        }

        $this->expectException(TemplateException::class);
        Code::getPHPCode($closure, [], false);
    }

    public function testGetPHPCodeHandlesIncompleteCallableFormatsInDebugMode(): void
    {
        if (!App::config()->debugMode() && !App::config()->devMode()) {
            $this->assertSame('', Code::getPHPCode('::method', [], false));
            $this->assertSame('', Code::getPHPCode([self::class, ''], [], false));

            return;
        }

        $this->assertTemplateException(static fn () => Code::getPHPCode('::method'));
        $this->assertTemplateException(static fn () => Code::getPHPCode([self::class, '']));
    }

    public function testGetPHPCodeHandlesInternalMethodSourceInDebugMode(): void
    {
        if (!App::config()->debugMode() && !App::config()->devMode()) {
            $this->assertSame('', Code::getPHPCode('DateTime::format', [], false));

            return;
        }

        $this->assertTemplateException(static fn () => Code::getPHPCode('DateTime::format'));
    }

    public function testGetPHPCodeHandlesUnreadableSourceInDebugMode(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'template-helper-');
        $this->assertNotFalse($filename);
        if ($filename === false) {
            return;
        }

        $className = 'UnreadableTemplateCode' . bin2hex(random_bytes(4));
        $source    = "<?php\nnamespace Dotclear\\Tests\\Plugin\\TemplateHelper;\nclass {$className} { public static function render(string \$value): void { echo \$value; } }\n";
        $this->assertNotFalse(file_put_contents($filename, $source));
        require $filename;
        unlink($filename);

        set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => true, E_WARNING);

        try {
            $method = [__NAMESPACE__ . '\\' . $className, 'render'];
            if (!App::config()->debugMode() && !App::config()->devMode()) {
                $this->assertSame('', Code::getPHPCode($method, ['value'], false));

                return;
            }

            $this->assertTemplateException(static fn () => Code::getPHPCode($method, ['value'], false));
        } finally {
            restore_error_handler();
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    }

    public function testGetPHPCodeUsesDirectReplacementForCodeAndNumericValues(): void
    {
        $code = Code::getPHPCode(
            [self::class, 'directCode'],
            ["echo 'raw';", 42, []],
            false,
        );

        $this->assertSame("echo 'raw';;\n        echo 42;\n        echo ;", $code);
    }

    public static function templateCode(string $value, string $valueSuffix, string $markup_HTML): void
    {
        echo $value;
        echo $valueSuffix;
        echo $markup_HTML;
    }

    public static function blockCode(string $value, string $content, array $params, string $tag): void
    {
        echo $value;
        echo $content;
        echo $params['flag'];
        echo $tag;
    }

    public static function valueCode(string $value, array $params, string $tag): void
    {
        echo $value;
        echo $params['upper_case'];
        echo $tag;
    }

    public static function arrayObjectCode(ArrayObject $attributes): void
    {
        echo $attributes['name'];
    }

    public static function codeWithMarkers(string $value): void
    {
        $removed = 'remove'; // @phpcode-remove
        $ignored = 'ignore'; // @phpcode-ignore
        $phpstan = 'phpstan'; // @phpstan-ignore unused.variable (phpcode-remove)
        echo $value;
    }

    public static function directCode(string $markup_CODE, int $number_CODE, array $invalid_CODE): void
    {
        $markup_CODE;
        echo $number_CODE;
        echo $invalid_CODE;
    }

    private function assertTemplateException(callable $callback): void
    {
        try {
            $callback();
        } catch (TemplateException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a TemplateException.');
    }
}

final class NonDebugConfig extends CoreConfig
{
    public function __construct()
    {
    }

    public function debugMode(): bool
    {
        return false;
    }

    public function devMode(): bool
    {
        return false;
    }
}

final class DebugConfig extends CoreConfig
{
    public function __construct()
    {
    }

    public function debugMode(): bool
    {
        return true;
    }

    public function devMode(): bool
    {
        return false;
    }
}

abstract class AbstractCodeFixture
{
    abstract public static function render(): void;
}
