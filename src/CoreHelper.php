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

use Closure;
use Dotclear\App;
use Dotclear\Exception\TemplateException;
use Exception;
use ReflectionFunction;
use ReflectionMethod;

class CoreHelper
{
    /**
     * Gets the PHP code of a template tag.
     *
     * @param      string|Closure|array<0:string|object, 1:string>  $method     The method name (fully qualified class::name)
     * @param      array                                            $variables  The variables
     * @param      string                                           $suffix     The method name suffix if any
     *
     * @throws     \Dotclear\Exception\TemplateException
     */
    public static function getPHPCode(string|Closure|array $method, array $variables = [], string $suffix = ''): string
    {
        $code = '';

        try {
            // Get PHP code of corresponding template code method
            if (is_string($method)) {
                [$class, $function] = explode('::', $method);
            } elseif (is_array($method)) {
                [$class, $function] = $method;
            } else {
                $reflection_function = new ReflectionFunction($method);
                $class               = $reflection_function->getNamespaceName();
                $function            = $reflection_function->getShortName();
                if ($class === '') {
                    $class = $reflection_function->getClosureScopeClass();
                    if ($class === null) {
                        if (App::config()->debugMode() || App::config()->devMode()) {
                            throw new TemplateException('Error processing the template code (unable to get class of given method)');
                        }

                        return '';
                    }
                    $class = $class->getName();
                }
            }

            $reflection_method = new ReflectionMethod($class, $function . $suffix);

            $filename = $reflection_method->getFileName();
            if ($filename === false) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code (unable to get source file)');
                }

                return '';
            }

            $start_line = $reflection_method->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
            $end_line   = $reflection_method->getEndLine();

            if ($start_line === false || $end_line === false || ($end_line - $start_line) <= 0) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code (unable to get source file lines range)');
                }

                return '';
            }

            $source = file($filename);
            if ($source === false) {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code (unable to read source file)');
                }

                return '';
            }
            $body = trim(implode('', array_slice($source, $start_line, $end_line - $start_line)));

            // Extract core code of method (excluding signature)
            $matches = [];
            if (preg_match('/{(.*)}/ms', $body, $matches) !== false && isset($matches[1])) {
                $body = $matches[1];

                // Replace static variables (values given in parameters of this helper) by their values
                $preg_patterns = [];
                $preg_values   = [];
                $index         = 0;

                $parameters = $reflection_method->getParameters();
                if (count($parameters) > count($variables)) {
                    if (App::config()->debugMode() || App::config()->devMode()) {
                        throw new TemplateException('Error processing the template code (not enough values given)');
                    }

                    return '';
                }
                foreach ($parameters as $parameter) {
                    $value = $variables[$index];

                    $type = $parameter->getType();
                    if ($type !== null) {
                        switch ($type->getName()) {
                            case 'string':
                                // May be not necessary, to be confirmed or infirmed
                                $value = addslashes((string) $value);

                                break;
                            case 'ArrayObject':
                                $value = $value->getArrayCopy();

                                break;
                        }
                    }
                    $preg_patterns[$index] = '/\$' . $parameter->name . '/';
                    $preg_values[$index]   = var_export($value, true);
                    $index++;
                }

                if ($preg_patterns !== []) {
                    $body = preg_replace($preg_patterns, $preg_values, $body);
                    if ($body === null) {
                        if (App::config()->debugMode() || App::config()->devMode()) {
                            throw new TemplateException('Error processing the template code (unable to replace variables)');
                        }

                        return '';
                    }
                }

                $code = trim($body);
            } else {
                if (App::config()->debugMode() || App::config()->devMode()) {
                    throw new TemplateException('Error processing the template code (unable to get method core code)');
                }

                return '';
            }
        } catch (Exception | TemplateException) {
            if (App::config()->debugMode() || App::config()->devMode()) {
                throw new TemplateException('Error processing template code');
            }
            $code = '/* Error processing method template code */';
        }

        return $code;
    }
}
