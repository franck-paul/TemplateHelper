<?php

/**
 * @brief TemplateHelper, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
$this->registerModule(
    'Template Helper',
    'Template Helper methods',
    'Franck Paul',
    '1.5',
    [
        'date'     => '2025-03-15T23:44:00+01.5',
        'requires' => [['core', '2.34']],
        'type'     => 'plugin',
        'settings' => [],

        'details'    => 'https://open-time.net/?q=TemplateHelper',
        'support'    => 'https://github.com/franck-paul/TemplateHelper',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/TemplateHelper/main/dcstore.xml',
        'license'    => 'gpl2',
    ]
);
