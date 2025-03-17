# TemplateHelper

## compileTemplates() helper

Not implemented yet:

An asynchronous tool to compile template files if necessary whithout waiting for a public require for the current blog.
To be activated by an admin or super-admin.

## getPHPCode() helper

Having this method to compile a {{tpl:WordCount …}} template value:

```php
    /**
     * Compile template tag
     *
     * {{tpl:WordCount [attributes]}}
     * with attributes may be one or more of:
     * - chars="0|1" show number of characters (0 = default)
     * - words="0|1" show number of words (1 = default)
     * - folios="0|1" show number of folios (0 = default)
     * - time="0|1" : show estimated reading time (0 = default)
     * - wpm="nnn" : words per minute (blog setting by default)
     * - list="0|1" : use ul/li markup (0 = none)
     *
     * Example :
     *
     * ```html
     * <p><strong>{{tpl:lang reading time:}}</strong> {{tpl:WordCount words="0" time="1"}}</p>
     * ```
     *
     * @param      array<string, mixed>|\ArrayObject<string, mixed>  $attr   The attribute
     */
    public static function WordCount(array|ArrayObject $attr): string
    {
        // Check attributes
        $chars  = isset($attr['chars']) ? (int) $attr['chars'] : 0;
        $words  = isset($attr['words']) ? (int) $attr['words'] : 1;
        $folios = isset($attr['folios']) ? (int) $attr['folios'] : 0;
        $time   = isset($attr['time']) ? (int) $attr['time'] : 0;
        $wpm    = isset($attr['wpm']) ? (int) $attr['wpm'] : 0;
        $list   = isset($attr['list']) ? (int) $attr['list'] : 0;

        // Get PHP Code
        return '<?php ' .
        Dotclear\Plugin\TemplateHelper\Code::getPHPCode(
            // __METHOD__ . 'TemplateCode', // Method in this file (same name than this method using 'TemplateCode' as suffix)
            FrontendTemplateCode::WordCount(...),   // Method in another file, using first class callable
            // [FrontendTemplateCode::class, 'WordCount'], // Method in another file, using array of strings
            [
                My::id(),
                $wpm,
                (bool) $chars,
                (bool) $words,
                (bool) $folios,
                (bool) $time,
                (bool) $list,
                App::frontend()->template()->getFiltersParams($attr),
                App::frontend()->template()->getCurrentTag(),
            ],
        ) .
        ' ?>';

        // You may also use
        return '<?php ' .
        Dotclear\Plugin\TemplateHelper\Code::getPHPTemplateCode(
            // __METHOD__ . 'TemplateCode', // Method in this file (same name than this method using 'TemplateCode' as suffix)
            FrontendTemplateCode::WordCount(...),   // Method in another file, using first class callable
            // [FrontendTemplateCode::class, 'WordCount'], // Method in another file, using array of strings
            [
                My::id(),
                $wpm,
                (bool) $chars,
                (bool) $words,
                (bool) $folios,
                (bool) $time,
                (bool) $list,
            ],
            $attr,
        ) .
        ' ?>';
    }
```

With `$attr` equal to:

```php
[
    'list' => '1',
    'graceful_cut' => '230',
]
```

Using this function `FrontendTemplaceCode::WordCount()` as source code for template code generator:

```php
class FrontendTemplateCode
{
    public static function WordCount(
        string $_id_,
        int $_wpm_,
        bool $_chars_,
        bool $_words_,
        bool $_folios_,
        bool $_time_,
        bool $_list_,
        array $_params_,
        string $_tag_
    ): void {
        $settings = App::blog()->settings()->get($_id_);
        $buffer   = \Dotclear\Plugin\wordCount\Helper::getCounters(
            App::frontend()->context()->posts->getExcerpt() . ' ' . App::frontend()->context()->posts->getContent(),
            $_wpm_ ?: $settings->wpm,
            true,
            $_chars_,
            $_words_,
            $_folios_,
            $_time_,
            $_list_
        );
        echo \Dotclear\Core\Frontend\Ctx::global_filters(
            $buffer,
            $_params_,
            $_tag_
        );
    }
}

```

Notes:

- Use fully qualified class when using external methods.
- It is recommanded to use `$_<name>_` pattern for replaced variables by their corresponding values given in the same order as method's parameters
- Avoid using comments inside the code, if necessary put them in function description, above function declaration

Will produce the following code to be inserted in template:

```php
$settings = App::blog()->settings()->get('wordCount');
        $buffer   = \Dotclear\Plugin\wordCount\Helper::getCounters(
            App::frontend()->context()->posts->getExcerpt() . ' ' . App::frontend()->context()->posts->getContent(),
            0 ?: $settings->wpm,
            true,
            false,
            true,
            false,
            false,
            true
        );
        echo \Dotclear\Core\Frontend\Ctx::global_filters(
            $buffer,
            array (
  0 => NULL,
  'encode_xml' => 0,
  'encode_html' => 0,
  'cut_string' => 0,
  'lower_case' => 0,
  'upper_case' => 0,
  'encode_url' => 0,
  'remove_html' => 0,
  'capitalize' => 0,
  'strip_tags' => 0,
  'list' => '1',
  'graceful_cut' => '230',
),
            'WordCount'
        );
```
