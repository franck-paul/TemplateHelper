<?php

/**
 * @brief TemplateHelper, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\TemplateHelper;

use ArrayObject;
use Closure;
use Dotclear\App;
use Dotclear\Exception\TemplateException;
use Exception;
use ReflectionFunction;
use ReflectionMethod;

class Code
{
    /**
     * Gets the PHP code of a template tag (block/value) using the given method code.
     *
     * In the given method code, use array $_params_, string $_content_ and string $_tag_ as three last arguments
     *
     * @param      string|Closure|array{0:string|object, 1:string}  $method     The fully qualified method name
     * @param      array<int, mixed>                                $variables  The variables
     * @param      array<string, mixed>|\ArrayObject<string, mixed> $attr       The attribute
     * @param      string                                           $content    The content (for block template tags)
     *
     * @throws     TemplateException
     */
    public static function getPHPTemplateCode(
        string|Closure|array $method,
        array $variables = [],
        array|ArrayObject $attr,
        string $content = ''
    ): string {
        return self::getPHPCode(
            $method,
            [
                ... $variables,
                App::frontend()->template()->getFiltersParams($attr),
                $content,
                App::frontend()->template()->getCurrentTag(),
            ]
        );
    }

    /**
     * Gets the PHP code of the given method.
     *
     * @param      string|Closure|array{0:string|object, 1:string}  $method     The fully qualified method name
     * @param      array<int, mixed>                                $variables  The variables
     *
     * @throws     TemplateException
     */
    public static function getPHPCode(string|Closure|array $method, array $variables = []): string
    {
        $code = '';

        try {
            // Get PHP code of corresponding template code method
            if (is_string($method)) {
                // Method should be given as '<class>::<function>'
                [$class, $function] = explode('::', $method);
                if ($class === '' || is_null($function) || $function === '') {  // @phpstan-ignore-line : uncertain type
                    if (App::config()->debugMode() || App::config()->devMode()) {
                        throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get class of given method)');
                    }

                    return '';
                }
            } elseif (is_array($method)) {
                // Method should be given as an array of 2 items: class (string or object = class instance), function (string)
                [$class, $function] = $method;
                if ((is_string($class) && $class === '') || is_null($function) || $function === '') {   // @phpstan-ignore-line : uncertain type
                    if (App::config()->debugMode() || App::config()->devMode()) {
                        throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get class of given method)');
                    }

                    return '';
                }
            } else {
                // Method should be a first class closure as class::function(...)
                $reflection_function = new ReflectionFunction($method);
                $class               = $reflection_function->getNamespaceName();
                $function            = $reflection_function->getShortName();
                if ($class === '') {
                    $class = $reflection_function->getClosureScopeClass();
                    if ($class === null) {
                        if (App::config()->debugMode() || App::config()->devMode()) {
                            throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get class of given method)');
                        }

                        return '';
                    }
                    $class = $class->getName();
                }
            }

            $reflection_method = new ReflectionMethod($class, $function);

            $filename = $reflection_method->getFileName();
            if ($filename === false) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get source file)');
                }

                return '';
            }

            $start_line = $reflection_method->getStartLine() - 1; // it's actually - 1, otherwise we wont get the function() block
            $end_line   = $reflection_method->getEndLine();

            if ($start_line === -1 || $end_line === false || ($end_line - $start_line) <= 0) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get source file lines range)');
                }

                return '';
            }

            $source = file($filename);
            if ($source === false) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to read source file)');
                }

                return '';
            }
            $body = trim(implode('', array_slice($source, $start_line, $end_line - $start_line)));

            // Extract core code of method (excluding signature)
            $matches = [];
            if (preg_match('/{(.*)}/ms', $body, $matches) !== false) {
                $body = $matches[1];

                if ($variables !== []) {
                    // Replace static variables (values given in parameters of this helper) by their values
                    $preg_patterns = [];
                    $preg_values   = [];
                    $index         = 0;

                    $parameters = $reflection_method->getParameters();
                    if (count($parameters) > count($variables)) {
                        if (App::config()->debugMode() || App::config()->devMode()) {
                            throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (not enough values given)');
                        }

                        return '';
                    }
                    foreach ($parameters as $parameter) {
                        $value = $variables[$index];

                        $type = $parameter->getType();
                        if ($type !== null) {
                            switch ((string) $type) {
                                case 'string':
                                    // May be not necessary, to be confirmed or infirmed
                                    $value = addslashes((string) $value);

                                    break;
                                case 'ArrayObject':
                                    $value = $value->getArrayCopy();

                                    break;
                            }
                        }
                        $preg_patterns[$index] = '/\$' . $parameter->name . '(?![a-zA-Z0-9_\x7f-\xff])/';
                        $preg_values[$index]   = var_export($value, true);
                        $index++;
                    }

                    if ($preg_patterns !== []) {
                        $body = preg_replace($preg_patterns, $preg_values, $body);
                        if ($body === null) {
                            if (App::config()->debugMode() || App::config()->devMode()) {
                                throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to replace variables)');
                            }

                            return '';
                        }
                    }
                }

                $code = trim($body);
            } else {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code for ' . self::callableName($method) . ' (unable to get method core code)');
                }

                return '';
            }
        } catch (Exception|TemplateException $e) {
            if (App::config()->debugMode() || App::config()->devMode()) {
                throw new TemplateException($e->getMessage() ?: 'Error processing template code for ' . self::callableName($method), (int) $e->getCode(), $e);
            }
            $code = '/* Error processing method template code for ' . self::callableName($method) . ' */';
        }

        return $code;
    }

    /**
     * Return a string representation of a callable (usually callback)
     *
     * @param      mixed   $callable  The callable
     */
    protected static function callableName(mixed $callable): string
    {
        $name = '(unknown)';

        try {
            if (is_string($callable)) {
                // Simple function name (no namespace)
                $name = $callable;
            } elseif (is_array($callable)) {
                // Class, method
                $name = is_object($callable[0]) ? $callable[0]::class . '-&gt;' . $callable[1] : $callable[0] . '::' . $callable[1];
            } elseif ($callable instanceof \Closure) {
                // Closure
                $r  = new ReflectionFunction($callable);
                $ns = (bool) $r->getNamespaceName() ? $r->getNamespaceName() . '::' : '';
                $fn = $r->getShortName() ?: '__closure__';
                if ($ns === '') {
                    // Cope with class::method(...) forms
                    $c = $r->getClosureScopeClass();
                    if (!is_null($c)) {
                        $ns = $c->getName() !== '' ? $c->getName() . '::' : ''; // @phpstan-ignore-line
                    }
                }

                $name = $ns . $fn;
            } else {
                // Not yet managed, give simpler response
                $name = print_r($callable, true);
            }
        } catch (Exception) {
        }

        return $name . '()';
    }
}
