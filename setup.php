<?php

/**
 * -------------------------------------------------------------------------
 * opencitaseg plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of opencitaseg.
 *
 * opencitaseg is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * opencitaseg is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with opencitaseg. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2026 by opencitaseg plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/Open-SA/opencitaseg
 * @link      https://github.com/pluginsGLPI/opencitaseg
 * ------------------------------------------------------------------------- */

define('PLUGIN_OPENCITASEG_VERSION', '1.0.0');

// Minimal GLPI version, inclusive
define("PLUGIN_OPENCITASEG_MIN_GLPI_VERSION", "11.0.0");

// Maximum GLPI version, exclusive
define("PLUGIN_OPENCITASEG_MAX_GLPI_VERSION", "11.0.99");

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_opencitaseg(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['opencitaseg'] = true;

    $PLUGIN_HOOKS['item_add']['opencitaseg'] = [
        'ITILFollowup' => 'plugin_opencitaseg_item_add',
    ];

    $PLUGIN_HOOKS['add_javascript']['opencitaseg'] = ['js/citas.js'];
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{
 *              min: string,
 *              max: string,
 *          }
 *      }
 * }
 */
function plugin_version_opencitaseg(): array
{
    return [
        'name'         => 'opencitaseg',
        'version'      => PLUGIN_OPENCITASEG_VERSION,
        'author'       => '<a href="http://www.opensa.com.ar">OPENSA\'</a>',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/Open-SA/opencitaseg',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_OPENCITASEG_MIN_GLPI_VERSION,
                'max' => PLUGIN_OPENCITASEG_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_opencitaseg_check_prerequisites(): bool
{
    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_opencitaseg_check_config(bool $verbose = false): bool
{
    // Your configuration check
    return true;

    // Example:
    // if ($verbose) {
    //    echo __('Installed / not configured', 'opencitaseg');
    // }
    // return false;
}
